<?php
// File: pages/resend_reset_code.php - Handles resending the password reset code.
session_start();

// === Dependencies ===
require_once '../vendor/autoload.php'; 
require_once '../classes/Login.php'; 
require_once '../classes/Mailer.php'; 
// ====================

// --- COOLDOWN SETTINGS ---
// Time in seconds to wait before allowing another code request.
define('RESEND_COOLDOWN_SECONDS', 60); 
// Session key used to store the last request time for this user.
define('RESEND_SESSION_KEY', 'last_resend_time');
// -------------------------

$email = $_GET['email'] ?? ''; 
$email = filter_var($email, FILTER_SANITIZE_EMAIL);

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: forgot_password.php");
    exit;
}

// 1. --- COOLDOWN CHECK ---
if (isset($_SESSION[RESEND_SESSION_KEY])) {
    $last_resend_time = $_SESSION[RESEND_SESSION_KEY];
    $time_since_last_resend = time() - $last_resend_time;
    
    if ($time_since_last_resend < RESEND_COOLDOWN_SECONDS) {
        $wait_time = RESEND_COOLDOWN_SECONDS - $time_since_last_resend;

        // Set a warning flash message and redirect back to the verification page
        $_SESSION['flash_message'] = [
            'type' => 'warning', 
            'content' => "⚠️ **Please wait!** You must wait " . $wait_time . " more seconds before requesting a new code."
        ];
        header("Location: reset_password.php?email=" . urlencode($email));
        exit;
    }
}
// -------------------------

// If we reach here, the cooldown has expired or this is the first request.

$login_handler = new Login();
$mailer = new Mailer(); 

// Request a NEW code/token for this email (assumes Login class handles DB update)
$new_code = $login_handler->forgotPasswordAndGetLink($email);

if (is_string($new_code) && strlen($new_code) === 6) {
    $email_sent = $mailer->sendResetCodeEmail($email, $new_code);

    if ($email_sent) {
        // 2. --- SET NEW COOLDOWN TIMESTAMP ON SUCCESS ---
        $_SESSION[RESEND_SESSION_KEY] = time(); 
        // ------------------------------------------------

        $_SESSION['flash_message'] = [
            'type' => 'success', 
            'content' => "✅ **New Code Sent!** A fresh 6-digit code has been successfully sent to **" . htmlspecialchars($email) . "**."
        ];
    } else {
        $_SESSION['flash_message'] = [
            'type' => 'error', 
            'content' => "❌ Failed to send new code. Please try again. (Mailer error: " . $mailer->getError() . ")"
        ];
    }
} else {
    // Generic failure message for security
    $_SESSION['flash_message'] = [
        'type' => 'security', // or 'info' or 'warning'
        'content' => "If the email is valid, a new code has been requested and sent. Please check your inbox and spam folder."
    ];
}

// Redirect the user back to the code entry page
header("Location: reset_password.php?email=" . urlencode($email));
exit;
?>