<?php
session_start();
include 'db_connect.php';


define('STATUS_PENDING', 'Pending Review');
define('STATUS_APPROVED', 'Approved');
define('STATUS_REJECTED', 'Rejected');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['product_id']) && isset($_POST['action'])) {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

    if ($product_id && in_array($action, ["approve", "reject", "reset"])) {
        switch ($action) {
            case "approve":
                $new_status = STATUS_APPROVED;
                $message_action = "approved";
                break;
            case "reject":
                $new_status = STATUS_REJECTED;
                $message_action = "rejected";
                break;
            case "reset":
                $new_status = STATUS_PENDING;
                $message_action = "reset to pending review";
                break;
        }

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
    } else {
        $_SESSION['message'] = "Invalid input data.";
        $_SESSION['message_type'] = 'error';
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// page
$records_per_page = 10;
$page = isset($_GET['page']) ? filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) : 1;
if (!$page) $page = 1;
$offset = ($page - 1) * $records_per_page;


$count_pending = $conn->query("SELECT COUNT(*) as count FROM listings WHERE product_status = '" . STATUS_PENDING . "'")->fetch_assoc()['count'];
$count_approved = $conn->query("SELECT COUNT(*) as count FROM listings WHERE product_status = '" . STATUS_APPROVED . "'")->fetch_assoc()['count'];
$count_rejected = $conn->query("SELECT COUNT(*) as count FROM listings WHERE product_status = '" . STATUS_REJECTED . "'")->fetch_assoc()['count'];

$pending_products = $conn->query("SELECT * FROM listings WHERE product_status = '" . STATUS_PENDING . "' ORDER BY created_at DESC LIMIT $offset, $records_per_page");
$approved_products = $conn->query("SELECT * FROM listings WHERE product_status = '" . STATUS_APPROVED . "' ORDER BY created_at DESC LIMIT $offset, $records_per_page");
$rejected_products = $conn->query("SELECT * FROM listings WHERE product_status = '" . STATUS_REJECTED . "' ORDER BY created_at DESC LIMIT $offset, $records_per_page");

$total_pages_pending = ceil($count_pending / $records_per_page);
$total_pages_approved = ceil($count_approved / $records_per_page);
$total_pages_rejected = ceil($count_rejected / $records_per_page);
function paginationLinks($current_page, $total_pages, $status)
{
    $links = '';

    if ($total_pages <= 1) return '';

    $links .= '<div class="pagination">';
    if ($current_page > 1) {
        $links .= '<a href="?page=' . ($current_page - 1) . '&status=' . $status . '" class="page-link">&laquo; Previous</a>';
    }

    for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++) {
        if ($i == $current_page) {
            $links .= '<span class="page-link active">' . $i . '</span>';
        } else {
            $links .= '<a href="?page=' . $i . '&status=' . $status . '" class="page-link">' . $i . '</a>';
        }
    }

    if ($current_page < $total_pages) {
        $links .= '<a href="?page=' . ($current_page + 1) . '&status=' . $status . '" class="page-link">Next &raquo;</a>';
    }
    $links .= '</div>';

    return $links;
}

