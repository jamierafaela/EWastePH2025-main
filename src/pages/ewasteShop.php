<?php
$conn = new mysqli("localhost", "root", "", "ewaste_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

session_start();
$logged_in = isset($_SESSION['user_id']);
$user_id = $logged_in ? $_SESSION['user_id'] : 0;

if ($logged_in && isset($_POST['cart_action'])) {
    $action = $_POST['cart_action'];

    error_log("Cart action: $action for user: $user_id");

    if ($action === 'get') {
        $cart_sql = "SELECT c.*, p.name, p.price, p.image, p.quantity as stock 
                    FROM cart_items c 
                    JOIN products p ON c.product_id = p.product_id 
                    WHERE c.user_id = ?";
        $cart_stmt = $conn->prepare($cart_sql);
        $cart_stmt->bind_param("i", $user_id);
        $cart_stmt->execute();
        $cart_result = $cart_stmt->get_result();

        $cart_items = [];
        while ($item = $cart_result->fetch_assoc()) {
            $cart_items[] = [
                'id' => (int)$item['product_id'],
                'name' => $item['name'],
                'price' => (float)$item['price'],
                'image' => $item['image'],
                'quantity' => (int)$item['quantity'],
                'stock' => (int)$item['stock']
            ];
        }

        error_log("Cart items found: " . count($cart_items));
        header('Content-Type: application/json');
        echo json_encode($cart_items);
        exit;
    } else if ($action === 'add' || $action === 'update') {
        $product_id = $_POST['product_id'];
        $quantity = $_POST['quantity'];

        $stock_sql = "SELECT quantity FROM products WHERE product_id = ?";
        $stock_stmt = $conn->prepare($stock_sql);
        $stock_stmt->bind_param("i", $product_id);
        $stock_stmt->execute();
        $stock_result = $stock_stmt->get_result();
        $stock_data = $stock_result->fetch_assoc();
        
        if (!$stock_data || $stock_data['quantity'] < $quantity) {
            echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
            exit;
        }

        $check_sql = "SELECT * FROM cart_items WHERE user_id = ? AND product_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $user_id, $product_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $update_sql = "UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("iii", $quantity, $user_id, $product_id);
            $update_stmt->execute();
        } else {    
            $insert_sql = "INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iii", $user_id, $product_id, $quantity);
            $insert_stmt->execute();
        }

        echo json_encode(['success' => true]);
        exit;
    } else if ($action === 'remove') {
        $product_id = $_POST['product_id'];

        $delete_sql = "DELETE FROM cart_items WHERE user_id = ? AND product_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $user_id, $product_id);
        $delete_stmt->execute();

        echo json_encode(['success' => true]);
        exit;
    }
}

// Get products
$sql = "SELECT * FROM products ORDER BY product_id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>EWastePH SHOP</title>
    <link rel="stylesheet" href="../styles/ewasteShop.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&family=Jersey+10&family=Jersey+25&display=swap" rel="stylesheet">
    <style>

        
        .login-alert {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
            display: none;
        }

        .login-alert a {
            color: #721c24;
            font-weight: bold;
            text-decoration: underline;
        }
        
        
    </style>
</head>

