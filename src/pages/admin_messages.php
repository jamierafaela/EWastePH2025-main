<?php
$conn = new mysqli("localhost", "root", "", "ewaste_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$messages = $conn->query("SELECT * FROM messages ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Messages</title>
    <style>
        :root {
            --primary-color: #2e7d32;
            --primary-light: #4caf50;
            --primary-dark: #1b5e20;
            --pending-color: #ff9800;
            --processing-color: #2196f3;
            --shipped-color: #9c27b0;
            --delivered-color: #4caf50;
            --cancelled-color: #f44336;
            --approved-color: #4caf50;
            --rejected-color: #f44336;
            --gray-light: #f0f4f1;
            --gray-medium: #e0e0e0;
            --white: #ffffff;
            --black: #333333;
            --text-dark: #212121;
            --text-light: #ffffff;
            --border-color: #e0e0e0;
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--gray-light);
            padding: 20px;
            color: var(--text-dark);
        }

        .container {
            max-width: 900px;
            margin: auto;
            background: var(--white);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .message-box {
            border-bottom: 1px solid var(--gray-medium);
            padding: 15px 0;
        }

        .row {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .info p {
            margin: 5px 0;
        }

        .copy-btn {
            margin-left: 5px;
            font-size: 12px;
            background: var(--primary-light);
            color: white;
            border: none;
            padding: 2px 6px;
            cursor: pointer;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .copy-btn:hover {
            background: var(--primary-dark);
        }

        .status-dropdown {
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid var(--gray-medium);
            background-color: var(--gray-light);
            font-weight: 500;
            color: var(--text-dark);
        }

        .reply-btn {
            font-size: 20px;
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 10;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background: var(--white);
            margin: 10% auto;
            padding: 20px;
            width: 90%;
            max-width: 500px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        textarea {
            width: 100%;
            height: 100px;
            margin-top: 10px;
            border: 1px solid var(--gray-medium);
            border-radius: var(--border-radius);
            padding: 10px;
        }

        .btn {
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            margin-top: 10px;
            font-weight: bold;
        }

        .btn:hover {
            background: var(--primary-dark);
        }

        .close {
            float: right;
            font-size: 24px;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>User Messages</h2>
    <h3>Quick Reply Templates</h3>
        <ul>
            <li>
                <span>"Thank you for contacting us. We've received your message and will get back to you shortly."</span>
                <button class="copy-btn" onclick="copyText('Thank you for contacting us. We\\'ve received your message and will get back to you shortly.')">Copy</button>
            </li>
            <li>
                <span>"We appreciate your feedback. Your concern has been noted and forwarded to our team."</span>
                <button class="copy-btn" onclick="copyText('We appreciate your feedback. Your concern has been noted and forwarded to our team.')">Copy</button>
            </li>
        </ul>





    <?php while ($row = $messages->fetch_assoc()): ?>
        <div class="message-box">
            <div class="row">
                <div class="info">
                    <p><strong>Name:</strong> <?= htmlspecialchars($row['name']) ?>
                        <button class="copy-btn" onclick="copyText('<?= htmlspecialchars($row['name']) ?>')">Copy</button></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($row['email']) ?>
                        <button class="copy-btn" onclick="copyText('<?= htmlspecialchars($row['email']) ?>')">Copy</button></p>
                    <p><strong>Message:</strong> <?= nl2br(htmlspecialchars($row['message'])) ?>
                        <button class="copy-btn" onclick="copyText(`<?= htmlspecialchars($row['message']) ?>`)">Copy</button></p>
                    <p><strong>Date:</strong> <?= $row['created_at'] ?></p>
                </div>

                <div>
                    <form method="POST" action="update_status.php">
                        <input type="hidden" name="message_id" value="<?= $row['message_id'] ?>">
                        <select name="status" onchange="this.form.submit()" class="status-dropdown">
                            <option value="Pending" <?= $row['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Replied" <?= $row['status'] == 'Replied' ? 'selected' : '' ?>>Replied</option>
                            <option value="Resolved" <?= $row['status'] == 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                            <option value="Ignored" <?= $row['status'] == 'Ignored' ? 'selected' : '' ?>>Ignored</option>
                        </select>
                    </form>

                    <button class="reply-btn" onclick="openModal('<?= $row['email'] ?>', <?= $row['message_id'] ?>)">✉️</button>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
</div>

<!-- Reply Modal -->
<div id="replyModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h3>Send Reply</h3>
        <form method="POST" action="send_reply.php">
            <input type="hidden" id="reply_email" name="to_email">
            <input type="hidden" id="reply_id" name="message_id">
            <textarea name="reply_message" required placeholder="Write your reply..."></textarea>
            <button type="submit" class="btn">Send Reply</button>
        </form>
    </div>
</div>

<script>
    function copyText(text) {
        navigator.clipboard.writeText(text).then(() => alert("Copied!"));
    }

    function openModal(email, id) {
        document.getElementById('reply_email').value = email;
        document.getElementById('reply_id').value = id;
        document.getElementById('replyModal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('replyModal').style.display = 'none';
    }

    window.onclick = function(e) {
        if (e.target == document.getElementById('replyModal')) {
            closeModal();
        }
    };









    
</script>
</body>
</html>
