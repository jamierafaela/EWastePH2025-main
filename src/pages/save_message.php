<?php
$conn = new mysqli("localhost", "root", "", "ewaste_db");
if ($conn->connect_error) {
    http_response_code(500);
    exit("Database connection failed.");
}

$name = htmlspecialchars($_POST['name'] ?? '');
$email = htmlspecialchars($_POST['email'] ?? '');
$message = htmlspecialchars($_POST['message'] ?? '');
$created_at = date('Y-m-d H:i:s');

if (!$name || !$email || !$message) {
    http_response_code(400);
    exit("All fields are required.");
}

$stmt = $conn->prepare("INSERT INTO messages (name, email, message, created_at) VALUES (?, ?, ?, ?)");
if (!$stmt) {
    http_response_code(500);
    exit("Statement preparation failed: " . $conn->error);
}

$stmt->bind_param("ssss", $name, $email, $message, $created_at);

if ($stmt->execute()) {
    http_response_code(200);
    echo "Message submitted successfully.";
} else {
    http_response_code(500);
    echo "Error saving message.";
}

$stmt->close();
$conn->close();
?>
