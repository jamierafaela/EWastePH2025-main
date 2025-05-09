<?php



$conn = new mysqli("localhost", "root", "", "ewaste_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

// ADD PRODUCT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add'])) {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $quantity = $_POST['quantity'];
    $category = $_POST['category'];
    $image = $_POST['image'];
    $condition = $_POST['condition']; 

   
    $stmt = $conn->prepare("INSERT INTO products (name, price, quantity, category, image) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sdiss", $name, $price, $quantity, $category, $image);

    if ($stmt->execute()) {
        $message = "Product added successfully!";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// UPDATE PRODUCT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    $product_id = $_POST['product_id'];
    $name = $_POST['name'];
    $price = $_POST['price'];
    $quantity = $_POST['quantity']; 
    $category = $_POST['category'];
    $image = $_POST['image']; 
    
    
    $stmt = $conn->prepare("UPDATE products SET name=?, price=?, quantity=?, category=?, image=? WHERE product_id=?");
    $stmt->bind_param("sdissi", $name, $price, $quantity, $category, $image, $product_id);
    
    if ($stmt->execute()) {
        $message = "Product with ID $product_id updated successfully!";
    } else {
        $message = "Error updating product: " . $stmt->error;
    }
    $stmt->close();
}

// DELETE PRODUCT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete'])) {
    $id = $_POST['product_id'];
    if ($conn->query("DELETE FROM products WHERE product_id=$id")) {
        $message = "Product deleted successfully!";
    } else {
        $message = "Error deleting product.";
    }
}

// FETCH PRODUCTS
$result = $conn->query("SELECT * FROM products ORDER BY created_at DESC");

$active_view = isset($_GET['view']) ? $_GET['view'] : 'products';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Waste Management System</title>
    <link rel="stylesheet" href="../../src/styles/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
        </style>
</head>

<body>

    <div class="container">
        <!-- Page Title -->
        <div class="page-title">
        <i class="fas fa-plus-circle"></i>
            <h1>Add Products</h1>
        </div>

        <!-- Navigation Tabs -->
        <div class="nav-tabs">
            <a href="?view=products" class="nav-tab <?php echo $active_view == 'products' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> Manage Products
            </a>
            <a href="?view=add" class="nav-tab <?php echo $active_view == 'add' ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i> Add New Product
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($active_view == 'add'): ?>
        <div class="card">
            <div class="card-header">
                Add New Product
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Product Name</label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="Enter product name" required>
                        </div>
                        <div class="form-group">
                            <label for="price">Price (₱)</label>
                            <input type="number" id="price" step="0.01" name="price" class="form-control" placeholder="99.99" required>
                        </div>
                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <input type="number" id="quantity" name="quantity" class="form-control" placeholder="Available quantity" required>
                        </div>
                        <div class="form-group">
                            <label for="category">Category</label>
                            <input type="text" id="category" name="category" class="form-control" placeholder="Product category" required>
                        </div>
                        <div class="form-group">
                            <label for="condition">Condition</label>
                            <select id="condition" name="condition" class="form-control" required>
                                <option value="">Select condition</option>
                                <option value="New">New</option>
                                <option value="Like New">Like New</option>
                                <option value="Good">Good</option>
                                <option value="Fair">Fair</option>
                                <option value="Poor">Poor</option>
                                <option value="Refurbished">Refurbished</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="image">Image URL</label>
                        <input type="text" id="image" name="image" class="form-control" placeholder="https://example.com/image.jpg" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="add" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Add Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <!-- Products Table -->
        <div class="card">
            <div class="card-header">
                Manage Products
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price (₱)</th>
                                <th>Quantity</th>
                                <th>Image URL</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <form method="POST">
                                            <td>
                                                <?= $row['product_id'] ?>
                                                <input type="hidden" name="product_id" value="<?= $row['product_id'] ?>">
                                            </td>
                                            <td>
                                                <img src="<?= htmlspecialchars($row['image']) ?>" alt="Product" class="product-image">
                                            </td>
                                            <td>
                                                <input type="text" name="name" value="<?= htmlspecialchars($row['name']) ?>" class="form-control">
                                            </td>
                                            <td>
                                                <input type="text" name="category" value="<?= htmlspecialchars($row['category']) ?>" class="form-control">
                                            </td>
                                            <td>
                                                <input type="number" name="price" value="<?= $row['price'] ?>" step="0.01" class="form-control input-sm">
                                            </td>
                                            <td>
                                                <input type="number" name="quantity" value="<?= $row['quantity'] ?>" class="form-control input-sm">
                                            </td>
                                            <td>
                                                <input type="text" name="image" value="<?= htmlspecialchars($row['image']) ?>" class="form-control">
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button type="submit" name="update" class="btn btn-sm btn-success">
                                                        <i class="fas fa-save"></i> Update
                                                    </button>
                                                    <button type="submit" name="delete" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </form>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 30px;">No products found in inventory.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

</body>

</html>