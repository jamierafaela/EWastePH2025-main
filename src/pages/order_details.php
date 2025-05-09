<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location:../pages/ewasteWeb.php#loginSection");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: purchase_history.php");
    exit();
}

$order_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "ewaste_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$auth_query = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$auth_query->bind_param("ii", $order_id, $user_id);
$auth_query->execute();
$auth_result = $auth_query->get_result();

if ($auth_result->num_rows === 0) {
    $auth_query->close();
    $conn->close();
    header("Location: purchase_history.php");
    exit();
}

// Get order details
$order_query = $conn->prepare("
    SELECT order_id, full_name, phone_number, street, city, province, zipcode,
           totalQuantity, product_details, totalPrice, payment_method, 
           gcashNumber, gcashName, proofOfPayment, order_status, order_date, updated_at
    FROM orders
    WHERE order_id = ?
");
$order_query->bind_param("i", $order_id);
$order_query->execute();
$order_result = $order_query->get_result();
$order_details = $order_result->fetch_assoc();
$order_query->close();

$items_query = $conn->prepare("
    SELECT id, order_id, quantity, price, product_id, product_name
    FROM order_items
    WHERE order_id = ?
");
$items_query->bind_param("i", $order_id);
$items_query->execute();
$items_result = $items_query->get_result();
$order_items = [];
while ($item = $items_result->fetch_assoc()) {
    $order_items[] = $item;
}
$items_query->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - E-WastePH</title>
    <link rel="stylesheet" href="../styles/ewasteWeb.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .order-details-container {
            width: 100%;
            margin-bottom: 30px;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .order-id {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .order-date {
            color: #666;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 500;
            color: #333;
        }

        .status-completed,
        .status-approved,
        .status-delivered {
            background-color: #e6f7e6;
            color: #2e7d32;
        }

        .status-processing {
            background-color: #e3f2fd;
            color: #1565c0;
        }

        .status-pending {
            background-color: #e3f2fd;
            color: #1565c0;
        }

        .status-shipped {
            background-color: #fff8e1;
            color: #f57f17;
        }

        .status-cancelled,
        .status-rejected {
            background-color: #ffebee;
            color: #c62828;
        }

        .order-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-box {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
        }

        .summary-box h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            font-size: 1.1rem;
        }

        .address-details p,
        .payment-details p {
            margin: 5px 0;
            font-size: 0.95rem;
        }

        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .item-table th,
        .item-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .item-table th {
            background-color: #f5f5f5;
            font-weight: 600;
        }

        .price-details {
            margin-left: auto;
            width: 300px;
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }

        .price-row:not(:last-child) {
            border-bottom: 1px solid #eee;
        }

        .price-total {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #333;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link i {
            margin-right: 5px;
        }

        .payment-proof {
            margin-top: 20px;
        }

        .payment-proof img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .product-details {
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .order-summary {
                grid-template-columns: 1fr;
            }

            .price-details {
                width: 100%;
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <header>
        <nav class="navbar">
            <div class="logo-container">
                <a href="../pages/ewasteWeb.php" class="logo"><img src="../../Public/images/logo.png" alt="EWastePH Logo" /></a>
            </div>
            <ul class="nav-links">
                <li><a href="../pages/ewasteWeb.php#home">Home</a></li>
                <li><a href="../pages/ewasteWeb.php#about">About Us</a></li>
                <li><a href="../pages/ewasteWeb.php#faq">FAQ</a></li>
                <li><a href="../pages/ewasteWeb.php#contact">Contact Us</a></li>
                <li><a href="../pages/ewasteShop.php">Shop</a></li>
                <li><a href="../pages/ewasteWeb.php#profile"><i class="fa fa-user"></i></a></li>
            </ul>
        </nav>
    </header>

    <div class="userDashSec">
        <div class="container">
            <main class="main-content" style="width: 100%;">
                <section class="card">
                    <h2 class="card-header">Order Details</h2>
                    <a href="purchase_history.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Purchase History</a>

                    <div class="order-details-container">
                        <div class="order-header">
                            <div>
                                <span class="order-id">Order #<?= $order_details['order_id'] ?></span>
                                <span class="order-date"><?= date("M d, Y h:i A", strtotime($order_details['order_date'])) ?></span>
                            </div>
                            <?php
                            $status_class = '';
                            switch (strtolower($order_details['order_status'])) {
                                case 'completed':
                                case 'approved':
                                case 'delivered':
                                    $status_class = 'status-delivered';
                                    break;
                                case 'processing':
                                    $status_class = 'status-processing';
                                    break;
                                case 'pending':
                                    $status_class = 'status-pending';
                                    break;
                                case 'shipped':
                                    $status_class = 'status-shipped';
                                    break;
                                case 'cancelled':
                                case 'rejected':
                                    $status_class = 'status-rejected';
                                    break;
                                default:
                                    $status_class = '';
                            }
                            ?>
                            <span class="status-badge <?= $status_class ?>"><?= $order_details['order_status'] ?></span>
                        </div>

                        <div class="order-summary">
                            <div class="summary-box address-details">
                                <h3>Shipping Information</h3>
                                <p><strong>Name:</strong> <?= htmlspecialchars($order_details['full_name']) ?></p>
                                <p><strong>Phone:</strong> <?= htmlspecialchars($order_details['phone_number']) ?></p>
                                <p><strong>Address:</strong> <?= htmlspecialchars($order_details['street']) ?></p>
                                <p><strong>City:</strong> <?= htmlspecialchars($order_details['city']) ?></p>
                                <p><strong>Province:</strong> <?= htmlspecialchars($order_details['province']) ?></p>
                                <p><strong>Zip Code:</strong> <?= htmlspecialchars($order_details['zipcode']) ?></p>
                            </div>

                            <div class="summary-box payment-details">
                                <h3>Payment Information</h3>
                                <p><strong>Payment Method:</strong> <?= htmlspecialchars($order_details['payment_method']) ?></p>
                                <?php if ($order_details['payment_method'] == 'GCash' || !empty($order_details['gcashNumber'])): ?>
                                    <p><strong>GCash Number:</strong> <?= htmlspecialchars($order_details['gcashNumber']) ?></p>
                                    <?php if (!empty($order_details['gcashName'])): ?>
                                        <p><strong>GCash Account Name:</strong> <?= htmlspecialchars($order_details['gcashName']) ?></p>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <p><strong>Total Items:</strong> <?= $order_details['totalQuantity'] ?></p>
                                <p><strong>Order Total:</strong> ₱<?= number_format($order_details['totalPrice'], 2) ?></p>
                                <p><strong>Last Updated:</strong> <?= date("M d, Y h:i A", strtotime($order_details['updated_at'])) ?></p>
                            </div>
                        </div>

                        <h3>Order Items</h3>
                        <table class="item-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($order_items) > 0): ?>
                                    <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['product_name']) ?></td>
                                            <td><?= $item['quantity'] ?></td>
                                            <td>₱<?= number_format($item['price'], 2) ?></td>
                                            <td>₱<?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                            
                                    <tr>
                                        <td colspan="4" class="product-details">
                                            <strong>Raw product details:</strong><br>
                                            <?= nl2br(htmlspecialchars($order_details['product_details'])) ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <div class="price-details">
                            <div class="price-row price-total">
                                <div>Total</div>
                                <div>₱<?= number_format($order_details['totalPrice'], 2) ?></div>
                            </div>
                        </div>

                        <?php if (!empty($order_details['proofOfPayment'])): ?>
                            <div class="payment-proof">
                                <h3>Proof of Payment</h3>
                                <img src="<?= htmlspecialchars($order_details['proofOfPayment']) ?>" alt="Proof of Payment">
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </main>
        </div>
    </div>
</body>

</html>