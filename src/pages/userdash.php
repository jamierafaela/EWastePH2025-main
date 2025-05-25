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

// Fetch seller_id from sellers table based on user_id
$current_seller_id = null;
$seller_id_query = $conn->prepare("SELECT seller_id FROM sellers WHERE user_id = ?");
if ($seller_id_query) {
    $seller_id_query->bind_param("i", $user_id);
    $seller_id_query->execute();
    $seller_id_result = $seller_id_query->get_result();
    if ($seller_id_row = $seller_id_result->fetch_assoc()) {
        $current_seller_id = $seller_id_row['seller_id'];
    }
    $seller_id_query->close();
} else {
    error_log("Failed to prepare seller_id query in userdash.php for user_id: " . $user_id);
}

$profileCheckStmt = $conn->prepare("SELECT profile_completed FROM users WHERE user_id = ?");
$profileCheckStmt->bind_param("i", $user_id);
$profileCheckStmt->execute();
$profileResult = $profileCheckStmt->get_result();
$profileData = $profileResult->fetch_assoc();

if (!isset($profileData['profile_completed']) || $profileData['profile_completed'] != 1) {
    header("Location: predashboard.php");
    exit();
}

$total_listed = 0;
if ($current_seller_id !== null) {
    $list_query = $conn->prepare("SELECT COUNT(*) FROM listings WHERE seller_id = ?");
    if ($list_query) {
        $list_query->bind_param("i", $current_seller_id);
        $list_query->execute();
        $list_query->bind_result($total_listed);
        $list_query->fetch();
        $list_query->close();
    }
}

//items purchased
$total_purchased = 0;
$purchase_query = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
if ($purchase_query) {
    $purchase_query->bind_param("i", $user_id);
    $purchase_query->execute();
    $purchase_query->bind_result($total_purchased);
    $purchase_query->fetch();
    $purchase_query->close();
}

    // Count total rejected listings
$total_rejected = 0;
if ($current_seller_id !== null) {
    $rejected_count_query = $conn->prepare("SELECT COUNT(*) FROM listings WHERE seller_id = ? AND product_status = 'Rejected'");
    if ($rejected_count_query) {
        $rejected_count_query->bind_param("i", $current_seller_id);
        $rejected_count_query->execute();
        $rejected_count_query->bind_result($total_rejected);
        $rejected_count_query->fetch();
        $rejected_count_query->close();
    }
}
//recent acts 
$activities = array();

// get recent listings
if ($current_seller_id !== null) {
    $list_activity = $conn->prepare("
        SELECT 'listed' AS type, product_name AS name, created_at AS activity_date 
        FROM listings 
        WHERE seller_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");

    if ($list_activity) {
        $list_activity->bind_param("i", $current_seller_id);
        $list_activity->execute();
        $result = $list_activity->get_result();
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
        $list_activity->close();
    }
}

// delete message
$delete_message = '';

// delete action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    $product_id = $_GET['id'];

    // Check if the current user (via their seller_id) is the owner of the listing
    if ($current_seller_id !== null) {
        $check_query = $conn->prepare("SELECT seller_id FROM listings WHERE listing_id = ?");
        $check_query->bind_param("i", $product_id);
        $check_query->execute();
        $check_query->store_result();

        if ($check_query->num_rows > 0) {
            $check_query->bind_result($listing_seller_id);
            $check_query->fetch();

            if ($listing_seller_id == $current_seller_id) {
                // Delete the product
                $delete_query = $conn->prepare("DELETE FROM listings WHERE listing_id = ?");
                $delete_query->bind_param("i", $product_id);

                if ($delete_query->execute()) {
                    $delete_message = '<div class="notification-message success-message">Your listing has been successfully deleted!</div>';
                } else {
                    $delete_message = '<div class="notification-message error-message">Error deleting listing. Please try again.</div>';
                }
                $delete_query->close();
            } else {
                $delete_message = '<div class="notification-message error-message">You are not authorized to delete this listing.</div>';
            }
        } else {
            $delete_message = '<div class="notification-message error-message">Listing not found.</div>';
        }
        $check_query->close();
    } else {
        $delete_message = '<div class="notification-message error-message">Cannot delete listing: Seller details not found.</div>';
    }
}

