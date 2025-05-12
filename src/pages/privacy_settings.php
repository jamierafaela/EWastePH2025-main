<?php
session_start();

$conn = new mysqli("localhost", "root", "", "ewaste_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$passwordMessage = '';
$passwordMessageType = '';
$emailMessage = '';
$emailMessageType = '';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/ewasteWeb.php#loginSection');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$currentEmail = '';

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    $currentEmail = $user['email'];
}
$stmt->close();

$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'password';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $passwordMessage = 'All fields are required';
        $passwordMessageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $passwordMessage = 'New password and confirmation do not match';
        $passwordMessageType = 'error';
    } elseif (strlen($newPassword) < 8) {
        $passwordMessage = 'Password must be at least 8 characters long';
        $passwordMessageType = 'error';
    } elseif (!preg_match('/[A-Z]/', $newPassword)) {
        $passwordMessage = 'Password must contain at least one uppercase letter';
        $passwordMessageType = 'error';
    } elseif (!preg_match('/[a-z]/', $newPassword)) {
        $passwordMessage = 'Password must contain at least one lowercase letter';
        $passwordMessageType = 'error';
    } elseif (!preg_match('/[0-9]/', $newPassword)) {
        $passwordMessage = 'Password must contain at least one number';
        $passwordMessageType = 'error';
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
                    $passwordMessage = 'Password updated successfully';
                    $passwordMessageType = 'success';
                } else {
                    $passwordMessage = 'Failed to update password: ' . $conn->error;
                    $passwordMessageType = 'error';
                }
                $updateStmt->close();
            } else {
                $passwordMessage = 'Current password is incorrect';
                $passwordMessageType = 'error';
            }
        } else {
            $passwordMessage = 'User not found';
            $passwordMessageType = 'error';
        }
        $stmt->close();
    }
    
    $activeTab = 'password';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_email'])) {
    $password = $_POST['email_password'] ?? '';
    $newEmail = $_POST['new_email'] ?? '';
    $confirmEmail = $_POST['confirm_email'] ?? '';

    if (empty($password) || empty($newEmail) || empty($confirmEmail)) {
        $emailMessage = 'All fields are required';
        $emailMessageType = 'error';
    } elseif ($newEmail !== $confirmEmail) {
        $emailMessage = 'Email addresses do not match';
        $emailMessageType = 'error';
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $emailMessage = 'Please enter a valid email address';
        $emailMessageType = 'error';
    } elseif ($newEmail === $currentEmail) {
        $emailMessage = 'New email is the same as current email';
        $emailMessageType = 'error';
    } else {

        $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $checkStmt->bind_param("si", $newEmail, $user_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $emailMessage = 'This email is already in use by another account';
            $emailMessageType = 'error';
        } else {
 
            $passStmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
            $passStmt->bind_param("i", $user_id);
            $passStmt->execute();
            $passResult = $passStmt->get_result();

            if ($passResult->num_rows === 1) {
                $user = $passResult->fetch_assoc();

                if (password_verify($password, $user['password'])) {
                    // Update the email in db
                    $updateStmt = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ?");
                    $updateStmt->bind_param("si", $newEmail, $user_id);
                    $updateResult = $updateStmt->execute();

                    if ($updateResult) {
                        $emailMessage = 'Email address updated successfully';
                        $emailMessageType = 'success';
                        $currentEmail = $newEmail; // Update displayed email
                    } else {
                        $emailMessage = 'Failed to update email: ' . $conn->error;
                        $emailMessageType = 'error';
                    }
                    $updateStmt->close();
                } else {
                    $emailMessage = 'Password is incorrect';
                    $emailMessageType = 'error';
                }
            } else {
                $emailMessage = 'User not found';
                $emailMessageType = 'error';
            }
            $passStmt->close();
        }
        $checkStmt->close();
    }

    $activeTab = 'email';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color:rgb(57, 141, 61);
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
            --card-bg: #ffffff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
            --input-focus: #e8f5e9;
            --tab-inactive: #e0e0e0;
            --tab-inactive-hover: #d0d0d0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(to bottom, rgba(25, 10, 10, 0.8), rgba(42, 93, 55, 0.7));
            color: var(--text-color);
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            flex-direction: column;

        }

        .back-link {
            display: inline-flex;
            align-items: center;
            color: var(--text-color);
            text-decoration: none;
            background-color: #757575;
            color: white;
            padding: 8px 16px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            align-self: flex-start;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .back-link:hover {
            background-color: #616161;
        }

        .back-link i {
            margin-right: 8px;
        }

        h1 {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 2rem;
            text-align: center;
        }

        .card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow);
            max-width: 700px;
            margin-bottom: 20px;
        }

        .card-header {
            display: flex;
            align-items: center;

        }

        .card-header i {
            font-size: 1.5rem;
            margin-right: 15px;
            color: var(--primary-color);
        }

        .card h2 {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.4rem;
        }

        .card-subtitle {
            color: var(--text-light);
            margin-bottom: 20px;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 22px;
        }

        label {
            display: block;
            margin-bottom: 4px;
            font-weight: 500;
            color: var(--text-color);
        }

        .password-field, .email-field {
            position: relative;
        }

        input[type="password"],
        input[type="text"],
        input[type="email"] {
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

        #match-status, #email-match-status {
            margin-top: 8px;
            font-weight: 500;
        }

        .current-email {
            background-color: var(--input-focus);
            padding: 12px 15px;
            border-radius: var(--border-radius);
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
            font-weight: 500;
        }
        
        .current-email span {
            font-weight: normal;
            margin-left: 5px;
        }

        .header-banner {
            background-color: var(--primary-color);
            color: white;
            padding: 10px;
            text-align: left;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
        }

        .header-banner i {
            font-size: 1.8rem;
            margin-right: 10px;
        }

        .header-banner h1 {
            color: white;
            margin: 0;
            text-align: left;
        }
        .tabs {
            display: flex;
            margin-bottom: 0;
            border-bottom: none;
        }

        .tab-link {
            padding: 10px 25px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            background-color: var(--tab-inactive);
            color: var(--text-color);
            border-top-left-radius: var(--border-radius);
            border-top-right-radius: var(--border-radius);
            margin-right: 5px;
            transition: background-color 0.2s;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .tab-link i {
            margin-right: 8px;
        }

        .tab-link:hover {
            background-color: var(--tab-inactive-hover);
        }

        .tab-link.active {
            background-color: var(--primary-color);
            color: white;
        }

        .tab-content {
            border-top-left-radius: 0;
            margin-top: 0;
            border-top: 5px solid var(--primary-color);
        }
        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <a href="../pages/userdash.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>

            <div class="header-banner">
                <i class="fas fa-user-cog"></i>
                <h1>Account Settings</h1>
            </div>
        </div>

        <!-- Tabs nav -->
        <div class="tabs">
            <a href="?tab=password" class="tab-link <?php echo $activeTab === 'password' ? 'active' : ''; ?>">
                <i class="fas fa-lock"></i> Change Password
            </a>
            <a href="?tab=email" class="tab-link <?php echo $activeTab === 'email' ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i> Change Email
            </a>
        </div>

        <div class="card tab-content">
            <!-- Change Password -->
            <div class="tab-pane <?php echo $activeTab === 'password' ? 'active' : ''; ?>" id="password-tab">
                <div class="card-header">
                    <i class="fas fa-lock"></i>
                    <div>
                        <h2>Change Password</h2>
                        <div class="card-subtitle">Update your password to keep your account secure</div>
                    </div>
                </div>

                <?php if (!empty($passwordMessage) && $activeTab === 'password'): ?>
                    <div class="alert alert-<?php echo $passwordMessageType; ?>">
                        <i class="fas <?php echo $passwordMessageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($passwordMessage); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?tab=password'); ?>" id="password-form">
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
                        <button type="submit" class="btn" id="password-submit-btn" name="change_password" disabled>
                            <i class="fas fa-lock"></i>Update Password
                        </button>
                    </div>
                </form>
            </div>

            <!-- Change Email -->
            <div class="tab-pane <?php echo $activeTab === 'email' ? 'active' : ''; ?>" id="email-tab">
                <div class="card-header">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <h2>Change Email Address</h2>
                        <div class="card-subtitle">Update your email address for account communications</div>
                    </div>
                </div>

                <?php if (!empty($emailMessage) && $activeTab === 'email'): ?>
                    <div class="alert alert-<?php echo $emailMessageType; ?>">
                        <i class="fas <?php echo $emailMessageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($emailMessage); ?>
                    </div>
                <?php endif; ?>

                <div class="current-email">
                    Current Email: <span><?php echo htmlspecialchars($currentEmail); ?></span>
                </div>

                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?tab=email'); ?>" id="email-form">
                    <div class="form-group">
                        <label for="new_email">New Email Address</label>
                        <div class="email-field">
                            <input type="email" id="new_email" name="new_email" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_email">Confirm Email Address</label>
                        <div class="email-field">
                            <input type="email" id="confirm_email" name="confirm_email" required>
                        </div>
                        <p id="email-match-status" class="password-requirements"></p>
                    </div>

                    <div class="form-group">
                        <label for="email_password">Your Password</label>
                        <div class="password-field">
                            <input type="password" id="email_password" name="email_password" required>
                            <button type="button" class="toggle-password" data-target="email_password">
                                <i class="far fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn" id="email-submit-btn" name="change_email" disabled>
                            <i class="fas fa-envelope"></i>Update Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // hide pass
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

        // check pass
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const passwordSubmitBtn = document.getElementById('password-submit-btn');
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

            // Check if passwords match
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
            passwordSubmitBtn.disabled = !valid || !confirmPassword.value || !currentPassword || password !== confirmPassword.value;
        }

        if (newPassword) {
            newPassword.addEventListener('input', validatePassword);
            confirmPassword.addEventListener('input', validatePassword);
            document.getElementById('current_password').addEventListener('input', validatePassword);

            document.getElementById('password-form').addEventListener('submit', function(event) {
                const password = newPassword.value;

                if (password.length < 8 || !(/[A-Z]/.test(password)) || !(/[a-z]/.test(password)) || !(/[0-9]/.test(password))) {
                    alert('Please ensure your password meets all requirements');
                    event.preventDefault();
                } else if (password !== confirmPassword.value) {
                    alert('New password and confirmation do not match');
                    event.preventDefault();
                }
            });
        }

        // Email check
        const newEmail = document.getElementById('new_email');
        const confirmEmail = document.getElementById('confirm_email');
        const emailPassword = document.getElementById('email_password');
        const emailSubmitBtn = document.getElementById('email-submit-btn');
        const emailMatchStatus = document.getElementById('email-match-status');

        function validateEmail() {
            const emailValue = newEmail.value;
            const confirmValue = confirmEmail.value;
            const passwordValue = emailPassword.value;
            
            if (emailValue && confirmValue) {
                if (emailValue === confirmValue) {
                    emailMatchStatus.innerHTML = '<i class="fas fa-check-circle"></i> Email addresses match!';
                    emailMatchStatus.style.color = "var(--success-color)";
                } else {
                    emailMatchStatus.innerHTML = '<i class="fas fa-times-circle"></i> Email addresses do not match!';
                    emailMatchStatus.style.color = "var(--error-color)";
                }
            } else {
                emailMatchStatus.textContent = "";
            }
            const isValidFormat = emailValue && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue);
            
         
            emailSubmitBtn.disabled = !isValidFormat || emailValue !== confirmValue || !passwordValue;
        }

        newEmail.addEventListener('input', validateEmail);
        confirmEmail.addEventListener('input', validateEmail);
        emailPassword.addEventListener('input', validateEmail);

        document.getElementById('email-form').addEventListener('submit', function(event) {
            const emailValue = newEmail.value;
            const confirmValue = confirmEmail.value;
            
            if (!emailValue || !(/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue))) {
                alert('Please enter a valid email address');
                event.preventDefault();
            } else if (emailValue !== confirmValue) {
                alert('Email addresses do not match');
                event.preventDefault();
            }
        });
    </script>
</body>

</html>