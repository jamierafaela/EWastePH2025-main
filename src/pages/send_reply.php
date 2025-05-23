<?php
$conn = new mysqli("localhost", "root", "", "ewaste_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message_id = $_GET['message_id'] ?? null;

if (!$message_id) {
    die("Message ID is required.");
}

// Fetch message data
$stmt = $conn->prepare("SELECT name, email, message FROM messages WHERE id = ?");
$stmt->bind_param("i", $message_id);
$stmt->execute();
$result = $stmt->get_result();
$message = $result->fetch_assoc();

if (!$message) {
    die("Message not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reply = $_POST['reply'] ?? '';
    if (!$reply) {
        $error = "Reply message cannot be empty.";
    } else {
        $stmt = $conn->prepare("INSERT INTO replies (message_id, reply_text) VALUES (?, ?)");
        $stmt->bind_param("is", $message_id, $reply);
        $stmt->execute();
        $stmt->close();

        // Update message status to 'Replied'
        $stmt = $conn->prepare("UPDATE messages SET status = 'Replied' WHERE id = ?");
        $stmt->bind_param("i", $message_id);
        $stmt->execute();
        $stmt->close();

        $conn->close();
        header("Location: admin_messages.php");
        exit;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Reply to Message</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f1;
            margin: 0; padding: 20px;
        }
        .container {
            max-width: 600px;
            background: white;
            margin: auto;
            padding: 25px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
        }
        h2 {
            margin-bottom: 15px;
            color: #2e7d32;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: 600;
        }
        textarea {
            width: 100%;
            min-height: 120px;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #ccc;
            resize: vertical;
            font-size: 16px;
        }
        .btn {
            margin-top: 20px;
            background-color: #2e7d32;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #1b5e20;
        }
        .error {
            color: red;
            margin-top: 10px;
        }
        .message-info {
            background-color: #e8f5e9;
            border-left: 6px solid #2e7d32;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        .message-info p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reply to <?php echo htmlspecialchars($message['name']); ?></h2>
        <div class="message-info">
            <p><strong>Email:</strong> <?php echo htmlspecialchars($message['email']); ?></p>
            <p><strong>Original Message:</strong><br><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
        </div>

        <form method="POST" action="">
            <label for="reply">Your Reply:</label>
            <textarea id="reply" name="reply" required><?php echo isset($_POST['reply']) ? htmlspecialchars($_POST['reply']) : ''; ?></textarea>

            <?php if (!empty($error)): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <button type="submit" class="btn">Send Reply</button>
        </form>
    </div>
</body>
</html>
