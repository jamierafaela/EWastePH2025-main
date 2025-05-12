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
    $query = "SELECT o.*, u.email, DATE_FORMAT(o.order_date, '%b %d, %Y %h:%i %p') as formatted_date 
              FROM orders o 
              LEFT JOIN users u ON o.user_id = u.user_id
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

function displayPaymentInfo($method, $proof = null)
{
    $output = '<div class="payment-info">';
    $output .= '<span class="payment-method">' . htmlspecialchars($method) . '</span>';

    if (!empty($proof)) {
        $output .= ' <a href="javascript:void(0);" class="btn btn-view receipt-view"';
        $output .= ' data-receipt="' . htmlspecialchars($proof) . '">';
        $output .= '<i class="fas fa-receipt"></i> View Receipt</a>';
    }

    $output .= '</div>';
    return $output;
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
    <style>

tr:hover {
    background-color: #f9f9f9;
}

tr:last-child td {
    border-bottom: none;
}

.customer-info {
    position: relative;
    cursor: pointer;
    display: flex;
    align-items: center;
}

.customer-info:hover {
    color: #4CAF50;
}

.customer-name {
    font-weight: 500;
}

.info-icon {
    margin-left: 5px;
    color: #4CAF50;
    font-size: 16px;
}

.customer-popup, .receipt-popup {
    display: none;
    position: fixed; /* Changed from absolute to fixed */
    left: 50%;
    top: 50%; /* Changed from 50vh to 50% */
    transform: translate(-50%, -50%);
    background-color: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
    z-index: 10000;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow: auto;
}


.popup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    border-bottom: 1px solid #eee;
    padding-bottom: 15px;
}

.popup-header h3 {
    margin: 0;
    color: #333;
    font-size: 20px;
    font-weight: 600;
}

.close-popup {
    cursor: pointer;
    font-size: 24px;
    color: #999;
    transition: color 0.2s;
}

.close-popup:hover {
    color: #333;
}

.popup-content {
    margin-bottom: 20px;
    max-height: 70vh;
    overflow-y: auto;
}

.popup-content h4 {
    margin: 15px 0 10px;
    color: #4CAF50;
    font-size: 16px;
    font-weight: 500;
    display: flex;
    align-items: center;
}

.popup-content h4 i {
    margin-right: 8px;
}

.popup-content p {
    margin: 8px 0;
    line-height: 1.6;
    display: flex;
    align-items: center;
}

.popup-content p strong {
    min-width: 80px;
    display: inline-block;
}

.clickable-contact {
    cursor: pointer;
    position: relative;
    padding: 2px 5px;
    border-radius: 3px;
    transition: all 0.2s;
    display: inline-block;
}

.clickable-contact:hover {
    background-color: #f0f0f0;
}

.clickable-contact:active {
    background-color: #e0e0e0;
}

.clickable-contact::after {
    content: '\f0c5';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    margin-left: 5px;
    opacity: 0.5;
    font-size: 14px;
}

.clickable-contact:hover::after {
    opacity: 1;
}

.copied-message {
    display: none;
    color: #4CAF50;
    font-size: 14px;
    margin-left: 5px;
    position: absolute;
    right: -60px;
    top: 0;
}
iframe {
    width: 100%;
    height: calc(150 - 170px); 
    border: none;
}
.overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    backdrop-filter: blur(2px);
}
.btn i {
    margin-right: 5px;
}

.btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.receipt-image-container {
    text-align: center;
    width: 100%;
}

#receipt-image {
    max-width: 100%;
    max-height: 60vh;
    object-fit: contain;
    border: 1px solid #eee;
    border-radius: 4px;
    display: block;
    margin: 0 auto;
}

iframe {
    width: 100%;
    height: calc(250vh - 120px);
    border: none;
    position: relative;
    z-index: 1;
}

