<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/ewasteWeb.php#loginSection");
    exit();
}

$logged_in = true;
// Add this near the top of your PHP code, after session_start()
if (!isset($_SESSION['policy_agreed'])) {
    $_SESSION['policy_agreed'] = false;
}
// Improved database connection with error handling
$conn = new mysqli("localhost", "root", "", "ewaste_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$user_details_query = $conn->prepare("SELECT * FROM user_details WHERE user_id = ?");
$user_details_query->bind_param("i", $user_id);
$user_details_query->execute();
$user_details_result = $user_details_query->get_result();
$user_details = $user_details_result->fetch_assoc();
$has_user_details = ($user_details_result->num_rows > 0);


$conditions = array("New", "Like New", "Good", "Fair", "Poor");

// Check if GCash details already exist
$gcash_query = $conn->prepare("SELECT * FROM sellers WHERE user_id = ?");
$gcash_query->bind_param("i", $user_id);
$gcash_query->execute();
$gcash_result = $gcash_query->get_result();
$gcash_details = $gcash_result->fetch_assoc();
$has_gcash_details = ($gcash_result->num_rows > 0);
$seller_id = $has_gcash_details ? $gcash_details['seller_id'] : null;

// Handle GCash details form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_gcash'])) {
    $gcash_name = trim($_POST['gcash_name']);
    $gcash_number = trim($_POST['gcash_number']);
    $phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
    $street = isset($_POST['street']) ? trim($_POST['street']) : '';
    $city = isset($_POST['city']) ? trim($_POST['city']) : '';
    $province = isset($_POST['province']) ? trim($_POST['province']) : '';
    $zip_code = isset($_POST['zip_code']) ? trim($_POST['zip_code']) : '';

    if (empty($gcash_name) || empty($gcash_number)) {
        $error = "Both GCash name and number are required.";
    } else if (!preg_match("/^[0-9]{11}$/", $gcash_number)) {
        $error = "GCash number must be 11 digits.";
    } else {
        if ($has_gcash_details) {
            // Update existing details
            $update_gcash = $conn->prepare("UPDATE sellers SET gcash_name = ?, gcash_number = ?, updated_at = NOW() WHERE user_id = ?");
            $update_gcash->bind_param("ssi", $gcash_name, $gcash_number, $user_id);

            if ($update_gcash->execute()) {
                if ($has_user_details) {
                    $update_user_details = $conn->prepare("UPDATE user_details SET phone_number = ?, street = ?, city = ?, province = ?, zipcode = ? WHERE user_id = ?");
                    $update_user_details->bind_param("sssssi", $phone_number, $street, $city, $province, $zip_code, $user_id);
                    $update_user_details->execute();
                } else {
                    $insert_user_details = $conn->prepare("INSERT INTO user_details (user_id, phone_number, street, city, province, zipcode) VALUES (?, ?, ?, ?, ?, ?)");
                    $insert_user_details->bind_param("isssss", $user_id, $phone_number, $street, $city, $province, $zip_code);
                    $insert_user_details->execute();
                }

                $_SESSION['success_message'] = "Seller details saved successfully!";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $error = "Error updating seller details: " . $conn->error;
            }
        } else {
            // Insert new details
            $insert_gcash = $conn->prepare("INSERT INTO sellers (user_id, gcash_name, gcash_number, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $insert_gcash->bind_param("iss", $user_id, $gcash_name, $gcash_number);

            if ($insert_gcash->execute()) {
                // Insert/Update user details as well
                if ($has_user_details) {
                    $update_user_details = $conn->prepare("UPDATE user_details SET phone_number = ?, street = ?, city = ?, province = ?, zipcode = ? WHERE user_id = ?");
                    $update_user_details->bind_param("sssssi", $phone_number, $street, $city, $province, $zip_code, $user_id);
                    $update_user_details->execute();
                } else {
                    $insert_user_details = $conn->prepare("INSERT INTO user_details (user_id, phone_number, street, city, province, zipcode) VALUES (?, ?, ?, ?, ?, ?)");
                    $insert_user_details->bind_param("isssss", $user_id, $phone_number, $street, $city, $province, $zip_code);
                    $insert_user_details->execute();
                }

                $_SESSION['success_message'] = "Seller details saved successfully!";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $error = "Error saving seller details: " . $conn->error;
            }
        }
    }
}
// Add this after your existing POST handlers (around where you handle save_gcash, create, etc.)
if (isset($_POST['agree_to_policy'])) {
    $_SESSION['policy_agreed'] = true;
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}
// Check for success message from session
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get user's products - make sure we use seller_id not user_id
if ($has_gcash_details) {
    $user_products_query = $conn->prepare("SELECT * FROM listings WHERE seller_id = ? ORDER BY created_at DESC");
    $user_products_query->bind_param("i", $seller_id);
    $user_products_query->execute();
    $user_products_result = $user_products_query->get_result();
} else {
    // Create a dummy result for users who aren't sellers yet
    $user_products_result = null;

    // Or run a query that returns no rows
    $empty_query = $conn->prepare("SELECT * FROM listings WHERE 1=0");
    $empty_query->execute();
    $user_products_result = $empty_query->get_result();
}
// Handle product deletion
if (isset($_POST['delete_product']) && isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);

    // Get current image path to delete the file
    $image_query = $conn->prepare("SELECT product_image FROM listings WHERE listing_id = ? AND seller_id = ?");
    $image_query->bind_param("ii", $product_id, $seller_id);
    $image_query->execute();
    $image_result = $image_query->get_result();

    if ($image_row = $image_result->fetch_assoc()) {
        $old_image = $image_row['product_image'];
        // Delete the image file if it exists
        if (file_exists($old_image)) {
            unlink($old_image);
        }
    }

    $delete_query = $conn->prepare("DELETE FROM listings WHERE listing_id = ? AND seller_id = ?");
    $delete_query->bind_param("ii", $product_id, $seller_id);

    if ($delete_query->execute() && $delete_query->affected_rows > 0) {
        $message = "Product deleted successfully!";

        $user_products_query->execute();
        $user_products_result = $user_products_query->get_result();
    } else {
        $error = "Error deleting product: " . $conn->error;
    }
}