//get recent purchase
$purchase_activity = $conn->prepare("
    SELECT 'purchased' AS type, oi.product_name AS name, o.order_date AS activity_date 
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.user_id = ?
    ORDER BY o.order_date DESC
    LIMIT 5
");

if ($purchase_activity) {
    $purchase_activity->bind_param("i", $user_id);
    $purchase_activity->execute();
    $result = $purchase_activity->get_result();
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    $purchase_activity->close();
}

$active_listings = array();
if ($current_seller_id !== null) {
    $active_query = $conn->prepare("
        SELECT listing_id, product_name, product_price, created_at 
        FROM listings 
        WHERE seller_id = ? AND product_status = 'Pending Review'
        ORDER BY created_at DESC
    ");

    if ($active_query) {
        $active_query->bind_param("i", $current_seller_id);
        $active_query->execute();
        $result = $active_query->get_result();
        while ($row = $result->fetch_assoc()) {
            $active_listings[] = $row;
        }
        $active_query->close();
    }
}

// approved listing
$sold_listings = array();
if ($current_seller_id !== null) {
    $sold_query = $conn->prepare("
        SELECT listing_id, product_name, product_price, created_at
        FROM listings 
        WHERE seller_id = ? AND product_status = 'Approved'
        ORDER BY created_at DESC
    ");

    if ($sold_query) {
        $sold_query->bind_param("i", $current_seller_id);
        $sold_query->execute();
        $result = $sold_query->get_result();
        while ($row = $result->fetch_assoc()) {
            $sold_listings[] = $row;
        }
        $sold_query->close();
    }
}

    // rejected listings
$rejected_listings = array();
if ($current_seller_id !== null) {
    $rejected_query = $conn->prepare("
        SELECT listing_id, product_name, product_price, created_at, rejection_reason
        FROM listings 
        WHERE seller_id = ? AND product_status = 'Rejected'
        ORDER BY created_at DESC
    ");

    if ($rejected_query) {
        $rejected_query->bind_param("i", $current_seller_id);
        $rejected_query->execute();
        $result = $rejected_query->get_result();
        while ($row = $result->fetch_assoc()) {
            $rejected_listings[] = $row;
        }
        $rejected_query->close();
    }
}

// Check if warning should be shown (if 3+ rejected listings)
$show_rejection_warning = ($total_rejected >= 3);
//sort recent
usort($activities, function ($a, $b) {
    return strtotime($b['activity_date']) - strtotime($a['activity_date']);
});

//get 5 recent acts
$activities = array_slice($activities, 0, 5);

//pfpandname
$isLoggedIn = isset($_SESSION['user_id']);

$userDetails = null;
if ($isLoggedIn) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM user_details WHERE user_id = ? ORDER BY detail_id DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $userDetails = $result->fetch_assoc();
    }
} else {
    header("Location: ../pages/ewasteWeb.php#loginSection");
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/ewasteWeb.php#Login");
    exit();
}

$user_id = $_SESSION['user_id'];

$show_profile_popup = false;
$profile_message = "";

if (isset($_SESSION['profile_success']) && $_SESSION['profile_success']) {
    $show_profile_popup = true;
    $profile_message = $_SESSION['profile_message'] ?? "Profile completed successfully!";


    unset($_SESSION['profile_success']);
    unset($_SESSION['profile_message']);
}
    $announcements_query = "SELECT a.* 
                       FROM announcements a 
                       ORDER BY a.created_at DESC 
                       LIMIT 2";
$announcements_result = $conn->query($announcements_query);

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-WastePH User Dashboard</title>
    <link rel="stylesheet" href="../styles/userdash.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .activity-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        .activity-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .activity-list li:last-child {
            border-bottom: none;
        }

        .activity-type {
            display: inline-flex;
            align-items: center;
            font-weight: bold;
            margin-right: 5px;
        }

        .activity-type.listed {
            color: #4CAF50;
        }

        .activity-type.purchased {
            color: #2196F3;
        }

        .activity-date {
            color: #777;
            font-size: 0.85em;
            font-style: italic;
        }

        .product-name {
            font-weight: 500;
        }

        .listings-container {
            display: none;
            margin-top: 15px;
        }

        .listings-container.active {
            display: block;
        }

        .action-btn {
            padding: 5px 8px;
            background-color: #2196F3;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.85em;
            margin-right: 5px;
        }

        .delete-btn {
            background-color: #f44336;
        }

        .notification-message {
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-weight: 500;
        }

        .success-message {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }

        .error-message {
            background-color: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }


        .notification-message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }

        .success-message:before {
            content: "\f00c";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            margin-right: 10px;
            font-size: 1.2em;
        }

        .error-message {
            background-color: #ffebee;
            color: #c62828;
            border-left: 4px solid #c62828;
        }

        .error-message:before {
            content: "\f00d";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            margin-right: 10px;
            font-size: 1.2em;
        }

        @keyframes modalAppear {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .modal-header h3 {
            margin: 0;
            color: #333;
            font-size: 1.2em;
        }

        .close-modal {
            cursor: pointer;
            font-size: 1.5em;
            font-weight: bold;
            color: #aaa;
        }

        .close-modal:hover {
            color: #333;
        }

        .modal-body {
            padding: 10px 0;
        }

        .modal-buttons {
            text-align: right;
            border-top: 1px solid #eee;
            padding-top: 15px;
            margin-top: 15px;
        }

        .modal-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            margin-left: 10px;
        }

        .modal-cancel {
            background-color: #f0f0f0;
            color: #333;
        }

        .modal-cancel:hover {
            background-color: #e0e0e0;
        }

        .modal-delete {
            background-color: #f44336;
            color: white;
            text-decoration: none;
            display: inline-block;
        }

        .modal-delete:hover {
            background-color: #e53935;
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .status-badge.approved {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .registration-success-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            width: 100%;
            position: fixed;
            top: 0;
            left: 0;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
        }

        .registration-success-container1 {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            max-width: 500px;
        }

        .success-message {
            color: green;
            margin-bottom: 20px;
        }

        .registration-success-container1 a {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
            font-weight: bold;
        }

        .close-popup {
            display: inline-block;
            padding: 10px 20px;
            background-color: #f44336;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
            font-weight: bold;
            cursor: pointer;
            }
            .warning-message {
    background-color: #fff3cd;
    color: #856404;
    border-left: 4px solid #ffeeba;
}

.warning-message:before {
    content: "\f071";
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
    margin-right: 10px;
    font-size: 1.2em;
}

.rejection-warning {
    margin-top: 5px;
    font-size: 0.8em;
    color: #c62828;
    display: flex;
    align-items: center;
    gap: 5px;
}

.status-badge.rejected {
    background-color: #ffebee;
    color: #c62828;
}
:root {
    --primary-color: #2e7d32;
    --primary-light: #4caf50;
    --primary-dark: #1b5e20;
    --success: #4caf50;
    --accent: #ff9800;
    --gray-light: #f0f4f1;
    --gray-medium: #e0e0e0;
    --white: #ffffff;
    --black: #333333;
    --background: #f0f2f0;
    --card-bg: #ffffff;
    --text-dark: #212121;
    --text-light: #ffffff;
    --text-muted: #666666;
    --border-color: #e0e0e0;
    --shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    --border-radius: 8px;
}

.announcements-container {
    background: var(--card-bg);
    border-radius: 15px;
    box-shadow: var(--shadow);
    margin-bottom: 30px;
    overflow: hidden;
    border: 1px solid var(--border-color);
    padding: 0;
}



.announcements-list {
    padding: 0;
}

.announcement-item {
    border-bottom: 1px solid var(--border-color);
    padding: 20px 25px;
    transition: all 0.3s ease;
    position: relative;
}

.announcement-item:last-child {
    border-bottom: none;
}

.announcement-item:hover {
    background: var(--gray-light);
    transform: translateX(5px);
}

.announcement-label {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--primary-color);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 2px solid var(--primary-color);
    padding-bottom: 8px;
}

