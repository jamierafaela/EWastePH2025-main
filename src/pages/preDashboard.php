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

  $full_name = $_POST['full_name'];

  $phone_number = $_POST['phone_number'];
  $street = $_POST['street'];
  $city = $_POST['city'];
  $province = $_POST['province'];
  $zipcode = $_POST['zipcode'];
  $payment_method = $_POST['payment_method'];

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

              <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" id="full_name" class="form-control"
                  value="<?php echo htmlspecialchars($default_name); ?>" required>
              </div>

              <div class="form-group">
                <label class="form-label">Email Address *</label>
                <input type="email" id="email" class="form-control"
                  value="<?php echo htmlspecialchars($default_email); ?>" readonly>
                <small class="form-text text-muted">Email cannot be changed</small>
                <span class="verified-badge">(verified)</span>
              </div>

              <div class="form-group">
                <label class="form-label">Phone Number *</label>
                <input type="tel" name="phone_number" class="form-control"
                  placeholder="Enter your phone number" required
                  value="<?php echo isset($user_details['phone_number']) ? htmlspecialchars($user_details['phone_number']) : ''; ?>">
              </div>

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
                  value="<?php echo isset($user_details['zipcode']) ? htmlspecialchars($user_details['zipcode']) : ''; ?>">
              </div>

              <div class="form-group">
                <label class="form-label">Profile Picture (optional)</label>
                <?php if (isset($user_details['pfp']) && !empty($user_details['pfp'])): ?>
                  <div class="current-pfp">
                    <p>Current profile picture: <?php echo basename($user_details['pfp']); ?></p>
                  </div>
                <?php endif; ?>
                <input type="file" name="pfp" accept="image/*">
              </div>

              <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-money-check-dollar"></i> Payment Methods *</label>
                <select name="payment_method" required>
                  <option value="">--- Select a Method ---</option>
                  <option value="Gcash" <?php echo (isset($user_details['payment_method']) && $user_details['payment_method'] == 'Gcash') ? 'selected' : ''; ?>>Gcash</option>
                  <option value="Card" <?php echo (isset($user_details['payment_method']) && $user_details['payment_method'] == 'Card') ? 'selected' : ''; ?>>Card</option>
                  <option value="Cash-on-delivery" <?php echo (isset($user_details['payment_method']) && $user_details['payment_method'] == 'Cash-on-delivery') ? 'selected' : ''; ?>>Cash-on-delivery</option>
                </select>
              </div>

            </div>

            <div class="modal-footer">
              <p class="required-fields-note">* Required fields</p>
              <button type="reset">Reset</button>
              <button type="submit" name="submit" class="submit-button">Save & Continue</button>
            </div>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>




  <script src="../../src/scripts/userDash.js"></script>

</script>
</body>

</html>
