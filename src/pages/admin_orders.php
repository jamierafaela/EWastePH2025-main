<?php
session_start();
include 'db_connect.php';

define('STATUS_PENDING', 'Pending');
define('STATUS_PROCESSING', 'Processing');
define('STATUS_SHIPPED', 'Shipped');
define('STATUS_DELIVERED', 'Delivered');
define('STATUS_CANCELLED', 'Cancelled');


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['order_id']) && isset($_POST['action'])) {
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

    if ($order_id && $action) {
        $valid_actions = ["process", "ship", "deliver", "cancel", "reset"];
        if (in_array($action, $valid_actions)) {
            switch ($action) {
                case "process":
                    $new_status = STATUS_PROCESSING;
                    $message_action = "marked as processing";
                    break;
                case "ship":
                    $new_status = STATUS_SHIPPED;
                    $message_action = "marked as shipped";
                    break;
                case "deliver":
                    $new_status = STATUS_DELIVERED;
                    $message_action = "marked as delivered";
                    break;
                case "cancel":
                    $new_status = STATUS_CANCELLED;
                    $message_action = "cancelled";
                    break;
                case "reset":
                    $new_status = STATUS_PENDING;
                    $message_action = "reset to pending";
                    break;
            }

            $stmt = $conn->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE order_id = ?");
            $stmt->bind_param("si", $new_status, $order_id);

            if ($stmt->execute()) {
                $_SESSION['message'] = "Order #$order_id has been successfully $message_action.";
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = "Error updating order: " . $conn->error;
                $_SESSION['message_type'] = 'error';
            }

            $stmt->close();
        } else {
            $_SESSION['message'] = "Invalid action specified.";
            $_SESSION['message_type'] = 'error';
        }
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

// Page
$records_per_page = 10;
$page = isset($_GET['page']) ? filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) : 1;
if (!$page) $page = 1;
$offset = ($page - 1) * $records_per_page;


$active_status = isset($_GET['status']) ? filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING) : 'pending';
$valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'all'];
$active_status = in_array($active_status, $valid_statuses) ? $active_status : 'pending';


$count_query = "SELECT 
    COUNT(CASE WHEN order_status = '" . STATUS_PENDING . "' THEN 1 END) as pending_count,
    COUNT(CASE WHEN order_status = '" . STATUS_PROCESSING . "' THEN 1 END) as processing_count,
    COUNT(CASE WHEN order_status = '" . STATUS_SHIPPED . "' THEN 1 END) as shipped_count,
    COUNT(CASE WHEN order_status = '" . STATUS_DELIVERED . "' THEN 1 END) as delivered_count,
    COUNT(CASE WHEN order_status = '" . STATUS_CANCELLED . "' THEN 1 END) as cancelled_count,
    COUNT(*) as total_count
    FROM orders";

$count_result = $conn->query($count_query);
$counts = $count_result->fetch_assoc();

function getOrders($status, $conn, $offset, $records_per_page)
{
    $query = "SELECT o.*, DATE_FORMAT(o.order_date, '%b %d, %Y %h:%i %p') as formatted_date 
              FROM orders o 
              WHERE ";

    if ($status !== 'all') {
        $query .= "o.order_status = '" . ucfirst($status) . "'";
    } else {
        $query .= "1=1"; // All orders
    }

    $query .= " ORDER BY o.order_date DESC LIMIT $offset, $records_per_page";

    return $conn->query($query);
}

$count_sql = "SELECT COUNT(*) as count FROM orders WHERE ";
if ($active_status !== 'all') {
    $count_sql .= "order_status = '" . ucfirst($active_status) . "'";
} else {
    $count_sql .= "1=1";
}
$count_total = $conn->query($count_sql)->fetch_assoc()['count'];
$total_pages = ceil($count_total / $records_per_page);

$current_orders = getOrders($active_status, $conn, $offset, $records_per_page);

function getProductList($order_id, $conn)
{
    $product_sql = "SELECT * FROM order_items WHERE order_id = ?";
    $stmt = $conn->prepare($product_sql);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $product_result = $stmt->get_result();

    $product_list = "";
    while ($product = $product_result->fetch_assoc()) {
        $product_list .= '<div class="product-item">' .
            '<span class="quantity">' . $product['quantity'] . 'x</span> ' .
            '<span class="product-name">' . htmlspecialchars($product['product_name']) . '</span>' .
            '</div>';
    }
    return $product_list ? $product_list : "<span class='no-products'>No products</span>";
}

