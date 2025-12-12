<?php
// pages/verify.php (REWRITTEN for 6-digit code entry)
session_start();
require_once '../classes/Student.php'; 

$error = '';
$message = '';
$email = $_GET['email'] ?? ''; 
$code = '';

// Handle flash message from signup.php
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['content'];
    $alert_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $code = filter_input(INPUT_POST, 'code', FILTER_SANITIZE_NUMBER_INT);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (empty($code) || strlen($code) !== 6) {
        $error = "Please enter the 6-digit code.";
    } else {
        $student_db = new Student();
        
        // Use the new verification method from Student.php
        if ($student_db->verifyStudentAccountByCode($email, $code)) {
            // Success: redirect to login
            $_SESSION['flash_message'] = ['type' => 'success', 'content' => "✅ Success! Your account has been verified. You may now log in."];
            header("Location: login.php");
            exit;
        } else {
            $error = "❌ Invalid verification code or email. Please check your input.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Account - CSM Borrowing</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f8f9fa; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background-color: #fff; padding: 40px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 100%; max-width: 450px; text-align: center; }
        .app-title { color: #8B0000; font-size: 1.2em; font-weight: bold; line-height: 1.4; margin-bottom: 25px; }
        .form-group { margin-bottom: 20px; text-align: left; }
        input[type="email"], input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 5px; box-sizing: border-box; font-size: 1.1em;}
        .code-input { text-align: center; font-size: 20px; letter-spacing: 5px; }
        .btn-submit { width: 100%; padding: 10px; background-color: #8B0000; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1em; transition: background-color 0.3s; }
        .btn-submit:hover { background-color: #6a0000; }
        .alert-error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .alert-warning { color: #856404; background-color: #fff3cd; border-color: #ffeeba; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="app-title">
            CSM LABORATORY<br>
            ACCOUNT VERIFICATION
        </div>

        <h2 style="font-size: 1.5em; margin-bottom: 10px;">Enter Verification Code</h2>
        <p style="margin-bottom: 25px; font-size: 0.95em;">A 6-digit code was sent to **<?= htmlspecialchars($email) ?>**.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
            <div class="alert alert-<?= htmlspecialchars($alert_type) ?>"><?= $message ?></div>
        <?php endif; ?>

        <form action="verify.php" method="POST">
            <div class="form-group">
                <label for="email" style="display: none;">Email (for reference):</label>
                <input type="email" id="email_display" name="email" value="<?= htmlspecialchars($email) ?>" readonly style="background-color: #eee;">
            </div>
            
            <div class="form-group">
                <label for="code">6-Digit Code:</label>
                <input type="text" id="code" name="code" class="code-input" maxlength="6" required placeholder="Enter Code">
            </div>
            
            <button type="submit" class="btn-submit">Verify Account</button>
        </form>

        <p style="margin-top: 15px; font-size: 0.9em;">
            <a href="forgot_password.php" style="color: #8B0000; text-decoration: none;">Request a new code</a> (or check for typos in the email you signed up with)
        </p>
    </div>
</body>
</html>