// Handle product editing
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_product'])) {
    $product_id = intval($_POST['product_id']);
    $product_name = trim($_POST['edit_product_name']);
    $product_description = trim($_POST['edit_product_description']);
    $product_condition = trim($_POST['edit_product_condition']);
    $product_price = floatval($_POST['edit_product_price']);

    if (empty($product_name) || $product_price <= 0 || empty($product_description) || empty($product_condition)) {
        $error = "All fields are required and price must be greater than zero.";
    } else {
        $get_product_query = $conn->prepare("SELECT product_image FROM listings WHERE listing_id = ? AND seller_id = ?");
        $get_product_query->bind_param("ii", $product_id, $seller_id);
        $get_product_query->execute();
        $product_result = $get_product_query->get_result();
        
        $current_product = null;
        $image_path = null; 

        if ($product_result && $product_result->num_rows > 0) {
            $current_product = $product_result->fetch_assoc();
            if ($current_product) {
                $image_path = $current_product['product_image'];
            } else {
                $error = "Error fetching product details after confirming existence.";
            }
        } else {
            $error = "Product not found or you do not have permission to edit it.";
        }
        
        if(empty($error) && isset($_FILES['edit_product_image']) && $_FILES['edit_product_image']['error'] === 0) {
            $upload_dir = '../uploads/listings/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES['edit_product_image']['name'], PATHINFO_EXTENSION);
            $filename = 'product_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
            $target_file = $upload_dir . $filename;

            $valid_extensions = array("jpg", "jpeg", "png", "gif");
            if (in_array(strtolower($file_extension), $valid_extensions)) {
                if (move_uploaded_file($_FILES['edit_product_image']['tmp_name'], $target_file)) {
                    // Delete old image if it exists and is different
                    if (!empty($current_product['product_image']) && file_exists($current_product['product_image']) && $current_product['product_image'] != $target_file) {
                        unlink($current_product['product_image']);
                    }
                    $image_path = $target_file;
                } else {
                    $error = "Sorry, there was an error uploading your file.";
                }
            } else {
                $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
            }
        }

        if (empty($error)) {
            $update_query = $conn->prepare("UPDATE listings SET 
                product_name = ?, 
                product_description = ?, 
                product_condition = ?, 
                product_price = ?, 
                product_image = ?,
                updated_at = NOW()
                WHERE listing_id = ? AND seller_id = ?");

            $update_query->bind_param(
                "sssdsii",
                $product_name,
                $product_description,
                $product_condition,
                $product_price,
                $image_path,
                $product_id,
                $seller_id
            );

            if ($update_query->execute()) {
                $_SESSION['success_message'] = "Product updated successfully!";
                header("Location: sell.php");
                exit();
            } else {
                $error = "Error updating product: " . $conn->error;
            }
        }
    }
}

// Handle new product creation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create'])) {
    if (!$has_gcash_details || is_null($seller_id)) {
        // This error should ideally be presented more prominently or prevent form submission via JS if $seller_id is null
        $error = "Seller details not found. Please ensure your seller information is complete.";
    } else {
        $product_name = trim($_POST['product_name']);
        $product_description = trim($_POST['product_description']);
        $product_condition = trim($_POST['product_condition']);
        $product_price = floatval($_POST['product_price']);
        
        if (empty($product_name) || $product_price <= 0 || empty($product_description) || empty($product_condition)) {
            $error = "All fields for product creation are required and price must be greater than zero.";
        } else {
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
                $upload_dir = '../uploads/listings/'; 
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
                $filename = 'product_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                $target_file = $upload_dir . $filename;
                
                $valid_extensions = array("jpg", "jpeg", "png", "gif");
                if (in_array($file_extension, $valid_extensions)) {
                    if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                        $image_path = $target_file;

                        $insert_query = $conn->prepare("INSERT INTO listings 
                            (product_name, product_description, product_condition, product_price, 
                            product_image, seller_id, created_at, updated_at) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
                        
                        $insert_query->bind_param("sssdsi", 
                            $product_name, 
                            $product_description, 
                            $product_condition,
                            $product_price,
                            $image_path,
                            $seller_id // Use the $seller_id from the sellers table
                        );
                        
                        if ($insert_query->execute()) {
                            $_SESSION['success_message'] = "Product listed successfully!";
                            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=manage-products");
                            exit();
                        } else {
                            $error = "Error creating listing: " . $conn->error;
                        }
                    } else {
                        $error = "Sorry, there was an error uploading your file.";
                    }
                } else {
                    $error = "Only JPG, JPEG, PNG & GIF files are allowed for product image.";
                }
            } else {
                $error = "Product image is required for creating a new listing.";
            }
        }
    }
}

$conn->close();

//edit redirect
$edit_redirect = false;
$edit_data = null;

if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $edit_redirect = true;
    $edit_product_id = intval($_GET['id']);
  
    $conn = new mysqli("localhost", "root", "", "ewaste_db");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Fetch the seller_id for the current user_id to correctly query listings
    $current_user_id_for_edit = $_SESSION['user_id'];
    $seller_id_for_edit_query = $conn->prepare("SELECT seller_id FROM sellers WHERE user_id = ?");
    $seller_id_for_edit_query->bind_param("i", $current_user_id_for_edit);
    $seller_id_for_edit_query->execute();
    $seller_id_result = $seller_id_for_edit_query->get_result();
    $fetched_seller_id_for_edit = null;
    if ($seller_id_result->num_rows > 0) {
        $fetched_seller_id_for_edit = $seller_id_result->fetch_assoc()['seller_id'];
    }
    $seller_id_for_edit_query->close();

    if ($fetched_seller_id_for_edit) {
        $edit_product_query = $conn->prepare("SELECT * FROM listings WHERE listing_id = ? AND seller_id = ?");
        $edit_product_query->bind_param("ii", $edit_product_id, $fetched_seller_id_for_edit);
        $edit_product_query->execute();
        $edit_result = $edit_product_query->get_result();
        
        if ($edit_result->num_rows > 0) {
            $edit_data = $edit_result->fetch_assoc();
        }
        $edit_product_query->close();
    } else {
        // Handle case where user is not a seller or seller_id couldn't be fetched
        // $edit_data will remain null, and the edit modal might not show or show an error
        // You could set an error message here if needed.
        error_log("Edit Product Redirect: Could not find seller_id for user_id: " . $current_user_id_for_edit);
    }
    
    $conn->close();
}
?>


<script>
// Set PHP variables as JavaScript variables
window.hasGcashDetails = <?= $has_gcash_details ? 'true' : 'false' ?>;
window.policyAgreed = <?= isset($_SESSION['policy_agreed']) && $_SESSION['policy_agreed'] ? 'true' : 'false' ?>;
</script>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - E-WastePH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../../src/styles/sell.css">

    <style>
        /* Additional styles for the centered modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow: auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            position: relative;
            background-color: #fff;
            margin: auto;
            padding: 25px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            animation: modalFadeIn 0.3s;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Styles for multi-step form */
        .form-step {
            display: none;
        }

        .form-step.active {
            display: block;
        }

        .steps-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .step-dot {
            height: 12px;
            width: 12px;
            margin: 0 5px;
            border-radius: 50%;
            background-color: #ddd;
            display: inline-block;
        }

        .step-dot.active {
            background-color: #4CAF50;
        }

        .form-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .form-navigation button {
            min-width: 80px;
        }

        /* Custom Popup Styles - REMOVED AS PER NEW APPROACH */
    </style>
  
