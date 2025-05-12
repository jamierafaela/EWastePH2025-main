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

    $stmt = $conn->prepare("INSERT INTO products (name, price, quantity, category, image, `condition`) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sdisss", $name, $price, $quantity, $category, $image, $condition);

    if ($stmt->execute()) {
        $message = "Product added successfully!";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['multi_update'])) {
    $success_count = 0;
    $error_count = 0;
    
    if (isset($_POST['product_id']) && is_array($_POST['product_id'])) {
        $product_count = count($_POST['product_id']);
        
        $stmt = $conn->prepare("UPDATE products SET name=?, price=?, quantity=?, category=?, image=?, `condition`=? WHERE product_id=?");
        $stmt->bind_param("sdisssi", $name, $price, $quantity, $category, $image, $condition, $product_id);
        
        for ($i = 0; $i < $product_count; $i++) {
            $product_id = $_POST['product_id'][$i];
            $name = $_POST['name'][$i];
            $price = $_POST['price'][$i];
            $quantity = $_POST['quantity'][$i];
            $category = $_POST['category'][$i];
            $image = $_POST['image'][$i];
            $condition = $_POST['condition'][$i];
            
            if ($stmt->execute()) {
                $success_count++;
            } else {
                $error_count++;
            }
        }
        
        $stmt->close();
        
        if ($success_count > 0) {
            $message = "$success_count products updated successfully!";
            if ($error_count > 0) {
                $message .= " $error_count products failed to update.";
            }
        } else {
            $message = "Error: No products were updated.";
        }
    }
}

// DELETE PRODUCT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete'])) {
    $id = $_POST['delete_id'];
    if ($conn->query("DELETE FROM products WHERE product_id=$id")) {
        $message = "Product deleted successfully!";
    } else {
        $message = "Error deleting product.";
    }
}

