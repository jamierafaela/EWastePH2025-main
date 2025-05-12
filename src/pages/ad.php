<?php
session_start();
include 'db_connect.php';

// Product Status 
define('STATUS_PENDING', 'Pending Review');
define('STATUS_APPROVED', 'Approved');
define('STATUS_REJECTED', 'Rejected');

// Order Status
define('ORDER_STATUS_PENDING', 'Pending');
define('ORDER_STATUS_PROCESSING', 'Processing');
define('ORDER_STATUS_SHIPPED', 'Shipped');
define('ORDER_STATUS_DELIVERED', 'Delivered');
define('ORDER_STATUS_CANCELLED', 'Cancelled');

$conn = $conn ?? new mysqli("localhost", "root", "", "ewaste_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// counts of pending
$count_pending = $conn->query("SELECT COUNT(*) as count FROM listings WHERE product_status = '" . STATUS_PENDING . "'")->fetch_assoc()['count'];

// counts orders
$order_count_query = "SELECT 
            COUNT(CASE WHEN order_status = '" . ORDER_STATUS_PENDING . "' THEN 1 END) as pending_count,
            COUNT(CASE WHEN order_status = '" . ORDER_STATUS_PROCESSING . "' THEN 1 END) as processing_count,
            COUNT(CASE WHEN order_status = '" . ORDER_STATUS_SHIPPED . "' THEN 1 END) as shipped_count,
            COUNT(CASE WHEN order_status = '" . ORDER_STATUS_DELIVERED . "' THEN 1 END) as delivered_count,
            COUNT(CASE WHEN order_status = '" . ORDER_STATUS_CANCELLED . "' THEN 1 END) as cancelled_count,
            COUNT(*) as total_count
            FROM orders";

$order_counts = $conn->query($order_count_query)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="ad.css">
    <title>Admin Dashboard</title>
    <style>
       
    </style>
</head>

<body>
    <button class="toggle-btn" id="toggle-sidebar">
        <i class="fas fa-chevron-left"></i>
    </button>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>E-Waste Admin</h2>
            <p>Welcome back, Admin</p>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Main Menu</div>
            <a href="#" class="nav-item active" data-page="admin_orders.php">
                <i class="fas fa-shopping-cart"></i>
                <span>Orders</span>
                <?php if ($order_counts['pending_count'] > 0): ?>
                    <span class="badge"><?php echo $order_counts['pending_count']; ?></span>
                <?php endif; ?>
            </a>

            <a href="#" class="nav-item" data-page="admin_products.php">
                <i class="fas fa-plus-circle"></i>
                <span>Add Product</span>
            </a>

            <a href="#" class="nav-item" data-page="admin_listing.php">
                <i class="fas fa-box-open"></i>
                <span>Listings Management</span>
                <?php if ($count_pending > 0): ?>
                    <span class="badge"><?php echo $count_pending; ?></span>
                <?php endif; ?>
            </a>
            <a href="#" class="nav-item" data-page="admin_accounts.php">
                <i class="fas fa-users"></i>
                <span>User Accounts</span>
            </a>


            <a href="#" class="nav-item" data-page="admin_messages.php">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
            </a>

            <a href="#" class="nav-item" data-page="admin_announcements.php">
                <i class="fas fa-bullhorn"></i>
                <span>Announcements</span>
            </a>
            <a href="#" class="nav-item" data-page="logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <div class="main-content" id="main-content">
        <div class="header">
            <div class="user-info">
            </div>
        </div>

        <div id="content-frame-container" class="content-area">
            <iframe id="content-frame" src="admin_orders.php"></iframe>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('toggle-sidebar');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const menuItems = document.querySelectorAll('.nav-item');
            const contentFrame = document.getElementById('content-frame');

            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';

            if (sidebarCollapsed) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                toggleBtn.classList.add('shifted');
                toggleBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
            } else {
                toggleBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
            }

            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                toggleBtn.classList.toggle('shifted');

                if (sidebar.classList.contains('collapsed')) {
                    toggleBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
                    localStorage.setItem('sidebarCollapsed', 'true');
                } else {
                    toggleBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
                    localStorage.setItem('sidebarCollapsed', 'false');
                }
            });

            function showPopup(popupId) {
                document.getElementById(popupId).style.display = 'block';
                document.querySelector('.overlay').style.display = 'block';
                document.body.style.overflow = 'hidden'; // Prevent scrolling
            }

            function hidePopup(popupId) {
                document.getElementById(popupId).style.display = 'none';
                document.querySelector('.overlay').style.display = 'none';
                document.body.style.overflow = 'auto'; // Enable scrolling
            }

            menuItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    menuItems.forEach(i => i.classList.remove('active'));

                    this.classList.add('active');
                    const page = this.getAttribute('data-page');
                    if (page === 'logout') {
                        window.location.href = 'logout.php';
                    } else {
                        loadPage(page);
                    }
                    if (window.innerWidth <= 768) {
                        sidebar.classList.add('collapsed');
                        mainContent.classList.add('expanded');
                        toggleBtn.classList.add('shifted');
                        toggleBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
                        localStorage.setItem('sidebarCollapsed', 'true');
                    }
                });
            });

            window.loadPage = function(page) {
                contentFrame.src = page;
            };

            loadPage('admin_orders.php');
        });

        function showPopup(popupId) {
            const modal = document.getElementById(popupId);
            modal.style.display = 'flex'; // Use flex instead of block
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        }

        function hidePopup(popupId) {
            document.getElementById(popupId).style.display = 'none';
            document.body.style.overflow = 'auto'; // Enable scrolling
        }

        if (document.querySelector('.overlay')) {
            document.querySelector('.overlay').addEventListener('click', function() {
                document.querySelectorAll('.customer-popup, .receipt-popup').forEach(popup => {
                    popup.style.display = 'none';
                });
                this.style.display = 'none';
                document.body.style.overflow = 'auto';
            });
        }
    </script>
</body>

</html>