</head>

<body>
    <div class="page-header">
        <div class="header-container">
            <h1 class="page-title"><i class="fas fa-boxes"></i> Sell Products </h1>
        </div>
    </div>

    <div class="container">
        <!-- Button Navigation -->
        <div class="button-nav">
            <a href="userdash.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            <button class="btn btn-gcash" onclick="showGcashModal()">
                <i class="fas fa-mobile-alt"></i> Seller Details
            </button>
            <button class="btn btn-info" onclick="showPolicyModal()">
                <i class="fas fa-info-circle"></i> Listing Policy
            </button>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)):
            // This block handles PHP-driven success messages on page load
        ?>
            <div class="alert alert-success" id="phpSuccessMessage">
                <?= $success_message ?>
            </div>
            <script>
            </script>
        <?php endif; ?>

        <?php if (!empty($error)):
        // This block handles PHP-driven error messages on page load
        ?>
            <div class="alert alert-danger">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="tabs-container">
            <ul class="tabs-nav">
                <li class="tab-item active" data-tab="manage-products" onclick="switchTab('manage-products')">
                    <i class="fas fa-list">Manage Products</i>
                </li>
                <li class="tab-item" data-tab="add-product" onclick="switchTab('add-product')">
                    <i class="fas fa-plus-circle"> Add New Product</i>
                </li>
            </ul>
        </div>

        <!-- Manage Products Tab -->
        <div id="manage-products" class="tab-content">
            <?php if ($has_gcash_details && $user_products_result->num_rows > 0): ?>
                <!-- Bulk Actions Bar -->
                <div class="bulk-actions-bar" id="bulkActionsBar" style="display: none;">
                    <div class="bulk-actions-left">
                        <span id="selectedCount">0</span> items selected
                    </div>
                    <div class="bulk-actions-right">
                        <button class="btn btn-danger btn-sm" onclick="showBulkDeleteModal()">
                            <i class="fas fa-trash-alt"></i> Delete Selected
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="clearSelection()">
                            <i class="fas fa-times"></i> Clear Selection
                        </button>
                    </div>
                </div>

                <table class="product-table">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                            </th>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Product Name</th>
                            <th>Price</th>
                            <th>Condition</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($product = $user_products_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="product-checkbox" value="<?= $product['listing_id'] ?>" onchange="updateBulkActions()">
                                </td>
                                <td><?= $product['listing_id'] ?></td>
                                <td>
                                    <img src="<?= htmlspecialchars($product['product_image']) ?>" alt="Product Image" class="product-image">
                                </td>
                                <td><?= htmlspecialchars($product['product_name']) ?></td>
                                <td>₱<?= number_format($product['product_price'], 2) ?></td>
                                <td><?= htmlspecialchars($product['product_condition']) ?></td>
                                <td>
                                    <span class="product-status <?= $product['product_status'] == 'Sold' ? 'status-sold' : 'status-available' ?>">
                                        <?= htmlspecialchars($product['product_status'] ?? 'Available') ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <button class="btn btn-edit" onclick="showEditModal(
                                        '<?= $product['listing_id'] ?>', 
                                        '<?= htmlspecialchars(addslashes($product['product_name'])) ?>', 
                                        '<?= $product['product_price'] ?>', 
                                        '<?= htmlspecialchars(addslashes($product['product_description'])) ?>', 
                                        '<?= htmlspecialchars($product['product_condition']) ?>', 
                                        '<?= htmlspecialchars($product['product_image']) ?>'
                                    )">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-delete" onclick="showDeleteConfirmation(<?= $product['listing_id'] ?>, '<?= htmlspecialchars(addslashes($product['product_name'])) ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No Products Found</h3>
                    <p>You haven't listed any products yet. Click the button below to add your first product.</p>
                    <?php if ($has_gcash_details): ?>
                        <button class="btn btn-add" onclick="switchTab('add-product')">
                            <i class="fas fa-plus-circle"></i> Add Your First Product
                        </button>
                    <?php else: ?>
                        <button class="btn btn-gcash" onclick="showGcashModal()">
                            <i class="fas fa-mobile-alt"></i> Add Seller Details First
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Add Product Tab -->
        <div id="add-product" class="tab-content">
            <?php if ($has_gcash_details): ?>
                <!-- Listing Policy Notice -->
                <div class="policy-notice">
                    <div class="policy-content">
                        <i class="fas fa-info-circle"></i>
                        <span>By creating a listing, you agree to our <a href="#" onclick="showPolicyModal(); return false;">Listing Policy</a>. Violations may result in account termination.</span>
                    </div>
                </div>

                <div class="product-form">
                    <h2 class="form-header">Create New Product Listing</h2>
                    <div class="addProductForm">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="form-row1">
                            <label for="product_name">Product Name</label>
                            <input type="text" id="product_name" name="product_name" class="form-control"
                                value="<?= isset($_POST['product_name']) ? htmlspecialchars($_POST['product_name']) : '' ?>"
                                placeholder="Enter product name" required>
                       

                            <label for="product_price">Price (₱)</label>
                            <input type="number" id="product_price" name="product_price" class="form-control"
                                value="<?= isset($_POST['product_price']) ? $_POST['product_price'] : '' ?>"
                                step="0.01" min="0.01" placeholder="Enter product price" required>
                        </div>

                        <div class="form-row1">
                            <label for="product_description">Description</label>
                            <textarea id="product_description" name="product_description" class="form-control"
                                placeholder="Describe your product in detail...include the quantity" required><?= isset($_POST['product_description']) ? htmlspecialchars($_POST['product_description']) : '' ?></textarea>
                        
                            <label for="product_condition">Condition</label>
                            <select id="product_condition" name="product_condition" class="form-control" required>
                                <option value="">Select Condition</option>
                                <?php foreach ($conditions as $condition): ?>
                                    <option value="<?= $condition ?>" <?= (isset($_POST['product_condition']) && $_POST['product_condition'] == $condition) ? 'selected' : '' ?>>
                                        <?= $condition ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="product_image">Product Image</label>
                            <div class="image-preview">
                                <img id="imagePreview" src="" style="display: none; max-height: 250px;">
                                <p id="previewText">No image selected</p>
                                <div class="file-input-wrapper">
                                    <button class="file-input-button" type="button">Choose Image</button>
                                    <input type="file" id="product_image" name="product_image" accept="image/*" onchange="previewImage(this, 'imagePreview', 'previewText')" required>
                                </div>
                            </div>
                        </div>

                        <div class="button-group">
                            <button type="submit" name="create" class="btn btn-primary">Create Listing</button>
                            <button type="button" onclick="switchTab('manage-products')" class="btn btn-secondary">Cancel</button>
                        </div>
                    </form>
                    
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-exclamation-circle"></i>
                    <h3>Seller Details Required</h3>
                    <p>You need to add your seller details before you can list products for sale.</p>
                    <button class="btn btn-gcash" onclick="showGcashModal()">
                        <i class="fas fa-mobile-alt"></i> Add Seller Details Now
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="modal" style="display: none;">
            <div class="modal-content">
                <h2>Confirm Deletion</h2>
                <p>Are you sure you want to delete the product "<span id="deleteProductName"></span>"?</p>
                <p>This action cannot be undone.</p>

                <form method="POST" action="">
                    <input type="hidden" id="deleteProductId" name="product_id">
                    <div class="button-group">
                        <button type="submit" name="delete_product" class="btn btn-danger">Delete</button>
                        <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bulk Delete Confirmation Modal -->
        <div id="bulkDeleteModal" class="modal" style="display: none;">
            <div class="modal-content">
                <h2>Confirm Bulk Deletion</h2>
                <p>Are you sure you want to delete <span id="bulkDeleteCount"></span> selected products?</p>
                <p><strong>This action cannot be undone.</strong></p>

                <form method="POST" action="">
                    <input type="hidden" id="bulkDeleteIds" name="bulk_delete_ids">
                    <div class="button-group">
                        <button type="submit" name="bulk_delete_products" class="btn btn-danger">Delete All Selected</button>
                        <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Product Modal -->
        <div id="editModal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="modal-close" onclick="closeModal()">&times;</span>
                <h2>Edit Product</h2>

                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" id="edit_product_id" name="product_id">

                    <div class="form-group">
                        <label for="edit_product_name">Product Name</label>
                        <input type="text" id="edit_product_name" name="edit_product_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_product_price">Price (₱)</label>
                        <input type="number" id="edit_product_price" name="edit_product_price" class="form-control" step="0.01" min="0.01" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_product_description">Description</label>
                        <textarea id="edit_product_description" name="edit_product_description" class="form-control" required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="edit_product_condition">Condition</label>
                        <select id="edit_product_condition" name="edit_product_condition" class="form-control" required>
                            <?php foreach ($conditions as $condition): ?>
                                <option value="<?= $condition ?>"><?= $condition ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_product_image">Product Image</label>
                        <div class="image-preview">
                            <img id="editImagePreview" src="" style="display: none; max-height: 250px;">
                            <p id="editPreviewText">Current image:</p>
                            <div class="file-input-wrapper">
                                <button class="file-input-button" type="button">Choose New Image</button>
                                <input type="file" id="edit_product_image" name="edit_product_image" accept="image/*" onchange="previewImage(this, 'editImagePreview', 'editPreviewText')">
                            </div>
                        </div>
                    </div>

                    <div class="button-group">
                        <button type="submit" name="edit_product" class="btn btn-primary">Save Changes</button>
                        <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Listing Policy Modal -->
        <div id="policyModal" class="modal">
            <div class="modal-content policy-modal">
                <h2><i class="fas fa-recycle"></i> EwastePH Listing Policy & Terms <span class="modal-close" onclick="closeModal()">&times;</span>
                </h2>
                <div class="policy-sections">
                    <div class="policy-section">
                        <h3><i class="fas fa-check-circle"></i> Accepted E-Waste Items</h3>
                        <ul>
                            <li>Computers, laptops, and tablets</li>
                            <li>Mobile phones and smartphones</li>
                            <li>Televisions, monitors, and displays</li>
                            <li>Audio equipment and speakers</li>
                            <li>Gaming consoles and accessories</li>
                            <li>Printers, scanners, and office electronics</li>
                            <li>Electronic components and circuit boards</li>
                            <li>Cables, chargers, and electronic accessories</li>
                            <li>Small household electronic appliances</li>
                        </ul>
                    </div>

                    <div class="policy-section">
                        <h3><i class="fas fa-ban"></i> Prohibited Items</h3>
                        <ul>
                            <li><strong>Non-electronic items</strong> (furniture, clothing, books, etc.)</li>
                            <li>Stolen or illegally obtained electronics</li>
                            <li>Items containing hazardous materials without proper disclosure</li>
                            <li>Counterfeit or pirated electronic products</li>
                            <li>Items you don't legally own or have right to sell</li>
                            <li>Batteries without proper safety information</li>
                            <li>Medical electronic equipment</li>
                        </ul>
                    </div>

                    <div class="policy-section">
                        <h3><i class="fas fa-exclamation-triangle"></i> Listing Requirements</h3>
                        <ul>
                            <li>Provide accurate and honest descriptions of electronic condition</li>
                            <li>Use genuine photos of the actual e-waste items</li>
                            <li>Clearly state working/non-working status</li>
                            <li>Include model numbers, brands, and specifications when possible</li>
                            <li>Disclose any missing parts or damage</li>
                            <li>Set fair prices based on current e-waste market values</li>
                            <li>Respond to buyer inquiries promptly</li>
                            <li>Update listing status when items are sold</li>
                        </ul>
                    </div>

                    <div class="policy-section warning">
                        <h3><i class="fas fa-gavel"></i> Policy Violations & Consequences</h3>
                        <div class="violation-levels">
                            <div class="violation-level">
                                <h4>Falsifying Information:</h4>
                                <p>Providing false descriptions, fake photos, or misrepresenting item condition is strictly prohibited and will result in immediate listing removal and account warning.</p>
                            </div>
                            <div class="violation-level">
                                <h4>First Offense:</h4>
                                <p>Official warning notice and listing removal</p>
                            </div>
                            <div class="violation-level">
                                <h4>Repeated Violations:</h4>
                                <p>Additional warnings with temporary listing restrictions</p>
                            </div>
                            <div class="violation-level severe">
                                <h4>Persistent Violations:</h4>
                                <p>Permanent account termination after multiple warnings</p>
                            </div>
                        </div>
                    </div>

                    <div class="policy-section">
                        <h3><i class="fas fa-handshake"></i> Best Practices for E-Waste Sellers</h3>
                        <ul>
                            <li>Be completely honest about electronic item condition and functionality</li>
                            <li>Include clear photos showing any damage or wear</li>
                            <li>Test items when possible and report results accurately</li>
                            <li>Package electronics securely to prevent damage during transport</li>
                            <li>Remove all personal data from devices before selling</li>
                            <li>Keep records of transactions for your protection</li>
                            <li>Report any suspicious activities or fraudulent listings</li>
                            <li>Maintain a positive reputation through honest dealings</li>
                        </ul>
                    </div>

                    <div class="policy-footer">
                        <p class="footers">EwasteHub is dedicated to responsible electronic waste recycling and reuse. 
                            By using our platform, you agree to these terms and conditions. We reserve the right to modify this policy at any time. Continued use of the platform constitutes acceptance of any changes.</p>
                        <p><em>Last updated: <?= date('F Y') ?></em></p>
                    </div>
                </div>

                <div class="button-group">
    <button type="button" onclick="closeModal()" class="btn btn-secondary">Close</button>
    <button type="button" onclick="agreeToPolicyAndProceed()" class="btn btn-primary" id="agreeButton" style="display: none;">I Understand & Agree</button>
