<?php
// pages/forgot_password.php
session_start();

// === Dependencies ===
// Assuming 'vendor' is in the project root (../)
require_once '../vendor/autoload.php'; 
// The path below assumes your 'Login.php' is in classes/ relative to the parent directory.
require_once '../classes/Login.php'; 
require_once '../classes/Mailer.php'; 
// ====================

$error = '';
// $security_info is kept for logic consistency but will no longer be displayed directly.
$security_info = ''; 
$email = $_POST['email'] ?? ''; // Variable to store the entered email

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate email input
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $login_handler = new Login();
        
        // Result is the 6-digit code on success
        // This must be handled securely in the Login class (new code, updated timestamp).
        $code = $login_handler->forgotPasswordAndGetLink($email);

        // Check if a code was successfully generated (meaning the email exists)
        if (is_string($code) && strlen($code) === 6) {
            
            $mailer = new Mailer(); 
            $email_sent = $mailer->sendResetCodeEmail($email, $code);

            if ($email_sent) {
                // === START CHANGE: Set flash message for success display on next page ===
                $_SESSION['flash_message'] = [
                    'type' => 'info',
                    'content' => "✅ A 6-digit verification code has been successfully sent to your email. Please check your inbox and spam folder."
                ];
                // === END CHANGE ===
                
                // SUCCESS: Redirect the user immediately to the reset page
                header("Location: reset_password.php?email=" . urlencode($email));
                exit;
            } else {
                // Should only happen if the mail server or Mailer class fails
                $error = "Failed to send the reset code email. Please try again. Mailer error: " . $mailer->getError(); 
            }
        } else {
            // SECURITY MESSAGE: This message is shown even if the email doesn't exist 
            // to prevent potential hackers from enumerating valid emails.
            $security_info = "If an account with that email exists, a password reset code has been sent. Please check your inbox and spam folder.";
            
            // === START CHANGE: Set generic flash message and redirect ===
            $_SESSION['flash_message'] = [
                'type' => 'security', // Using 'security' type for custom styling if needed
                'content' => $security_info
            ];
            header("Location: reset_password.php?email=" . urlencode($email));
            exit;
            // === END CHANGE ===
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - CSM Borrowing</title>
    <style>
        /* General Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            
            /* === START BACKGROUND IMAGE FIX === */
            background: 
                linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), /* Dark overlay for contrast */
                url("../uploads/Western_Mindanao_State_University_College_of_Teacher_Education_(Normal_Road,_Baliwasan,_Zamboanga_City;_10-06-2023).jpg") 
                no-repeat center center fixed; /* Center, cover viewport, fixed position */
            background-size: cover;
            /* === END BACKGROUND IMAGE FIX === */

            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        /* Card Container - Matching Login Card */
        .login-card {
            background-color: #fff;
            padding: 40px;
            border-radius: 8px; /* Slightly less rounded than the original CSS */
            /* Adjusted box-shadow for contrast against dark background */
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3); 
            width: 100%;
            max-width: 400px; /* Slightly narrower to match the login screen proportions */
            text-align: center;
            z-index: 10;
        }

        /* Logo and Title */
        .logo {
            /* FIX: Assuming wmsu_logo is parallel to uploads/ and classes/ */
            width: 80px;
            margin-bottom: 10px;
        }
        .app-title {
            /* Consistent WMSU red */
            color: #8B0000; 
            font-size: 1.1em;
            font-weight: 500; /* Slightly lighter weight */
            line-height: 1.3;
            margin-bottom: 30px;
            text-transform: uppercase;
        }
        .main-heading {
            font-size: 1.75em;
            margin-bottom: 15px;
            color: #333;
        }
        .instruction-text {
            margin-bottom: 30px;
            font-size: 0.9em; /* Slightly smaller text */
            color: #666; /* Slightly darker text for contrast */
            line-height: 1.5;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 25px;
            text-align: left;
        }
        label {
            display: block;
            font-weight: 500; /* Medium weight, less bold */
            margin-bottom: 5px; /* Less space between label and input */
            color: #333;
            font-size: 0.95em;
        }
        input[type="email"] {
            width: 100%;
            padding: 10px 12px; /* Slightly less padding */
            border: 1px solid #ccc; /* Lighter border color */
            border-radius: 4px; /* Slightly less rounded inputs */
            box-sizing: border-box;
            font-size: 1em;
            transition: border-color 0.2s;
        }
        input[type="email"]:focus {
            /* Keep focus color consistent with the theme */
            border-color: #8B0000;
            outline: none;
            box-shadow: 0 0 0 1px #8B0000; /* More subtle focus ring */
        }
        /* Placeholder styling to match the login screen */
        input::placeholder {
            color: #aaa;
        }

        /* Button - Matching Login Button Style */
        .btn-submit {
            width: 100%;
            padding: 12px;
            background-color: #8B0000; /* WMSU Red */
            color: white;
            border: none;
            border-radius: 6px; 
            cursor: pointer;
            font-size: 1.05em;
            font-weight: 600; /* Bold text for the button */
            text-transform: capitalize; /* Consistent with the login button's look */
            transition: background-color 0.3s, box-shadow 0.2s;
            margin-top: 10px; /* Added a little margin on top */
        }
        .btn-submit:hover {
            background-color: #6a0000;
        }
        .btn-submit:active {
            transform: translateY(0); /* Removed the scale effect for consistency */
            box-shadow: 0 0 0 0;
        }

        /* Alerts */
        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: left;
            font-size: 0.9em;
        }
        .alert-error {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        .alert-security {
            color: #004085;
            background-color: #cce5ff;
            border: 1px solid #b8daff;
        }

        /* Back Link - Matching the small links on the login screen */
        .back-link {
            display: inline-block; 
            margin-top: 25px;
            color: #8B0000; /* Consistent red color */
            text-decoration: none;
            font-size: 0.9em; 
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <img src="../wmsu_logo/wmsu.png" alt="WMSU Logo" class="logo"> 
        
        <div class="app-title">
            CSM LABORATORY<br>
            BORROWING APPARATUS
        </div>

        <h2 class="main-heading">Forgot Your Password?</h2>
        <p class="instruction-text">
            Enter the email address associated with your account, and we will send a 6-digit verification code to reset your password.
        </p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="forgot_password.php" method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required 
                        placeholder="e.g. student@gmail.com"
                        value="<?php echo htmlspecialchars($email); ?>">
            </div>
            
            <button type="submit" class="btn-submit">
                Request Password Reset Code
            </button>
        </form>
        
        <a href="login.php" class="back-link">Back to Login</a>
    </div>
</body>
</html>