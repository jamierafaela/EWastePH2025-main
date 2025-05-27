<?php
session_start();

header('Content-Type: application/json');

// Add error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['otp']) || empty(trim($_POST['otp']))) {
    echo json_encode(['success' => false, 'message' => 'OTP code is required']);
    exit;
}

$enteredOtp = trim($_POST['otp']);

// Debug logging
error_log("Entered OTP: " . $enteredOtp);
error_log("Session OTP: " . ($_SESSION['email_otp'] ?? 'not set'));
error_log("Session Timestamp: " . ($_SESSION['otp_timestamp'] ?? 'not set'));

// Check if OTP exists in session
if (!isset($_SESSION['email_otp']) || !isset($_SESSION['otp_timestamp'])) {
    echo json_encode(['success' => false, 'message' => 'No verification code found. Please request a new one.']);
    exit;
}

// Check if OTP has expired (10 minutes = 600 seconds)
$otpAge = time() - $_SESSION['otp_timestamp'];
if ($otpAge > 600) {
    // Clear expired OTP
    unset($_SESSION['email_otp']);
    unset($_SESSION['otp_timestamp']);
    unset($_SESSION['otp_email']);
    
    echo json_encode(['success' => false, 'message' => 'Verification code has expired. Please request a new one.']);
    exit;
}

// Verify OTP - case insensitive comparison and trim whitespace
if (strcasecmp(trim($enteredOtp), trim($_SESSION['email_otp'])) === 0) {
    // Mark as verified
    $_SESSION['otp_verified'] = true;
    $_SESSION['otp_verified_timestamp'] = time();
    
    // Keep the email in session but clear the OTP
    unset($_SESSION['email_otp']);
    unset($_SESSION['otp_timestamp']);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Email verification successful!'
    ]);
} else {
    error_log("OTP Mismatch - Entered: $enteredOtp, Expected: " . $_SESSION['email_otp']);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid verification code. Please check the code and try again.'
    ]);
}
?>