</div>
            </div>
        </div>

        <!-- GCash Details Modal with Multi-step Form -->
        <div id="gcashModal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="modal-close" onclick="closeModal()">&times;</span>
                <h2><i class="fas fa-user-circle"></i> Seller Details</h2>

                <form method="POST" action="">
                        <div class="form-group">
                            <div class="form-row">
                                    <div>
                                        <label for="gcash_name">GCash Name</label>
                                        <input type="text" id="gcash_name" name="gcash_name" class="form-control"
                                            value="<?= $has_gcash_details ? htmlspecialchars($gcash_details['gcash_name']) : '' ?>"
                                            placeholder="Enter your GCash registered name" required>
                                    </div>
                                    
                                    <div>
                                        <label for="gcash_number">GCash Number</label>
                                        <input type="tel" id="gcash_number" name="gcash_number" class="form-control"
                                            value="<?= $has_gcash_details ? htmlspecialchars($gcash_details['gcash_number']) : '' ?>"
                                            placeholder="09XXXXXXXXX" maxlength="11"
                                            oninput="validateGCashNumber(this)"
                                            required>
                                        <small id="gcash-error-msg">Number Format: 09XXXXXXXXX</small>
                                    </div>
                                </div>
                            </div>
                                
                                <h3>Additional Information</h3>
                                <p class="form-info">Add contact and pickup details for buyers</p>

                                <div class="form-group">
                                    <label for="phone_number">Phone Number</label>
                                    <input type="tel" id="phone_number" name="phone_number" class="form-control"
                                        value="<?= $has_user_details && isset($user_details['phone_number']) ? htmlspecialchars($user_details['phone_number']) : '' ?>"
                                        placeholder="09XXXXXXXXX" maxlength="11"
                                        oninput="validatePhilippineNumber(this)"
                                        required>
                                    <small>Format: 09XXXXXXXXX</small>
                                </div>
                                
                                <div class="form-row">
                                <div>
                                    <label for="street">Street Address</label>
                                    <input type="text" id="street" name="street" class="form-control"
                                        value="<?= $has_user_details && isset($user_details['street']) ? htmlspecialchars($user_details['street']) : '' ?>">
                                </div>

                                <div>
                                    <label for="city">City/Municipality</label>
                                    <input type="text" id="city" name="city" class="form-control"
                                        value="<?= $has_user_details && isset($user_details['city']) ? htmlspecialchars($user_details['city']) : '' ?>">
                                </div>
                                </div>


                                <div class="form-row">
                                <div>
                                    <label for="province">Province</label>
                                    <input type="text" id="province" name="province" class="form-control"
                                        value="<?= $has_user_details && isset($user_details['province']) ? htmlspecialchars($user_details['province']) : '' ?>">
                                </div>

                                <div>
                                    <label for="zip_code">ZIP Code</label>
                                    <input type="text" id="zip_code" name="zip_code" class="form-control"
                                        value="<?= $has_user_details && isset($user_details['zipcode']) ? htmlspecialchars($user_details['zipcode']) : '' ?>"
                                        placeholder="1234" maxlength="4"
                                        oninput="validateZipCode(this)">
                                    <small>Format: 4 digits (e.g., 1234)</small>
                                </div>
                                </div>

                            <div class="form-navigation">
                                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Close</button>
                                <button type="submit" name="save_gcash" class="btn btn-success">Save Details</button>
                            </div>
                    </div>






































                    <!-- Step 2: Additional Details -->
                    <div class="form-step form-step-2">
                        <h3>Additional Information</h3>
                        <p class="form-info">Add contact and pickup details for buyers</p>

                        <div class="form-group">
                            <label for="phone_number">Phone Number</label>
                            <input type="tel" id="phone_number" name="phone_number" class="form-control"
                                value="<?= $has_user_details && isset($user_details['phone_number']) ? htmlspecialchars($user_details['phone_number']) : '' ?>"
                                placeholder="09XXXXXXXXX" maxlength="11"
                                oninput="validatePhilippineNumber(this)"
                                required>
                            <small>Format: 09XXXXXXXXX</small>
                        </div>

                        <div class="form-group">
                            <label for="street">Street Address</label>
                            <input type="text" id="street" name="street" class="form-control"
                                value="<?= $has_user_details && isset($user_details['street']) ? htmlspecialchars($user_details['street']) : '' ?>">
                        </div>

                        <div class="form-group">
                            <label for="city">City/Municipality</label>
                            <input type="text" id="city" name="city" class="form-control"
                                value="<?= $has_user_details && isset($user_details['city']) ? htmlspecialchars($user_details['city']) : '' ?>">
                        </div>

                        <div class="form-group">
                            <label for="province">Province</label>
                            <input type="text" id="province" name="province" class="form-control"
                                value="<?= $has_user_details && isset($user_details['province']) ? htmlspecialchars($user_details['province']) : '' ?>">
                        </div>

                        <div class="form-group">
                            <label for="zip_code">ZIP Code</label>
                            <input type="text" id="zip_code" name="zip_code" class="form-control"
                                value="<?= $has_user_details && isset($user_details['zipcode']) ? htmlspecialchars($user_details['zipcode']) : '' ?>"
                                placeholder="1234" maxlength="4"
                                oninput="validateZipCode(this)">
                            <small>Format: 4 digits (e.g., 1234)</small>
                        </div>

                        <div class="form-navigation">
                            <button type="button" class="btn btn-secondary" onclick="prevStep(2)"><i class="fas fa-arrow-left"></i> Back</button>
                            <button type="submit" name="save_gcash" class="btn btn-success">Save Details</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add necessary JavaScript to handle the Next button functionality -->
        <script>
            function nextStep(currentStep) {
                // Validate current step fields
                if (currentStep === 1) {
                    const gcashName = document.getElementById('gcash_name').value;
                    const gcashNumber = document.getElementById('gcash_number').value;

                    if (!gcashName || !gcashNumber || !gcashNumber.match(/^[0-9]{11}$/)) {
                        alert('Please fill in all required fields correctly before proceeding.');
                        return;
                    }
                }

                // Hide current step and show next step
                document.querySelector(`.form-step-${currentStep}`).classList.remove('active');
                document.querySelector(`.form-step-${currentStep + 1}`).classList.add('active');

                // Update progress indicators
                updateProgressIndicators(currentStep + 1);
            }

            function prevStep(currentStep) {
                // Hide current step and show previous step
                document.querySelector(`.form-step-${currentStep}`).classList.remove('active');
                document.querySelector(`.form-step-${currentStep - 1}`).classList.add('active');

                // Update progress indicators
                updateProgressIndicators(currentStep - 1);
            }

            function updateProgressIndicators(activeStep) {
                // Update step dots
                const dots = document.querySelectorAll('.step-dot');
                dots.forEach((dot, index) => {
                    if (index + 1 < activeStep) {
                        dot.classList.add('completed');
                        dot.classList.remove('active');
                    } else if (index + 1 === activeStep) {
                        dot.classList.add('active');
                        dot.classList.remove('completed');
                    } else {
                        dot.classList.remove('active', 'completed');
                    }
                });

                // Update progress bar
                const progressBar = document.querySelector('.progress-bar');
                const progress = ((activeStep - 1) / (dots.length - 1)) * 100;
                progressBar.style.width = `${progress}%`;
            }

            function closeModal() {
                document.getElementById('gcashModal').style.display = 'none';
            }

            // Form validation and step navigation functions
