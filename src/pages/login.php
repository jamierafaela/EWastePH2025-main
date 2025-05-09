<?php
session_start();
include 'db_connect.php';


if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
} else {

    if (time() - $_SESSION['last_attempt_time'] > 3600) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt_time'] = time();
    }
}

if ($_SESSION['login_attempts'] >= 5) {
    $_SESSION['login_error'] = "Too many login attempts. Please try again later.";
    header("Location: ewasteWeb.php#loginSection");
    exit();
}

$_SESSION['login_attempts']++;
$_SESSION['last_attempt_time'] = time();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['full_name'] = $row['full_name'];
            
            $_SESSION['just_logged_in'] = true;
            
            //remember me
            if (isset($_POST['remember_me'])) {
                $token = bin2hex(random_bytes(32));
                $selector = bin2hex(random_bytes(8));
                $expires = time() + 60*60*24*30; // 30 days

                $stmt = $conn->prepare("INSERT INTO auth_tokens (user_id, selector, token, expires) VALUES (?, ?, ?, ?)");
                $hashedToken = password_hash($token, PASSWORD_DEFAULT);
                $stmt->bind_param("issi", $row['user_id'], $selector, $hashedToken, $expires);
                $stmt->execute();

                setcookie("remember", $selector . ':' . $token, $expires, '/', '', false, true);
            }
            
            header("Location: ewasteWeb.php");
            exit();
        } else {
            $_SESSION['login_error'] = "Invalid email or password";
            header("Location: ewasteWeb.php#loginSection");
            exit();
        }
    } else {
        $_SESSION['login_error'] = "Invalid email or password";
        header("Location: ewasteWeb.php#loginSection");
        exit();
    }
}
?>