.announcement-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.announcement-title::before {
    content: '';
    width: 4px;
    height: 18px;
    background: var(--accent);
    border-radius: 2px;
    flex-shrink: 0;
}

.announcement-body {
    color: var(--text-dark);
    line-height: 1.6;
    margin-bottom: 12px;
    font-size: 0.95rem;
}

.announcement-meta {
    display: flex;
    justify-content: flex-end;
    align-items: flex-end;
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-top: 15px;
}

.announcement-date {
    text-align: right;
    line-height: 1.3;
}

.announcement-date small {
    font-size: 0.75rem;
    opacity: 0.8;
}



.announcement-date {
    font-style: italic;
}

.announcement-badge {
    background: var(--success);
    color: var(--white);
    font-size: 0.65rem;
    padding: 4px 8px;
    border-radius: 12px;
    font-weight: 600;
    text-transform: uppercase;
    margin-left: 10px;
}

.announcement-badge.new {
    background: var(--success);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

.no-announcements {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}

.no-announcements i {
    font-size: 2.5rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

.no-announcements h4 {
    margin-bottom: 8px;
    color: var(--text-dark);
}





/* Responsive design */
@media (max-width: 768px) {
    .announcements-container {
        margin: 0 -10px 20px -10px;
        border-radius: 10px;
    }
    
    .announcements-header {
        padding: 15px 20px;
    }
    
    .announcements-header h3 {
        font-size: 1.2rem;
    }
    
    .announcement-item {
        padding: 15px 20px;
    }
    
    .announcement-meta {
        align-items: flex-start;
        gap: 5px;
    }
    
    .announcement-badge {
        margin-left: 0;
        margin-top: 5px;
    }
        }
    </style>
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
            <!-- Sidebar -->
            <aside class="sidebar">
                <!-- Profile Card -->
                <div class="profile-card">
                    <div class="profile-image">
                        <?php if (!empty($userDetails['pfp'])): ?>
                            <img src="<?php echo htmlspecialchars($userDetails['pfp']); ?>" alt="Profile Picture" style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <div class="profile-image-placeholder">X</div>
                        <?php endif; ?>
                    </div>
                    <div class="profile-details">
                        <h2 class="username"> <?php echo htmlspecialchars($userDetails['full_name'] ?? 'Guest'); ?> </h2>
                        <p><?php echo htmlspecialchars($userDetails['email'] ?? ''); ?></p>
                        <button id="logoutBtn" class="btn" onclick="window.location.href='logout.php'">Log out</button>
                    </div>
                </div>

                <!-- Sidebar Menu -->
                <div class="sidebar-menu">
                    <div class="sidebar-menu-section">
                        <h3 class="section-title">View</h3>
                        <a href="#" class="sidebar-link">Likes<i class="fas fa-heart"></i></a>
                        <a href="#" class="sidebar-link">Wishlist<i class="fas fa-star"></i></a>
                        <a href="#" class="sidebar-link">My Purchases<i class="fas fa-shopping-cart"></i></a>
                    </div>
                    <div class="sidebar-menu-section">
                        <h3 class="section-title">Notifications</h3>
                        <a href="#" class="sidebar-link">Alerts<i class="fas fa-bell"></i></a>
                    </div>
                    <div class="sidebar-menu-section">
                        <h3 class="section-title">Settings</h3>
                        <a href="account_settings.php" class="sidebar-link">Account Settings<i class="fas fa-cog"></i></a>
                        <a href="privacy_settings.php   " class="sidebar-link">Privacy Settings<i class="fas fa-shield-alt"></i></a>
                        <a href="  " class="sidebar-link">Terms and Condition</a>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="main-content">
                <?php if ($show_rejection_warning): ?>
    <div class="notification-message warning-message">
        <i class=""></i> Warning: You have multiple rejected listings. Please review our guidelines carefully before submitting new items.
    </div>
<?php endif; ?>
                <?php
                if (!empty($delete_message)) {
                    echo $delete_message;
                }
                ?>
                <!-- Dashboard Overview -->
                <section class="card dashboard-overview">
                    <h2 class="card-header">Dashboard Overview</h2>
                    <div class="stats-container">
                        <div class="stat-card">
                            <p id="totalListed"><?= $total_listed ?></p>
                            <h3>Total Items Listed for Sale</h3>
                        </div>
                        <div class="stat-card">
                            <p id="totalListed"><?= $total_listed ?></p>
                            <h3>Total Items Purchased</h3>
                        </div>
                            
                    </div>
                    <div class="stats-container" style="margin-top: 15px;">
                        <div class="stat-card">
                            <h3>Recent Activity</h3>
                            <div class="activity-list-container">
                                <?php if (empty($activities)): ?>
                                    <p>No recent activity</p>
                                <?php else: ?>
                                    <ul class="activity-list">
                                        <?php foreach ($activities as $activity): ?>
                                            <li>
                                                <span class="activity-type <?= $activity['type'] ?>">
                                                    <?php if ($activity['type'] == 'listed'): ?>
                                                        <i class="fas fa-tag"></i> Listed:
                                                    <?php elseif ($activity['type'] == 'purchased'): ?>
                                                        <i class="fas fa-shopping-cart"></i> Purchased:
                                                    <?php endif; ?>
                                                </span>
                                                <span class="product-name"><?= htmlspecialchars($activity['name']) ?></span>
                                                <span class="activity-date">(<?= date("M d, Y h:i A", strtotime($activity['activity_date'])) ?>)</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Listings Management -->
                <section class="card">
                    <h2 class="card-header">Listings Management</h2>
                    <div class="listings-actions">
                        <a href="#" class="listings-action" id="active-tab">Pending Listings</a>
                        <a href="sell.php" class="listings-action">Add New Listing</a>
                        <a href="#" class="listings-action" id="sold-tab">Approved Listings</a>
                            <a href="#" class="listings-action" id="rejected-tab">Rejected Listings</a>
                    </div>

                    <!-- Pending Listings Table -->
                    <div id="active-listings" class="listings-container">
                        <?php if (empty($active_listings)): ?>
                            <p>You have no pending listings awaiting approval</p>
                        <?php else: ?>
                            <table class="listings-table">
                                <thead>
                                    <tr>
                                        <th>Product Name</th>
                                        <th>Price</th>
                                        <th>Date Listed</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($active_listings as $listing): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($listing['product_name']) ?></td>
                                            <td>₱<?= number_format($listing['product_price'], 2) ?></td>
                                            <td><?= date("M d, Y", strtotime($listing['created_at'])) ?></td>
                                            <td>
                                                <a href="sell.php?action=edit&id=<?= $listing['listing_id'] ?>" class="action-btn">Edit</a>
                                                <a href="#" class="action-btn delete-btn"
                                                    data-id="<?= $listing['listing_id'] ?>"
                                                    data-name="<?= htmlspecialchars($listing['product_name']) ?>"
                                                    onclick="showDeleteModal(this)">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <!-- Approved Listings Table -->
                    <div id="sold-listings" class="listings-container">
                        <?php if (empty($sold_listings)): ?>
                            <p>You have no approved listings</p>
                        <?php else: ?>
                            <table class="listings-table">
                                <thead>
                                    <tr>
                                        <th>Product Name</th>
                                        <th>Price</th>
                                        <th>Date Listed</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sold_listings as $listing): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($listing['product_name']) ?></td>
                                            <td>₱<?= number_format($listing['product_price'], 2) ?></td>
                                            <td><?= date("M d, Y", strtotime($listing['created_at'])) ?></td>
                                            <td><span class="status-badge approved">Approved</span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                        <!-- Rejected Listings Table -->
<div id="rejected-listings" class="listings-container">
    <?php if (empty($rejected_listings)): ?>
        <p>You have no rejected listings</p>
    <?php else: ?>
        <?php if ($show_rejection_warning): ?>
            <div class="notification-message warning-message">
                <i class=""></i> Warning: You have multiple rejected listings. Please review our guidelines carefully before submitting new items.
            </div>
        <?php endif; ?>
        <table class="listings-table">
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Price</th>
                    <th>Date Listed</th>
                    <th>Rejection Reason</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rejected_listings as $listing): ?>
                    <tr>
                        <td><?= htmlspecialchars($listing['product_name']) ?></td>
                        <td>₱<?= number_format($listing['product_price'], 2) ?></td>
                        <td><?= date("M d, Y", strtotime($listing['created_at'])) ?></td>
                        <td><?= htmlspecialchars($listing['rejection_reason'] ?? 'No reason provided') ?></td>
                        <td>
                        
                            <a href="#" class="action-btn delete-btn"
                               data-id="<?= $listing['listing_id'] ?>"
                               data-name="<?= htmlspecialchars($listing['product_name']) ?>"
                               onclick="showDeleteModal(this)">Delete</a>
                        </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Orders & Transactions -->
                <section class="card">
                    <h2 class="card-header">Orders & Transactions</h2>
                    <a href="purchase_history.php" class="listings-action">Purchase History</a>
                    <a href="sales_history.php" class="listings-action">Sales History</a>
                </section>

                <!-- Admin Announcements -->
                <section class="card">
                    <h2 class="card-header">Admin Announcements & Alerts</h2>
                     <div class="announcements-container">
    <div class="announcements-list">
        <?php if ($announcements_result && $announcements_result->num_rows > 0): ?>
            <?php 
            $current_time = new DateTime();
            $announcements_array = [];
            while ($announcement = $announcements_result->fetch_assoc()) {
                $announcements_array[] = $announcement;
            }
            
            foreach ($announcements_array as $index => $announcement): 
                $created_time = new DateTime($announcement['created_at']);
                $updated_time = $announcement['updated_at'] ? new DateTime($announcement['updated_at']) : null;
                $time_diff = $current_time->diff($created_time);
                $is_new = $time_diff->days < 7; // Mark as new if less than 7 days old
                
                // Determine which date to show and label
                $display_time = $created_time;
                $time_label = "Posted";
                
                if ($updated_time && $updated_time > $created_time) {
                    $display_time = $updated_time;
                    $time_label = "Updated";
                    $time_diff = $current_time->diff($updated_time);
                    $is_new = $time_diff->days < 7; // Update new status based on update time
                }
                
                // Format date for display (include full date)
                $full_date = $display_time->format('M j, Y \a\t g:i A');
                if ($time_diff->days == 0) {
                    $relative_date = "Today";
                } elseif ($time_diff->days == 1) {
                    $relative_date = "Yesterday";
                } elseif ($time_diff->days < 7) {
                    $relative_date = $time_diff->days . " days ago";
                } else {
                    $relative_date = $display_time->format('M j, Y');
                }
                
                // Determine announcement label
                $announcement_label = ($index === 0) ? "Latest Announcement" : "Last Announcement";
            ?>
                <div class="announcement-item">
                    <div class="announcement-label">
                        <?php echo $announcement_label; ?>
                        <?php if ($is_new): ?>
                            <span class="announcement-badge new">New</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="announcement-title">
                        <?php echo htmlspecialchars($announcement['title']); ?>
                    </div>
                    
                    <div class="announcement-body">
                        <?php echo nl2br(htmlspecialchars($announcement['body'])); ?>
                    </div>
                    
                    <div class="announcement-meta">
                        <div class="announcement-date">
                            <strong><?php echo $time_label . ' ' . $relative_date; ?></strong><br>
                            <small><?php echo $full_date; ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-announcements">
                <i class="fas fa-bullhorn"></i>
                <h4>No Announcements</h4>
                <p>There are no announcements at this time.</p>
            </div>
        <?php endif; ?>
    </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete "<span id="deleteItemName"></span>"?</p>
                <p>This action cannot be undone.</p>
            </div>
            <div class="modal-buttons">
                <button class="modal-btn modal-cancel" onclick="closeModal()">Cancel</button>
                <a href="#" id="confirmDelete" class="modal-btn modal-delete">Delete</a>
            </div>
        </div>
    </div>
    <?php if ($show_profile_popup): ?>
        <div class='registration-success-container' id='profileSuccessPopup'>
            <div class='registration-success-container1'>
                <h2 class='success-message'><?php echo $profile_message; ?></h2>
                <button class='close-popup' onclick="document.getElementById('profileSuccessPopup').style.display='none';">Close</button>
            </div>
        </div>
    <?php endif; ?>












    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function showActiveListings() {
                document.getElementById('active-listings').classList.add('active');
                document.getElementById('sold-listings').classList.remove('active');
        document.getElementById('rejected-listings').classList.remove('active');
                document.getElementById('active-tab').classList.add('active');
                document.getElementById('sold-tab').classList.remove('active');
        document.getElementById('rejected-tab').classList.remove('active');
            }

            function showSoldListings() {
                document.getElementById('active-listings').classList.remove('active');
                document.getElementById('sold-listings').classList.add('active');
        document.getElementById('rejected-listings').classList.remove('active');
                document.getElementById('active-tab').classList.remove('active');
                document.getElementById('sold-tab').classList.add('active');
        document.getElementById('rejected-tab').classList.remove('active');
    }
    
    function showRejectedListings() {
        document.getElementById('active-listings').classList.remove('active');
        document.getElementById('sold-listings').classList.remove('active');
        document.getElementById('rejected-listings').classList.add('active');
        document.getElementById('active-tab').classList.remove('active');
        document.getElementById('sold-tab').classList.remove('active');
        document.getElementById('rejected-tab').classList.add('active');
            }

            document.getElementById('active-tab').addEventListener('click', function(e) {
                e.preventDefault();
                showActiveListings();
            });

            document.getElementById('sold-tab').addEventListener('click', function(e) {
                e.preventDefault();
                showSoldListings();
            });
    
    document.getElementById('rejected-tab').addEventListener('click', function(e) {
        e.preventDefault();
        showRejectedListings();
    });

            showActiveListings();

            const notification = document.querySelector('.notification-message');
            if (notification) {
                setTimeout(function() {
                    notification.style.opacity = '0';
                    notification.style.transition = 'opacity 0.5s ease';
                    setTimeout(function() {
                        notification.style.display = 'none';
                    }, 500);
                }, 5000);
            }
        });


        function showDeleteModal(element) {
            const modal = document.getElementById('deleteModal');
            const productId = element.getAttribute('data-id');
            const productName = element.getAttribute('data-name');

            document.getElementById('deleteItemName').textContent = productName;
            document.getElementById('confirmDelete').href = `?action=delete&id=${productId}&confirm=yes`;

            modal.style.display = 'block';
        }

        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                closeModal();
            }
        }












            
    </script>

</body>

</html>