function validateGCashNumber(input) {
    // Strip all non-numeric characters
    let number = input.value.replace(/\D/g, '');

    // Limit to 11 digits
    if (number.length > 11) {
        number = number.substring(0, 11);
    }

    // Update the input value
    input.value = number;

    // Show validation message
    const errorElement = document.getElementById('gcash-error-msg');
    if (errorElement) {
        if (number.length > 0 && (!number.startsWith('09') || number.length !== 11)) {
            errorElement.textContent = 'Please enter a valid mobile number (09XXXXXXXXX)';
            errorElement.style.color = 'red';
            input.style.borderColor = 'red';
        } else {
            errorElement.textContent = 'Number Format: 09XXXXXXXXX';
            errorElement.style.color = '#666';
            input.style.borderColor = '';
        }
    }
}

function validatePhilippineNumber(input) {
    // Strip all non-numeric characters
    let number = input.value.replace(/\D/g, '');

    // Limit to 11 digits
    if (number.length > 11) {
        number = number.substring(0, 11);
    }

    // Update the input value
    input.value = number;

    // Show validation message
    const errorElement = input.nextElementSibling;
    if (errorElement) {
        if (number.length > 0 && (!number.startsWith('09') || number.length !== 11)) {
            errorElement.textContent = 'Please enter a valid mobile number (09XXXXXXXXX)';
            errorElement.style.color = 'red';
            input.style.borderColor = 'red';
        } else {
            errorElement.textContent = 'Format: 09XXXXXXXXX';
            errorElement.style.color = '#666';
            input.style.borderColor = '';
        }
    }
}

