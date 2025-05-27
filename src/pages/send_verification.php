<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../../PHPMailer/src/Exception.php';
require __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../../PHPMailer/src/SMTP.php';

header('Content-Type: application/json');

// Add error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['email']) || empty(trim($_POST['email']))) {
    echo json_encode(['success' => false, 'message' => 'Email address is required']);
    exit;
}

$email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

try {
    // Generate a 6-digit OTP
    $otp = str_pad(strval(mt_rand(0, 999999)), 6, '0', STR_PAD_LEFT);
    
    // Store in session
    $_SESSION['email_otp'] = $otp;
    $_SESSION['otp_email'] = $email;
    $_SESSION['otp_timestamp'] = time();
    $_SESSION['otp_verified'] = false;
    
    // Debug logging
    error_log("Generated OTP: " . $otp);
    error_log("Email: " . $email);
 
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'luxanniemarie@gmail.com';
    $mail->Password = 'jxwc teba adnj hywp';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    $mail->setFrom('luxanniemarie@gmail.com', 'E-Waste Shop');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Email Verification Code - E-Waste Shop';
    $mail->Body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { text-align: center; margin-bottom: 30px; }
            .otp-code { font-size: 32px; font-weight: bold; color:rgb(9, 103, 0); text-align: center; padding: 20px; background: #f8fafc; border-radius: 8px; margin: 20px 0; letter-spacing: 5px; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='color: #333; margin: 0;'>E-Waste Shop</h1>
                <p style='color: #666; margin: 10px 0 0 0;'>Email Verification Required</p>
            </div>
            
            <p>Hello,</p>
            <p>To complete your Cash on Delivery order, please verify your email address using the code below:</p>
            
            <div class='otp-code'>$otp</div>
            
            <p><strong>Important:</strong></p>
            <ul>
                <li>This code will expire in 10 minutes</li>
                <li>Do not share this code with anyone</li>
                <li>If you didn't request this code, please ignore this email</li>
            </ul>
            
            <div class='footer'>
                <p>This is an automated email from E-Waste Shop. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>";
    
    $mail->AltBody = "Your E-Waste Shop verification code is: $otp\n\nThis code will expire in 10 minutes.\nDo not share this code with anyone.";
    
    $mail->send();
    
    // Debug logging
    error_log("Email sent successfully to: " . $email);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Verification code sent to your email address. Please check your inbox and spam folder.'
    ]);
    
} catch (Exception $e) {
    error_log("Email sending failed: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to send verification email. Please try again or contact support.'
    ]);
}
?>