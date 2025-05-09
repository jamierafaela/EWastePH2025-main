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