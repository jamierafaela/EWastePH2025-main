<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location:../pages/ewasteWeb.php#loginSection");
    exit();
}

$conn = new mysqli("localhost", "root", "", "ewaste_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// get history
$purchases = array();
$purchase_query = $conn->prepare("
    SELECT order_id, full_name, order_date, totalPrice, order_status as status
    FROM orders 
    WHERE user_id = ?
    ORDER BY order_date DESC
");

if ($purchase_query) {
    $purchase_query->bind_param("i", $user_id);
    $purchase_query->execute();
    $result = $purchase_query->get_result();
    while ($row = $result->fetch_assoc()) {
        $item_count_query = $conn->prepare("
            SELECT COUNT(*) as item_count FROM order_items WHERE order_id = ?
        ");
        $item_count_query->bind_param("i", $row['order_id']);
        $item_count_query->execute();
        $item_count_result = $item_count_query->get_result();
        $item_count_row = $item_count_result->fetch_assoc();
        $row['item_count'] = $item_count_row['item_count'];

        $purchases[] = $row;
        $item_count_query->close();
    }
    $purchase_query->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase History - E-WastePH</title>
    <link rel="stylesheet" href="../styles/ewasteWeb.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .purchase-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .purchase-table th,
        .purchase-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .purchase-table th {
            background-color: #f5f5f5;
            font-weight: 600;
        }

        .purchase-table tr:hover {
            background-color: #f9f9f9;
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

        .order-details-btn {
            background-color: #f0f0f0;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.2s;
        }

        .order-details-btn:hover {
            background-color: #e0e0e0;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #777;
        }

        .empty-state i {
            font-size: 3em;
            margin-bottom: 15px;
            color: #ddd;
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
                    <h2 class="card-header">Purchase History</h2>
                    <a href="userdash.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

                    <?php if (empty($purchases)): ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-bag"></i>
                            <h3>No Purchase History</h3>
                            <p>You haven't made any purchases yet.</p>
                            <a href="../pages/ewasteShop.php" class="btn">Shop Now</a>
                        </div>
                    <?php else: ?>
                        <table class="purchase-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Items</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($purchases as $purchase): ?>
                                    <tr>
                                        <td>#<?= $purchase['order_id'] ?></td>
                                        <td><?= date("M d, Y h:i A", strtotime($purchase['order_date'])) ?></td>
                                        <td><?= $purchase['item_count'] ?> item(s)</td>
                                        <td>₱<?= number_format($purchase['totalPrice'], 2) ?></td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            switch (strtolower($purchase['status'])) {
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
                                            <span class="status-badge <?= $status_class ?>"><?= $purchase['status'] ?></span>

                                        </td>
                                        <td>
                                            <a href="order_details.php?id=<?= $purchase['order_id'] ?>" class="order-details-btn">View Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>
</body>

</html>