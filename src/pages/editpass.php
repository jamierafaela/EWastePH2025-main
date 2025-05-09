<?php
session_start();

$conn = new mysqli("localhost", "root", "", "ewaste_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';
$messageType = '';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/ewasteWeb.php#loginSection');
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = 'All fields are required';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'New password and confirmation do not match';
        $messageType = 'error';
    } elseif (strlen($newPassword) < 8) {
        $message = 'Password must be at least 8 characters long';
        $messageType = 'error';
    } elseif (!preg_match('/[A-Z]/', $newPassword)) {
        $message = 'Password must contain at least one uppercase letter';
        $messageType = 'error';
    } elseif (!preg_match('/[a-z]/', $newPassword)) {
        $message = 'Password must contain at least one lowercase letter';
        $messageType = 'error';
    } elseif (!preg_match('/[0-9]/', $newPassword)) {
        $message = 'Password must contain at least one number';
        $messageType = 'error';
    } else {
        // Get pass from db
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($currentPassword, $user['password'])) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                // Update the pass in db
                $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $updateStmt->bind_param("si", $hashedPassword, $user_id);
                $updateResult = $updateStmt->execute();

                if ($updateResult) {
                    $message = 'Password updated successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update password: ' . $conn->error;
                    $messageType = 'error';
                }
                $updateStmt->close();
            } else {
                $message = 'Current password is incorrect';
                $messageType = 'error';
            }
        } else {
            $message = 'User not found';
            $messageType = 'error';
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2e7d32;
            --primary-hover: #1b5e20;
            --success-color: #4caf50;
            --success-bg: #e8f5e9;
            --error-color: #f44336;
            --error-bg: #ffebee;
            --text-color: #333;
            --text-light: #666;
            --border-color: #e0e0e0;
            --icon-color: #757575;
            --icon-hover: #424242;
            --bg-color: #f9f9f9;
            --card-bg: #ffffff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
            --input-focus: #e8f5e9;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: var(--bg-color);
            color: var(--text-color);
            padding: 30px 20px;
        }

        .container {
            max-width: 500px;
            margin: 0 auto;
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
        }

        h1 {
            color: var(--primary-color);
            margin-bottom: 25px;
            text-align: center;
            font-weight: 600;
            font-size: 1.8rem;
        }

        .form-group {
            margin-bottom: 22px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }

        .password-field {
            position: relative;
        }

        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 16px;
            transition: all 0.2s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--primary-color);
            background-color: var(--input-focus);
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--icon-color);
            font-size: 18px;
            background: none;
            border: none;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }

        .toggle-password:hover {
            color: var(--icon-hover);
        }

        .btn {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            width: 100%;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn:hover {
            background-color: var(--primary-hover);
        }

        .btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            font-weight: 500;
            display: flex;
            align-items: center;
        }

        .alert i {
            margin-right: 10px;
            font-size: 20px;
        }

        .alert-success {
            background-color: var(--success-bg);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-error {
            background-color: var(--error-bg);
            color: var(--error-color);
            border-left: 4px solid var(--error-color);
        }

        .password-requirements {
            margin-top: 12px;
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .requirements-list {
            list-style-type: none;
            padding-left: 5px;
            margin-top: 8px;
        }

        .requirements-list li {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            font-size: 0.85rem;
        }

        .valid {
            color: var(--success-color);
        }

        .valid i,
        .invalid i {
            margin-right: 6px;
            width: 16px;
            text-align: center;
        }

        .invalid {
            color: var(--text-light);
        }

        #match-status {
            margin-top: 8px;
            font-weight: 500;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--primary-color);
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }
        
    </style>
</head>