function validateZipCode(input) {
    // Strip all non-numeric characters
    let zipCode = input.value.replace(/\D/g, '');

    // Limit to 4 digits
    if (zipCode.length > 4) {
        zipCode = zipCode.substring(0, 4);
    }

    // Update the input value
    input.value = zipCode;

    // Show validation message
    const errorElement = input.nextElementSibling;
    if (errorElement) {
        if (zipCode.length > 0 && zipCode.length !== 4) {
            errorElement.textContent = 'ZIP code must be exactly 4 digits';
            errorElement.style.color = 'red';
            input.style.borderColor = 'red';
        } else {
            errorElement.textContent = 'Format: 4 digits (e.g., 1234)';
            errorElement.style.color = '#666';
            input.style.borderColor = '';
        }
    }
}

// Step navigation functions
function nextStep(currentStep) {
    // Validate current step fields
    if (currentStep === 1) {
        const gcashName = document.getElementById('gcash_name');
        const gcashNumber = document.getElementById('gcash_number');

        if (!gcashName || !gcashNumber) {
            console.error('GCash form elements not found');
            return;
        }

        const gcashNameValue = gcashName.value.trim();
        const gcashNumberValue = gcashNumber.value.trim();

        if (!gcashNameValue) {
            gcashName.focus();
            gcashName.style.borderColor = 'red';
            alert('Please enter your GCash registered name.');
            return;
        }

        if (!gcashNumberValue) {
            gcashNumber.focus();
            alert('Please enter your GCash number.');
            return;
        }

        if (!gcashNumberValue.startsWith('09') || gcashNumberValue.length !== 11) {
            gcashNumber.focus();
            validateGCashNumber(gcashNumber);
            alert('Please enter a valid mobile number and follow number format.');
            return;
        }

        // Reset border colors if validation passes
        gcashName.style.borderColor = '';
        gcashNumber.style.borderColor = '';
    }

    // Hide current step and show next step
    const currentStepElement = document.querySelector(`.form-step-${currentStep}`);
    const nextStepElement = document.querySelector(`.form-step-${currentStep + 1}`);

    if (currentStepElement && nextStepElement) {
        currentStepElement.classList.remove('active');
        nextStepElement.classList.add('active');
        updateProgressIndicators(currentStep + 1);
    }
}

function prevStep(currentStep) {
    // Hide current step and show previous step
    const currentStepElement = document.querySelector(`.form-step-${currentStep}`);
    const prevStepElement = document.querySelector(`.form-step-${currentStep - 1}`);

    if (currentStepElement && prevStepElement) {
        currentStepElement.classList.remove('active');
        prevStepElement.classList.add('active');
        updateProgressIndicators(currentStep - 1);
    }
}

function updateProgressIndicators(activeStep) {
    // Update step dots
    const dots = document.querySelectorAll('.step-dot');
    dots.forEach((dot, index) => {
        if (index + 1 < activeStep) {
            dot.classList.add('completed');
            dot.classList.remove('active');
        } else if (index + 1 === activeStep) {
            dot.classList.add('active');
            dot.classList.remove('completed');
        } else {
            dot.classList.remove('active', 'completed');
        }
    });

    // Update progress bar
    const progressBar = document.querySelector('.progress-bar');
    if (progressBar && dots.length > 0) {
        const progress = ((activeStep - 1) / (dots.length - 1)) * 100;
        progressBar.style.width = `${progress}%`;
    }
}

