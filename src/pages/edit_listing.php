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
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = "";
$error = "";

$check_query = $conn->prepare("SELECT * FROM listings WHERE listing_id = ? AND seller_id = ?");
$check_query->bind_param("ii", $product_id, $user_id);
$check_query->execute();
$result = $check_query->get_result();

if ($result->num_rows === 0) {
    header("Location: user_dashboard.php");
    exit();
}

$product = $result->fetch_assoc();


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    $product_name = trim($_POST['product_name']);
    $product_price = floatval($_POST['product_price']);
    $product_description = trim($_POST['product_description']);
    $product_condition = trim($_POST['product_condition']);
    
    if (empty($product_name) || $product_price <= 0 || empty($product_description) || empty($product_condition)) {
        $error = "All fields are required and price must be greater than zero.";
    } else {

        $image_path = $product['product_image']; 
        if(isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
            $upload_dir = '../uploads/';
            $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
            $filename = 'product_' . $product_id . '_' . time() . '.' . $file_extension;
            $target_file = $upload_dir . $filename;
            
            $valid_extensions = array("jpg", "jpeg", "png", "gif");
            if(in_array(strtolower($file_extension), $valid_extensions)) {
                if(move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                    $image_path = $target_file;
                } else {
                    $error = "Sorry, there was an error uploading your file.";
                }
            } else {
                $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
            }
        }
        
        if(empty($error)) {
            $update_query = $conn->prepare("UPDATE listings SET 
                product_name = ?, 
                product_price = ?, 
                product_description = ?, 
                product_condition = ?,
                product_image = ?,
                updated_at = NOW() 
                WHERE listing_id = ? AND seller_id = ?");
            
            $update_query->bind_param("sdsssii", 
                $product_name, 
                $product_price, 
                $product_description, 
                $product_condition,
                $image_path,
                $product_id,
                $user_id
            );
            
            if ($update_query->execute()) {
                $message = "Listing updated successfully!";
                
                // Refresh product data
                $result = $conn->query("SELECT * FROM listings WHERE listing_id = $product_id");
                $product = $result->fetch_assoc();
            } else {
                $error = "Error updating listing: " . $conn->error;
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete'])) {
    // Confirm the product belongs to the user
    $delete_query = $conn->prepare("DELETE FROM listings WHERE listing_id = ? AND seller_id = ?");
    $delete_query->bind_param("ii", $product_id, $user_id);
    
    if ($delete_query->execute() && $delete_query->affected_rows > 0) {
        // Redirect to dashboard with success message
        $_SESSION['message'] = "Listing deleted successfully!";
        header("Location: user_dashboard.php");
        exit();
    } else {
        $error = "Error deleting listing: " . $conn->error;
    }
}


$conditions = array("New", "Like New", "Good", "Fair", "Poor");

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Listing - E-WastePH</title>
    <link rel="stylesheet" href="../styles/ewasteWeb.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .edit-form {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        textarea.form-control {
            min-height: 150px;
        }

        .button-group {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }

        .btn-primary {
            background-color: rgb(20, 123, 24);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 5px;
            width: 400px;
            max-width: 80%;
            text-align: center;
        }

        .modal-buttons {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .image-preview {
            margin-bottom: 20px;
            text-align: center;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 300px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
        }

        .image-preview-text {
            margin-top: 10px;
            color: #6c757d;
            font-style: italic;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }

        .file-input-wrapper input[type=file] {
            font-size: 100px;
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            cursor: pointer;
        }

        .file-input-button {
            background-color: #6c757d;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            display: inline-block;
        }
    </style>
    <script>
        function showDeleteConfirmation() {
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const previewText = document.getElementById('previewText');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    previewText.textContent = 'New image preview:';
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
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
                <li><a href="user_dashboard.php"><i class="fa fa-user"></i></a></li>
            </ul>
        </nav>
    </header>

    <div class="container" style="padding-top: 80px;">
        <div class="card edit-form">
            <h2 class="card-header">Edit Listing</h2>

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

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="product_name">Product Name</label>
                    <input type="text" id="product_name" name="product_name" class="form-control" value="<?= htmlspecialchars($product['product_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="product_price">Price (₱)</label>
                    <input type="number" id="product_price" name="product_price" class="form-control" value="<?= $product['product_price'] ?>" step="0.01" min="0.01" required>
                </div>

                <div class="form-group">
                    <label for="product_description">Description</label>
                    <textarea id="product_description" name="product_description" class="form-control" required><?= htmlspecialchars($product['product_description']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="product_condition">Condition</label>
                    <select id="product_condition" name="product_condition" class="form-control" required>
                        <?php foreach ($conditions as $condition): ?>
                            <option value="<?= $condition ?>" <?= ($condition == $product['product_condition']) ? 'selected' : '' ?>>
                                <?= $condition ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Product Image Preview and Upload -->
                <div class="form-group">
                    <label>Product Image</label>
                    <div class="image-preview">
                        <?php if (!empty($product['product_image'])): ?>
                            <img src="<?= htmlspecialchars($product['product_image']) ?>" alt="Product Image" id="imagePreview">
                            <p id="previewText" class="image-preview-text">Current image</p>
                        <?php else: ?>
                            <img src="../uploads/default-product.png" alt="No Image Available" id="imagePreview">
                            <p id="previewText" class="image-preview-text">No image available</p>
                        <?php endif; ?>
                    </div>
                    <div class="file-input-wrapper">
                        <span class="file-input-button">Choose New Image</span>
                        <input type="file" name="product_image" accept="image/*" onchange="previewImage(this)">
                    </div>
                    <small>Leave empty to keep the current image.</small>
                </div>

                <div class="button-group">
                    <button type="button" class="btn-danger" onclick="showDeleteConfirmation()">Delete Listing</button>
                    <div>
                        <a href="user_dashboard.php" class="btn-secondary">Cancel</a>
                        <button type="submit" name="update" class="btn-primary">Update Listing</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3>Confirm Delete</h3>
            <p>Are you sure you want to delete this listing? This action cannot be undone.</p>
            <div class="modal-buttons">
                <button class="btn-secondary" onclick="closeModal()">Cancel</button>
                <form id="deleteForm" method="POST" action="">
                    <button type="submit" name="delete" class="btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>