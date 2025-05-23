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

// Improved query using LEFT JOIN to get order information more efficiently
$query = "SELECT 
            l.listing_id, 
            l.product_name, 
            l.product_price, 
            l.created_at,
            l.product_status, 
            l.rejection_reason,
            l.updated_at,
            oi.order_id,
            COALESCE(o.updated_at, l.updated_at) as sold_date
          FROM 
            listings l
          LEFT JOIN 
            order_items oi ON l.listing_id = oi.product_id
          LEFT JOIN 
            orders o ON oi.order_id = o.order_id
          WHERE 
            l.seller_id = ?
          ORDER BY 
            l.created_at DESC";

$sales = array();
$sales_query = $conn->prepare($query);

if ($sales_query) {
    $sales_query->bind_param("i", $user_id);
    $sales_query->execute();
    $result = $sales_query->get_result();
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }
    $sales_query->close();
}

// Get sales statistics
$stats = array(
    'total' => 0,
    'sold' => 0,
    'approved' => 0,
    'pending' => 0,
    'rejected' => 0,
    'revenue' => 0
);

foreach ($sales as $sale) {
    $stats['total']++;

    // Check if item is sold either by having an order_id or explicitly marked as "Sold"
    if (!empty($sale['order_id']) || $sale['product_status'] == 'Sold') {
        $stats['sold']++;
        $stats['revenue'] += $sale['product_price'];
    } else {
        switch ($sale['product_status']) {
            case 'Approved':
                $stats['approved']++;
                break;
            case 'Pending Review':
                $stats['pending']++;
                break;
            case 'Rejected':
                $stats['rejected']++;
                break;
        }
    }
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
    
    <link rel="stylesheet" href="../styles/sales_history.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
     
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
                    <h2 class="card-header">Listings History</h2>
                    <a href="userdash.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

                    <?php if (empty($sales)): ?>
                        <div class="empty-state">
                            <i class="fas fa-tags"></i>
                            <h3>No Listings History</h3>
                            <p>You haven't listed any items for sale yet.</p>
                            <a href="sell.php" class="btn">List an Item</a>
                        </div>
                    <?php else: ?>
                        <!-- Sales Statistics -->
                        <div class="stats-container">
                            <div class="stat-card">
                                <h3><?= $stats['total'] ?></h3>
                                <p>Total Listings</p>
                            </div>
                            <div class="stat-card">
                                <h3><?= $stats['sold'] ?></h3>
                                <p>Items Sold</p>
                            </div>
                            <div class="stat-card">
                                <h3>₱<?= number_format($stats['revenue'], 2) ?></h3>
                                <p>Total Revenue</p>
                            </div>
                            <div class="stat-card">
                                <h3><?= $stats['pending'] ?></h3>
                                <p>Pending Review</p>
                            </div>
                        </div>

                        <!-- Filters and Search -->
                        <div class="filters">
                            <button class="filter-btn active" data-filter="all">All</button>
                            <button class="filter-btn" data-filter="sold">Sold</button>
                            <button class="filter-btn" data-filter="approved">Approved</button>
                            <button class="filter-btn" data-filter="pending">Pending</button>
                            <button class="filter-btn" data-filter="rejected">Rejected</button>
                            <input type="text" class="search-box" placeholder="Search by product name...">
                        </div>

                        <!-- No results message -->
                        <div class="no-results">
                            <p>No listings match your current filters</p>
                        </div>

                        <table class="sales-table">
                            <thead>
                                <tr>
                                    <th>Product ID</th>
                                    <th>Product Name</th>
                                    <th>Price</th>
                                    <th>Date Listed</th>
                                    <th>Status</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales as $sale): ?>
                                    <?php
                                    $status_class = '';
                                    $row_class = '';

                                    // Improved status detection logic
                                    if (!empty($sale['order_id']) || $sale['product_status'] == 'Sold') {
                                        $status_class = 'status-sold';
                                        $status_text = 'Sold';
                                        $row_class = 'sold';
                                    } else {
                                        switch ($sale['product_status']) {
                                            case 'Approved':
                                                $status_class = 'status-approved';
                                                $status_text = 'Approved';
                                                $row_class = 'approved';
                                                break;
                                            case 'Pending Review':
                                                $status_class = 'status-pending';
                                                $status_text = 'Pending';
                                                $row_class = 'pending';
                                                break;
                                            case 'Rejected':
                                                $status_class = 'status-rejected';
                                                $status_text = 'Rejected';
                                                $row_class = 'rejected';
                                                break;
                                            default:
                                                $status_text = $sale['product_status'];
                                                $row_class = strtolower($sale['product_status']);
                                        }
                                    }
                                    ?>
                                    <tr class="<?= $row_class ?>-row" data-product-name="<?= htmlspecialchars(strtolower($sale['product_name'])) ?>">
                                        <td>#<?= $sale['listing_id'] ?></td>
                                        <td><?= htmlspecialchars($sale['product_name']) ?></td>
                                        <td>₱<?= number_format($sale['product_price'], 2) ?></td>
                                        <td><?= date("M d, Y", strtotime($sale['created_at'])) ?></td>
                                        <td>
                                            <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                                        </td>

                                        <td class="details-column">
                                            <?php if (!empty($sale['order_id'])): ?>
                                                <div class="sold-detail">
                                                    <i class="fas fa-check-circle"></i>
                                                    <span class="detail-label">Order:</span>
                                                    <span class="detail-value">#<?= $sale['order_id'] ?></span>
                                                </div>
                                                <span class="date-info">
                                                    <i class="far fa-calendar-alt"></i>
                                                    Sold on <?= !empty($sale['sold_date']) ? date("M d, Y h:i A", strtotime($sale['sold_date'])) : date("M d, Y h:i A", strtotime($sale['updated_at'])) ?>
                                                </span>
                                            <?php elseif ($sale['product_status'] == 'Sold'): ?>
                                                <div class="sold-detail">
                                                    <i class="fas fa-check-circle"></i>
                                                    <span class="detail-value">Sale completed</span>
                                                </div>
                                                <span class="date-info">
                                                    <i class="far fa-calendar-alt"></i>
                                                    Sold on <?= date("M d, Y h:i A", strtotime($sale['updated_at'])) ?>
                                                </span>
                                            <?php elseif ($sale['product_status'] == 'Rejected'): ?>
                                                <div class="rejected-detail">
                                                    <i class="fas fa-exclamation-circle"></i>
                                                    <div>
                                                        <span class="detail-value">Rejected</span>
                                                        <?php if (!empty($sale['rejection_reason'])): ?>
                                                            <span class="rejection-reason"><?= htmlspecialchars($sale['rejection_reason']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <span class="date-info">
                                                    <i class="far fa-calendar-alt"></i>
                                                    Rejected on <?= date("M d, Y h:i A", strtotime($sale['updated_at'])) ?>
                                                </span>
                                            <?php elseif ($sale['product_status'] == 'Approved'): ?>
                                                <div class="approved-detail">
                                                    <i class="fas fa-tag"></i>
                                                    <span class="detail-value">Listed for sale</span>
                                                </div>
                                                <span class="date-info">
                                                    <i class="far fa-clock"></i>
                                                    Approved on <?= date("M d, Y h:i A", strtotime($sale['updated_at'])) ?>
                                                </span>
                                            <?php elseif ($sale['product_status'] == 'Pending Review'): ?>
                                                <div class="pending-detail">
                                                    <i class="fas fa-hourglass-half"></i>
                                                    <span class="detail-value">Awaiting approval</span>
                                                </div>
                                                <span class="date-info">
                                                    <i class="far fa-calendar-alt"></i>
                                                    Submitted on <?= date("M d, Y h:i A", strtotime($sale['created_at'])) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="detail-value">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const filterButtons = document.querySelectorAll('.filter-btn');
                                const searchBox = document.querySelector('.search-box');
                                const tableRows = document.querySelectorAll('.sales-table tbody tr');
                                const noResultsMsg = document.querySelector('.no-results');

                                // Apply filters and search
                                function applyFilters() {
                                    const activeFilter = document.querySelector('.filter-btn.active').getAttribute('data-filter');
                                    const searchTerm = searchBox.value.toLowerCase();

                                    let visibleCount = 0;

                                    tableRows.forEach(row => {
                                        // First apply category filter
                                        let showRow = activeFilter === 'all' || row.classList.contains(activeFilter + '-row');

                                        // Then apply search filter
                                        if (showRow && searchTerm) {
                                            const productName = row.getAttribute('data-product-name');
                                            showRow = productName.includes(searchTerm);
                                        }

                                        row.style.display = showRow ? '' : 'none';

                                        if (showRow) {
                                            visibleCount++;
                                        }
                                    });

                                    // Show/hide no results message
                                    noResultsMsg.style.display = visibleCount === 0 ? 'block' : 'none';
                                }

                                // Filter button click handlers
                                filterButtons.forEach(button => {
                                    button.addEventListener('click', function() {
                                        filterButtons.forEach(btn => btn.classList.remove('active'));
                                        this.classList.add('active');
                                        applyFilters();
                                    });
                                });

                                // Search input handler
                                searchBox.addEventListener('input', applyFilters);

                                // Apply filters on page load
                                applyFilters();
                            });
                        </script>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>
</body>

</html>