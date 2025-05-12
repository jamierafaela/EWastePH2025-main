<?php
session_start();
include 'db_connect.php';

define('STATUS_PENDING', 'Pending Review');
define('STATUS_APPROVED', 'Approved');
define('STATUS_REJECTED', 'Rejected');

define('REASON_INAPPROPRIATE', 'Inappropriate Content');
define('REASON_NOT_EWASTE', 'Not E-waste');
define('REASON_INCOMPLETE', 'Incomplete Information');
define('REASON_OTHER', 'Other');
define('REASON_NONE', 'No Reason Provided');

function trackUserViolation($conn, $seller_id, $listing_id, $violation_type)
{
    if (!$seller_id || $seller_id <= 0 || !$listing_id || !$violation_type) {
        error_log("Invalid parameters for violation tracking: seller_id=$seller_id, listing_id=$listing_id, type=$violation_type");
        return false;
    }

    
    $seller_id_param = $seller_id;
    $listing_id_param = $listing_id;
    $violation_type_param = $violation_type;

    $stmt = $conn->prepare("INSERT INTO user_violations (seller_id, listing_id, violation_type, reported_at) VALUES (?, ?, ?, NOW())");

    if (!$stmt) {
        error_log("Prepare statement failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("iis", $seller_id_param, $listing_id_param, $violation_type_param);
    $result = $stmt->execute();

    if (!$result) {
        error_log("Execute statement failed: " . $stmt->error);
    }

    $stmt->close();
    return $result;
}

function checkImageFilename($filename)
{
    $suspicious_terms = ['adult', 'xxx', 'nude', 'sex', 'porn', 'explicit', 'nsfw'];
    $filename_lower = strtolower($filename);

    foreach ($suspicious_terms as $term) {
        if (strpos($filename_lower, $term) !== false) {
            return true;
        }
    }
    return false;
}

function getUserIdFromListing($conn, $listing_id)
{
    $listing_id = (int)$listing_id;
    if ($listing_id <= 0) {
        error_log("Invalid listing ID in getUserIdFromListing: $listing_id");
        return 0;
    }

    $stmt = $conn->prepare("SELECT seller_id FROM listings WHERE listing_id = ?");
    if (!$stmt) {
        error_log("Prepare statement failed in getUserIdFromListing: " . $conn->error);
        return 0;
    }

    $stmt->bind_param("i", $listing_id);
    if (!$stmt->execute()) {
        error_log("Execute failed in getUserIdFromListing: " . $stmt->error);
        $stmt->close();
        return 0;
    }

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $seller_id = $row ? ($row['seller_id'] ?? 0) : 0;

    $stmt->close();
    return $seller_id;
}

function rejectWithReason($conn, $product_id, $reason, $admin_notes)
{
    $seller_id = getUserIdFromListing($conn, $product_id);

    $status = STATUS_REJECTED;

    if (empty($reason)) {
        $reason = REASON_NONE;
    }
    $stmt = $conn->prepare("UPDATE listings SET product_status = ?, rejection_reason = ?, admin_notes = ?, updated_at = NOW() WHERE listing_id = ?");
    $stmt->bind_param("sssi", $status, $reason, $admin_notes, $product_id);
    $result = $stmt->execute();
    $stmt->close();

    if ($reason == REASON_INAPPROPRIATE && $seller_id > 0) {
        trackUserViolation($conn, $seller_id, $product_id, 'inappropriate_image');
    }

    return $result;
}


function getUserTrustScore($conn, $seller_id)
{

    $seller_id = (int)$seller_id;
    if ($seller_id <= 0) {
        return 0;
    }

    $approved_status = STATUS_APPROVED;

    $stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM listings WHERE seller_id = ? AND product_status = ?) as approved_count,
            (SELECT COUNT(*) FROM user_violations WHERE seller_id = ?) as violation_count
    ");

    if (!$stmt) {
        error_log("Prepare statement failed in getUserTrustScore: " . $conn->error);
        return 0;
    }

    $stmt->bind_param("isi", $seller_id, $approved_status, $seller_id);

    if (!$stmt->execute()) {
        error_log("Execute failed in getUserTrustScore: " . $stmt->error);
        $stmt->close();
        return 0;
    }

    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();


    $approved_count = $data['approved_count'] ?? 0;
    $violation_count = $data['violation_count'] ?? 0;

    $trust_score = ($approved_count * 10) - ($violation_count * 25);

    return max(0, $trust_score); 
}

// Flagging users with poor trust scores
function flagSuspiciousListings($conn)
{
    $suspicious_users = $conn->query("
        SELECT DISTINCT v.seller_id 
        FROM user_violations v 
        WHERE 
            v.reported_at > DATE_SUB(NOW(), INTERVAL 30 DAY) 
            OR (SELECT COUNT(*) FROM user_violations WHERE seller_id = v.seller_id) > 1
    ");

    if (!$suspicious_users) {
        error_log("Query failed in flagSuspiciousListings: " . $conn->error);
        return;
    }

    if ($suspicious_users->num_rows > 0) {
        $flagged = 0;
        $status = STATUS_PENDING;

        while ($user = $suspicious_users->fetch_assoc()) {
            if (isset($user['seller_id']) && $user['seller_id'] > 0) {
                $stmt = $conn->prepare("UPDATE listings SET is_flagged = 1 WHERE seller_id = ? AND product_status = ?");
                if (!$stmt) {
                    error_log("Prepare statement failed when flagging: " . $conn->error);
                    continue;
                }

                $stmt->bind_param("is", $user['seller_id'], $status);
                $stmt->execute();
                $flagged += $stmt->affected_rows;
                $stmt->close();
            }
        }

        if ($flagged > 0) {
            $_SESSION['message'] = "$flagged suspicious listings have been automatically flagged for careful review.";
            $_SESSION['message_type'] = 'warning';
        }
    }
}
function updateLegacyUserIds($conn)
{
    $conn->query("UPDATE listings SET seller_id = user_id WHERE seller_id != user_id AND user_id IS NOT NULL");

    $affected = $conn->affected_rows;
    if ($affected > 0) {
        error_log("Updated seller_id to match user_id in $affected listings for compatibility");
    }

    return $affected;
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['product_id']) && isset($_POST['action'])) {
        $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ($product_id && in_array($action, ["approve", "reject", "reset"])) {
            switch ($action) {
                case "approve":
                    $new_status = STATUS_APPROVED;
                    $message_action = "approved";

                    $stmt = $conn->prepare("UPDATE listings SET product_status = ?, updated_at = NOW(), is_flagged = 0 WHERE listing_id = ?");
                    $stmt->bind_param("si", $new_status, $product_id);

                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Product #$product_id has been successfully $message_action.";
                        $_SESSION['message_type'] = 'success';
                    } else {
                        $_SESSION['message'] = "Error updating product: " . $conn->error;
                        $_SESSION['message_type'] = 'error';
                    }
                    $stmt->close();
                    break;

                case "reject":
                
                    if (!isset($_POST['rejection_reason'])) {
                   
                        if (rejectWithReason($conn, $product_id, '', '')) {
                            $_SESSION['message'] = "Product #$product_id has been rejected.";
                            $_SESSION['message_type'] = 'success';
                        } else {
                            $_SESSION['message'] = "Error rejecting product: " . $conn->error;
                            $_SESSION['message_type'] = 'error';
                        }
                    }
                    break;

                case "reset":
                    $new_status = STATUS_PENDING;
                    $message_action = "reset to pending review";

                    $stmt = $conn->prepare("UPDATE listings SET product_status = ?, updated_at = NOW() WHERE listing_id = ?");
                    $stmt->bind_param("si", $new_status, $product_id);

                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Product #$product_id has been successfully $message_action.";
                        $_SESSION['message_type'] = 'success';
                    } else {
                        $_SESSION['message'] = "Error updating product: " . $conn->error;
                        $_SESSION['message_type'] = 'error';
                    }
                    $stmt->close();
                    break;
            }
        } else {
            $_SESSION['message'] = "Invalid input data.";
            $_SESSION['message_type'] = 'error';
        }
    }

    if (isset($_POST['reject_with_reason']) && isset($_POST['product_id']) && isset($_POST['rejection_reason'])) {
        $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $reason = filter_input(INPUT_POST, 'rejection_reason', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $admin_notes = filter_input(INPUT_POST, 'admin_notes', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ($product_id) {
            if (rejectWithReason($conn, $product_id, $reason, $admin_notes)) {
                $_SESSION['message'] = "Product #$product_id has been rejected" . (!empty($reason) ? " with reason: $reason" : ".");
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = "Error rejecting product: " . $conn->error;
                $_SESSION['message_type'] = 'error';
            }
        }
    }

    if (isset($_POST['report_image']) && isset($_POST['product_id'])) {
        $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

     
        if (rejectWithReason($conn, $product_id, REASON_INAPPROPRIATE, 'Flagged for inappropriate image content')) {
            $_SESSION['message'] = "Product #$product_id has been rejected and flagged for inappropriate image content.";
            $_SESSION['message_type'] = 'warning';
        }
    }


    header("Location: " . $_SERVER['PHP_SELF'] . (isset($_GET['status']) ? '?status=' . $_GET['status'] : '') .
        (isset($_GET['flagged']) && $_GET['flagged'] == '1' ? '&flagged=1' : ''));
    exit();
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// auto-flag for suspicious users
flagSuspiciousListings($conn);


$check_column = $conn->query("SHOW COLUMNS FROM listings LIKE 'rejection_reason'");
if ($check_column->num_rows === 0) {
    $conn->query("ALTER TABLE listings ADD COLUMN rejection_reason VARCHAR(255) DEFAULT NULL");
}

$check_column = $conn->query("SHOW COLUMNS FROM listings LIKE 'admin_notes'");
if ($check_column->num_rows === 0) {
    $conn->query("ALTER TABLE listings ADD COLUMN admin_notes TEXT DEFAULT NULL");
}

$check_column = $conn->query("SHOW COLUMNS FROM listings LIKE 'is_flagged'");
if ($check_column->num_rows === 0) {
    $conn->query("ALTER TABLE listings ADD COLUMN is_flagged TINYINT(1) DEFAULT 0");
}

updateLegacyUserIds($conn);

// page
$records_per_page = 10;
$page = isset($_GET['page']) ? filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) : 1;
if (!$page) $page = 1;
$offset = ($page - 1) * $records_per_page;

$flagged_filter = isset($_GET['flagged']) && $_GET['flagged'] == '1' ? " AND is_flagged = 1" : "";

$count_pending = $conn->query("SELECT COUNT(*) as count FROM listings WHERE product_status = '" . STATUS_PENDING . "'" . $flagged_filter)->fetch_assoc()['count'];
$count_approved = $conn->query("SELECT COUNT(*) as count FROM listings WHERE product_status = '" . STATUS_APPROVED . "'")->fetch_assoc()['count'];
$count_rejected = $conn->query("SELECT COUNT(*) as count FROM listings WHERE product_status = '" . STATUS_REJECTED . "'")->fetch_assoc()['count'];
$count_flagged = $conn->query("SELECT COUNT(*) as count FROM listings WHERE is_flagged = 1 AND product_status = '" . STATUS_PENDING . "'")->fetch_assoc()['count'];

$pending_products = $conn->query("SELECT l.*, u.full_name, u.email
                                 FROM listings l 
                                 LEFT JOIN users u ON l.seller_id = u.user_id
                                 WHERE l.product_status = '" . STATUS_PENDING . "'" . $flagged_filter . " 
                                 ORDER BY l.is_flagged DESC, l.created_at DESC 
                                 LIMIT $offset, $records_per_page");

$approved_products = $conn->query("SELECT l.*, u.full_name, u.email
                                  FROM listings l 
                                  LEFT JOIN users u ON l.seller_id = u.user_id
                                  WHERE l.product_status = '" . STATUS_APPROVED . "' 
                                  ORDER BY l.created_at DESC 
                                  LIMIT $offset, $records_per_page");

$rejected_products = $conn->query("SELECT l.*, u.full_name, u.email
                                  FROM listings l 
                                  LEFT JOIN users u ON l.seller_id = u.user_id
                                  WHERE l.product_status = '" . STATUS_REJECTED . "' 
                                  ORDER BY l.created_at DESC 
                                  LIMIT $offset, $records_per_page");

$total_pages_pending = ceil($count_pending / $records_per_page);
$total_pages_approved = ceil($count_approved / $records_per_page);
$total_pages_rejected = ceil($count_rejected / $records_per_page);

function paginationLinks($current_page, $total_pages, $status)
{
    $flagged = isset($_GET['flagged']) && $_GET['flagged'] == '1' ? '&flagged=1' : '';

    $links = '';

    if ($total_pages <= 1) return '';

    $links .= '<div class="pagination">';
    if ($current_page > 1) {
        $links .= '<a href="?page=' . ($current_page - 1) . '&status=' . $status . $flagged . '" class="page-link">&laquo; Previous</a>';
    }

    for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++) {
        if ($i == $current_page) {
            $links .= '<span class="page-link active">' . $i . '</span>';
        } else {
            $links .= '<a href="?page=' . $i . '&status=' . $status . $flagged . '" class="page-link">' . $i . '</a>';
        }
    }

    if ($current_page < $total_pages) {
        $links .= '<a href="?page=' . ($current_page + 1) . '&status=' . $status . $flagged . '" class="page-link">Next &raquo;</a>';
    }
    $links .= '</div>';

    return $links;
}

function displayStatus($status, $is_flagged = 0)
{
    $flag_icon = $is_flagged ? ' <i class="fas fa-flag text-warning" title="Flagged for review"></i>' : '';

    switch ($status) {
        case STATUS_PENDING:
            return '<span class="status-badge pending">' . $status . $flag_icon . '</span>';
        case STATUS_APPROVED:
            return '<span class="status-badge approved">' . $status . $flag_icon . '</span>';
        case STATUS_REJECTED:
            return '<span class="status-badge rejected">' . $status . $flag_icon . '</span>';
        default:
            return '<span class="status-badge">' . $status . $flag_icon . '</span>';
    }
}

// Shows trust score
function displayTrustScore($conn, $seller_id)
{
    if (!$seller_id) return '<span class="badge badge-secondary">No user ID</span>';
    $trust_score = getUserTrustScore($conn, $seller_id);
    if ($trust_score >= 50) {
        return '<span class="badge badge-success" title="High trust score">Trust: ' . $trust_score . '</span>';
    } elseif ($trust_score >= 20) {
        return '<span class="badge badge-info" title="Medium trust score">Trust: ' . $trust_score . '</span>';
    } else {
        return '<span class="badge badge-warning" title="Low trust score">Trust: ' . $trust_score . '</span>';
    }
}

$active_status = isset($_GET['status']) ? $_GET['status'] : 'pending';

$update_empty = $conn->query("UPDATE listings SET product_status = '" . STATUS_PENDING . "' 
                            WHERE product_status = '' OR product_status IS NULL");

if ($update_empty) {
    $affected = $conn->affected_rows;
    if ($affected > 0) {
        $_SESSION['message'] = "$affected products were automatically set to Pending Review status.";
        $_SESSION['message_type'] = 'info';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Listings Management</title>
    <link rel="stylesheet" href="../../src/styles/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>

    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-box-open"></i>Listings Management</h1>
        </div>
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= isset($_SESSION['message_type']) && $_SESSION['message_type'] == 'error' ? 'error' : (isset($_SESSION['message_type']) && $_SESSION['message_type'] == 'warning' ? 'warning' : 'success') ?>">
                <i class="fas fa-<?= isset($_SESSION['message_type']) && $_SESSION['message_type'] == 'error' ? 'exclamation-circle' : (isset($_SESSION['message_type']) && $_SESSION['message_type'] == 'warning' ? 'exclamation-triangle' : 'check-circle') ?>"></i>
                <?= $_SESSION['message']; ?>
            </div>
            <?php
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        <?php endif; ?>

        <div class="tab-container">
            <a href="?status=pending" class="tab <?= $active_status == 'pending' ? 'active' : '' ?>">
                <i class="fas fa-hourglass"></i> Pending Review <span class="tab-count"><?= $count_pending ?></span>
            </a>
            <a href="?status=approved" class="tab <?= $active_status == 'approved' ? 'active' : '' ?>">
                <i class="fas fa-check-circle"></i> Approved <span class="tab-count"><?= $count_approved ?></span>
            </a>
            <a href="?status=rejected" class="tab <?= $active_status == 'rejected' ? 'active' : '' ?>">
                <i class="fas fa-times-circle"></i> Rejected <span class="tab-count"><?= $count_rejected ?></span>
            </a>
            <?php if ($count_flagged > 0): ?>
                <a href="?status=pending&flagged=1" class="tab <?= isset($_GET['flagged']) && $_GET['flagged'] == '1' ? 'active' : '' ?>">
                    <i class="fas fa-flag"></i> Flagged <span class="tab-count"><?= $count_flagged ?></span></a>
            <?php endif; ?>
        </div>

        <?php if ($active_status == 'pending' && isset($_GET['flagged']) && $_GET['flagged'] == '1'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Showing flagged listings that require special attention.
                <a href="?status=pending" class="alert-link">Clear filter</a>
            </div>
        <?php endif; ?>


        <?php if ($active_status == 'pending' && $pending_products && $pending_products->num_rows > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Seller</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Desc</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($product = $pending_products->fetch_assoc()): ?>
                        <tr>
                            <td><?= $product['listing_id'] ?></td>
                            <td>
                                <div class="user-id-info">
                                    <strong>ID: <?= $product['seller_id'] ?? 'Unknown' ?></strong>
                                    <?= displayTrustScore($conn, $product['seller_id']) ?>
                                </div>
                                <?= htmlspecialchars($product['full_name'] ?? 'Unknown') ?>
                                <div class="user-info">
                                    <?= htmlspecialchars($product['email'] ?? 'No email') ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($product['product_image'])): ?>
                                    <img src="<?= htmlspecialchars($product['product_image']) ?>" alt="Product Thumbnail" class="product-thumbnail"
                                        data-action="view-image"
                                        data-id="<?= $product['listing_id'] ?>"
                                        data-image="<?= htmlspecialchars($product['product_image']) ?>"
                                        data-name="<?= htmlspecialchars($product['product_name']) ?>">
                                <?php else: ?>
                                    <i class="fas fa-ban"></i> N/A
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($product['product_name']) ?></td>
                            <td class="description"><?= substr(htmlspecialchars($product['product_description'] ?? 'No description'), 0, 100) ?></td>
                            <td><?= htmlspecialchars($product['product_condition'] ?? 'N/A') ?></td>
                            <td>₱<?= number_format((float)($product['product_price'] ?? 0), 2) ?></td>
                            <td><?= displayStatus($product['product_status'], $product['is_flagged']) ?></td>
                            <td class="actions">

                                <form method="post" style="display:inline">
                                    <input type="hidden" name="product_id" value="<?= $product['listing_id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-small btn-approve"><i class="fas fa-check"></i></button>
                                </form>
                                <button class="btn btn-small btn-reject" data-action="reject" data-id="<?= $product['listing_id'] ?>">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php if ($product['is_flagged']): ?>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="product_id" value="<?= $product['listing_id'] ?>">
                                        <input type="hidden" name="unflag" value="1">

                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?= paginationLinks($page, $total_pages_pending, 'pending') ?>
        <?php else: ?>
            <?php if ($active_status == 'pending'): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>No Pending Products</h3>
                    <p>There are no products waiting for review at this time.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($active_status == 'approved' && $approved_products && $approved_products->num_rows > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Seller</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($product = $approved_products->fetch_assoc()): ?>
                        <tr>
                            <td><?= $product['listing_id'] ?></td>
                            <td>
                                <div class="user-id-info">
                                    <strong>ID: <?= $product['seller_id'] ?? 'Unknown' ?></strong>
                                    <?= displayTrustScore($conn, $product['seller_id']) ?>
                                </div>
                                <?= htmlspecialchars($product['full_name'] ?? 'Unknown') ?>
                                <div class="user-info">
                                    <?= htmlspecialchars($product['email'] ?? 'No email') ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($product['product_image'])): ?>
                                    <img src="<?= htmlspecialchars($product['product_image']) ?>" alt="Product Thumbnail" class="product-thumbnail"
                                        data-action="view-image"
                                        data-id="<?= $product['listing_id'] ?>"
                                        data-image="<?= htmlspecialchars($product['product_image']) ?>"
                                        data-name="<?= htmlspecialchars($product['product_name']) ?>">
                                <?php else: ?>
                                    <i class="fas fa-ban"></i> N/A
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($product['product_name']) ?></td>
                            <td><?= htmlspecialchars($product['product_condition'] ?? 'N/A') ?></td>
                            <td>₱<?= number_format((float)($product['product_price'] ?? 0), 2) ?></td>
                            <td><?= displayStatus($product['product_status'], $product['is_flagged']) ?></td>
                            <td class="actions">

                                <form method="post" style="display:inline">
                                    <input type="hidden" name="product_id" value="<?= $product['listing_id'] ?>">
                                    <input type="hidden" name="action" value="reset">
                                    <button type="submit" class="btn btn-small btn-reset" title="Reset to pending">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?= paginationLinks($page, $total_pages_approved, 'approved') ?>
        <?php else: ?>
            <?php if ($active_status == 'approved'): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h3>No Approved Products</h3>
                    <p>There are no approved products at this time.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($active_status == 'rejected' && $rejected_products && $rejected_products->num_rows > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Seller</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Reason</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($product = $rejected_products->fetch_assoc()): ?>
                        <tr>
                            <td><?= $product['listing_id'] ?></td>
                            <td>
                                <div class="user-id-info">
                                    <strong>ID: <?= $product['seller_id'] ?? 'Unknown' ?></strong>
                                    <?= displayTrustScore($conn, $product['seller_id']) ?>
                                </div>
                                <?= htmlspecialchars($product['full_name'] ?? 'Unknown') ?>
                                <div class="user-info">
                                    <?= htmlspecialchars($product['email'] ?? 'No email') ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($product['product_image'])): ?>
                                    <img src="<?= htmlspecialchars($product['product_image']) ?>" alt="Product Thumbnail" class="product-thumbnail"
                                        data-action="view-image"
                                        data-id="<?= $product['listing_id'] ?>"
                                        data-image="<?= htmlspecialchars($product['product_image']) ?>"
                                        data-name="<?= htmlspecialchars($product['product_name']) ?>">
                                <?php else: ?>
                                    <i class="fas fa-ban"></i> N/A
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($product['product_name']) ?></td>
                            <td><?= htmlspecialchars($product['product_condition'] ?? 'N/A') ?></td>
                            <td>₱<?= number_format((float)($product['product_price'] ?? 0), 2) ?></td>
                            <td><?= displayStatus($product['product_status'], $product['is_flagged']) ?></td>
                            <td>
                                <span class="rejection-reason" data-toggle="tooltip" title="Click to see admin notes">
                                    <?= htmlspecialchars($product['rejection_reason'] ?? REASON_NONE) ?>
                                </span>
                                <?php if (!empty($product['admin_notes'])): ?>
                                    <div class="admin-notes" style="display: none;">
                                        <strong>Admin Notes:</strong><br>
                                        <?= nl2br(htmlspecialchars($product['admin_notes'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="actions">

                                <form method="post" style="display:inline">
                                    <input type="hidden" name="product_id" value="<?= $product['listing_id'] ?>">
                                    <input type="hidden" name="action" value="reset">
                                    <button type="submit" class="btn btn-small btn-reset" title="Reset to pending">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?= paginationLinks($page, $total_pages_rejected, 'rejected') ?>
        <?php else: ?>
            <?php if ($active_status == 'rejected'): ?>
                <div class="empty-state">
                    <i class="fas fa-times-circle"></i>
                    <h3>No Rejected Products</h3>
                    <p>There are no rejected products at this time.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <!-- Unified Modal System -->
        <div id="modal-overlay" class="modal-overlay"></div>

        <!-- Image View Modal -->
        <div id="image-modal" class="modal-container">
            <div class="popup-header">
                <h3 id="image-modal-title">Product Image</h3>
                <span class="close-popup" data-action="close-modal">&times;</span>
            </div>
            <div class="popup-content">
                <div class="image-container">
                    <img id="modal-image" src="" alt="Product Image" class="popup-image">
                </div>
            </div>
            <div class="image-actions">
                <form method="post" id="report-image-form">
                    <input type="hidden" id="report-image-id" name="product_id" value="">
                    <input type="hidden" name="report_image" value="1">
                    <button type="button" class="btn btn-warning" data-action="confirm-report">
                        <i class="fas fa-flag"></i> Report Inappropriate
                    </button>
                </form>
            </div>
        </div>

        <!-- Rejection Modal -->
        <div id="reject-modal" class="modal-container">
            <div class="popup-header">
                <h3>Reject Listing</h3>
                <span class="close-popup" data-action="close-modal">&times;</span>
            </div>
            <div class="popup-content">
                <form method="post" id="reject-form">
                    <input type="hidden" id="reject-product-id" name="product_id" value="">
                    <input type="hidden" name="reject_with_reason" value="1">

                    <div class="form-group">
                        <label for="rejection-reason">Rejection Reason:</label>
                        <select name="rejection_reason" id="rejection-reason" class="form-control" required>
                            <option value="<?= REASON_NONE ?>"><?= REASON_NONE ?></option>
                            <option value="<?= REASON_INAPPROPRIATE ?>"><?= REASON_INAPPROPRIATE ?></option>
                            <option value="<?= REASON_NOT_EWASTE ?>"><?= REASON_NOT_EWASTE ?></option>
                            <option value="<?= REASON_INCOMPLETE ?>"><?= REASON_INCOMPLETE ?></option>
                            <option value="<?= REASON_OTHER ?>"><?= REASON_OTHER ?></option>

                        </select>
                    </div>

                    <div class="form-group">
                        <label for="admin-notes">Additional Notes (Optional):</label>
                        <textarea name="admin_notes" id="admin-notes" class="form-control"
                            placeholder="Add any additional notes or context for this rejection"></textarea>
                    </div>

                    <div class="form-group" style="text-align: right;">
                        <button type="button" class="btn" data-action="close-modal">Cancel</button>
                        <button type="submit" class="btn btn-reject">Reject Listing</button>
                    </div>
                </form>
            </div>
        </div>


        <!-- Confirmation Modal -->
        <div id="confirm-modal" class="modal-container" style="max-width: 400px;">
            <div class="popup-header">
                <h3>Confirmation</h3>
                <span class="close-popup" data-action="close-modal">&times;</span>
            </div>
            <div class="popup-content">
                <p id="confirm-message">Are you sure you want to perform this action?</p>
                <div class="form-group" style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn" data-action="close-modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirm-action-btn">Confirm</button>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modalOverlay = document.getElementById('modal-overlay');
                const imageModal = document.getElementById('image-modal');
                const rejectModal = document.getElementById('reject-modal');
                const confirmModal = document.getElementById('confirm-modal');

                const modalImage = document.getElementById('modal-image');
                const imageModalTitle = document.getElementById('image-modal-title');
                const rejectProductId = document.getElementById('reject-product-id');
                const reportImageId = document.getElementById('report-image-id');
                const confirmMessage = document.getElementById('confirm-message');
                const confirmActionBtn = document.getElementById('confirm-action-btn');

                function showImageModal(productId, imageUrl, productName) {
                    imageModalTitle.textContent = productName || 'Product Image';
                    modalImage.src = imageUrl;
                    reportImageId.value = productId;

                    showModal(imageModal);
                }

                function showRejectModal(productId) {
                    rejectProductId.value = productId;
                    document.getElementById('rejection-reason').selectedIndex = 0;
                    document.getElementById('admin-notes').value = '';

                    showModal(rejectModal);
                }

                function showConfirmModal(message, callback) {
                    confirmMessage.textContent = message;
                    confirmActionBtn.onclick = callback;

                    showModal(confirmModal);
                }

                function showModal(modal) {
                    hideAllModals();
                    modalOverlay.style.display = 'block';
                    modal.style.display = 'block';
                }

                function hideAllModals() {
                    modalOverlay.style.display = 'none';
                    imageModal.style.display = 'none';
                    rejectModal.style.display = 'none';
                    confirmModal.style.display = 'none';
                }

                function handleActionClick(event) {
                    const action = this.getAttribute('data-action');
                    const productId = this.getAttribute('data-id');

                    switch (action) {
                        case 'view-image':
                            showImageModal(
                                productId,
                                this.getAttribute('data-image'),
                                this.getAttribute('data-name')
                            );
                            break;

                        case 'reject':
                            showRejectModal(productId);
                            break;

                        case 'close-modal':
                            hideAllModals();
                            break;

                        case 'confirm-report':
                            showConfirmModal(
                                "Are you sure you want to report this image as inappropriate? This will reject the listing and flag the user.",
                                function() {
                                    document.getElementById('report-image-form').submit();
                                }
                            );
                            break;
                    }
                }

                function attachEventListeners() {

                    document.querySelectorAll('[data-action]').forEach(element => {
                        const action = element.getAttribute('data-action');

                        element.removeEventListener('click', handleActionClick);

                        element.addEventListener('click', handleActionClick);
                    });

                    document.querySelectorAll('.rejection-reason').forEach(element => {
                        element.addEventListener('click', function() {
                            const notes = this.parentElement.querySelector('.admin-notes');
                            if (notes) {
                                if (notes.style.display === 'block') {
                                    notes.style.display = 'none';
                                } else {
                                    notes.style.display = 'block';
                                }
                            }
                        });
                    });
                    modalOverlay.addEventListener('click', hideAllModals);
                }

                attachEventListeners();
            });
        </script>


</body>

</html>