<body>
    <div class="container">
        <h1>Change Your Password</h1>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <div class="password-field">
                    <input type="password" id="current_password" name="current_password" required>
                    <button type="button" class="toggle-password" data-target="current_password">
                        <i class="far fa-eye-slash"></i>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <div class="password-field">
                    <input type="password" id="new_password" name="new_password" required>
                    <button type="button" class="toggle-password" data-target="new_password">
                        <i class="far fa-eye-slash"></i>
                    </button>
                </div>
                <div class="password-requirements">
                    <p>Password requirements:</p>
                    <ul class="requirements-list">
                        <li id="length" class="invalid"><i class="fas fa-times-circle"></i>At least 8 characters</li>
                        <li id="uppercase" class="invalid"><i class="fas fa-times-circle"></i>At least one uppercase letter</li>
                        <li id="lowercase" class="invalid"><i class="fas fa-times-circle"></i>At least one lowercase letter</li>
                        <li id="number" class="invalid"><i class="fas fa-times-circle"></i>At least one number</li>
                    </ul>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <div class="password-field">
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <button type="button" class="toggle-password" data-target="confirm_password">
                        <i class="far fa-eye-slash"></i>
                    </button>
                </div>
                <p id="match-status" class="password-requirements"></p>
            </div>

            <div class="form-group">
                <button type="submit" class="btn" id="submit-btn" disabled>
                    <i class="fas fa-lock"></i>Update Password
                </button>
            </div>
        </form>

        <a href="../pages/userdash.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <script>
            
        document.querySelectorAll('.toggle-password').forEach(function(toggle) {
            toggle.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
            });
        });

        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('submit-btn');
        const matchStatus = document.getElementById('match-status');

        function validatePassword() {
            const password = newPassword.value;
            let valid = true;

            // Check length
            const lengthElement = document.getElementById('length');
            if (password.length >= 8) {
                lengthElement.className = 'valid';
                lengthElement.querySelector('i').className = 'fas fa-check-circle';
            } else {
                lengthElement.className = 'invalid';
                lengthElement.querySelector('i').className = 'fas fa-times-circle';
                valid = false;
            }

            // Check uppercase
            const uppercaseElement = document.getElementById('uppercase');
            if (/[A-Z]/.test(password)) {
                uppercaseElement.className = 'valid';
                uppercaseElement.querySelector('i').className = 'fas fa-check-circle';
            } else {
                uppercaseElement.className = 'invalid';
                uppercaseElement.querySelector('i').className = 'fas fa-times-circle';
                valid = false;
            }

            // Check lowercase
            const lowercaseElement = document.getElementById('lowercase');
            if (/[a-z]/.test(password)) {
                lowercaseElement.className = 'valid';
                lowercaseElement.querySelector('i').className = 'fas fa-check-circle';
            } else {
                lowercaseElement.className = 'invalid';
                lowercaseElement.querySelector('i').className = 'fas fa-times-circle';
                valid = false;
            }

            // Check number
            const numberElement = document.getElementById('number');
            if (/[0-9]/.test(password)) {
                numberElement.className = 'valid';
                numberElement.querySelector('i').className = 'fas fa-check-circle';
            } else {
                numberElement.className = 'invalid';
                numberElement.querySelector('i').className = 'fas fa-times-circle';
                valid = false;
            }

            // Check if pass match
            if (password && confirmPassword.value) {
                if (password === confirmPassword.value) {
                    matchStatus.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match!';
                    matchStatus.style.color = "var(--success-color)";
                } else {
                    matchStatus.innerHTML = '<i class="fas fa-times-circle"></i> Passwords do not match!';
                    matchStatus.style.color = "var(--error-color)";
                    valid = false;
                }
            } else {
                matchStatus.textContent = "";
            }

            const currentPassword = document.getElementById('current_password').value;
            submitBtn.disabled = !valid || !confirmPassword.value || !currentPassword || password !== confirmPassword.value;
        }

        newPassword.addEventListener('input', validatePassword);
        confirmPassword.addEventListener('input', validatePassword);
        document.getElementById('current_password').addEventListener('input', validatePassword);

        document.querySelector('form').addEventListener('submit', function(event) {
            const password = newPassword.value;

            if (password.length < 8 || !(/[A-Z]/.test(password)) || !(/[a-z]/.test(password)) || !(/[0-9]/.test(password))) {
                alert('Please ensure your password meets all requirements');
                event.preventDefault();
            } else if (password !== confirmPassword.value) {
                alert('New password and confirmation do not match');
                event.preventDefault();
            }
        });
    </script>
</body>

</html>