function goToStep(stepNumber) {
    // Hide all steps
    document.querySelectorAll('.form-step').forEach(step => {
        step.classList.remove('active');
    });

    // Show the current step
    const targetStep = document.querySelector(`.form-step-${stepNumber}`);
    if (targetStep) {
        targetStep.classList.add('active');
        updateStepIndicators(stepNumber);
    }
}

function updateStepIndicators(currentStep) {
    // Update dots
    document.querySelectorAll('.step-dot').forEach((dot, index) => {
        if (index + 1 <= currentStep) {
            dot.classList.add('active');
        } else {
            dot.classList.remove('active');
        }
    });

    // Update progress bar
    const progressBar = document.querySelector('.progress-bar');
    const totalSteps = document.querySelectorAll('.step-dot').length;
    if (progressBar && totalSteps > 0) {
        const progressPercentage = ((currentStep - 1) / (totalSteps - 1)) * 100;
        progressBar.style.width = `${progressPercentage}%`;
    }
}

// Modal functions - FIXED VERSION
function closeModal() {
    // Close all modals
    const modals = [
        'gcashModal',
        'deleteModal', 
        'editModal',
        'policyModal',
        'bulkDeleteModal'
    ];
    
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
    });
}

function showPolicyModal() {
    const policyModal = document.getElementById('policyModal');
    const agreeButton = document.getElementById('agreeButton');
    
    if (policyModal) {
        policyModal.style.display = 'flex';
    }
    
    if (agreeButton) {
        agreeButton.style.display = 'none';
    }
}

function showPolicyModalForAgreement() {
    const policyModal = document.getElementById('policyModal');
    const agreeButton = document.getElementById('agreeButton');
    
    if (policyModal) {
        policyModal.style.display = 'flex';
    }
    
    if (agreeButton) {
        agreeButton.style.display = 'inline-block';
    }
}

function agreeToPolicyAndProceed() {
    console.log("agreeToPolicyAndProceed called"); 
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'agree_to_policy=1'
    })
    .then(response => {
        console.log("Fetch response received:", response.status); 
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error(`Network response was not ok (${response.status}). Server said: ${text || 'No additional error message'}`);
            });
        }
        return response.json();
    })
    .then(data => {
        console.log("Fetch data received:", data); 
        if (data.success) {
            console.log("Policy agreement successful. Closing modal and showing in-page alert."); 
            closeModal();
            window.policyAgreed = true; // Update client-side state immediately
            showInPageAlert('Thank you for agreeing to the policy. You can now add your product.', 'success');
            
            setTimeout(() => {
                console.log("Switching to add-product tab."); 
            switchTab('add-product');
            }, 1500); 
        } else {
            console.error("Policy agreement failed on server:", data.message || 'No specific error from server.'); 
            closeModal(); 
            showInPageAlert(data.message || 'Error: Could not process your agreement. Please try again.', 'error');
        }
    })
    .catch(error => {
        console.error('Fetch Error:', error); 
        closeModal(); 
        showInPageAlert(`Error: ${error.message || 'Could not process your agreement. Please try again.'}`, 'error');
    });
}

function showGcashModal() {
    const gcashModal = document.getElementById('gcashModal');
    if (gcashModal) {
        gcashModal.style.display = 'flex';
        // Reset form to first step when showing modal
        goToStep(1);
    }
}

// Tab switching function
function switchTab(tabId) {
    // Check if we have the required variables (these should be set by PHP)
    const hasGcashDetails = window.hasGcashDetails || false;
    const policyAgreed = window.policyAgreed || false;
    
    // If trying to switch to add-product tab but no GCash details, show GCash modal first
    if (tabId === 'add-product' && !hasGcashDetails) {
        showGcashModal();
        return; // Prevent tab switching
    }
    
    // If trying to switch to add-product tab but policy not agreed, show policy modal first
    if (tabId === 'add-product' && !policyAgreed) {
        showPolicyModalForAgreement();
        return; // Prevent tab switching
    }

    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.style.display = 'none';
    });
    
    // Remove active class from all tab items
    document.querySelectorAll('.tab-item').forEach(item => {
        item.classList.remove('active');
    });

    // Show selected tab content
    const selectedTab = document.getElementById(tabId);
    if (selectedTab) {
        selectedTab.style.display = 'block';
    }
    
    // Add active class to selected tab item
    const selectedTabItem = document.querySelector(`[data-tab="${tabId}"]`);
    if (selectedTabItem) {
        selectedTabItem.classList.add('active');
    }
}

// Product management functions
function showDeleteConfirmation(productId, productName) {
    const deleteModal = document.getElementById('deleteModal');
    const deleteProductId = document.getElementById('deleteProductId');
    const deleteProductName = document.getElementById('deleteProductName');
    
    if (deleteProductId) deleteProductId.value = productId;
    if (deleteProductName) deleteProductName.textContent = productName;
    if (deleteModal) deleteModal.style.display = 'flex';
}

function showEditModal(productId, productName, productPrice, productDescription, productCondition, productImage) {
    const editModal = document.getElementById('editModal');
    
    // Set form values
    const editProductId = document.getElementById('edit_product_id');
    const editProductName = document.getElementById('edit_product_name');
    const editProductPrice = document.getElementById('edit_product_price');
    const editProductDescription = document.getElementById('edit_product_description');
    const editProductCondition = document.getElementById('edit_product_condition');
    
    if (editProductId) editProductId.value = productId;
    if (editProductName) editProductName.value = productName;
    if (editProductPrice) editProductPrice.value = productPrice;
    if (editProductDescription) editProductDescription.value = productDescription;
    
    if (editProductCondition) {
        for (let i = 0; i < editProductCondition.options.length; i++) {
            if (editProductCondition.options[i].value === productCondition) {
                editProductCondition.selectedIndex = i;
                break;
            }
        }
    }
    
    // Set image preview
    const imagePreview = document.getElementById('editImagePreview');
    const previewText = document.getElementById('editPreviewText');
    
    if (imagePreview && productImage) {
        imagePreview.src = productImage;
        imagePreview.style.display = 'block';
    }
    if (previewText) {
        previewText.textContent = 'Current image:';
    }

    if (editModal) {
        editModal.style.display = 'flex';
    }
}

