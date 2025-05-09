<?php
session_start();

if (!isset($_SESSION['user_id'])) {

    header("Location: ../pages/ewasteWeb.php#loginSection");
    exit();
}

$conn = new mysqli("localhost", "root", "", "ewaste_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

//status
$sales = array();
$sales_query = $conn->prepare("
    SELECT ps.listing_id, ps.product_name, ps.product_price, ps.created_at,
           ps.product_status, oi.order_id, o.order_date
    FROM listings ps
    LEFT JOIN order_items oi ON ps.listing_id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.order_id
    WHERE ps.seller_id = ?
    ORDER BY ps.created_at DESC
");

if ($sales_query) {
    $sales_query->bind_param("i", $user_id);
    $sales_query->execute();
    $result = $sales_query->get_result();
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }
    $sales_query->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History - E-WastePH</title>
    <link rel="stylesheet" href="../styles/ewasteWeb.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .sales-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .sales-table th,
        .sales-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .sales-table th {
            background-color: #f5f5f5;
            font-weight: 600;
        }

        .sales-table tr:hover {
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

        .status-sold {
            background-color: #e6f7e6;
            color: #2e7d32;
        }

        .status-approved {
            background-color: #e3f2fd;
            color: #1565c0;
        }

        .status-pending {
            background-color: #fff8e1;
            color: #f57f17;
        }

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
                    <h2 class="card-header">Sales History</h2>
                    <a href="userdash.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

                    <?php if (empty($sales)): ?>
                        <div class="empty-state">
                            <i class="fas fa-tags"></i>
                            <h3>No Sales History</h3>
                            <p>You haven't listed any items for sale yet.</p>
                            <a href="sell.php" class="btn">List an Item</a>
                        </div>
                    <?php else: ?>
                        <table class="sales-table">
                            <thead>
                                <tr>
                                    <th>Product ID</th>
                                    <th>Product Name</th>
                                    <th>Price</th>
                                    <th>Date Listed</th>
                                    <th>Status</th>
                                    <th>Order ID</th>
                                    <th>Sale Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales as $sale): ?>
                                    <tr>
                                        <td>#<?= $sale['listing_id'] ?></td>
                                        <td><?= htmlspecialchars($sale['product_name']) ?></td>
                                        <td>₱<?= number_format($sale['product_price'], 2) ?></td>
                                        <td><?= date("M d, Y", strtotime($sale['created_at'])) ?></td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            if ($sale['order_id']) {
                                                $status_class = 'status-sold';
                                                $status_text = 'Sold';
                                            } else {
                                                switch ($sale['product_status']) {
                                                    case 'Approved':
                                                        $status_class = 'status-approved';
                                                        $status_text = 'Approved';
                                                        break;
                                                    case 'Pending Review':
                                                        $status_class = 'status-pending';
                                                        $status_text = 'Pending';
                                                        break;
                                                    case 'Rejected':
                                                        $status_class = 'status-rejected';
                                                        $status_text = 'Rejected';
                                                        break;
                                                    default:
                                                        $status_text = $sale['product_status'];
                                                }
                                            }
                                            ?>
                                            <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                                        </td>
                                        <td>
                                            <?= $sale['order_id'] ? "#" . $sale['order_id'] : "Not sold yet" ?>
                                        </td>
                                        <td>
                                            <?= $sale['order_date'] ? date("M d, Y", strtotime($sale['order_date'])) : "N/A" ?>
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