function displayStatus($status)
{
    $status_lower = strtolower($status);
    return '<span class="status-badge ' . $status_lower . '">' . $status . '</span>';
}

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


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Orders Management</title>
    <link rel="stylesheet" href="../../src/styles/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <div class="header">
            <h1> <i class="fas fa-shopping-cart"></i> Orders</h1>
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
                <i class="fas fa-hourglass"></i> Pending <span class="tab-count"><?= $counts['pending_count'] ?></span>
            </a>

            <a href="?status=processing" class="tab <?= $active_status == 'processing' ? 'active' : '' ?>">
                <i class="fas fa-cogs"></i> Processing <span class="tab-count"><?= $counts['processing_count'] ?></span>
            </a>
            <a href="?status=shipped" class="tab <?= $active_status == 'shipped' ? 'active' : '' ?>">
                <i class="fas fa-shipping-fast"></i> Shipped <span class="tab-count"><?= $counts['shipped_count'] ?></span>
            </a>
            <a href="?status=delivered" class="tab <?= $active_status == 'delivered' ? 'active' : '' ?>">
                <i class="fas fa-check-circle"></i> Delivered <span class="tab-count"><?= $counts['delivered_count'] ?></span>
            </a>
            <a href="?status=cancelled" class="tab <?= $active_status == 'cancelled' ? 'active' : '' ?>">
                <i class="fas fa-times-circle"></i> Cancelled <span class="tab-count"><?= $counts['cancelled_count'] ?></span>
            </a>
            <a href="?status=all" class="tab <?= $active_status == 'all' ? 'active' : '' ?>">
                <i class="fas fa-list-alt"></i> All Orders <span class="tab-count"><?= $counts['total_count'] ?></span>
            </a>
        </div>

        <?php if ($current_orders && $current_orders->num_rows > 0): ?>
            <table>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th class="hide-sm">Contact</th>
                    <th>Address</th>
                    <th>Products</th>
                    <th>Total</th>
                    <th class="hide-sm">Payment</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                <?php while ($order = $current_orders->fetch_assoc()): ?>
                    <tr>
                        <td>
                            #<?= $order['order_id'] ?>
                            <span class="date-time"><?= $order['formatted_date'] ?></span>
                        </td>
                        <td><?= htmlspecialchars($order['full_name']) ?></td>
                        <td class="hide-sm"><?= htmlspecialchars($order['phone_number']) ?></td>
                        <td class="address">
                            <?= htmlspecialchars($order['street']) ?>,
                            <?= htmlspecialchars($order['city']) ?>,
                            <?= htmlspecialchars($order['province']) ?>,
                            <?= htmlspecialchars($order['zipcode']) ?>
                        </td>
                        <td><?= getProductList($order['order_id'], $conn) ?></td>
                        <td>₱<?= number_format($order['totalPrice'], 2) ?></td>
                        <td class="hide-sm">
                            <?= htmlspecialchars($order['payment_method']) ?>
                            <?php if (!empty($order['proofOfPayment'])): ?>
                                <br>
                                <a href="<?= htmlspecialchars($order['proofOfPayment']) ?>" target="_blank" class="btn btn-view">
                                    <i class="fas fa-receipt"></i> View Receipt
                                </a>
                            <?php endif; ?>
                        </td>
                        <td><?= displayStatus($order['order_status']) ?></td>
                        <td>
                            <?php if ($order['order_status'] == STATUS_PENDING): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                    <input type="hidden" name="action" value="process">
                                    <button type="submit" class="btn btn-process">
                                        <i class="fas fa-cogs"></i> Process
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="btn btn-cancel">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </form>
                            <?php elseif ($order['order_status'] == STATUS_PROCESSING): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                    <input type="hidden" name="action" value="ship">
                                    <button type="submit" class="btn btn-ship">
                                        <i class="fas fa-shipping-fast"></i> Ship
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                    <input type="hidden" name="action" value="reset">
                                    <button type="submit" class="btn btn-reset">
                                        <i class="fas fa-undo"></i> Reset
                                    </button>
                                </form>
                            <?php elseif ($order['order_status'] == STATUS_SHIPPED): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                    <input type="hidden" name="action" value="deliver">
                                    <button type="submit" class="btn btn-deliver">
                                        <i class="fas fa-check"></i> Deliver
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                    <input type="hidden" name="action" value="reset">
                                    <button type="submit" class="btn btn-reset">
                                        <i class="fas fa-undo"></i> Reset
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                    <input type="hidden" name="action" value="reset">
                                    <button type="submit" class="btn btn-reset">
                                        <i class="fas fa-undo"></i> Reset
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
            <?= paginationLinks($page, $total_pages, $active_status) ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <h3>No <?= $active_status == 'all' ? '' : $active_status ?> Orders Found</h3>
                <p>There are no<?= $active_status == 'all' ? '' : ' ' . $active_status ?> orders at this time.</p>
            </div>
        <?php endif; ?>
    </div>

</body>

</html>