function previewImage(input, previewId, textId) {
    const preview = document.getElementById(previewId);
    const previewText = document.getElementById(textId);

    if (input.files && input.files[0]) {
        const reader = new FileReader();

        reader.onload = function(e) {
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            if (previewText) {
                previewText.textContent = 'Image preview:';
            }
        };

        reader.readAsDataURL(input.files[0]);
    }
}

// Bulk selection functionality
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.product-checkbox');

    if (selectAll) {
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
        });
    }

    updateBulkActions();
}

function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.product-checkbox:checked');
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    const selectedCount = document.getElementById('selectedCount');
    const selectAll = document.getElementById('selectAll');
    const totalCheckboxes = document.querySelectorAll('.product-checkbox');

    if (selectedCount) {
        selectedCount.textContent = checkboxes.length;
    }

    if (bulkActionsBar) {
        if (checkboxes.length > 0) {
            bulkActionsBar.style.display = 'flex';
        } else {
            bulkActionsBar.style.display = 'none';
        }
    }

    // Update select all checkbox state
    if (selectAll) {
        if (checkboxes.length === totalCheckboxes.length && totalCheckboxes.length > 0) {
            selectAll.checked = true;
            selectAll.indeterminate = false;
        } else if (checkboxes.length > 0) {
            selectAll.checked = false;
            selectAll.indeterminate = true;
        } else {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }
    }
}

function clearSelection() {
    const checkboxes = document.querySelectorAll('.product-checkbox');
    const selectAll = document.getElementById('selectAll');

    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });

    if (selectAll) {
        selectAll.checked = false;
        selectAll.indeterminate = false;
    }
    
    updateBulkActions();
}

function showBulkDeleteModal() {
    const checkboxes = document.querySelectorAll('.product-checkbox:checked');
    const selectedIds = Array.from(checkboxes).map(cb => cb.value);

    if (selectedIds.length === 0) {
        alert('Please select at least one product to delete.');
        return;
    }

    const bulkDeleteCount = document.getElementById('bulkDeleteCount');
    const bulkDeleteIds = document.getElementById('bulkDeleteIds');
    const bulkDeleteModal = document.getElementById('bulkDeleteModal');

    if (bulkDeleteCount) bulkDeleteCount.textContent = selectedIds.length;
    if (bulkDeleteIds) bulkDeleteIds.value = selectedIds.join(',');
    if (bulkDeleteModal) bulkDeleteModal.style.display = 'flex';
}

// NEW showInPageAlert function
function showInPageAlert(message, type = 'success') {
    console.log(`showInPageAlert called with message: "${message}", type: "${type}"`);

    // Remove any existing dynamic in-page alerts created by this function
    const existingAlert = document.querySelector('.dynamic-page-alert');
    if (existingAlert) {
        existingAlert.remove();
    }

    const alertDiv = document.createElement('div');
    // Added 'dynamic-page-alert' to specifically target alerts created by this function for removal
    alertDiv.className = `alert dynamic-page-alert alert-${type === 'error' ? 'danger' : 'success'}`;
    alertDiv.setAttribute('role', 'alert');
    alertDiv.textContent = message;

    // Insert the alert into the DOM, e.g., at the top of the main container
    const mainContainer = document.querySelector('.container'); // Main content container
    const buttonNav = document.querySelector('.button-nav'); // Reference point for insertion

    if (mainContainer && buttonNav) {
        // Insert after the button navigation but before other content within .container
        buttonNav.parentNode.insertBefore(alertDiv, buttonNav.nextSibling);
    } else if (mainContainer) {
        mainContainer.insertBefore(alertDiv, mainContainer.firstChild); // Fallback to top of container
    } else {
        document.body.insertBefore(alertDiv, document.body.firstChild); // Fallback to body
    }

    // Automatically remove the alert after a few seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 3500); // Slightly longer duration
}

// Event listeners for modal close buttons
document.addEventListener('DOMContentLoaded', function() {
    // Update bulk actions on page load
    updateBulkActions();
    
    // Add event listeners for close buttons
    document.querySelectorAll('.modal-close, .close-btn').forEach(button => {
        button.addEventListener('click', closeModal);
    });
    
    // Add event listeners for clicking outside modals to close them
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
    });
    
    // Add event listener for ESC key to close modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    // Auto-hide PHP-generated success alert
    const phpSuccessAlert = document.getElementById('phpSuccessMessage');
    if (phpSuccessAlert) {
        setTimeout(() => {
            if (phpSuccessAlert.parentNode) {
                phpSuccessAlert.parentNode.removeChild(phpSuccessAlert);
            }
        }, 3500); // Disappear after 3.5 seconds
    }

    // Auto-hide PHP-generated error alerts
    // Targets any .alert-danger within .container that wasn't dynamically added by showInPageAlert
    const phpErrorAlert = document.querySelector('.container > .alert.alert-danger:not(.dynamic-page-alert)');
    if (phpErrorAlert) {
         setTimeout(() => {
            if (phpErrorAlert.parentNode) {
                phpErrorAlert.parentNode.removeChild(phpErrorAlert);
            }
        }, 3500); // Disappear after 3.5 seconds
    }

    // Initialize page state
    const hasGcashDetails = window.hasGcashDetails || false;
    const policyAgreed = window.policyAgreed || false;
    const editRedirect = <?= $edit_redirect ? 'true' : 'false' ?>; // Get PHP variable
    const editData = <?= $edit_data ? json_encode($edit_data) : 'null' ?>; // Get PHP variable

    // Check for success message from PHP (via session)
    <?php if (isset($success_message) && !empty($success_message) && !isset($_POST['agree_to_policy'])):
        // Only show PHP session success message if it's NOT from the policy agreement AJAX response,
        // as that will be handled by showInPageAlert in JS.
    ?>
    // The PHP success message is already rendered as a div with class alert-success.
    // We can make it self-removing if needed, or let showInPageAlert handle all dynamic messages.
    // For now, relying on the PHP rendered div.
    <?php endif; ?>

    if (editRedirect && editData) {
        switchTab('manage-products'); 
        showEditModal(
            editData.listing_id,
            editData.product_name, 
            editData.product_price,
            editData.product_description,
            editData.product_condition,
            editData.product_image
        );
    } else if (!hasGcashDetails) {
        showGcashModal();
        const closeButton = document.querySelector('#gcashModal .modal-close');
        if (closeButton) {
            closeButton.style.display = 'none';
        }
    } else {
        // Default to manage products if no other action is pending
        // Only switch if no other modal was opened by editRedirect or Gcash check
        if (!document.querySelector('.modal[style*="display: flex"]') && !document.querySelector('.modal[style*="display: block"]')) {
            switchTab('manage-products');
        }
    }
});

        </script>
        
</body>

</html>
