<?php
$host = "localhost";
$db = "ewaste_db";
$user = "root";
$pass = ""; // Leave blank unless you set a MySQL password

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$name = $_POST['name'];
$email = $_POST['email'];
$message = $_POST['message'];
$created_at = date("Y-m-d H:i:s");

$sql = "INSERT INTO messages (name, email, message, created_at) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $name, $email, $message, $created_at);

if ($stmt->execute()) {
    echo "Message sent successfully. <a href='index.html'>Back</a>";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
