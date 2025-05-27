

<?php
// 1. CONNECT TO DATABASE
$conn = new mysqli("localhost", "root", "", "ewaste_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/ewasteWeb.php#loginSection");
    exit();
}

$user_id = $_SESSION['user_id'];
$userDetails = [];

$stmt = $conn->prepare("
    SELECT d.full_name, d.phone_number, d.street, d.city, d.province, d.zipcode, u.email
    FROM users u
    JOIN user_details d ON u.user_id = d.user_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $userDetails = $result->fetch_assoc();
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $payment_method = $_POST['payment'] ?? '';
    $email = $_POST['email'] ?? '';
    
    // Validate payment method selection
    if (empty($payment_method)) {
        die("Error: Please select a payment method.");
    }
    
    // For COD orders, verify email verification
    if ($payment_method === 'cod') {
        // Check if COD agreement is checked
        if (!isset($_POST['cod_agreement'])) {
            die("Error: <i>Please agree to the COD terms and conditions.<i/>");
        }
        
        // Check if email verification was completed
        if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
            die("Error: Email verification is required for Cash on Delivery orders. Please complete the verification process.");
        }
        
        // Check if the verified email matches the form email
        if (!isset($_SESSION['otp_email']) || $_SESSION['otp_email'] !== $email) {
            die("Error: Email verification mismatch. Please verify your email address again.");
        }
        
        // Check if verification is still valid (within 30 minutes)
        if (!isset($_SESSION['otp_verified_timestamp']) || 
            (time() - $_SESSION['otp_verified_timestamp']) > 1800) {
            die("Error: Email verification has expired. Please verify your email again.");
        }
        
        // Verification passed - clear verification data
        unset($_SESSION['email_otp']);
        unset($_SESSION['otp_timestamp']);
        unset($_SESSION['otp_email']);
        unset($_SESSION['otp_verified']);
        unset($_SESSION['otp_verified_timestamp']);
        
        // Continue with COD order processing
        echo "COD Order verified and processed successfully!";
        
    } elseif ($payment_method === 'gcash') {
        // Validate GCash fields
        $gcash_number = $_POST['gcashNumber'] ?? '';
        $gcash_name = $_POST['gcashName'] ?? '';
        
        if (empty($gcash_number) || empty($gcash_name)) {
            die("Error: Please fill in all GCash payment details.");
        }
        
        // Check if proof of payment file was uploaded
        if (!isset($_FILES['proofOfPayment']) || $_FILES['proofOfPayment']['error'] !== UPLOAD_ERR_OK) {
            die("Error: Please upload proof of payment for GCash orders.");
        }
        
        // Validate file type and size
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $file_type = $_FILES['proofOfPayment']['type'];
        $file_size = $_FILES['proofOfPayment']['size'];
        
        if (!in_array($file_type, $allowed_types)) {
            die("Error: Invalid file type. Please upload JPG, JPEG, or PNG files only.");
        }
        
        if ($file_size > 5 * 1024 * 1024) { // 5MB limit
            die("Error: File size exceeds 5MB limit.");
        }
        
        // Handle file upload
        $upload_dir = 'uploads/payment_proofs/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['proofOfPayment']['name'], PATHINFO_EXTENSION);
        $new_filename = 'proof_' . time() . '_' . uniqid() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['proofOfPayment']['tmp_name'], $upload_path)) {
            // Continue with GCash order processing
            echo "GCash Order processed successfully!";
        } else {
            die("Error: Failed to upload proof of payment.");
        }
    }
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
</head>
<style>
      .gcash-details, .cod-details {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        .cod-info-box, .gcash-info-box {
            background-color: #f8f9fa;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 12px;
            margin-bottom: 15px;
        }
        
        .cod-info-box h3, .gcash-info-box h3 {
            margin-top: 0;
            color: #2d3748;
        }
         
        .cod-verification {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .verification-option {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .verification-option input[type="checkbox"] {
            margin-top: 5px;
        }
        
        .otp-input-group {
            display: flex;
            gap: 10px;
            margin-top: 5px;
        }
        
        #phone-otp {
            width: 120px;
            letter-spacing: 3px;
            text-align: center;
        }
        
        #otp-status {
            margin-top: 10px;
            padding: 10px;
            border-radius: 5px;
            display: none;
        }
        
        #otp-status.show {
            display: block;
        }
        
        .verification-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.3s ease;
        }
        
        .verification-btn:hover {
            background-color: #45a049;
        }
        
        .verification-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        
        .verification-btn.loading {
            background-color: #2196F3;
            position: relative;
        }
        
        .verification-btn.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin: -10px 0 0 -10px;
            border: 3px solid #ffffff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: button-loading-spinner 1s linear infinite;
        }
        
        @keyframes button-loading-spinner {
            from {
                transform: rotate(0turn);
            }
            to {
                transform: rotate(1turn);
            }
        }
        
        small#phone-validation-message {
            display: block;
            margin-top: 4px;
            font-size: 12px;
        }
        
        .error {
            color: red;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .success {
            color: green;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .file-upload-preview {
            max-width: 200px;
            margin-top: 10px;
            display: none;
        }
</style>
<body>
    <div class="container">
        <div class="checkoutLayout">

            <!-- Return Cart Summary -->
            <div class="returnCart">
                <a href="../pages/ewasteShop.php">Keep shopping</a>
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
                            } else {
                                echo '<div class="item out-of-stock">';
                                echo '<img src="' . $image . '" alt="' . $name . '">';
                                echo '<div class="info">';
                                echo '<div class="name">' . $name . '</div>';
                                echo '<div class="price">P ' . number_format($price, 2) . ' / each</div>';
                                echo '</div>';
                                echo '<div class="quantity">Qty: ' . $quantity . ' (Only ' . $availableStock . ' available)</div>';
                                echo '<div class="returnPrice">P ' . number_format($itemTotal, 2) . '</div>';
                                echo '</div>';
                                
                                $checkoutDisabled = true;
                            }
                        }
                        
                        // If cart is empty after stock checks
                        if ($totalItems === 0) {
                            echo '<div class="empty-cart">No items available in cart or all items are out of stock.</div>';
                            $checkoutDisabled = true;
                        }
                    } else {
                        echo '<div class="empty-cart">Your cart is empty</div>';
                        $checkoutDisabled = true;
                    }
                    ?>

                </div>
            </div>

            <!-- Checkout Form -->
            <div class="right">
                <h1>CHECKOUT</h1>
                <form id="checkoutForm" action="checkout.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="cartData" value='<?php echo urlencode(json_encode($cart)); ?>'>
                    <input type="hidden" id="phone-verification-token" name="phone_verification_token" value="">
                    <div class="form">
                        <div class="group">
                            <label for="full-name">Full Name</label>
                            <input type="text" name="full_name" id="full-name" value="<?php echo isset($userDetails['full_name']) ? 
                            htmlspecialchars($userDetails['full_name']) : ''; ?>" required>
                        </div>
                           <div class="group">
                            <label for="email">Email Address</label>
                            <input type="email" name="email" id="email" value="<?php echo isset($userDetails['email']) ? 
                            htmlspecialchars($userDetails['email']) : ''; ?>" required>
                            <small id="email-validation-message"></small>
                        </div>
                        <div class="group">
                            <label for="phone_number">Phone Number</label>
                            <input type="tel" name="phone_number" id="phone_number" value="<?php echo isset($userDetails['phone_number']) ? 
                            htmlspecialchars($userDetails['phone_number']) : ''; ?>" maxlength="11" oninput="validatePhilippineNumber(this)" required>
                            <small id="phone-validation-message"></small>
                        </div>
                        <div class="group">
                            <label for="street">Street</label>
                            <input type="text" name="street" id="street" value="<?php echo isset($userDetails['street']) ? 
                            htmlspecialchars($userDetails['street']) : ''; ?>" required>
                        </div>
                        <div class="group">
                            <label for="city">City</label>
                            <input type="text" name="city" id="city" value="<?php echo isset($userDetails['city']) ? 
                            htmlspecialchars($userDetails['city']) : ''; ?>" required>
                        </div>
                        <div class="group">
                            <label for="province">Province</label>
                            <input type="text" name="province" id="province" value="<?php echo isset($userDetails['province']) ? 
                            htmlspecialchars($userDetails['province']) : ''; ?>" required>
                        </div>
                        <div class="group">
                            <label for="zipcode">Zipcode</label>
                            <input type="text" name="zipcode" id="zipcode" value="<?php echo isset($userDetails['zipcode']) ? 
                            htmlspecialchars($userDetails['zipcode']) : ''; ?>" maxlength="4" oninput="validateZipCode(this)" required>
                            <small id="zipcode-validation-message"></small>
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
                                    <input type="radio" name="payment" value="cod" onclick="showCodDetails()"> Cash On Delivery
                                </label>
                            </div>

                            <div class="gcash-details" id="gcashDetails" style="display:none;">
                                <div class="gcash-info-box">
                                    <h3>Our GCash Information</h3>
                                    <p>Send payment to: <strong>09XX-XXX-XXXX</strong></p>
                                    <p>Please include your name in the payment reference</p>
                                </div>
                                <label for="gcashNumber">GCash Number:</label>
                                <input type="text" id="gcashNumber" name="gcashNumber" placeholder="Enter your GCash number">
                                <small id="gcash-number-validation"></small>

                                <label for="gcashName">GCash Account Name:</label>
                                <input type="text" id="gcashName" name="gcashName" placeholder="Enter account holder's name">

                                <div class="upload-proof">
                                    <label for="proofOfPayment">Upload Proof of Payment:</label>
                                    <input type="file" id="proofOfPayment" name="proofOfPayment" accept="image/jpeg,image/png,image/jpg">
                                    <small>Accepted formats: JPG, JPEG, PNG (Max 5MB)</small>
                                    <div id="file-error" class="error"></div>
                                    <img id="proof-preview" class="file-upload-preview" alt="Proof preview">
                                </div>
                            </div>
                        
                            <!-- COD Verification Section -->
                            <div class="cod-details" id="codDetails" style="display:none;">
                                <div class="cod-info-box">
                                    <h3>Cash On Delivery Verification</h3>
                                    <p>To confirm your order, we need to verify your email address.</p>
                        </div>
                                
                                <div class="cod-verification">
                                    <div class="verification-option">
                                        <input type="checkbox" id="cod-agreement" name="cod_agreement">
                                        <label for="cod-agreement">I confirm that I will be present at the delivery address and will pay the full amount of P <?php echo number_format($totalPrice, 2); ?> upon delivery.</label>
                    </div>

                                    <div id="otp-section" style="display:none;">
                                        <label for="email-otp">Enter 6-digit code sent to your email:</label>
                                        <div class="otp-input-group">
                                            <input type="text" id="email-otp" name="email_otp" maxlength="6" placeholder="_ _ _ _ _ _">
                                            <button type="button" id="verify-otp-btn">Verify</button>
                                            <button type="button" id="resend-otp-btn">Resend Code</button>
                                        </div>
                                        <div id="otp-status"></div>
                                    </div>
                                    
                                    <button type="button" id="send-verification-btn" class="verification-btn" onclick="console.log('Button clicked directly')">Send Verification Code</button>
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

    <!-- Success Popup -->
                    <div class="popup-overlay" id="successPopup">
                        <div class="popup-container">
                            <div class="popup-title success">Order Placed Successfully!</div>
                            <div class="popup-message">Your order has been submitted and is being processed.</div>
                            <button class="popup-button" onclick="closePopup()">Continue Shopping</button>
                        </div>
                    </div>

    <!-- Email Verification Required Popup -->
        <div class="popup-overlay" id="emailVerificationPopup">
            <div class="popup-container">
                <div class="popup-title">Email Verification Required</div>
                <div class="popup-message">Please complete email verification for Cash on Delivery orders.</div>
                <button class="popup-button" onclick="closeEmailVerificationPopup()">OK</button>
            </div>
        </div>

    <!-- COD Agreement Required Popup -->
        <div class="popup-overlay" id="codAgreementPopup">
            <div class="popup-container">
                <div class="popup-title">COD Agreement Required</div>
                <div class="popup-message">Please agree to the COD terms and conditions to proceed with your order.</div>
                <button class="popup-button" onclick="closeCodAgreementPopup()">OK</button>
            </div>
        </div>

    <script>
    function showGcashDetails() {
    document.getElementById("gcashDetails").style.display = "flex";
}

    function hideGcashDetails() {
        document.getElementById("gcashDetails").style.display = "none";
    }

    function showSuccessPopup() {
        document.getElementById('successPopup').classList.add('show');
    }

    function closePopup() {
        document.getElementById('successPopup').classList.remove('show');
        window.location.href = '../pages/ewasteShop.php';
    }

    function showEmailVerificationPopup() {
    document.getElementById('emailVerificationPopup').classList.add('show');
}

    function closeEmailVerificationPopup() {
        document.getElementById('emailVerificationPopup').classList.remove('show');
        // Scroll to the OTP section after closing popup
        document.getElementById('otp-section').scrollIntoView({ behavior: 'smooth' });
    }

    function showCodAgreementPopup() {
    document.getElementById('codAgreementPopup').classList.add('show');
    }

    function closeCodAgreementPopup() {
        document.getElementById('codAgreementPopup').classList.remove('show');
        // Scroll to the COD agreement checkbox after closing popup
        document.getElementById('cod-agreement').scrollIntoView({ behavior: 'smooth' });
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
    const messageElement = document.getElementById('phone-validation-message');
    if (number.length > 0 && (!number.startsWith('09') || number.length !== 11)) {
        messageElement.textContent = 'Please enter a valid mobile number';
        messageElement.style.color = 'red';
        input.style.borderColor = 'red';
    } else if (number.length === 11) {
        messageElement.style.color = 'green';
        input.style.borderColor = 'green';
    } else {
        messageElement.textContent = 'Format: 09XXXXXXXXX';
        messageElement.style.color = '#666';
        input.style.borderColor = '';
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
    const messageElement = input.nextElementSibling;
    if (zipCode.length > 0 && zipCode.length !== 4) {
        if (!messageElement || messageElement.tagName !== 'SMALL') {
            const small = document.createElement('small');
            small.id = 'zipcode-validation-message';
            input.parentNode.appendChild(small);
        }
        const validationMsg = document.getElementById('zipcode-validation-message') || messageElement;
        validationMsg.textContent = 'ZIP code must be exactly 4 digits';
        validationMsg.style.color = 'red';
        input.style.borderColor = 'red';
    } else if (zipCode.length === 4) {
        const validationMsg = document.getElementById('zipcode-validation-message') || messageElement;
        if (validationMsg) {
            validationMsg.style.color = 'green';
        }
        input.style.borderColor = 'green';
    } else {
        const validationMsg = document.getElementById('zipcode-validation-message') || messageElement;
        if (validationMsg) {
            validationMsg.textContent = 'Format: 4 digits (e.g., 1234)';
            validationMsg.style.color = '#666';
        }
        input.style.borderColor = '';
    }
}

        // GCash number validation
        document.getElementById('gcashNumber').addEventListener('input', function() {
            const gcashNumber = this.value.trim();
            const phoneRegex = /^(09|\+639)\d{9}$/;
            const messageElement = document.getElementById('gcash-number-validation');
            
            if (gcashNumber.length > 0) {
                if (phoneRegex.test(gcashNumber)) {
                    messageElement.textContent = "✓ Valid GCash number";
                    messageElement.style.color = "green";
                    this.setCustomValidity('');
                } else {
                    messageElement.textContent = "× Please enter a valid Philippine phone number for GCash";
                    messageElement.style.color = "red";
                    this.setCustomValidity('Invalid GCash number');
                }
            } else {
                messageElement.textContent = "";
                this.setCustomValidity('');
            }
        });

        // File upload preview and validation
        document.getElementById('proofOfPayment').addEventListener('change', function() {
            const fileInput = this;
            const filePreview = document.getElementById('proof-preview');
            const fileError = document.getElementById('file-error');
            
            fileError.textContent = '';
            filePreview.style.display = 'none';
            
            if (fileInput.files && fileInput.files[0]) {
                const file = fileInput.files[0];
                
                // Check file type
                const validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                if (!validTypes.includes(file.type)) {
                    fileError.textContent = 'Please upload a valid image file (JPG, JPEG, or PNG)';
                    fileInput.value = '';
                    return;
                }
                
                // Check file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    fileError.textContent = 'File size exceeds 5MB limit';
                    fileInput.value = '';
                    return;
        }
        
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    filePreview.src = e.target.result;
                    filePreview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });

        function showGcashDetails() {
            document.getElementById("gcashDetails").style.display = "flex";
            document.getElementById("codDetails").style.display = "none";
        
            // Reset verification fields
            if (document.getElementById("cod-agreement")) {
                document.getElementById("cod-agreement").checked = false;
            }
            if (document.getElementById("otp-section")) {
                document.getElementById("otp-section").style.display = "none";
            }
            document.getElementById("phone-verification-token").value = "";
            
            // Make GCash fields required
            document.getElementById("gcashNumber").required = true;
            document.getElementById("gcashName").required = true;
            document.getElementById("proofOfPayment").required = true;
            
            // Remove COD verification requirement
            const phoneOtp = document.getElementById("phone-otp");
            if (phoneOtp) phoneOtp.required = false;
            
            // Update button state based on valid form
            updateCheckoutButtonState();
        }

        function showCodDetails() {
            document.getElementById("codDetails").style.display = "block";
            document.getElementById("gcashDetails").style.display = "none";
            
            // Make GCash fields not required
            document.getElementById("gcashNumber").required = false;
            document.getElementById("gcashName").required = false;
            document.getElementById("proofOfPayment").required = false;
            
            // Reset GCash fields
            document.getElementById("gcashNumber").value = "";
            document.getElementById("gcashName").value = "";
            document.getElementById("proofOfPayment").value = "";
            document.getElementById("proof-preview").style.display = "none";
            document.getElementById("file-error").textContent = "";
            
            // Update button state based on verification status
            updateCheckoutButtonState();
    }

        // OTP verification
        let otpVerified = false;
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded');
            const sendVerificationBtn = document.getElementById('send-verification-btn');
            const verifyOtpBtn = document.getElementById('verify-otp-btn');
            const resendOtpBtn = document.getElementById('resend-otp-btn');
            
            if (sendVerificationBtn) {
                console.log('Send verification button found');
                sendVerificationBtn.onclick = function() {
                    console.log('Button clicked via onclick');
                    const email = document.getElementById('email').value.trim();
                    console.log('Email value:', email);
                    
                    if (!email) {
                        alert('Please enter your email address first.');
                        return;
                    }
                    
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        alert('Please enter a valid email address.');
                        return;
                    }
                    
                    // Show loading state
                    sendVerificationBtn.disabled = true;
                    sendVerificationBtn.classList.add('loading');
                    sendVerificationBtn.textContent = 'Sending...';
                    
                    const otpStatus = document.getElementById('otp-status');
                    otpStatus.textContent = 'Sending verification code...';
                    otpStatus.style.color = '#2563eb';
                    otpStatus.classList.add('show');
                    
                    console.log('Sending verification request to server...');
                    
                    // Send verification code via AJAX
                    fetch('send_verification.php', {
            method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'email=' + encodeURIComponent(email)
        })
        .then(response => { 
                        console.log('Response status:', response.status);
            if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.status);
            }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Server response:', data);
                        sendVerificationBtn.classList.remove('loading');
                        
                        if (data.success) {
                            document.getElementById('otp-section').style.display = 'block';
                            otpStatus.textContent = data.message;
                            otpStatus.style.color = "#2563eb";
                            sendVerificationBtn.textContent = "Code Sent";
                            
                            // Enable after 30 seconds
                            setTimeout(() => {
                                sendVerificationBtn.disabled = false;
                                sendVerificationBtn.textContent = "Send Verification Code";
                            }, 30000);
                        } else {
                            otpStatus.textContent = data.message || 'Failed to send verification code';
                            otpStatus.style.color = "red";
                            sendVerificationBtn.disabled = false;
                            sendVerificationBtn.textContent = "Send Verification Code";
                        }
                    })
                    .catch(error => {
                        console.error('Error details:', error);
                        sendVerificationBtn.classList.remove('loading');
                        otpStatus.textContent = "An error occurred: " + error.message;
                        otpStatus.style.color = "red";
                        sendVerificationBtn.disabled = false;
                        sendVerificationBtn.textContent = "Send Verification Code";
                    });
                };
                
                sendVerificationBtn.addEventListener('click', function(e) {
                    console.log('Send verification button clicked via addEventListener');
                    e.preventDefault(); // Prevent any default action
                    
                    const email = document.getElementById('email').value.trim();
                    console.log('Email value:', email);
                    
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
                    if (!emailRegex.test(email)) {
                        alert("Please enter a valid email address first.");
                        document.getElementById('email').focus();
                return;
            }
            
                    console.log('Sending verification code to:', email);
                    
                    // Send verification code via AJAX
                    fetch('send_verification.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'email=' + encodeURIComponent(email)
                    })
                    .then(response => {
                        console.log('Response received:', response);
                        return response.json();
        })
        .then(data => {
                        console.log('Data received:', data);
                        if (data.success) {
            document.getElementById('otp-section').style.display = 'block';
                            document.getElementById('otp-status').textContent = data.message;
            document.getElementById('otp-status').style.color = "#2563eb";
            document.getElementById('send-verification-btn').textContent = "Code Sent";
            document.getElementById('send-verification-btn').disabled = true;
            
            // Enable after 30 seconds
            setTimeout(() => {
                document.getElementById('send-verification-btn').disabled = false;
                document.getElementById('send-verification-btn').textContent = "Send Verification Code";
            }, 30000);
                        } else {
                            document.getElementById('otp-status').textContent = data.message;
                            document.getElementById('otp-status').style.color = "red";
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('otp-status').textContent = "An error occurred. Please try again.";
                        document.getElementById('otp-status').style.color = "red";
                    });
                });
            } else {
                console.error('Send verification button not found');
            }
            
            if (verifyOtpBtn) {
                console.log('Verify OTP button found');
                verifyOtpBtn.addEventListener('click', function() {
                    console.log('Verify OTP button clicked');
                    const enteredOtp = document.getElementById('email-otp').value.trim();
            
                    console.log('Verifying OTP:', enteredOtp);
                    
                    // Verify OTP via AJAX
                    fetch('verify_otp.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'otp=' + encodeURIComponent(enteredOtp)
                    })
                    .then(response => {
                        console.log('Response received:', response);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Data received:', data);
                        if (data.success) {
                otpVerified = true;
                            document.getElementById('otp-status').textContent = "✓ Email verified successfully!";
                document.getElementById('otp-status').style.color = "green";
                            document.getElementById('email-otp').disabled = true;
                document.getElementById('verify-otp-btn').disabled = true;
                document.getElementById('resend-otp-btn').disabled = true;
                document.getElementById('send-verification-btn').disabled = true;
                document.getElementById('send-verification-btn').textContent = "Verified";
                            document.getElementById('email-otp').required = false;
                
                // Set the verification token
                document.getElementById('phone-verification-token').value = "verified_" + Date.now();
                
                // Update checkout button state
                updateCheckoutButtonState();
            } else {
                            document.getElementById('otp-status').textContent = data.message;
                            document.getElementById('otp-status').style.color = "red";
            }
        })
        .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('otp-status').textContent = "An error occurred. Please try again.";
                document.getElementById('otp-status').style.color = "red";
                    });
                });
            } else {
                console.error('Verify OTP button not found');
            }
            
            if (resendOtpBtn) {
                console.log('Resend OTP button found');
                resendOtpBtn.addEventListener('click', function() {
                    console.log('Resend OTP button clicked');
                    // Trigger the send verification button click
                    document.getElementById('send-verification-btn').click();
                });
            } else {
                console.error('Resend OTP button not found');
            }
        });
        
        // Update checkout button state based on verification status
        function updateCheckoutButtonState() {
            const checkoutBtn = document.getElementById('checkout-btn');
            if (checkoutBtn.disabled) return; // Don't change if disabled due to stock issues
            
            const paymentMethod = document.querySelector('input[name="payment"]:checked');
            
            if (!paymentMethod) {
                checkoutBtn.disabled = false; // Allow form submission, validation will happen on submit
                return;
            }
            
            if (paymentMethod.value === 'cod') {
                const agreement = document.getElementById('cod-agreement');
                
                if (agreement.checked && otpVerified) {
                    checkoutBtn.disabled = false;
                } else {
                    // Don't disable the button - validation will be handled on submit
                    checkoutBtn.disabled = false;
                }
            } else if (paymentMethod.value === 'gcash') {
                // Don't disable the button - validation will be handled on submit
                checkoutBtn.disabled = false;
            }
        }
        
        // Add event listener for the COD agreement checkbox
        document.getElementById('cod-agreement').addEventListener('change', updateCheckoutButtonState);
        
        // Form submission validation
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    e.preventDefault(); // Always prevent default to handle with AJAX
    
    const paymentMethod = document.querySelector('input[name="payment"]:checked');
    
    if (!paymentMethod) {
        alert("Please select a payment method.");
        return;
    }
    
    if (paymentMethod.value === 'cod') {
        const agreement = document.getElementById('cod-agreement');
        
        if (!agreement.checked) {
        showCodAgreementPopup();
        return;
    }
            
        if (!otpVerified) {
            showEmailVerificationPopup();
            return;
        }
        
        // Check if verification token is set
        if (!document.getElementById('phone-verification-token').value) {
            alert("Email verification failed. Please try again.");
            return;
        }
        
    } else if (paymentMethod.value === 'gcash') {
        const gcashNumber = document.getElementById('gcashNumber').value.trim();
        const gcashName = document.getElementById('gcashName').value.trim();
        const proofFile = document.getElementById('proofOfPayment').files;
        
        if (!gcashNumber) {
            alert("Please enter your GCash number.");
            document.getElementById('gcashNumber').focus();
            return;
        }
        
        if (!gcashName) {
            alert("Please enter your GCash account name.");
            document.getElementById('gcashName').focus();
            return;
        }
        
        if (proofFile.length === 0) {
            alert("Please upload proof of payment for GCash orders.");
            document.getElementById('proofOfPayment').focus();
            return;
        }
    }
    
    // If validation passes, submit via AJAX
    const formData = new FormData(this);
    
    fetch('checkout.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text();
    })
    .then(data => {
        // Check if the response indicates success
        if (data.includes('Order processed successfully') || 
            data.includes('COD Order verified') || 
            data.includes('GCash Order processed')) {
            showSuccessPopup();
        } else {
            // If there's an error, show it or submit normally
            console.log(data);
            alert('Order submission failed. Please try again.');
        }
    })
    .catch(error => {
        console.log('Fetch failed:', error);
        alert('An error occurred. Please try again.');
    });
});

</script>
</body>

</html>