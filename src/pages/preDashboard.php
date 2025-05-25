<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: ../pages/ewasteWeb.php#Login");
  exit();
}

$user_id = $_SESSION['user_id'];
$profile_completed = false;
$message = "";

$userStmt = $conn->prepare("SELECT u.email, u.profile_completed FROM users u WHERE u.user_id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();

if ($userResult->num_rows > 0) {
  $userData = $userResult->fetch_assoc();
  $default_email = $userData['email'];

  if (isset($userData['profile_completed']) && $userData['profile_completed'] == 1) {
    header("Location: userdash.php");
    exit();
  }
} else {
  $default_email = '';
}

$detailStmt = $conn->prepare("SELECT * FROM user_details WHERE user_id = ?");
$detailStmt->bind_param("i", $user_id);
$detailStmt->execute();
$detailResult = $detailStmt->get_result();
$user_details = null;

if ($detailResult->num_rows > 0) {
  $user_details = $detailResult->fetch_assoc();
  $default_name = $user_details['full_name'];
} else {

  $nameStmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
  $nameStmt->bind_param("i", $user_id);
  $nameStmt->execute();
  $nameResult = $nameStmt->get_result();
  $nameData = $nameResult->fetch_assoc();
  $default_name = $nameData['full_name'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

  if (!isset($_POST['terms_agreed']) || $_POST['terms_agreed'] != '1') {
    $message = "You must agree to the terms and conditions to continue.";
    // To display this message, ensure your HTML part that shows $message is still active
    // or handle this error display appropriately (e.g., re-render form with error).
  } else {
    $full_name = $_POST['full_name'];
    $phone_number = $_POST['phone_number'];
    $street = $_POST['street'];
    $city = $_POST['city'];
    $province = $_POST['province'];
    $zipcode = $_POST['zipcode'];
    $payment_method = $_POST['payment_method'];

    // Server-side validation for phone number
    if (!preg_match("/^09[0-9]{9}$/", $phone_number)) {
        $message = "Invalid phone number format. It must be 11 digits starting with 09.";
    } else {
        $pfp = null;
        if (isset($_FILES['pfp']) && $_FILES['pfp']['error'] == UPLOAD_ERR_OK) {
          $uploadDir = 'uploads/pfp/';

          if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
          }

          $fileExtension = pathinfo($_FILES['pfp']['name'], PATHINFO_EXTENSION);
          $uniqueFilename = uniqid('profile_') . '.' . $fileExtension;
          $pfp = $uploadDir . $uniqueFilename;

          move_uploaded_file($_FILES['pfp']['tmp_name'], $pfp);
        }

        $checkStmt = $conn->prepare("SELECT user_id FROM user_details WHERE user_id = ?");
        $checkStmt->bind_param("i", $user_id);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows > 0) {

          if ($pfp) {
            $updateStmt = $conn->prepare("UPDATE user_details SET full_name = ?, phone_number = ?, 
                                        street = ?, city = ?, province = ?, zipcode = ?, payment_method = ?, pfp = ? 
                                        WHERE user_id = ?");
            $updateStmt->bind_param("ssssssssi", $full_name, $phone_number, $street, $city, $province, $zipcode, $payment_method, $pfp, $user_id);
          } else {
            $updateStmt = $conn->prepare("UPDATE user_details SET full_name = ?, phone_number = ?, 
                                        street = ?, city = ?, province = ?, zipcode = ?, payment_method = ? 
                                        WHERE user_id = ?");
            $updateStmt->bind_param("sssssssi", $full_name, $phone_number, $street, $city, $province, $zipcode, $payment_method, $user_id);
          }
        
          if ($updateStmt->execute()) {
            $profile_completed = true;
            $message = "Profile updated successfully!";

            $updateProfileStatus = $conn->prepare("UPDATE users SET profile_completed = 1 WHERE user_id = ?");
            $updateProfileStatus->bind_param("i", $user_id);
            $updateProfileStatus->execute();

            $_SESSION['profile_success'] = true;
            $_SESSION['profile_message'] = $message;
            header("Location: userdash.php");
            exit();
          } else {
            $message = "Error updating profile: " . $updateStmt->error;
          }
        } else {

          $insertStmt = $conn->prepare("INSERT INTO user_details (user_id, full_name, phone_number, 
                                     street, city, province, zipcode, pfp, payment_method) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
          

          if ($pfp) {
            $insertStmt->bind_param(
              "issssssss",
              $user_id,
              $full_name,
              $phone_number,
              $street,
              $city,
              $province,
              $zipcode,
              $pfp,
              $payment_method
            );
          } else {

            $emptyPfp = "";
            $insertStmt->bind_param(
              "issssssss",
              $user_id,
              $full_name,
              $phone_number,
              $street,
              $city,
              $province,
              $zipcode,
              $emptyPfp,
              $payment_method
            );
          }

          if ($insertStmt->execute()) {
            $profile_completed = true;
            $message = "Profile completed successfully!";

            $updateProfileStatus = $conn->prepare("UPDATE users SET profile_completed = 1 WHERE user_id = ?");
            $updateProfileStatus->bind_param("i", $user_id);
            $updateProfileStatus->execute();

            $_SESSION['profile_success'] = true;
            $_SESSION['profile_message'] = $message;
            header("Location: userdash.php");
            exit();
          } else {
            $message = "Error: " . $insertStmt->error;
          }
        }
    }
  }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile Completion</title>
  <link rel="stylesheet" href="../../src/styles/preUserDash.css">
  <link rel="stylesheet" href="../../src/styles/profileCompletion.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script defer src="../../src/scripts/userDash.js"></script>

  <style>
    /* Terms Agreement Checkbox Component CSS */
    .terms-agreement {
        background: linear-gradient(135deg, #52b569 0%, #4a9c5a 100%);
        padding: 15px; /* Adjusted padding */
        border-radius: 12px;
        margin: 20px 0;
        position: relative;
        overflow: hidden;
    }

    .terms-agreement::before {
        content: \'\';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
        pointer-events: none;
    }

    .checkbox-container {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        position: relative;
        z-index: 1;
    }

    .custom-checkbox {
        position: relative;
        min-width: 24px;
        height: 24px;
        margin-top: 2px;
    }

    .custom-checkbox input[type="checkbox"] {
        opacity: 0;
        position: absolute;
        width: 100%;
        height: 100%;
        cursor: pointer;
        z-index: 2;
    }

    .checkbox-visual {
        width: 24px;
        height: 24px;
        border: 2px solid rgba(255,255,255,0.8);
        border-radius: 4px;
        background: rgba(255,255,255,0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
    }

    .custom-checkbox input[type="checkbox"]:checked + .checkbox-visual {
        background: rgba(255,255,255,0.9);
        border-color: rgba(255,255,255,1);
        transform: scale(1.05);
    }

    .checkbox-visual svg {
        opacity: 0;
        transform: scale(0.5);
        transition: all 0.3s ease;
        color: #52b569;
    }

    .custom-checkbox input[type="checkbox"]:checked + .checkbox-visual svg {
        opacity: 1;
        transform: scale(1);
    }

    .terms-text {
        color: white;
        font-size: 14px; /* Adjusted font size */
        line-height: 1.5;
        flex: 1;
    }

    .terms-link {
        color: rgba(255,255,255,0.9);
        text-decoration: underline;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
    }

    .terms-link:hover {
        color: white;
        text-shadow: 0 0 8px rgba(255,255,255,0.3);
    }

    .company-name {
        font-weight: 600;
        color: rgba(255,255,255,0.95);
    }

    /* Submit button styling for enabled/disabled state */
    .submit-button {
        /* Your existing submit button styles */
        opacity: 0.5;
        /* pointer-events: none; /* This is handled by the disabled attribute */
    }

    .submit-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .submit-button.enabled {
        opacity: 1;
        /* pointer-events: auto; */
        cursor: pointer;
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

    .modal-content-wrapper { /* Updated class name */
        background: white;
        border-radius: 15px;
        width: 90%;
        max-width: 800px;
        max-height: 90vh;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        transform: scale(0.8);
        transition: transform 0.3s ease;
        display: flex; /* Use flex to structure header and body */
        flex-direction: column; /* Stack header and body vertically */
    }

    .modal-overlay.active .modal-content-wrapper { /* Updated class name */
        transform: scale(1);
    }

    .modal-header {
        background: linear-gradient(135deg, #2e7d32, #4caf50);
        color: white;
        padding: 15px 20px; /* Adjusted padding */
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-title {
        font-size: 1.3rem; /* Adjusted font size */
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

    .modal-body-content { /* Updated class name */
        overflow-y: auto; /* Enables scrolling for the body only */
        padding: 0; /* Remove padding here, add to actual-modal-content */
        flex-grow: 1; /* Allows body to take available space */
    }

    .actual-modal-content { /* Updated class name */
        padding: 20px; /* Adjusted padding */
        line-height: 1.6;
        color: #333;
    }

    .actual-modal-content h1 { /* Updated class name */
        color: #65c476;
        font-size: 1.5rem; /* Adjusted font size */
        margin-bottom: 10px;
        text-align: center;
    }

    .actual-modal-content h2 { /* Updated class name */
        color: #65c476;
        font-size: 1.2rem; /* Adjusted font size */
        margin-top: 20px; /* Adjusted margin */
        margin-bottom: 10px; /* Adjusted margin */
        padding-bottom: 8px;
        border-bottom: 2px solid #e9ecef;
    }

    .actual-modal-content h3 { /* Updated class name */
        color:rgb(81, 155, 95);
        font-size: 1.1rem;
        margin-top: 15px; /* Adjusted margin */
        margin-bottom: 10px;
    }

    .actual-modal-content p { /* Updated class name */
        margin-bottom: 10px; /* Adjusted margin */
        text-align: justify;
        margin-left: 30px;
        justify-content: center;
        width: 90%;
    }

    .actual-modal-content ul { /* Updated class name */
        margin: 10px 0; /* Adjusted margin */
        padding-left: 30px;
        justify-content: center;
        width: 90%;
        margin-bottom: 50px; /* Adjusted margin */
    }

    .actual-modal-content li { /* Updated class name */
        margin-bottom: 8px;
        justify-content: center;
        width: 90%;
    }

    .actual-modal-content strong { /* Updated class name */
        color:rgb(103, 103, 103);
    }

    .effective-date {
        text-align: center;
        font-style: italic;
        color: #6c757d;
        margin-bottom: 20px; /* Adjusted margin */
        padding: 10px; /* Adjusted padding */
        background-color: #f8f9fa;
        border-radius: 8px;
    }

    .contact-info {
        background-color: #f8f9fa;
        padding: 15px; /* Adjusted padding */
        border-radius: 8px;
        margin-top: 20px; /* Adjusted margin */
    }

    .final-acknowledgment {
        background: linear-gradient(135deg,rgb(102, 234, 120) 0%,rgb(75, 162, 105) 100%);
        color: white;
        padding: 15px; /* Adjusted padding */
        border-radius: 8px;
        margin-top: 20px; /* Adjusted margin */
        text-align: center;
        font-weight: 500;
    }

    /* Scrollbar Styling */
    .modal-body-content::-webkit-scrollbar { /* Updated class name */
        width: 8px;
    }

    .modal-body-content::-webkit-scrollbar-track { /* Updated class name */
        background:rgb(222, 222, 222);
    }

    .modal-body-content::-webkit-scrollbar-thumb { /* Updated class name */
        background: #65c476;
        border-radius: 4px;
    }

    .modal-body-content::-webkit-scrollbar-thumb:hover { /* Updated class name */
        background: #65c476;
    }

  </style>
</head>

<body>
  <div class="container">
        <div class="left-side">
          <div class="progress-container">
              <svg class="progress-ring" width="120" height="120">
                  <circle class="progress-ring__circle" stroke="yellow" stroke-width="8" fill="transparent" r="52" cx="60" cy="60" />
              </svg>
              <div class="progress-text">70%</div>
          </div>

          <h2>Complete Your Profile</h2>
          <p class="motivational-quote">🌿 "A better planet starts with a better profile!" 🌎</p>
          <div class="floatingIconsCont">
            <div class="floating-icons">
                <img src="../../Public/images/userDesign/plant.png" alt="Leaf Icon" class="floating-icon" />
                <img src="../../Public/images/userDesign/recycle.png" alt="Recycle Icon" class="floating-icon" />
                <img src="../../Public/images/userDesign/battery.webp" alt="Battery Icon" class="floating-icon" />
            </div>
          </div>
      </div>


    <div class="right-side">
      <?php if ($profile_completed): ?>
        <div class='registration-success-container'>
          <div class='registration-success-container1'>
            <div class='success-message'>
              <h2>Profile Updated Successfully!</h2>
              <p>Your profile has been updated and you can now continue to your dashboard.</p>
            </div>
            <a href='userdash.php'>Go to Dashboard</a>
          </div>
        </div>
      <?php else: ?>
        <div class="modal">
          <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data">
            <div class="modal-body">
              
              <p class="instruction-text">Please complete your profile information to continue</p>

              <ul> 
                <li>
                  <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" id="full_name" class="form-control"
                      value="<?php echo htmlspecialchars($default_name); ?>" required>
                  </div>
                </li>

                <li class="form-row">
                  <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" id="email" class="form-control" 
                          value="<?php echo htmlspecialchars($default_email); ?>" readonly 
                          style="color: #888; font-style: italic;">
                    <small class="form-text text-muted">Email cannot be changed</small>
                    <!--<span class="verified-badge" style="color: green; font-style: italic;">(verified)</span>-->
                  </div>


                  <div class="form-group">  
                    <label class="form-label">Phone Number *</label>
                    <input type="tel" name="phone_number" id="phone_number" class="form-control"
                          placeholder="Enter your phone number" required
                          value="<?php echo isset($user_details['phone_number']) ? htmlspecialchars($user_details['phone_number']) : ''; ?>"
                          inputmode="numeric"
                          pattern="09[0-9]{9}"
                          maxlength="11"
                          oninput="validatePhoneNumber(this)">
                    <small class="phone-error-msg" style="color: white;">Format: 09XXXXXXXXX</small>
                  </div>     
                </li>

                <li class="form-row">
                  <div class="form-group">
                    <label class="form-label">Street</label>
                    <input type="text" name="street" class="form-control"
                      placeholder="Enter your street" required
                      value="<?php echo isset($user_details['street']) ? htmlspecialchars($user_details['street']) : ''; ?>">
                  </div>

                  <div class="form-group">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control"
                      placeholder="Enter your city" required
                      value="<?php echo isset($user_details['city']) ? htmlspecialchars($user_details['city']) : ''; ?>">
                  </div>
                </li>

                <li class="form-row">
                  <div class="form-group">
                    <label class="form-label">Province</label>
                    <input type="text" name="province" class="form-control"
                      placeholder="Enter your province" required
                      value="<?php echo isset($user_details['province']) ? htmlspecialchars($user_details['province']) : ''; ?>">
                  </div>

                  <div class="form-group">
                    <label class="form-label">Zipcode</label>
                      <input type="text" name="zipcode" class="form-control"
                            placeholder="Enter your zipcode" required
                            value="<?php echo isset($user_details['zipcode']) ? htmlspecialchars($user_details['zipcode']) : ''; ?>"
                            inputmode="numeric"
                            pattern="[0-9]*">
                 </div>
                </li>

                <li class="form-row">
                  <div class="form-group">
                    <label class="form-label">Profile Picture (optional)</label>
                    <?php if (isset($user_details['pfp']) && !empty($user_details['pfp'])): ?>
                      <div class="current-pfp">
                        <p>Current profile picture: <?php echo basename($user_details['pfp']); ?></p>
                      </div>
                    <?php endif; ?>
                    <input type="file" name="pfp" accept="image/*">
                  </div>
                </li>

                <li>
                  <div class="form-group">
                    <label class="form-label">
                      <i class="fa-solid fa-money-check-dollar"></i> Payment Methods *
                    </label>
                    <select name="payment_method" class="form-control" required>
                      <option value="">--- Select a Method ---</option>
                      <option value="Gcash" <?php echo (isset($user_details['payment_method']) && $user_details['payment_method'] == 'Gcash') ? 'selected' : ''; ?>>Gcash</option>
                      <option value="Card" <?php echo (isset($user_details['payment_method']) && $user_details['payment_method'] == 'Card') ? 'selected' : ''; ?>>Card</option>
                      <option value="Cash-on-delivery" <?php echo (isset($user_details['payment_method']) && $user_details['payment_method'] == 'Cash-on-delivery') ? 'selected' : ''; ?>>Cash-on-delivery</option>
                    </select>
                  </div>
                </li>
                <li>
                  <!-- Terms Agreement Component -->
                  <div class="terms-agreement">
                      <div class="checkbox-container">
                          <div class="custom-checkbox">
                              <input type="checkbox" id="terms-checkbox" name="terms_agreed" value="1" onchange="toggleSubmitButton()">
                              <div class="checkbox-visual">
                                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                      <polyline points="20,6 9,17 4,12"></polyline>
                                  </svg>
                              </div>
                          </div>
                          <div class="terms-text">
                              I accept the <span class="terms-link" onclick="openModal()">terms and conditions</span> of <span class="company-name">E-Waste PH</span>
                          </div>
                      </div>
                  </div>
              </li>
             </ul>

              <div class="form-group">
              </div>

            <div class="modal-footer">
              <p class="required-fields-note">* Required fields</p>
              <button type="reset">Reset</button>
              <button type="submit" name="submit" class="submit-button" id="submit-btn" disabled>Save & Continue</button>
            </div>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Modal -->
  <div class="modal-overlay" id="modalOverlay" onclick="closeModal(event)">
      <div class="modal-content-wrapper" onclick="event.stopPropagation()"> <!-- Renamed class to avoid conflict -->
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
              <button class="close-btn" onclick="closeModal()">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <line x1="18" y1="6" x2="6" y2="18"/>
                      <line x1="6" y1="6" x2="18" y2="18"/>
                  </svg>
              </button>
          </div>
          <div class="modal-body-content"> <!-- Renamed class -->
              <div class="actual-modal-content"> <!-- Renamed class -->
                  <h1>Terms and Conditions</h1>
                  <div class="effective-date">
                      <strong>E-Waste PH</strong><br>
                      <strong>Effective Date:</strong> <?php echo date("F j, Y"); ?><br>
                      <strong>Last Updated:</strong> <?php echo date("F j, Y"); ?>
                  </div>

                  <h2>1. Introduction and Acceptance</h2>
                  <p>Welcome to E-Waste PH ("we," "our," "us"). These Terms and Conditions ("Terms") govern your use of our website and all related services provided by E-Waste PH.</p>
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
                  
                  <h2>6. Data Privacy and Security</h2>
                  <p>We are committed to protecting your privacy. Please review our <span class="terms-link" onclick="openPrivacyPolicyModal()">Privacy Policy</span> for details on how we collect, use, and protect your personal information. By agreeing to these Terms, you also acknowledge and agree to our Privacy Policy.</p>


                  <h2>7. Contact Information</h2>
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

  <script src="../../src/scripts/userDash.js"></script>

<script>
  function toggleSubmitButton() {
      const checkbox = document.getElementById('terms-checkbox');
      const submitBtn = document.getElementById('submit-btn');
      
      if (checkbox.checked) {
          submitBtn.disabled = false;
          submitBtn.classList.add('enabled');
      } else {
          submitBtn.disabled = true;
          submitBtn.classList.remove('enabled');
      }
  }

  function openModal() {
      const modalOverlay = document.getElementById('modalOverlay');
      if (modalOverlay) { // Check if element exists
          modalOverlay.classList.add('active');
          document.body.style.overflow = 'hidden';
      }
  }

  function closeModal(event) {
      const modalOverlay = document.getElementById('modalOverlay');
      // If the click is on the overlay itself (not its children) or on a close button
      if (modalOverlay && ( (event && event.target === modalOverlay) || (event && event.target.closest('.close-btn')) || !event ) ) {
          modalOverlay.classList.remove('active');
          document.body.style.overflow = 'auto';
      }
  }

  // Close modal with Escape key
  document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
          const modalOverlay = document.getElementById('modalOverlay');
          if (modalOverlay && modalOverlay.classList.contains('active')) {
             closeModal(); // Call without event to ensure it closes
          }
      }
  });

  // Call toggleSubmitButton on page load to set initial state of the button, in case the page is reloaded with checkbox checked (e.g. browser back button)
  document.addEventListener('DOMContentLoaded', function() {
    toggleSubmitButton(); 
    // Initialize phone number validation on load if there's a pre-filled value
    const phoneInput = document.getElementById('phone_number');
    if (phoneInput && phoneInput.value) {
        validatePhoneNumber(phoneInput);
    }
  });

  function validatePhoneNumber(input) {
    let number = input.value.replace(/\\D/g, ''); // Remove non-numeric characters
    
    if (number.length > 11) {
        number = number.substring(0, 11); // Limit to 11 digits
    }
    input.value = number; // Update input value

    const errorElement = input.parentElement.querySelector('.phone-error-msg');
    const submitBtn = document.getElementById('submit-btn');

    if (number.length > 0 && (!number.startsWith('09') || number.length !== 11)) {
        if (errorElement) {
            errorElement.textContent = 'Must be 11 digits starting with 09.';
            errorElement.style.color = 'red';
        }
        input.style.borderColor = 'red';
        // Optionally disable submit button if phone number is invalid and other validations pass
        // toggleSubmitButton(); // Re-evaluate submit button state
    } else {
        if (errorElement) {
            errorElement.textContent = 'Format: 09XXXXXXXXX';
            errorElement.style.color = '#666';
        }
        input.style.borderColor = ''; // Reset border color
        // toggleSubmitButton(); // Re-evaluate submit button state
    }
}
</script>
</body>

</html>
