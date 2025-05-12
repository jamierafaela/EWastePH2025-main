<?php
session_start();
include 'db_connect.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userDetails = null;

if ($isLoggedIn) {
    $user_id = $_SESSION['user_id'];

    // Get user details 
    $stmt = $conn->prepare("SELECT * FROM user_details WHERE user_id = ? ORDER BY detail_id DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $userDetails = $result->fetch_assoc();
    }

    //Updates user details
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $update_email = $_POST['update_email'];
        $update_name = $_POST['update_fullname'];
        $update_phone_number = $_POST['update_phone_number'];
        $update_street = $_POST['update_street'];
        $update_city = $_POST['update_city'];
        $update_province = $_POST['update_province'];
        $update_zipcode = $_POST['update_zipcode'];  
        $update_payment_method = $_POST['payment_method'];
    
        // pfp
        if (isset($_FILES['update_pfp']) && $_FILES['update_pfp']['error'] == UPLOAD_ERR_OK) {
            $pfp_tmp_name = $_FILES['update_pfp']['tmp_name'];
            $pfp_name = basename($_FILES['update_pfp']['name']);
            $pfp_path = 'uploads/pfp/' . $pfp_name;
    
            move_uploaded_file($pfp_tmp_name, $pfp_path);
        } else {
            $pfp_path = $userDetails['pfp']; // Keep existing if no new file uploaded
        }
    
        $update_stmt = $conn->prepare("UPDATE user_details SET email = ?, full_name = ?, phone_number = ?, street = ?, city = ?, 
        province = ?, zipcode = ?, pfp = ?, payment_method = ? WHERE user_id = ?");
        $update_stmt->bind_param("sssssssssi", $update_email, $update_name, $update_phone_number, $update_street, $update_city, $update_province, $update_zipcode, $pfp_path, $update_payment_method, $user_id);
    
        if ($update_stmt->execute()) {
            echo "<p style='color: green;'>Profile has been updated successfully!</p>";
            $userDetails['email'] = $update_email;
            $userDetails['full_name'] = $update_name;
            $userDetails['phone_number'] = $update_phone_number;
            $userDetails['street'] = $update_street;
            $userDetails['city'] = $update_city;
            $userDetails['province'] = $update_province;
            $userDetails['zipcode'] = $update_zipcode;
            $userDetails['pfp'] = $pfp_path;
            $userDetails['payment_method'] = $update_payment_method;
        } else {
            echo "<p style='color: red;'>Update failed: " . $update_stmt->error . "</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Settings</title>
    <style>
        /* Reset & Base Styles */
/* Reset & Base Styles */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background-color: #e8f5e9; /* Soft green background */
  color: #333;
  height: 100vh;
  display: flex;
  justify-content: center;
  align-items: center;
}

/* Card-style Container */
.account-settings {
  width: 800px;
  background-color: white;
  padding: 3rem;
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  border-left: 10px solid #2e7d32;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

/* Form Head */
.account-settings h2 {
  text-align: center;
  color: #2e7d32;
  margin-bottom: 1.5rem;
  font-size: 2rem;
}

/* Form layout */
form {
  display: flex;
  flex-direction: column;
}

/* Labels & Inputs */
label {
  font-weight: bold;
  margin-bottom: 0.3rem;
  color: #2e7d32;
}

input[type="text"],
input[type="email"],
input[type="file"],
select {
  padding: 0.7rem;
  font-size: 1rem;
  border: 1px solid #ccc;
  border-radius: 8px;
  margin-bottom: 1rem;
  background-color: #fefefe;
  transition: border-color 0.3s;
}

input[type="text"]:focus,
input[type="email"]:focus,
select:focus {
  border-color: #fbc02d; /* yellow highlight */
  outline: none;
}

/* Profile Picture Styling */
.column img {
  width: 100px;
  height: 100px;
  object-fit: cover;
  border-radius: 50%;
  border: 3px solid #2e7d32;
  margin-top: 0.5rem;
}

.profile-image-placeholder {
  width: 100px;
  height: 100px;
  background-color: #ccc;
  border-radius: 50%;
  font-size: 1.5rem;
  color: white;
  font-weight: bold;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 0.5rem;
}

/* Buttons */
input[type="submit"],
a.btn {
  margin-top: 1rem;
  padding: 0.8rem 1.5rem;
  border: none;
  font-weight: bold;
  font-size: 1rem;
  border-radius: 8px;
  cursor: pointer;
  text-decoration: none;
  transition: background-color 0.3s;
  width: fit-content;
}

input[type="submit"] {
  background-color: #2e7d32;
  color: white;
}

input[type="submit"]:hover {
  background-color: #1b5e20;
}

a.btn {
  background-color: #fbc02d;
  color: #333;
  margin-left: 1rem;
}

a.btn:hover {
  background-color: #f9a825;
}

/* Status Messages */
p {
  font-weight: bold;
  margin-top: 1rem;
  text-align: center;
}

p[style*='color: green'] {
  color: #2e7d32 !important;
}

p[style*='color: red'] {
  color: #c62828 !important;
}

/* Responsive */
@media (max-width: 900px) {
  .account-settings {
    width: 90%;
    padding: 2rem;
  }
}

</style>
</head>
<body>
    <div class="account-settings">
        <?php if ($userDetails): ?>
        <form action="" method="POST" enctype="multipart/form-data">
            <class="inputBox">
             <!--Full Name-->
                <label> Full Name:</label>
                <input type="text" name="update_fullname" value="<?php echo htmlspecialchars($userDetails['full_name']); ?>" 
                class="box">
             <!--Email-->
                <label>Email:</label>
                <input type="email" name="update_email" value="<?php echo htmlspecialchars($userDetails['email']); ?>" 
                class="box">
             <!--Phone Number-->
                <label>Phone Number:</label>
                <input type="text" name="update_phone_number" value="<?php echo htmlspecialchars(
                    $userDetails['phone_number']); ?>" 
                class="box" required>
             <!--Shipping Address-->
                <label>Street:</label>
                <input type="text" name="update_street" value="<?php echo htmlspecialchars(
                    $userDetails['street']); ?>" class="box">
                 <label>City:</label>
                <input type="text" name="update_city" value="<?php echo htmlspecialchars(
                    $userDetails['city']); ?>" class="box">
                 <label>Province:</label>
                <input type="text" name="update_province" value="<?php echo htmlspecialchars(
                    $userDetails['province']); ?>" class="box">
                 <label>Zipcode:</label>
                <input type="text" name="update_zipcode" value="<?php echo htmlspecialchars(
                    $userDetails['zipcode']); ?>" class="box">
            <!--Profile Picture-->
                <div class="column">
                    <label class="form-label">Profile Picture:</label>
                    <input type="file" name="update_pfp" value="<?php echo htmlspecialchars(
                    $userDetails['pfp']); ?>" class="box">

                    <!--Display for Profile Picture-->
                    <?php if (!empty($userDetails['pfp'])): ?>
                        <img src="<?php echo htmlspecialchars($userDetails['pfp']); ?>" alt="Profile Picture" style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                        <div class="profile-image-placeholder">X</div>
                    <?php endif; ?>
                </div>
            <!--Payment Method-->
                <div class="column">
                    <label class="form-label">Payment Methods</label>
                        <select name="payment_method" id="payment_method" class="payment-method">
                        <option value="Gcash" <?php if ($userDetails['payment_method'] == 'Gcash') echo 'selected'; ?>>Gcash</option>
                        <option value="Card" <?php if ($userDetails['payment_method'] == 'Card') echo 'selected'; ?>>Card</option>
                        <option value="Cash-on-delivery" <?php if ($userDetails['payment_method'] == 'Cash-on-delivery') 
                        echo 'selected'; ?>>Cash-on-delivery</option>
                       </select>
                </div>

                <input type="submit" value="Update Profile">

                <a href="userdash.php" class="btn">Back to Dashboard</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>