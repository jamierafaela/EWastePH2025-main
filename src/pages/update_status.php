<?php
$conn = new mysqli("localhost", "root", "", "ewaste_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message_id = $_POST['message_id'];
$status = $_POST['status'];

$stmt = $conn->prepare("UPDATE messages SET status = ? WHERE message_id = ?");
$stmt->bind_param("si", $status, $message_id);
$stmt->execute();
$stmt->close();
$conn->close();

header("Location: admin_messages.php");
exit;
?>