.receipt-popup, .customer-popup {
    z-index: 10000;
}
@media (max-width: 768px) {
    .customer-popup, .receipt-popup {
        width: 95%;
        padding: 15px;
    }
    
    .copied-message {
        right: -40px;
        font-size: 12px;
    }
    
    #receipt-image {
        max-height: 50vh;
    }
}
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-shopping-cart"></i> Orders</h1>
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
                    <th>Products</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Actions</th>
                    <?php while ($order = $current_orders->fetch_assoc()): ?>
                    <tr>
                        <td>
                            #<?= $order['order_id'] ?>
                            <span class="date-time"><?= $order['formatted_date'] ?></span>
                        </td>
                        <td>
                            <div class="customer-info" data-order-id="<?= $order['order_id'] ?>">
                                <span class="customer-name"><?= htmlspecialchars($order['full_name']) ?></span>
                                <i class="fas fa-info-circle info-icon"></i>

                                <div class="hidden-data" style="display:none;">
                                    <span class="customer-name"><?= htmlspecialchars($order['full_name']) ?></span>
                                    <span class="customer-email"><?= htmlspecialchars($order['email']) ?></span>
                                    <span class="customer-phone"><?= htmlspecialchars($order['phone_number']) ?></span>
                                    <span class="customer-address"><?= htmlspecialchars($order['street']) ?>, <?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['province']) ?>, <?= htmlspecialchars($order['zipcode']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?= getProductList($order['order_id'], $conn) ?></td>
                        <td>₱<?= number_format($order['totalPrice'], 2) ?></td>
                        <td class="payment-column hide-sm">
                            <?= htmlspecialchars($order['payment_method']) ?>
                            <?php if (!empty($order['proofOfPayment'])): ?>
                                <br>
                                <a href="javascript:void(0);" class="btn btn-view receipt-view"
                                    data-receipt="<?= htmlspecialchars($order['proofOfPayment']) ?>">
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

    <div class="overlay" id="popup-overlay"></div>
    
    <div class="customer-popup" id="customer-popup">
        <div class="popup-header">
            <h3>Customer Details</h3>
            <span class="close-popup" id="close-popup">&times;</span>
        </div>
        <div class="popup-content">
            <h4><i class="fas fa-user"></i> Contact Information</h4>
            <p>
                <strong>Name:</strong> <span id="popup-name"></span>
            </p>
            <p>
                <strong>Email:</strong> <span id="popup-email" class="clickable-contact" data-copy="email"></span>
                <span class="copied-message" id="email-copied">Copied!</span>
            </p>
            <p>
                <strong>Phone:</strong> <span id="popup-phone" class="clickable-contact" data-copy="phone"></span>
                <span class="copied-message" id="phone-copied">Copied!</span>
            </p>

            <h4><i class="fas fa-map-marker-alt"></i> Shipping Address</h4>
            <p>
                <span id="popup-address" class="clickable-contact" data-copy="address"></span>
                <span class="copied-message" id="address-copied">Copied!</span>
            </p>
        </div>
    </div>

    <!-- Receipt popup -->
    <div class="receipt-popup" id="receipt-popup">
        <div class="popup-header">
            <h3>Payment Receipt</h3>
            <span class="close-popup" id="close-receipt-popup">&times;</span>
        </div>
        <div class="popup-content">
            <div class="receipt-image-container">
                <img id="receipt-image" src="" alt="Payment Receipt">
            </div>
        </div>
    </div>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    const customerInfoElements = document.querySelectorAll('.customer-info');
    const popup = document.getElementById('customer-popup');
    const overlay = document.getElementById('popup-overlay');
    const closeBtn = document.getElementById('close-popup');
    const receiptPopup = document.getElementById('receipt-popup');
    const closeReceiptBtn = document.getElementById('close-receipt-popup');
    const receiptImage = document.getElementById('receipt-image');

function adjustPopupPosition(popupElement) {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    const viewportHeight = window.innerHeight;
    

    popupElement.style.position = 'fixed';
    popupElement.style.top = '50%';     
    popupElement.style.left = '50%';
    popupElement.style.transform = 'translate(-50%, -50%)';
}


function adjustOverlay() {
    overlay.style.position = 'fixed'; // Change to fixed
    overlay.style.top = '0';
    overlay.style.left = '0';
    overlay.style.width = '100%';
    overlay.style.height = '100%';
}

    customerInfoElements.forEach(element => {
        element.addEventListener('click', function() {
            const hiddenData = this.querySelector('.hidden-data');

            document.getElementById('popup-name').textContent = hiddenData.querySelector('.customer-name').textContent;
            document.getElementById('popup-email').textContent = hiddenData.querySelector('.customer-email').textContent;
            document.getElementById('popup-phone').textContent = hiddenData.querySelector('.customer-phone').textContent;
            document.getElementById('popup-address').textContent = hiddenData.querySelector('.customer-address').textContent;

            adjustPopupPosition(popup);
            adjustOverlay();
            popup.style.display = 'block';
            overlay.style.display = 'block';
        });
    });


    closeBtn.addEventListener('click', closePopup);
    overlay.addEventListener('click', closePopup);

    function closePopup() {
        popup.style.display = 'none';
        receiptPopup.style.display = 'none';
        overlay.style.display = 'none';

        document.querySelectorAll('.copied-message').forEach(el => {
            el.style.display = 'none';
        });
    }

    document.querySelectorAll('.clickable-contact').forEach(element => {
        element.addEventListener('click', function(e) {
            e.stopPropagation(); 
            const copyType = this.getAttribute('data-copy');
            const textToCopy = this.textContent.trim();
            
            navigator.clipboard.writeText(textToCopy)
                .then(() => showCopiedMessage(copyType + '-copied'))
                .catch(err => {
                    console.error('Could not copy text: ', err);
                });
        });
    });

    function showCopiedMessage(id) {
        const message = document.getElementById(id);
        message.style.display = 'inline';

        setTimeout(() => {
            message.style.display = 'none';
        }, 2000);
    }

    // Receipt popup
    document.querySelectorAll('.receipt-view').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation(); // Prevent event bubbling
            
            const receiptUrl = this.getAttribute('data-receipt');
            
            receiptImage.onload = function() {
                // Fix popup positioning
                adjustPopupPosition(receiptPopup);
                adjustOverlay();
                
                receiptPopup.style.display = 'block';
                overlay.style.display = 'block';
            };
            
            receiptImage.onerror = function() {
                alert('Failed to load receipt image. Please try again.');
            };
            
            receiptImage.src = receiptUrl;
        });
    });


    closeReceiptBtn.addEventListener('click', function(e) {
        e.stopPropagation(); 
        closePopup();
    });

 
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePopup();
        }
    });

    window.addEventListener('resize', function() {
        if (popup.style.display === 'block') {
            adjustPopupPosition(popup);
            adjustOverlay();
        }
        
        if (receiptPopup.style.display === 'block') {
            adjustPopupPosition(receiptPopup);
            adjustOverlay();
        }
    });

    window.addEventListener('scroll', function() {
        if (popup.style.display === 'block') {
            adjustPopupPosition(popup);
            adjustOverlay();
        }
        
        if (receiptPopup.style.display === 'block') {
            adjustPopupPosition(receiptPopup);
            adjustOverlay();
        }
    });
});
    </script>
</body>

</html>