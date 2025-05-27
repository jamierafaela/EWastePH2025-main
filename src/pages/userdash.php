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
          /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content-wrapper {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            transform: scale(0.8);
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .modal-overlay.active .modal-content-wrapper {
            transform: scale(1);
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
        }

        .close-btn:hover {
            background-color: rgba(255,255,255,0.2);
        }

        .modal-body-content {
            overflow-y: auto;
            padding: 0;
            flex-grow: 1;
        }

        .actual-modal-content {
            padding: 20px;
            line-height: 1.6;
            color: #333;
        }

        .actual-modal-content h1 {
            color: #667eea;
            font-size: 1.5rem;
            margin-bottom: 10px;
            text-align: center;
        }

        .actual-modal-content h2 {
            color: #667eea;
            font-size: 1.2rem;
            margin-top: 20px;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e9ecef;
        }

        .actual-modal-content h3 {
            color: #495057;
            font-size: 1.1rem;
            margin-top: 15px;
            margin-bottom: 10px;
        }

        .actual-modal-content p {
            margin-bottom: 10px;
            text-align: justify;
        }

        .actual-modal-content ul {
            margin: 10px 0;
            padding-left: 20px;
        }

        .actual-modal-content li {
            margin-bottom: 8px;
        }

        .actual-modal-content strong {
            color: #495057;
        }

        .effective-date {
            text-align: center;
            font-style: italic;
            color: #6c757d;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        .contact-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .final-acknowledgment {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
            font-weight: 500;
        }

        .terms-link {
            color: #667eea;
            text-decoration: underline;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .terms-link:hover {
            color: #5a6fd8;
        }

        /* Scrollbar Styling */
        .modal-body-content::-webkit-scrollbar {
            width: 8px;
        }

        .modal-body-content::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .modal-body-content::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 4px;
        }

        .modal-body-content::-webkit-scrollbar-thumb:hover {
            background: #5a6fd8;
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

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            border-radius: 5px;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
            animation: modalAppear 0.3s ease-out;
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
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }

            100% {
                opacity: 1;
            }
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
                        <a href="privacy_settings.php" class="sidebar-link">Privacy Settings<i class="fas fa-shield-alt"></i></a>
                        <a href="#"  class="sidebar-link" onclick="openTermsModal(event)">Terms and Conditions</a> 
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

    <!-- Terms and Conditions Modal -->
    <div class="modal-overlay" id="termsModalOverlay" onclick="closeTermsModal(event)">
        <div class="modal-content-wrapper" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div class="modal-title">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14,2 14,8 20,8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10,9 9,9 8,9"/>
                    </svg>
                    Terms and Conditions
                </div>
                <button class="close-btn" onclick="closeTermsModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body-content"> 
              <div class="actual-modal-content">
                  <h1>Terms and Conditions</h1>
                  <div class="effective-date">
                      <strong>E-Waste PH</strong><br>
                      <strong>Effective Date:</strong> <?php echo date("F j, Y"); ?><br>
                      <strong>Last Updated:</strong> <?php echo date("F j, Y"); ?>
                  </div>

                  <h2>1. Introduction and Acceptance</h2>
                  <p>Welcome to E-Waste PH ("we," "our," "us"). These Terms and Conditions ("Terms") govern your use of our website located at www.ewasteph.com and all related services provided by E-Waste PH.</p>
                  <p>By accessing or using our website and services, you agree to be bound by these Terms. If you do not agree to these Terms, please do not use our services.</p>

                  <h2>2. About Our Services</h2>
                  <p>E-Waste PH provides electronic waste collection, recycling, and disposal services in the Philippines. Our services include:</p>
                  <ul>
                      <li>Collection of electronic waste from residential and commercial locations</li>
                      <li>Proper recycling and disposal of electronic devices</li>
                      <li>Data destruction services</li>
                      <li>Environmental compliance certification</li>
                      <li>Educational resources about e-waste management</li>
                      <li>Online marketplace for buying and selling refurbished electronics</li>
                  </ul>

                  <h2>3. Definitions</h2>
                  <ul>
                      <li><strong>"E-waste"</strong> refers to discarded electronic devices and equipment</li>
                      <li><strong>"User"</strong> refers to any person or entity using our website or services</li>
                      <li><strong>"Services"</strong> refers to all services provided by E-Waste PH</li>
                      <li><strong>"Content"</strong> refers to all information, text, images, and materials on our website</li>
                      <li><strong>"Listing"</strong> refers to any item posted for sale or exchange on our platform</li>
                      <li><strong>"Seller"</strong> refers to users who list items for sale on our platform</li>
                      <li><strong>"Buyer"</strong> refers to users who purchase items through our platform</li>
                  </ul>

                  <h2>4. User Eligibility and Account Registration</h2>
                  <h3>4.1 Eligibility</h3>
                  <ul>
                      <li>Users must be at least 18 years old or have parental/guardian consent</li>
                      <li>Users must provide accurate and complete information</li>
                      <li>Users must comply with all applicable Philippine laws and regulations</li>
                  </ul>
                  <h3>4.2 Account Registration</h3>
                  <ul>
                      <li>Some services may require account creation</li>
                      <li>You are responsible for maintaining account security</li>
                      <li>You must notify us immediately of any unauthorized access</li>
                      <li>One person or entity per account unless otherwise authorized</li>
                  </ul>
                  <h3>4.3 Identity Verification and Truthful Information</h3>
                  <ul>
                      <li>Users must provide accurate, current, and truthful information</li>
                      <li>Falsification of identity, contact information, or personal details is strictly prohibited</li>
                      <li>Users must promptly update any changes to their account information</li>
                      <li>We reserve the right to verify user identity and may request additional documentation</li>
                      <li>Providing false information may result in immediate account suspension or termination</li>
                  </ul>

                  <h2>5. User Conduct and Prohibited Activities</h2>
                  <h3>5.1 Acceptable Use</h3>
                  <p>Users must conduct themselves professionally and respectfully when using our services and interacting with other users, staff, or partners.</p>
                  <h3>5.2 Prohibited Activities</h3>
                  <p>Users are strictly prohibited from:</p>
                  <ul>
                      <li>Providing false, misleading, or inaccurate information</li>
                      <li>Impersonating another person or entity</li>
                      <li>Creating multiple accounts to circumvent restrictions</li>
                      <li>Posting inappropriate, offensive, or harmful content</li>
                      <li>Using profanity, hate speech, or discriminatory language</li>
                      <li>Sharing content that is defamatory, threatening, or harassing</li>
                      <li>Posting sexually explicit, violent, or illegal content</li>
                      <li>Engaging in fraudulent activities or scams</li>
                      <li>Attempting to bypass security measures</li>
                      <li>Interfering with the proper functioning of our services</li>
                  </ul>
                  <h3>5.3 Content Standards</h3>
                  <p>All user-generated content must:</p>
                  <ul>
                      <li>Be accurate and truthful</li>
                      <li>Comply with Philippine laws and regulations</li>
                      <li>Respect intellectual property rights</li>
                      <li>Be appropriate for all audiences</li>
                      <li>Not contain spam, advertisements, or promotional material (unless authorized)</li>
                  </ul>

                  <h2>6. Listing Terms and Marketplace Rules</h2>
                  <h3>6.1 Listing Requirements</h3>
                  <ul>
                      <li>All listings must accurately describe the item being sold</li>
                      <li>Photos must be clear, recent, and accurately represent the item</li>
                      <li>Pricing must be clearly stated and reasonable</li>
                      <li>Item condition must be honestly described</li>
                      <li>Only functional or repairable electronic items may be listed</li>
                  </ul>
                  <h3>6.2 Listing Restrictions</h3>
                  <p>The following items may not be listed:</p>
                  <ul>
                      <li>Stolen or illegally obtained items</li>
                      <li>Items that violate intellectual property rights</li>
                      <li>Dangerous or hazardous materials</li>
                      <li>Non-electronic items (unless specifically allowed)</li>
                      <li>Items requiring special permits or licenses</li>
                      <li>Counterfeit or replica items marketed as genuine</li>
                  </ul>
                  <h3>6.3 Listing Management</h3>
                  <ul>
                      <li>We reserve the right to remove any listing that violates these Terms</li>
                      <li>Listings may be subject to review and approval</li>
                      <li>Users are responsible for keeping their listings current and accurate</li>
                      <li>Inactive or expired listings may be automatically removed</li>
                  </ul>
                  <h3>6.4 Transaction Responsibilities</h3>
                  <ul>
                      <li>Sellers are responsible for accurate item descriptions</li>
                      <li>Buyers are responsible for inspecting items before purchase</li>
                      <li>Payment and delivery arrangements are between buyers and sellers</li>
                      <li>We may provide dispute resolution services but are not liable for transaction outcomes</li>
                  </ul>

                  <h2>7. Service Terms and Scheduling</h2>
                  <h3>7.1 Collection Services</h3>
                  <ul>
                      <li>Collection services are available within our designated service areas</li>
                      <li>Scheduling is subject to availability and confirmation</li>
                      <li>We reserve the right to refuse collection of certain items or materials</li>
                      <li>Minimum quantities may apply for collection services</li>
                  </ul>
                  <h3>7.2 Accepted Items</h3>
                  <p>We accept various electronic devices including but not limited to:</p>
                  <ul>
                      <li>Computers, laptops, and tablets</li>
                      <li>Mobile phones and accessories</li>
                      <li>Televisions and monitors</li>
                      <li>Printers and office equipment</li>
                      <li>Home appliances with electronic components</li>
                  </ul>
                  <h3>7.3 Prohibited Items</h3>
                  <p>We do not accept:</p>
                  <ul>
                      <li>Hazardous materials beyond standard e-waste</li>
                      <li>Items containing radioactive materials</li>
                      <li>Medical devices without proper authorization</li>
                      <li>Items that pose safety risks to our personnel</li>
                  </ul>

                  <h2>8. Pricing and Payment</h2>
                  <h3>8.1 Service Fees</h3>
                  <ul>
                      <li>Pricing information is available on our website or upon request</li>
                      <li>Fees may vary based on location, quantity, and type of items</li>
                      <li>Special handling fees may apply for certain items</li>
                      <li>Listing fees may apply for marketplace services</li>
                  </ul>
                  <h3>8.2 Payment Terms</h3>
                  <ul>
                      <li>Payment is due as specified in your service agreement</li>
                      <li>We accept various payment methods as indicated on our website</li>
                      <li>Late payment fees may apply for overdue accounts</li>
                      <li>All prices are in Philippine Peso (PHP) unless otherwise stated</li>
                  </ul>

                  <h2>9. Data Security and Privacy</h2>
                  <h3>9.1 Data Destruction</h3>
                  <ul>
                      <li>We provide secure data destruction services for storage devices</li>
                      <li>Data destruction certificates are available upon request</li>
                      <li>We are not responsible for data not properly backed up by users</li>
                      <li>Users should remove personal data before service when possible</li>
                  </ul>
                  <h3>9.2 Privacy</h3>
                  <ul>
                      <li>Our <span class="terms-link" onclick="openPrivacyPolicyModal()">Privacy Policy</span> governs the collection and use of personal information</li>
                      <li>We implement industry-standard security measures</li>
                      <li>Personal information is handled in compliance with Philippine data protection laws</li>
                  </ul>

                  <h2>10. Environmental Compliance</h2>
                  <h3>10.1 Regulatory Compliance</h3>
                  <ul>
                      <li>We operate in compliance with Philippine environmental laws</li>
                      <li>We maintain proper licenses and certifications</li>
                      <li>We follow Department of Environment and Natural Resources (DENR) guidelines</li>
                      <li>We adhere to international e-waste management standards</li>
                  </ul>
                  <h3>10.2 Certificates and Documentation</h3>
                  <ul>
                      <li>Certificates of proper disposal are available upon request</li>
                      <li>We maintain records of all processed materials</li>
                      <li>Compliance documentation is available for audit purposes</li>
                  </ul>

                  <h2>11. Moderation and Enforcement</h2>
                  <h3>11.1 Content Moderation</h3>
                  <ul>
                      <li>We reserve the right to monitor, review, and moderate all user content</li>
                      <li>Inappropriate content will be removed without notice</li>
                      <li>Repeated violations may result in account restrictions or termination</li>
                  </ul>
                  <h3>11.2 Enforcement Actions</h3>
                  <p>Violations of these Terms may result in:</p>
                  <ul>
                      <li>Content removal</li>
                      <li>Account warnings or restrictions</li>
                      <li>Temporary or permanent account suspension</li>
                      <li>Legal action where appropriate</li>
                      <li>Cooperation with law enforcement authorities</li>
                  </ul>

                  <h2>12. Liability and Disclaimers</h2>
                  <h3>12.1 Service Disclaimers</h3>
                  <ul>
                      <li>Services are provided "as is" without warranties</li>
                      <li>We do not guarantee specific outcomes or timelines</li>
                      <li>Weather, traffic, and other factors may affect service delivery</li>
                      <li>We reserve the right to modify or discontinue services</li>
                  </ul>
                  <h3>12.2 Limitation of Liability</h3>
                  <ul>
                      <li>Our liability is limited to the fees paid for specific services</li>
                      <li>We are not liable for indirect, consequential, or punitive damages</li>
                      <li>Total liability shall not exceed the amount paid for services</li>
                      <li>Users assume responsibility for backing up important data</li>
                  </ul>
                  <h3>12.3 Indemnification</h3>
                  <p>Users agree to indemnify E-Waste PH against claims arising from:</p>
                  <ul>
                      <li>Misrepresentation of items or materials</li>
                      <li>Violation of applicable laws or regulations</li>
                      <li>Unauthorized or improper use of our services</li>
                      <li>Breach of these Terms and Conditions</li>
                      <li>False information or identity falsification</li>
                      <li>Inappropriate content or conduct</li>
                  </ul>

                  <h2>13. Intellectual Property</h2>
                  <h3>13.1 Our Content</h3>
                  <ul>
                      <li>All website content is owned by E-Waste PH or licensed to us</li>
                      <li>Users may not reproduce, distribute, or modify our content without permission</li>
                      <li>Our trademarks and logos are protected intellectual property</li>
                  </ul>
                  <h3>13.2 User Content</h3>
                  <ul>
                      <li>Users retain ownership of content they provide to us</li>
                      <li>Users grant us license to use provided content for service delivery</li>
                      <li>Users represent that they have rights to all provided content</li>
                  </ul>

                  <h2>14. Cancellation and Refunds</h2>
                  <h3>14.1 Service Cancellation</h3>
                  <ul>
                      <li>Services may be cancelled with reasonable notice</li>
                      <li>Cancellation fees may apply depending on timing and circumstances</li>
                      <li>Scheduled collections must be cancelled at least 24 hours in advance</li>
                  </ul>
                  <h3>14.2 Refund Policy</h3>
                  <ul>
                      <li>Refunds are considered on a case-by-case basis</li>
                      <li>Service fees are generally non-refundable once services are rendered</li>
                      <li>Unused service credits may be refunded at our discretion</li>
                  </ul>

                  <h2>15. Force Majeure</h2>
                  <p>We are not liable for delays or failures due to circumstances beyond our reasonable control, including:</p>
                  <ul>
                      <li>Natural disasters, weather conditions, or acts of God</li>
                      <li>Government actions, regulations, or restrictions</li>
                      <li>Labor disputes, strikes, or transportation issues</li>
                      <li>Technical failures or infrastructure problems</li>
                  </ul>

                  <h2>16. Governing Law and Dispute Resolution</h2>
                  <h3>16.1 Governing Law</h3>
                  <p>These Terms are governed by the laws of the Republic of the Philippines.</p>
                  <h3>16.2 Dispute Resolution</h3>
                  <ul>
                      <li>Disputes should first be addressed through direct communication</li>
                      <li>Mediation may be pursued for unresolved disputes</li>
                      <li>Philippine courts have jurisdiction over legal proceedings</li>
                      <li>Users consent to venue in Quezon City for legal matters</li>
                  </ul>

                  <h2>17. Modifications and Updates</h2>
                  <h3>17.1 Terms Updates</h3>
                  <ul>
                      <li>We reserve the right to modify these Terms at any time</li>
                      <li>Material changes will be communicated via website notice or email</li>
                      <li>Continued use of services constitutes acceptance of updated Terms</li>
                      <li>Users should regularly review Terms for changes</li>
                  </ul>
                  <h3>17.2 Service Changes</h3>
                  <ul>
                      <li>We may modify, suspend, or discontinue services with notice</li>
                      <li>New features may be subject to additional terms</li>
                      <li>We strive to provide advance notice of significant changes</li>
                  </ul>

                  <h2>18. Termination</h2>
                  <h3>18.1 Termination by Users</h3>
                  <ul>
                      <li>Users may terminate their account at any time</li>
                      <li>Outstanding obligations survive termination</li>
                      <li>Data and content may be deleted upon termination</li>
                  </ul>
                  <h3>18.2 Termination by E-Waste PH</h3>
                  <p>We may terminate user accounts for:</p>
                  <ul>
                      <li>Violation of these Terms</li>
                      <li>Fraudulent or illegal activity</li>
                      <li>Non-payment of fees</li>
                      <li>Abuse of our services or personnel</li>
                      <li>Providing false information or falsifying identity</li>
                      <li>Posting inappropriate content</li>
                      <li>Repeated policy violations</li>
                  </ul>

                  <h2>19. Miscellaneous Provisions</h2>
                  <h3>19.1 Entire Agreement</h3>
                  <p>These Terms, along with our <span class="terms-link" onclick="openPrivacyPolicyModal()">Privacy Policy</span>, constitute the entire agreement between users and E-Waste PH.</p>
                  <h3>19.2 Severability</h3>
                  <p>If any provision of these Terms is found invalid, the remaining provisions shall remain in effect.</p>
                  <h3>19.3 Assignment</h3>
                  <p>Users may not assign their rights under these Terms without our written consent.</p>
                  <h3>19.4 Waiver</h3>
                  <p>Failure to enforce any provision does not constitute a waiver of our rights.</p>

                  <h2>20. Contact Information</h2>
                  <div class="contact-info">
                      <p>For questions about these Terms and Conditions, please contact us:</p>
                      <p><strong>E-Waste PH</strong><br>
                      Email: ewasteph.services@gmail.com<br>
                      Phone: +63 912 345 6789<br>
                      Address: 123 E-Waste Avenue, Quezon City, Philippines<br>
                      Website: www.ewasteph.com</p>
                  </div>

                  <div class="final-acknowledgment">
                      <strong>By using our services, you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions.</strong>
                  </div>
              </div>
          </div>
      </div>
  </div>
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
     
    //for footer modal
      function openTermsModal(event) {
            event.preventDefault(); // Prevent default link behavior
            const modalOverlay = document.getElementById('termsModalOverlay');
            if (modalOverlay) {
                modalOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeTermsModal(event) {
            const modalOverlay = document.getElementById('termsModalOverlay');
            // If the click is on the overlay itself or on a close button
            if (modalOverlay && ((event && event.target === modalOverlay) || (event && event.target.closest('.close-btn')) || !event)) {
                modalOverlay.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modalOverlay = document.getElementById('termsModalOverlay');
                if (modalOverlay && modalOverlay.classList.contains('active')) {
                    closeTermsModal();
                }
            }
        });

        // Set current date on page load
        document.addEventListener('DOMContentLoaded', function() {
            const currentDate = new Date().toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            const currentDateEl = document.getElementById('current-date');
            const updatedDateEl = document.getElementById('updated-date');
            
            if (currentDateEl) currentDateEl.textContent = currentDate;
            if (updatedDateEl) updatedDateEl.textContent = currentDate;
        });

   
        function openPrivacyPolicyModal() {
            alert('Privacy Policy modal would open here. You can implement this similarly to the Terms modal.');
        }
    </script>

</body>

</html>