<body>
    <header>
        <form>
            <nav class="navbar">
                <div class="logo-container">
                    <button formaction="../pages/ewasteWeb.php" class="logo"><img src="../../Public/images/logo.png" alt="EWastePH Logo" /></button>
                </div>
                <ul class="nav-links">
                    <li><a href="../pages/ewasteWeb.php#home">Home</a></li>
                    <li><a href="../pages/ewasteWeb.php#about">About Us</a></li>
                    <li><a href="../pages/ewasteWeb.php#faq">FAQ</a></li>
                    <li><a href="../pages/ewasteWeb.php#contact">Contact Us</a></li>
                    <li><a href="../pages/ewasteShop.php">Shop</a></li>
                    <li><a href="<?php echo $logged_in ? '../pages/userdash.php' : '../pages/ewasteWeb.php#loginSection'; ?>"><i class="fa fa-user"></i></a></li>

                </ul>
            </nav>
        </form>
    </header>

    <section id="shop" class="section shop-section">
        <h2>Shop</h2>

        <?php if (!$logged_in): ?>
            <div class="login-alert" id="login-alert" style="display: block;">
                Please <a href="../pages/ewasteWeb.php#loginSection">log in</a> to add items to cart or make a purchase.
            </div>
        <?php endif; ?>

        <div class="shop-header">
            <input type="text" id="search-bar" placeholder="Search products..." class="search-bar" />
            <select id="category-filter" class="category-filter">
                <option value="all">All Categories</option>
                <option value="motherboard">Motherboard</option>
                <option value="processor">Processor</option>
                <option value="ram">RAM</option>
                <option value="keyboard">Keyboard</option>
                <option value="laptop">Laptop</option>
                <option value="monitor">Monitor</option>
                <option value="hdd">HDD/SDD</option>
                <option value="usb">USB</option>
                <option value="batteries">Batteries</option>
                <option value="coolingFans">Cooling Fans</option>
                <option value="smartphones">Smartphones</option>
                <option value="tablets">Tablets</option>
                <option value="player">Player</option>
                <option value="chargers">Chargers</option>
            </select>

            <?php if ($logged_in): ?>
                <div class="iconCart" onclick="toggleCart()">
                    <div class="cartIcon"><i class="fas fa-cart-plus"></i></div>
                    <div class="totalQuantity">0</div>
                </div>
            <?php else: ?>
                <div class="iconCart" onclick="showLoginAlert()">
                    <div class="cartIcon"><i class="fas fa-cart-plus"></i></div>
                    <div class="totalQuantity">0</div>
                </div>
            <?php endif; ?>
        </div>

        <div class="container">
            <div class="new-products">

            </div>
        </div>

        <div class="all-products">
            <h3>All Available Items</h3>
            <div class="product-grid" id="product-list">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <div class="product-card"
                            data-category="<?= htmlspecialchars($row['category']) ?>"
                            data-stock="<?= $row['quantity'] ?>"
                            data-product-id="<?= $row['product_id'] ?>"
                            data-price="<?= $row['price'] ?>"
                            data-name="<?= htmlspecialchars($row['name']) ?>"
                            data-image="<?= htmlspecialchars($row['image']) ?>">
                            <img src="<?= htmlspecialchars($row['image']) ?>" alt="<?= htmlspecialchars($row['name']) ?>">
                            <h3><?= htmlspecialchars($row['name']) ?></h3>
                            <p>P <?= number_format($row['price'], 2) ?></p>
                            <p>Stock: <?= $row['quantity'] ?></p>

                            <?php if ($logged_in): ?>
                                <button class="btn add-to-cart" <?= $row['quantity'] <= 0 ? 'disabled style="background-color: gray; cursor: not-allowed;"' : '' ?>>Add to Cart</button>
                                <button class="btn" <?= $row['quantity'] <= 0 ? 'disabled style="background-color: gray; cursor: not-allowed;"' : '' ?> onclick="<?= $row['quantity'] > 0 ? 'location.href=\'checkout1.php?name=' . urlencode($row['name']) . '&price=' . $row['price'] . '&quantity=1&image=' . urlencode($row['image']) . '\'' : 'return false;' ?>"> Buy</button>
                            <?php else: ?>
                                <button class="btn add-to-cart" onclick="showLoginAlert()">Add to Cart</button>
                                <button class="btn" onclick="showLoginAlert()">Buy</button>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No products available.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Cart Section -->
    <?php if ($logged_in): ?>
        <div class="cart" id="cart">
            <h2>CART</h2>

            <div class="select-all-container">
                <input type="checkbox" id="selectAll" class="cart-item-checkbox">
                <label for="selectAll">Select All</label>
                <div class="selected-total-price">Selected Total: P 0.00</div>
            </div>
            
            <div class="listCart"></div>
            <div id="stockWarning" class="stock-warning"></div>

            <div class="cart-actions">
                <button id="deleteSelectedBtn" class="action-btn disabled">Delete Selected</button>
            </div>
            
            <div class="buttons">
                <div class="close" onclick="toggleCart()">CLOSE</div>
                <div class="checkout" id="checkoutButton">
                    <a id="checkoutLink" href="#">Checkout</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- JavaScript -->
    <script>
        function showLoginAlert() {
            const alert = document.getElementById('login-alert');
            alert.style.display = 'block';

            alert.scrollIntoView({
                behavior: 'smooth'
            });

            setTimeout(function() {
                window.location.href = '../pages/ewasteWeb.php#loginSection';
            }, 1500);
        }

        function toggleCart() {
            const cart = document.querySelector('.cart');
            cart.style.right = cart.style.right === '0px' ? '-100%' : '0px';
        }

        document.addEventListener("DOMContentLoaded", function() {
            const isLoggedIn = <?php echo $logged_in ? 'true' : 'false'; ?>;

            if (!isLoggedIn) {
                return;
            }

            let cart = [];
            const cartContainer = document.querySelector(".listCart");
            const totalQuantity = document.querySelector(".totalQuantity");
            const products = document.querySelectorAll(".product-card");
            const stockWarning = document.getElementById("stockWarning");
            const checkoutButton = document.getElementById("checkoutButton");
            const checkoutLink = document.getElementById("checkoutLink");
            const deleteSelectedBtn = document.getElementById("deleteSelectedBtn");
            const selectAllCheckbox = document.getElementById("selectAll");
            
            loadCartFromServer();

            // Select all checkbox handler
            selectAllCheckbox.addEventListener("change", function() {
                const checkboxes = document.querySelectorAll(".cart-item-checkbox:not(#selectAll)");
                checkboxes.forEach(checkbox => {
        
                    const itemElement = checkbox.closest(".item");
                    const productId = parseInt(itemElement.dataset.productId);
                    const cartItem = cart.find(item => item.id === productId);
                    
                    if (cartItem && cartItem.stock > 0 && cartItem.quantity <= cartItem.stock) {
                        checkbox.checked = selectAllCheckbox.checked;
                    } else if (selectAllCheckbox.checked) {
                        checkbox.checked = false; 
                    }
                });
                updateActionButtons();
            });

            deleteSelectedBtn.addEventListener("click", function() {
                if (this.classList.contains("disabled")) return;
                
                const selectedItems = getSelectedItems();
                if (selectedItems.length === 0) return;

                if (confirm(`Are you sure you want to remove ${selectedItems.length} selected item(s) from your cart?`)) {
                    selectedItems.forEach(productId => {
                        const index = cart.findIndex(item => item.id === productId);
                        if (index !== -1) {
                            updateCartOnServer(productId, 0, 'remove');
                            cart.splice(index, 1);
                        }
                    });
                    
                    updateCart();
                    selectAllCheckbox.checked = false;
                }
            });

            // Filter
            document.getElementById("category-filter").addEventListener("change", function() {
                const selected = this.value;
                products.forEach(product => {
                    if (selected === "all" || product.dataset.category === selected) {
                        product.style.display = "block";
                    } else {
                        product.style.display = "none";
                    }
                });
            });

            // Search bar
            document.getElementById("search-bar").addEventListener("input", function() {
                const searchTerm = this.value.toLowerCase();
                products.forEach(product => {
                    const productName = product.dataset.name.toLowerCase();
                    if (productName.includes(searchTerm)) {
                        product.style.display = "block";
                    } else {
                        product.style.display = "none";
                    }
                });
            });

            // Add to cart 
            document.querySelectorAll(".product-card .btn.add-to-cart").forEach(button => {
                button.addEventListener("click", function() {
                    if (!isLoggedIn) {
                        showLoginAlert();
                        return;
                    }

                    const productCard = button.closest(".product-card");
                    const productId = parseInt(productCard.dataset.productId);
                    const productName = productCard.dataset.name;
                    const productPrice = parseFloat(productCard.dataset.price);
                    const productImage = productCard.dataset.image;
                    const productStock = parseInt(productCard.dataset.stock);
                    
                    if (productStock <= 0) {
                        alert("This item is out of stock.");
                        return;
                    }

                    const existingItem = cart.find(item => item.id === productId);
                    if (existingItem) {
                        if (existingItem.quantity < existingItem.stock) {
                            existingItem.quantity++;
                            updateCartOnServer(productId, existingItem.quantity, 'update');
                        } else {
                            alert(`Cannot add more of this item. Only ${existingItem.stock} available in stock.`);
                        }
                    } else {
                        const newItem = {
                            id: productId,
                            name: productName,
                            price: productPrice,
                            image: productImage,
                            quantity: 1,
                            stock: productStock
                        };
                        cart.push(newItem);
                        updateCartOnServer(productId, 1, 'add');
                    }
                    updateCart();
                });
            });

            function updateCart() {
                cartContainer.innerHTML = "";
                let total = 0;
                let hasOutOfStockItems = false;
                let hasInsufficientStockItems = false;
                let outOfStockNames = [];

                // Display all items in the cart
                cart.forEach((item, index) => {
                    total += item.quantity;

                    if (item.stock <= 0) {
                        hasOutOfStockItems = true;
                        outOfStockNames.push(item.name);
                    } else if (item.quantity > item.stock) {
                        hasInsufficientStockItems = true;
                        outOfStockNames.push(`${item.name} (requested: ${item.quantity}, available: ${item.stock})`);
                    }
                    
                    // item selection
                    const isSelectable = item.stock > 0 && item.quantity <= item.stock;
                    
                    cartContainer.innerHTML += `
                    <div class="item" data-product-id="${item.id}">
                        <input type="checkbox" class="cart-item-checkbox" ${!isSelectable ? 'disabled' : ''}>
                        <div class="item-content-wrapper">
                            <img src="${item.image}" alt="${item.name}">
                            <div class="content">
                                <div class="name">${item.name}</div>
                                <div class="price">P ${item.price.toFixed(2)} / 1 product</div>
                                <div class="stock">Stock: ${item.stock}</div>
                                ${item.stock <= 0 ? '<div class="stock-warning">Out of stock!</div>' : 
                                item.quantity > item.stock ? `<div class="stock-warning">Only ${item.stock} available!</div>` : ''}
                            </div>
                            <div class="quantity">
                                <button class="decrease" data-index="${index}">-</button>
                                <span class="value">${item.quantity}</span>
                                <button class="increase" data-index="${index}" ${item.quantity >= item.stock ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''}>+</button>
                            </div>
                        </div>
                    </div>
                `;
                });

                totalQuantity.innerText = total;
                
                if (hasOutOfStockItems || hasInsufficientStockItems) {
                    stockWarning.innerHTML = "Unable to checkout: " + outOfStockNames.join(", ");
                    checkoutButton.classList.add("disabled");
                    checkoutLink.removeAttribute("href");
                    checkoutLink.style.pointerEvents = "none";
                } else if (cart.length === 0) {
                    stockWarning.innerHTML = "";
                    checkoutButton.classList.add("disabled");
                    checkoutLink.removeAttribute("href");
                    checkoutLink.style.pointerEvents = "none";
                } else {
                    stockWarning.innerHTML = "";
                    checkoutButton.classList.remove("disabled");
                    checkoutLink.style.pointerEvents = "auto";
                    checkoutLink.href = "checkout1.php?cartData=" + encodeURIComponent(JSON.stringify(cart));
                }

                attachEventHandlers();
                updateActionButtons();
            }

            function attachEventHandlers() {
                document.querySelectorAll(".decrease").forEach(button => {
                    button.addEventListener("click", function() {
                        const index = parseInt(button.getAttribute("data-index"));
                        const productId = cart[index].id;

                        if (cart[index].quantity > 1) {
                            cart[index].quantity--;
                            updateCartOnServer(productId, cart[index].quantity, 'update');
                        } else {
                            updateCartOnServer(productId, 0, 'remove');
                            cart.splice(index, 1);
                        }
                        updateCart();
                    });
                });

                document.querySelectorAll(".increase").forEach(button => {
                    button.addEventListener("click", function() {
                        const index = parseInt(button.getAttribute("data-index"));
                        const productId = cart[index].id;

                        if (cart[index].quantity < cart[index].stock) {
                            cart[index].quantity++;
                            updateCartOnServer(productId, cart[index].quantity, 'update');
                            updateCart();
                        } else {
                            alert(`Cannot add more of this item. Only ${cart[index].stock} available in stock.`);
                        }
                    });
                });
                
                document.querySelectorAll(".cart-item-checkbox:not(#selectAll)").forEach(checkbox => {
                    checkbox.addEventListener("change", function() {
                        updateActionButtons();
                        
                        const allCheckboxes = document.querySelectorAll(".cart-item-checkbox:not(#selectAll):not(:disabled)");
                        const checkedCheckboxes = document.querySelectorAll(".cart-item-checkbox:not(#selectAll):checked");
                        
                        if (allCheckboxes.length === checkedCheckboxes.length && allCheckboxes.length > 0) {
                            selectAllCheckbox.checked = true;
                        } else {
                            selectAllCheckbox.checked = false;
                        }
                    });
                });
                
                document.getElementById("checkoutButton").addEventListener("click", function(e) {
                    if (this.classList.contains("disabled")) {
                        e.preventDefault();
                        alert("Unable to checkout due to out of stock or insufficient stock items.");
                        return false;
                    }
                });
            }

            function getSelectedItems() {
                const selectedItems = [];
                document.querySelectorAll(".cart-item-checkbox:not(#selectAll):checked").forEach(checkbox => {
                    const itemElement = checkbox.closest(".item");
                    const productId = parseInt(itemElement.dataset.productId);
                    selectedItems.push(productId);
                });
                return selectedItems;
            }
            
            // Calculate and update the total price of selected items
            function updateSelectedTotal() {
                const selectedItems = getSelectedItems();
                let totalPrice = 0;
                
                selectedItems.forEach(productId => {
                    const item = cart.find(item => item.id === productId);
                    if (item) {
                        totalPrice += item.price * item.quantity;
                    }
                });
                
                document.querySelector('.selected-total-price').textContent = `Selected Total: P ${totalPrice.toFixed(2)}`;
            }
            function updateActionButtons() {
                const selectedItems = getSelectedItems();
                
                if (selectedItems.length > 0) {
                    deleteSelectedBtn.classList.remove("disabled");
                } else {
                    deleteSelectedBtn.classList.add("disabled");
                }
                updateSelectedTotal();
            }

            //load cart from db
            function loadCartFromServer() {
                totalQuantity.innerText = "...";

                fetch('ewasteShop.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'cart_action=get'
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Cart data received:', data);
                        cart = data;
                        updateCart();
                    })
                    .catch(error => {
                        console.error('Error loading cart:', error);
                        totalQuantity.innerText = "!";
                    });
            }

            //update in db
            function updateCartOnServer(productId, quantity, action) {
                fetch('ewasteShop.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `cart_action=${action}&product_id=${productId}&quantity=${quantity}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            console.error('Failed to update cart on server');
                            if (data.message) {
                                alert(data.message);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error updating cart:', error);
                    });
            }

        });

        
    </script>

</body>

</html>
<?php $conn->close(); ?>