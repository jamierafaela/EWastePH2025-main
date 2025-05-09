<?php
session_start();

$conn = new mysqli("localhost", "root", "", "ewaste_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['signup'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $_SESSION['signup_error'] = "All fields are required";
        header("Location: ewasteWeb.php#signupForm");
        exit();
    }
    
    if ($password !== $confirm_password) {
        $_SESSION['signup_error'] = "Passwords do not match";
        header("Location: ewasteWeb.php#signupForm");
        exit();
    }

    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || 
        !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $_SESSION['signup_error'] = "Password must be at least 8 characters and include uppercase, lowercase, and numbers";
        header("Location: ewasteWeb.php#signupForm");
        exit();
    }

    $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['signup_error'] = "Email already exists. Please use a different email or log in.";
        header("Location: ewasteWeb.php#signupForm");
        exit();
    }
    
   
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $full_name, $email, $hashed_password);
    
    if ($stmt->execute()) {

        $user_id = $conn->insert_id;
        

        $_SESSION['user_id'] = $user_id;
        $_SESSION['full_name'] = $full_name;
        $_SESSION['just_logged_in'] = true;
        
        header("Location: predashboard.php");
        exit();
    } else {
        $_SESSION['signup_error'] = "Registration failed: " . $conn->error;
        header("Location: ewasteWeb.php#signupForm");
        exit();
    }
    
    $stmt->close();
}

$conn->close();
?>