function displayStatus($status)
{
    switch ($status) {
        case STATUS_PENDING:
            return '<span class="status-badge pending">' . $status . '</span>';
        case STATUS_APPROVED:
            return '<span class="status-badge approved">' . $status . '</span>';
        case STATUS_REJECTED:
            return '<span class="status-badge rejected">' . $status . '</span>';
        default:
            return '<span class="status-badge">' . $status . '</span>';
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
    <title>Admin Panel - Products Management</title>
    <link rel="stylesheet" href="../../src/styles/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>

    <div class="container">
        <div class="header">
            <h1><i class="fas fa-box-open"></i> Products Management</h1>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= isset($_SESSION['message_type']) && $_SESSION['message_type'] == 'error' ? 'error' : 'success' ?>">
                <i class="fas fa-<?= isset($_SESSION['message_type']) && $_SESSION['message_type'] == 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
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
        </div>

        <!-- Pending Products -->
        <div id="pending-tab" class="tab-content <?= $active_status == 'pending' ? 'active' : '' ?>">
            <?php if ($pending_products->num_rows > 0): ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Product Name</th>
                        <th>Description</th>
                        <th>Condition</th>
                        <th>Price (₱)</th>
                        <th>Image</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    <?php while ($product = $pending_products->fetch_assoc()): ?>
                        <tr>
                            <td><?= $product['listing_id'] ?></td>
                            <td><?= htmlspecialchars($product['product_name']) ?></td>
                            <td class="truncate"><?= htmlspecialchars($product['product_description']) ?></td>
                            <td><?= htmlspecialchars($product['product_condition']) ?></td>
                            <td>₱<?= number_format($product['product_price'], 2) ?></td>
                            <td>
                                <?php if (!empty($product['product_image'])): ?>
                                    <a href="<?= htmlspecialchars($product['product_image']) ?>" target="_blank">
                                        <i class="fas fa-image"></i> View
                                    </a>
                                <?php else: ?>
                                    <i class="fas fa-ban"></i> N/A
                                <?php endif; ?>
                            </td>
                            <td><?= displayStatus($product['product_status']) ?></td>
                            <td>
                                <form method="POST" style="display:inline;" >
                                    <input type="hidden" name="product_id" value="<?= $product['listing_id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-approve">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" >
                                    <input type="hidden" name="product_id" value="<?= $product['listing_id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-reject">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </table>
                <?= paginationLinks($page, $total_pages_pending, 'pending') ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>No Pending Products</h3>
                    <p>There are no products waiting for review at this time.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Approved Products -->
        <div id="approved-tab" class="tab-content <?= $active_status == 'approved' ? 'active' : '' ?>">
            <?php if ($approved_products->num_rows > 0): ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Product Name</th>
                        <th>Description</th>
                        <th>Condition</th>
                        <th>Price (₱)</th>
                        <th>Image</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    <?php while ($product = $approved_products->fetch_assoc()): ?>
                        <tr>
                            <td><?= $product['listing_id'] ?></td>
                            <td><?= htmlspecialchars($product['product_name']) ?></td>
                            <td class="truncate"><?= htmlspecialchars($product['product_description']) ?></td>
                            <td><?= htmlspecialchars($product['product_condition']) ?></td>
                            <td>₱<?= number_format($product['product_price'], 2) ?></td>
                            <td>
                                <?php if (!empty($product['product_image'])): ?>
                                    <a href="<?= htmlspecialchars($product['product_image']) ?>" target="_blank">
                                        <i class="fas fa-image"></i> View
                                    </a>
                                <?php else: ?>
                                    <i class="fas fa-ban"></i> N/A
                                <?php endif; ?>
                            </td>
                            <td><?= displayStatus($product['product_status']) ?></td>
                            <td>
                                <form method="POST" >
                                    <input type="hidden" name="product_id" value="<?= $product['listing_id'] ?>">
                                    <input type="hidden" name="action" value="reset">
                                    <button type="submit" class="btn btn-reset">
                                        <i class="fas fa-redo"></i> Reset
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </table>
                <?= paginationLinks($page, $total_pages_approved, 'approved') ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h3>No Approved Products</h3>
                    <p>There are no approved products at this time.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Rejected Products -->
        <div id="rejected-tab" class="tab-content <?= $active_status == 'rejected' ? 'active' : '' ?>">
            <?php if ($rejected_products->num_rows > 0): ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Product Name</th>
                        <th>Description</th>
                        <th>Condition</th>
                        <th>Price (₱)</th>
                        <th>Image</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    <?php while ($product = $rejected_products->fetch_assoc()): ?>
                        <tr>
                            <td><?= $product['listing_id'] ?></td>
                            <td><?= htmlspecialchars($product['product_name']) ?></td>
                            <td class="truncate"><?= htmlspecialchars($product['product_description']) ?></td>
                            <td><?= htmlspecialchars($product['product_condition']) ?></td>
                            <td>₱<?= number_format($product['product_price'], 2) ?></td>
                            <td>
                                <?php if (!empty($product['product_image'])): ?>
                                    <a href="<?= htmlspecialchars($product['product_image']) ?>" target="_blank">
                                        <i class="fas fa-image"></i> View
                                    </a>
                                <?php else: ?>
                                    <i class="fas fa-ban"></i> N/A
                                <?php endif; ?>
                            </td>
                            <td><?= displayStatus($product['product_status']) ?></td>
                            <td>
                                <form method="POST" >
                                    <input type="hidden" name="product_id" value="<?= $product['listing_id'] ?>">
                                    <input type="hidden" name="action" value="reset">
                                    <button type="submit" class="btn btn-reset">
                                        <i class="fas fa-redo"></i> Reset
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </table>
                <?= paginationLinks($page, $total_pages_rejected, 'rejected') ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-times-circle"></i>
                    <h3>No Rejected Products</h3>
                    <p>There are no rejected products at this time.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>

<?php $conn->close(); ?>