// MULTI DELETE PRODUCTS
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['multi_delete'])) {
    if (!empty($_POST['selected_products'])) {
        $selected_products = $_POST['selected_products'];
        $ids = implode(",", array_map('intval', $selected_products));
        
        if ($conn->query("DELETE FROM products WHERE product_id IN ($ids)")) {
            $count = count($selected_products);
            $message = "$count products deleted successfully!";
        } else {
            $message = "Error deleting products.";
        }
    } else {
        $message = "No products selected for deletion.";
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
    <style>
        .action-bar {
            background-color: #f5f5f5;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .action-bar .btn {
            margin-right: 10px;
        }
        
        .selected-count {
            color: #6c757d;
            font-size: 0.9em;
            margin-left: 8px;
        }
        
        .product-checkbox {
            transform: scale(1.1);
            margin-right: 5px;
        }
        
        .modified-row {
            background-color: #fffde7 !important;
        }
        
        .data-table input[type="text"],
        .data-table input[type="number"],
        .data-table select {
            width: 100%;
            border: 1px solid #ddd;
            padding: 5px;
            border-radius: 3px;
        }
        
        .data-table input[type="text"]:focus,
        .data-table input[type="number"]:focus,
        .data-table select:focus {
            border-color: #3c8dbc;
            box-shadow: 0 0 3px rgba(60, 141, 188, 0.5);
        }
        
        .btn-floating {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 15px 20px;
            border-radius: 50px;
            font-size: 16px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            z-index: 1000;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 40%;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            animation: modalopen 0.3s;
        }
        
        @keyframes modalopen {
            from {opacity: 0; transform: scale(0.8);}
            to {opacity: 1; transform: scale(1);}
        }
        
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #dc3545;
        }
        
        .close-modal {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: black;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .select-actions {
            display: flex;
            align-items: center;
        }
        
        .select-all-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 4px 8px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9em;
            transition: all 0.2s;
        }
        
        .select-all-label:hover {
            background-color: #e9ecef;
        }
        
        #select-all {
            margin-right: 5px;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .table-title {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
        }
        
        .actions-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .actions-btn {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
        }
        
        .actions-btn:hover {
            background-color: #e9ecef;
        }
        
        .actions-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: #fff;
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .actions-content button {
            width: 100%;
            text-align: left;
            padding: 10px 12px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 0.9em;
        }
        
        .actions-content button:hover {
            background-color: #f1f1f1;
        }
        
        .actions-content .btn-danger {
            color: #dc3545;
        }
        
        .show {
            display: block;
        }
    </style>
</head>

<body>

    <div class="container">
        <!-- Page Title -->
        <div class="page-title">
        <i class="fas fa-plus-circle"></i>
            <h1>Manage Products</h1>
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
                <?php if ($result && $result->num_rows > 0): ?>
                <form method="POST" id="products-form">
                    <div class="table-header">
                        <h3 class="table-title">Product Inventory</h3>
                        <div class="select-actions">
                            <label class="select-all-label">
                                <input type="checkbox" id="select-all" onchange="toggleAllCheckboxes()">
                                Select All
                            </label>
                            <span id="selected-count" class="selected-count">(0 selected)</span>
                            
                            <div class="actions-dropdown">
                                <button type="button" class="actions-btn" onclick="toggleActionsMenu()">
                                    <i class="fas fa-ellipsis-v"></i> Actions
                                </button>
                                <div id="actions-menu" class="actions-content">
                                    <button type="button" onclick="showMultiDeleteModal()" id="delete-selected-btn" disabled>
                                        <i class="fas fa-trash"></i> Delete Selected
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th width="5%">Select</th>
                                    <th width="5%">ID</th>
                                    <th width="10%">Image</th>
                                    <th width="15%">Product Name</th>
                                    <th width="10%">Category</th>
                                    <th width="10%">Price (₱)</th>
                                    <th width="8%">Quantity</th>
                                    <th width="10%">Condition</th>
                                    <th width="15%">Image URL</th>
                                    <th width="12%">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $index = 0;
                                while ($row = $result->fetch_assoc()): 
                                ?>
                                    <tr id="product-row-<?= $row['product_id'] ?>">
                                        <td>
                                            <input type="checkbox" name="selected_products[]" value="<?= $row['product_id'] ?>" class="product-checkbox" onchange="updateSelectedCount()">
                                        </td>
                                        <td>
                                            <?= $row['product_id'] ?>
                                            <input type="hidden" name="product_id[<?= $index ?>]" value="<?= $row['product_id'] ?>">
                                        </td>
                                        <td>
                                            <img src="<?= htmlspecialchars($row['image']) ?>" alt="Product" class="product-image">
                                        </td>
                                        <td>
                                            <input type="text" name="name[<?= $index ?>]" value="<?= htmlspecialchars($row['name']) ?>" class="form-control" onchange="markAsModified(this)">
                                        </td>
                                        <td>
                                            <input type="text" name="category[<?= $index ?>]" value="<?= htmlspecialchars($row['category']) ?>" class="form-control" onchange="markAsModified(this)">
                                        </td>
                                        <td>
                                            <input type="number" name="price[<?= $index ?>]" value="<?= $row['price'] ?>" step="0.01" class="form-control input-sm" onchange="markAsModified(this)">
                                        </td>
                                        <td>
                                            <input type="number" name="quantity[<?= $index ?>]" value="<?= $row['quantity'] ?>" class="form-control input-sm" onchange="markAsModified(this)">
                                        </td>
                                        <td>
                                            <select name="condition[<?= $index ?>]" class="form-control" onchange="markAsModified(this)">
                                                <option value="New" <?= ($row['condition'] ?? '') == 'New' ? 'selected' : '' ?>>New</option>
                                                <option value="Like New" <?= ($row['condition'] ?? '') == 'Like New' ? 'selected' : '' ?>>Like New</option>
                                                <option value="Good" <?= ($row['condition'] ?? '') == 'Good' ? 'selected' : '' ?>>Good</option>
                                                <option value="Fair" <?= ($row['condition'] ?? '') == 'Fair' ? 'selected' : '' ?>>Fair</option>
                                                <option value="Poor" <?= ($row['condition'] ?? '') == 'Poor' ? 'selected' : '' ?>>Poor</option>
                                                <option value="Refurbished" <?= ($row['condition'] ?? '') == 'Refurbished' ? 'selected' : '' ?>>Refurbished</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="image[<?= $index ?>]" value="<?= htmlspecialchars($row['image']) ?>" class="form-control" onchange="markAsModified(this)">
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="showDeleteModal(<?= $row['product_id'] ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php 
                                $index++;
                                endwhile; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Floating Save Button -->
                    <button type="submit" name="multi_update" class="btn btn-primary btn-floating">
                        <i class="fas fa-save"></i> Save All Changes
                    </button>
                </form>
                
                <!-- Hidden form for individual delete -->
                <form id="delete-form" method="POST" style="display: none;">
                    <input type="hidden" name="delete_id" id="delete_id">
                    <input type="hidden" name="delete" value="1">
                </form>
                
                <!-- Delete Confirmation Modal -->
                <div id="delete-modal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h2>
                            <span class="close-modal" onclick="closeModal('delete-modal')">&times;</span>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to delete this product? This action cannot be undone.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('delete-modal')">Cancel</button>
                            <button type="button" class="btn btn-danger" onclick="confirmSingleDelete()">Delete Product</button>
                        </div>
                    </div>
                </div>
                
                <!-- Multi-Delete Confirmation Modal -->
                <div id="multi-delete-modal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2><i class="fas fa-exclamation-triangle"></i> Confirm Multiple Deletion</h2>
                            <span class="close-modal" onclick="closeModal('multi-delete-modal')">&times;</span>
                        </div>
                        <div class="modal-body">
                            <p id="multi-delete-message">Are you sure you want to delete the selected products? This action cannot be undone.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('multi-delete-modal')">Cancel</button>
                            <button type="button" class="btn btn-danger" onclick="confirmMultiDelete()">Delete Products</button>
                        </div>
                    </div>
                </div>
                
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No products found in inventory.
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
window.addEventListener('message', function(event) {
    if (event.origin !== window.location.origin) return;
    
    if (event.data.action === 'showPopup') {
        showPopup(event.data.popupId);
    } else if (event.data.action === 'hidePopup') {
        hidePopup(event.data.popupId);
    }
});
        function toggleAllCheckboxes() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.product-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateSelectedCount();
        }
        
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.product-checkbox:checked');
            const countDisplay = document.getElementById('selected-count');
            const deleteBtn = document.getElementById('delete-selected-btn');
            
            countDisplay.textContent = `(${checkboxes.length} selected)`;
            
            if (checkboxes.length > 0) {
                deleteBtn.removeAttribute('disabled');
            } else {
                deleteBtn.setAttribute('disabled', 'disabled');
            }
        }
        
        function markAsModified(element) {
            const row = element.closest('tr');
            row.classList.add('modified-row');
        }
        function showDeleteModal(productId) {
            document.getElementById('delete_id').value = productId;
            document.getElementById('delete-modal').style.display = 'block';
        }
        function showMultiDeleteModal() {
            const checkboxes = document.querySelectorAll('.product-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Please select at least one product to delete.');
                return;
            }
            
            const message = document.getElementById('multi-delete-message');
            message.textContent = `Are you sure you want to delete ${checkboxes.length} selected products? This action cannot be undone.`;
            
            document.getElementById('multi-delete-modal').style.display = 'block';
        }
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function confirmSingleDelete() {
            document.getElementById('delete-form').submit();
            closeModal('delete-modal');
        }
        
        function confirmMultiDelete() {
            const form = document.getElementById('products-form');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'multi_delete';
            input.value = '1';
            form.appendChild(input);
            form.submit();
        }
        
        function toggleActionsMenu() {
            document.getElementById("actions-menu").classList.toggle("show");
        }
        
        window.onclick = function(event) {
            const deleteModal = document.getElementById('delete-modal');
            const multiDeleteModal = document.getElementById('multi-delete-modal');
            const actionsMenu = document.getElementById('actions-menu');
            
            if (event.target === deleteModal) {
                closeModal('delete-modal');
            }
            if (event.target === multiDeleteModal) {
                closeModal('multi-delete-modal');
            }
            
            if (!event.target.matches('.actions-btn') && actionsMenu.classList.contains('show')) {
                actionsMenu.classList.remove('show');
            }
        }
    </script>

</body>

</html>