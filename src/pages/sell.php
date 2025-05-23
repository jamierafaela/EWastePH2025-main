<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/ewasteWeb.php#loginSection");
    exit();
}


$logged_in = true;

$conn = new mysqli("localhost", "root", "", "ewaste_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$message = "";
$error = "";

$conditions = array("New", "Like New", "Good", "Fair", "Poor");


$user_products_query = $conn->prepare("SELECT * FROM listings WHERE seller_id = ? ORDER BY created_at DESC");
$user_products_query->bind_param("i", $user_id);
$user_products_query->execute();
$user_products_result = $user_products_query->get_result();


if (isset($_POST['delete_product']) && isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);

    $delete_query = $conn->prepare("DELETE FROM listings WHERE listing_id = ? AND seller_id = ?");
    $delete_query->bind_param("ii", $product_id, $user_id);
    
    if ($delete_query->execute() && $delete_query->affected_rows > 0) {
        $message = "Product deleted successfully!";
   
        $user_products_query->execute();
        $user_products_result = $user_products_query->get_result();
    } else {
        $error = "Error deleting product: " . $conn->error;
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_product'])) {
    $product_id = intval($_POST['product_id']);
    $product_name = trim($_POST['edit_product_name']);
    $product_description = trim($_POST['edit_product_description']);
    $product_condition = trim($_POST['edit_product_condition']);
    $product_price = floatval($_POST['edit_product_price']);
    
   
    if (empty($product_name) || $product_price <= 0 || empty($product_description) || empty($product_condition)) {
        $error = "All fields are required and price must be greater than zero.";
    } else {
     
        $get_product_query = $conn->prepare("SELECT product_image FROM 
        listings WHERE listing_id = ? AND seller_id = ?");
        $get_product_query->bind_param("ii", $product_id, $user_id);
        $get_product_query->execute();
        $current_product = $get_product_query->get_result()->fetch_assoc();
        
        $image_path = $current_product['product_image']; 
        
        if(isset($_FILES['edit_product_image']) && $_FILES['edit_product_image']['error'] === 0) {
            $upload_dir = '../uploads/listings';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['edit_product_image']['name'], PATHINFO_EXTENSION);
            $filename = 'product_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
            $target_file = $upload_dir . $filename;
            

            $valid_extensions = array("jpg", "jpeg", "png", "gif");
            if(in_array(strtolower($file_extension), $valid_extensions)) {
                if(move_uploaded_file($_FILES['edit_product_image']['tmp_name'], $target_file)) {
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
            
            $update_query->bind_param("sssdsii", 
                $product_name, 
                $product_description, 
                $product_condition,
                $product_price,
                $image_path,
                $product_id,
                $user_id
            );
            
            if ($update_query->execute()) {
                $message = "Product updated successfully!";
                $user_products_query->execute();
                $user_products_result = $user_products_query->get_result();
            } else {
                $error = "Error updating product: " . $conn->error;
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create'])) {
    $product_name = trim($_POST['product_name']);
    $product_description = trim($_POST['product_description']);
    $product_condition = trim($_POST['product_condition']);
    $product_price = floatval($_POST['product_price']);
    

    if (empty($product_name) || $product_price <= 0 || empty($product_description) || empty($product_condition)) {
        $error = "All fields are required and price must be greater than zero.";
    } else {

        if(isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
            $upload_dir = '../uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
            $filename = 'product_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
            $target_file = $upload_dir . $filename;
            
            $valid_extensions = array("jpg", "jpeg", "png", "gif");
            if(in_array(strtolower($file_extension), $valid_extensions)) {
                if(move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
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
                        $user_id
                    );
                    
                    if ($insert_query->execute()) {
                        $message = "Product listed successfully!";
                        $user_products_query->execute();
                        $user_products_result = $user_products_query->get_result();
      
                        echo "<script>window.onload = function() { switchTab('manage-products'); }</script>";
                    } else {
                        $error = "Error creating listing: " . $conn->error;
                    }
                } else {
                    $error = "Sorry, there was an error uploading your file.";
                }
            } else {
                $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
            }
        } else {
            $error = "Product image is required.";
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

    $edit_product_query = $conn->prepare("SELECT * FROM listings WHERE listing_id = ? AND seller_id = ?");
    $edit_product_query->bind_param("ii", $edit_product_id, $user_id);
    $edit_product_query->execute();
    $edit_result = $edit_product_query->get_result();
    
    if ($edit_result->num_rows > 0) {
        $edit_data = $edit_result->fetch_assoc();
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - E-WastePH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../../src/styles/sell.css">
    <script>
        
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            document.querySelectorAll('.tab-item').forEach(item => {
                item.classList.remove('active');
            });
            
            document.getElementById(tabId).style.display = 'block';
            document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
        }
        
        function showDeleteConfirmation(productId, productName) {
            document.getElementById('deleteProductId').value = productId;
            document.getElementById('deleteProductName').textContent = productName;
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        function showEditModal(productId, productName, productPrice, productDescription, productCondition, productImage) {
        
            document.getElementById('edit_product_id').value = productId;
            document.getElementById('edit_product_name').value = productName;
            document.getElementById('edit_product_price').value = productPrice;
            document.getElementById('edit_product_description').value = productDescription;
            
            const conditionSelect = document.getElementById('edit_product_condition');
            for (let i = 0; i < conditionSelect.options.length; i++) {
                if (conditionSelect.options[i].value === productCondition) {
                    conditionSelect.selectedIndex = i;
                    break;
                }
            }
            const imagePreview = document.getElementById('editImagePreview');
            imagePreview.src = productImage;
            imagePreview.style.display = 'block';
            document.getElementById('editPreviewText').textContent = 'Current image:';

            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
            document.getElementById('editModal').style.display = 'none';
        }
        
        function previewImage(input, previewId, textId) {
            const preview = document.getElementById(previewId);
            const previewText = document.getElementById(textId);
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    previewText.textContent = 'Image preview:';
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        
        window.onload = function() {
            switchTab('manage-products');
        };
    </script>
     
</head>

<body>


    <div class="page-header">
        <div class="header-container">
            <h1 class="page-title"><i class="fas fa-boxes"></i> Sell Products </h1>
        </div>
    </div>

    <div class="container">
        <!-- Back -->
        <a href="userdash.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="tabs-container">
            <ul class="tabs-nav">
                <li class="tab-item active" data-tab="manage-products" onclick="switchTab('manage-products')">
                    <i class="fas fa-list"></i> Manage Products
                </li>
                <li class="tab-item" data-tab="add-product" onclick="switchTab('add-product')">
                    <i class="fas fa-plus-circle"></i> Add New Product
                </li>
            </ul>
        </div>

        <!-- Manage Products -->
        <div id="manage-products" class="tab-content">
            <?php if ($user_products_result->num_rows > 0): ?>
                <table class="product-table">
                    <thead>
                        <tr>
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
                    <button class="btn btn-add" onclick="switchTab('add-product')">
                        <i class="fas fa-plus-circle"></i> Add Your First Product
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Add Product -->
        <div id="add-product" class="tab-content" >
            <div class="product-form">
                <h2 class="form-header">Create New Product Listing</h2>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="product_name">Product Name</label>
                        <input type="text" id="product_name" name="product_name" class="form-control" 
                               value="<?= isset($_POST['product_name']) ? htmlspecialchars($_POST['product_name']) : '' ?>" 
                               placeholder="Enter product name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="product_price">Price (₱)</label>
                        <input type="number" id="product_price" name="product_price" class="form-control" 
                               value="<?= isset($_POST['product_price']) ? $_POST['product_price'] : '' ?>" 
                               step="0.01" min="0.01" placeholder="Enter product price" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="product_description">Description</label>
                        <textarea id="product_description" name="product_description" class="form-control" 
                                  placeholder="Describe your product in detail...include the quantity" required><?= isset($_POST['product_description']) ? htmlspecialchars($_POST['product_description']) : '' ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="product_condition">Condition</label>
                        <select id="product_condition" name="product_condition" class="form-control" required>
                        <option value="">Select Condition</option>
                            <?php foreach($conditions as $condition): ?>
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
        </div>
    </div>

    <!-- Delete Popup -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3>Confirm Delete</h3>
            <p>Are you sure you want to delete "<span id="deleteProductName"></span>"?</p>
            <p>This action cannot be undone.</p>
            <div class="modal-buttons">
                <button class="btn btn-back" onclick="closeModal()">Cancel</button>
                <form method="POST" action="">
                    <input type="hidden" id="deleteProductId" name="product_id">
                    <button type="submit" name="delete_product" class="btn btn-delete">Delete</button>
                </form>
            </div>
        </div>
    </div>
    
 <!-- Edit Product-->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h3>Edit Product</h3>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" id="edit_product_id" name="product_id">
            
            <div class="form-group">
                <label for="edit_product_name">Product Name</label>
                <input type="text" id="edit_product_name" name="edit_product_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="edit_product_price">Price (₱)</label>
                <input type="number" id="edit_product_price" name="edit_product_price" class="form-control" 
                       step="0.01" min="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="edit_product_description">Description</label>
                <textarea id="edit_product_description" name="edit_product_description" class="form-control" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="edit_product_condition">Condition</label>
                <select id="edit_product_condition" name="edit_product_condition" class="form-control" required>
                    <option value="">Select Condition</option>
                    <?php foreach($conditions as $condition): ?>
                        <option value="<?= $condition ?>"><?= $condition ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="edit_product_image">Product Image</label>
                <div class="image-preview">
                    <img id="editImagePreview" src="">
                    <p id="editPreviewText">Current image</p>
                    <div class="file-input-wrapper">
                        <button class="file-input-button" type="button">Choose New Image (Optional)</button>
                        <input type="file" id="edit_product_image" name="edit_product_image" accept="image/*" 
                              onchange="previewImage(this, 'editImagePreview', 'editPreviewText')">
                    </div>
                </div>
            </div>
            
            <div class="modal-buttons">
                <button type="button" class="btn btn-back" onclick="closeModal()">Cancel</button>
                <button type="submit" name="edit_product" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<script>
    <?php if ($edit_redirect && $edit_data): ?>
    window.onload = function() {
        switchTab('manage-products');
        showEditModal(
            '<?= $edit_data['listing_id'] ?>',
            '<?= htmlspecialchars(addslashes($edit_data['product_name'])) ?>',
            '<?= $edit_data['product_price'] ?>',
            '<?= htmlspecialchars(addslashes($edit_data['product_description'])) ?>',
            '<?= htmlspecialchars($edit_data['product_condition']) ?>',
            '<?= htmlspecialchars($edit_data['product_image']) ?>'
        );
    };
    <?php endif; ?>
</script>
</body>
</html> 