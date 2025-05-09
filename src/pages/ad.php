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
    <title>Admin Dashboard</title>
    <style>
        :root {
            --primary-color: #2e7d32;
            --primary-light: #4caf50;
            --primary-dark: #1b5e20;
            --pending-color: #ff9800;
            --processing-color: #2196f3;
            --shipped-color: #9c27b0;
            --delivered-color: #4caf50;
            --cancelled-color: #f44336;
            --approved-color: #4caf50;
            --rejected-color: #f44336;
            --gray-light: #f0f4f1;
            --gray-medium: #e0e0e0;
            --white: #ffffff;
            --black: #333333;
            --text-dark: #212121;
            --text-light: #ffffff;
            --border-color: #e0e0e0;
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
            --sidebar-bg: #283040;
            --sidebar-hover: #1e2733;
            --sidebar-active: #1b5e20;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--gray-light);
            color: var(--black);
            line-height: 1.6;
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background-color: var(--sidebar-bg);
            color: var(--white);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            margin-bottom: 5px;
            color: var(--primary-light);
        }

        .sidebar-header p {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .nav-section {
            padding: 15px 0;
        }

        .nav-section-title {
            padding: 10px 20px;
            font-size: 12px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
            letter-spacing: 1px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--white);
            text-decoration: none;
            position: relative;
            transition: background-color 0.3s;
        }

        .nav-item:hover {
            background-color: var(--sidebar-hover);
            text-decoration: none;
        }

        .nav-item.active {
            background-color: var(--sidebar-active);
            border-left: 4px solid var(--primary-light);
        }

        .nav-item i {
            font-size: 1.2rem;
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Badge for notifications */
        .badge {
            background-color: #4caf50;
            color: white;
            font-size: 12px;
            font-weight: bold;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: auto;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            overflow-y: auto;
        }



        .user-info {
            display: flex;
            align-items: center;
        }

        .user-info span {
            margin-right: 10px;
            font-weight: 500;
        }

        .user-info .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }

        .content-area {
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 10px;
        }

        iframe {
            width: 100%;
            height: calc(250vh - 120px);
            border: none;
        }
        @media (max-width: 991px) {
            .sidebar {
                width: 200px;
            }

            .main-content {
                margin-left: 200px;
            }
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .sidebar-header {
                padding: 10px;
            }

            .nav-item {
                padding: 10px 15px;
            }

            .main-content {
                margin-left: 0;
            }


            .user-info {
                margin-top: 10px;
            }

            .toggle-btn {
                display: block;
                position: fixed;
                left: 10px;
                top: 10px;
                z-index: 1000;
                background: none;
                border: none;
                color: var(--text-dark);
                font-size: 24px;
                cursor: pointer;
            }

            .sidebar {
                position: fixed;
                left: -250px;
                height: 100%;
                z-index: 999;
                transition: all 0.3s;
            }

            .sidebar.active {
                left: 0;
            }
        }
    </style>
</head>

<body>

    <button class="toggle-btn" id="toggle-sidebar">☰</button>

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
                <span>Product Management</span>
                <?php if ($count_pending > 0): ?>
                    <span class="badge"><?php echo $count_pending; ?></span>
                <?php endif; ?>
            </a>

            <a href="#" class="nav-item" data-page="logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <div class="user-info">
            </div>
        </div>

        <div id="content-frame-container" class="content-area">
            <iframe id="content-frame" src="admin_sellreview.php"></iframe>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('toggle-sidebar');
            const sidebar = document.getElementById('sidebar');
            const menuItems = document.querySelectorAll('.nav-item');
            const contentFrame = document.getElementById('content-frame');

            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });

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
                });
            });

            window.loadPage = function(page) {
                contentFrame.src = page;
            };

   
            loadPage('admin_orders.php');
        });
    </script>
</body>

</html>