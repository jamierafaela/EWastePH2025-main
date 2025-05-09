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
$list_query = $conn->prepare("SELECT COUNT(*) FROM listings WHERE seller_id = ?");
if ($list_query) {
    $list_query->bind_param("i", $user_id);
    $list_query->execute();
    $list_query->bind_result($total_listed);
    $list_query->fetch();
    $list_query->close();
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

//recent acts 
$activities = array();

// get recent listings
$list_activity = $conn->prepare("
    SELECT 'listed' AS type, product_name AS name, created_at AS activity_date 
    FROM listings 
    WHERE seller_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");

if ($list_activity) {
    $list_activity->bind_param("i", $user_id);
    $list_activity->execute();
    $result = $list_activity->get_result();
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    $list_activity->close();
}

// delete message
$delete_message = '';

// delete action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    $product_id = $_GET['id'];

    $check_query = $conn->prepare("SELECT seller_id FROM listings WHERE listing_id = ?");
    $check_query->bind_param("i", $product_id);
    $check_query->execute();
    $check_query->store_result();

    if ($check_query->num_rows > 0) {
        $check_query->bind_result($seller_id);
        $check_query->fetch();

        if ($seller_id == $user_id) {
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
$active_query = $conn->prepare("
    SELECT listing_id, product_name, product_price, created_at 
    FROM listings 
    WHERE seller_id = ? AND product_status = 'Pending Review'
    ORDER BY created_at DESC
");

if ($active_query) {
    $active_query->bind_param("i", $user_id);
    $active_query->execute();
    $result = $active_query->get_result();
    while ($row = $result->fetch_assoc()) {
        $active_listings[] = $row;
    }
    $active_query->close();
}

// approved listing
$sold_listings = array();
$sold_query = $conn->prepare("
    SELECT listing_id, product_name, product_price, created_at
    FROM listings 
    WHERE seller_id = ? AND product_status = 'Approved'
    ORDER BY created_at DESC
");

if ($sold_query) {
    $sold_query->bind_param("i", $user_id);
    $sold_query->execute();
    $result = $sold_query->get_result();
    while ($row = $result->fetch_assoc()) {
        $sold_listings[] = $row;
    }
    $sold_query->close();
}

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

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-WastePH User Dashboard</title>
    <link rel="stylesheet" href="../styles/userDash.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
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
                        <button id="logoutBtn" class="logout-btn" onclick="window.location.href='logout.php'">Log out</button>
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
                        <a href="editpass.php   " class="sidebar-link">Privacy Settings<i class="fas fa-shield-alt"></i></a>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="main-content">
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
                            <h3>Total Items Listed for Sale</h3>
                            <p id="totalListed"><?= $total_listed ?></p>
                        </div>
                        <div class="stat-card">
                            <h3>Total Items Purchased</h3>
                            <p id="totalPurchased"><?= $total_purchased ?></p>
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
                                                <a href="sell.php?id=<?= $listing['listing_id'] ?>" class="action-btn">Edit</a>
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
                    <div id="announcements">
                        <p>No new announcements</p>
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
                document.getElementById('active-tab').classList.add('active');
                document.getElementById('sold-tab').classList.remove('active');
            }

            function showSoldListings() {
                document.getElementById('active-listings').classList.remove('active');
                document.getElementById('sold-listings').classList.add('active');
                document.getElementById('active-tab').classList.remove('active');
                document.getElementById('sold-tab').classList.add('active');
            }

            document.getElementById('active-tab').addEventListener('click', function(e) {
                e.preventDefault();
                showActiveListings();
            });

            document.getElementById('sold-tab').addEventListener('click', function(e) {
                e.preventDefault();
                showSoldListings();
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