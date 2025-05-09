

<?php
$conn = new mysqli("localhost", "root", "", "ewaste_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/ewasteWeb.php#loginSection");
    exit();
}


$sql = "SELECT * FROM products ORDER BY product_id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Page</title>
    <link rel="stylesheet" href="../styles/checkout1.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>

<body>
    <div class="container">
        <div class="checkoutLayout">

            <!-- Return Cart Summary -->
            <div class="returnCart">
                <a href="../pages/ewasteShop.php"><i class="fas fa-shopping-cart"></i> Keep shopping</a>
                <h1>List Product In Cart</h1>
                <div class="list">
                    <!-- PHP Cart -->
                    <?php

                    $cart = [];
                    $totalItems = 0;
                    $totalPrice = 0;
                    $checkoutDisabled = false;

                    if (isset($_GET['cartData'])) {
                        $decodedData = json_decode(urldecode($_GET['cartData']), true);
                        if (is_array($decodedData)) {
                            $cart = $decodedData;
                        }
                    }

                    // Buy
                    if (isset($_GET['name'])) {
                        $cart[] = [
                            'name' => htmlspecialchars($_GET['name']),
                            'price' => floatval($_GET['price']),
                            'quantity' => isset($_GET['quantity']) ? intval($_GET['quantity']) : 1,
                            'image' => htmlspecialchars($_GET['image']),
                        ];
                    }

                    
                    if (!empty($cart)) {
                        foreach ($cart as $item) {
                            $name = htmlspecialchars($item['name']);
                            $price = floatval($item['price']);
                            $quantity = intval($item['quantity']);
                            $image = htmlspecialchars($item['image']);
                            $itemTotal = $price * $quantity;

                           
                            $stmt = $conn->prepare("SELECT quantity FROM products WHERE name = ?");
                            $stmt->bind_param("s", $name);
                            $stmt->execute();
                            $stmt->bind_result($availableStock);
                            $stmt->fetch();
                            $stmt->close();

                            if ($quantity <= $availableStock) {
                                echo '<div class="item">';
                                echo '<img src="' . $image . '" alt="' . $name . '">';
                                echo '<div class="info">';
                                echo '<div class="name">' . $name . '</div>';
                                echo '<div class="price">P ' . number_format($price, 2) . ' / each</div>';
                                echo '</div>';
                                echo '<div class="quantity">Qty: ' . $quantity . '</div>';
                                echo '<div class="returnPrice">P ' . number_format($itemTotal, 2) . '</div>';
                                echo '</div>';

                    
                                

                                $totalItems += $quantity;
                                $totalPrice += $itemTotal;
                            }
                        }
                    }
                    ?>

                </div>
            </div>

            <!-- Checkout Form -->
            <div class="right">
                <h1>CHECKOUT</h1>
                <form action="checkout.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="cartData" value='<?php echo urlencode(json_encode($cart)); ?>'>
                    <div class="form">
                        <div class="group half">
                            <label for="full-name">Full Name</label>
                            <input type="text" name="full_name" id="full-name" required>
                        </div>
                        <div class="group half">
                            <label for="phone-number">Phone Number</label>
                            <input type="text" name="phone_number" id="phone-number" required>
                        </div>
                        <div class="group half">
                            <label for="street">Street</label>
                            <input type="text" name="street" id="street" required>
                        </div>
                        <div class="group half">
                            <label for="city">City</label>
                            <input type="text" name="city" id="city" required>
                        </div>

                        <div class="group half">
                            <label for="province">Province</label>
                            <input type="text" name="province" id="province" required>
                        </div>
                        <div class="group half">
                            <label for="zipcode">Zipcode</label>
                            <input type="text" name="zipcode" id="zipcode" required>
                        </div>
                    </div>


                    <div class="return">
                        <div class="row">
                            <div>Total Quantity</div>
                            <div class="totalQuantity"><?php echo $totalItems; ?></div>
                        </div>
                        <div class="row">
                            <div>Total Price</div>
                            <div class="totalPrice">P <?php echo number_format($totalPrice, 2); ?></div>
                        </div>

                        <!-- Payment Section -->
                        <div class="payment-section">
                            <h2>Select Payment Method</h2>
                            <div class="payment-options">
                                <label>
                                    <input type="radio" name="payment" value="gcash" onclick="showGcashDetails()" required> GCash
                                </label>
                                <label>
                                    <input type="radio" name="payment" value="others" onclick="hideGcashDetails()"> Others
                                </label>
                            </div>
                            <div class="gcash-details" id="gcashDetails" style="display:none;">
                                <label for="gcashNumber">GCash Number:</label>
                                <input type="text" id="gcashNumber" name="gcashNumber" placeholder="Enter your GCash number">

                                <label for="gcashName">GCash Account Name:</label>
                                <input type="text" id="gcashName" name="gcashName" placeholder="Enter account holder's name">

                                <div class="upload-proof">
                                    <label for="proofOfPayment">Upload Proof of Payment:</label>
                                    <input type="file" id="proofOfPayment" name="proofOfPayment">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->

                    <?php if ($checkoutDisabled): ?>
                    
                        <button class="buttonCheckout" type="submit" disabled>Checkout Unavailable -  Your cart exceeds the available stock.</button>
                    <?php else: ?>
                        <button class="buttonCheckout" type="submit">CONFIRM CHECKOUT</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showGcashDetails() {
            document.getElementById("gcashDetails").style.display = "flex";
        }

        function hideGcashDetails() {
            document.getElementById("gcashDetails").style.display = "none";
        }
    </script>
</body>

</html>