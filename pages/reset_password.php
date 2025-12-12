<?php
// File: pages/reset_password.php 
session_start();
// Define the token expiry time for UX display
define('TOKEN_EXPIRY_MINUTES', 10); 

require_once '../classes/Login.php'; 

// === START: FLASH MESSAGE HANDLING ADDITION ===
// Retrieves flash message set by forgot_password.php or resend_reset_code.php
$flash_message = null;
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
// === END: FLASH MESSAGE HANDLING ADDITION ===

$message = '';
$error = '';

$email_from_get = $_GET['email'] ?? ''; 
$code_from_get = $_GET['code'] ?? ''; 

$is_code_validated = false;
$user_id = 0;
$login_handler = new Login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email_from_post = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    if (!empty($email_from_post)) {
        $email_from_get = $email_from_post;
    }
}


if (empty($email_from_get)) {
    header("Location: forgot_password.php");
    exit;
}

// Case A: Code is submitted via form (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'validate_code') {
    // Note: JS handles filtering, but PHP must sanitize and validate final submission
    $code_to_validate = filter_input(INPUT_POST, 'code', FILTER_SANITIZE_NUMBER_INT);
    $code_from_get = $code_to_validate; 
    
    if (empty($code_to_validate) || strlen($code_to_validate) !== 6) {
        $error = "Please enter the 6-digit code.";
    } elseif (empty($error)) {
        $user_id = $login_handler->validateResetToken($email_from_get, $code_to_validate);
        
        if ($user_id) {
            header("Location: reset_password.php?email=" . urlencode($email_from_get) . "&code=" . urlencode($code_to_validate));
            exit;
        } else {
            $error = "The reset code is invalid or has expired.";
        }
    }
} 
// Case B: Code is already in the URL (GET from successful validation)
elseif (!empty($code_from_get) && empty($error)) {
    $user_id = $login_handler->validateResetToken($email_from_get, $code_from_get);
    if ($user_id) {
        $is_code_validated = true; 
    } else {
        $error = "The reset code is invalid or has expired. Please request a new code.";
    }
}

// STAGE 2: Handle Password Submission (POST on validated code)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'reset_password' && !empty($code_from_get)) {
    $user_id = $login_handler->validateResetToken($email_from_get, $code_from_get);
    
    if (!$user_id) {
        $error = "The reset code is no longer valid. Please start over.";
        $is_code_validated = false;
    } else {
        $new_password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } else {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            if ($login_handler->updateUserPassword($user_id, $new_hash)) {
                $login_handler->deleteResetToken($code_from_get);
                $message = "Your password has been successfully reset. You can now log in.";
                $is_code_validated = false; 
            } else {
                $error = "A database error occurred while updating your password.";
            }
        }
        if ($error) {
            $is_code_validated = true; 
        }
    }
}

// === END PHP LOGIC ===
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        /* --- CARD THEME MATCHING (From Forgot Password Images) --- */
        
        /* Global & Layout Styles */
        body {
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        .card {
            background-color: #ffffff;
            border-radius: 10px; /* Matched radius */
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3); /* Stronger shadow to pop against the background */
            padding: 40px;
            width: 100%;
            max-width: 400px;
            text-align: center;
            z-index: 10;
        }
        
        /* Header and Branding */
        .logo {
            max-width: 90px;
            margin: 0 auto 15px auto;
        }
        .app-title {
            color: #8B0000; /* Main Red Color */
            font-size: 1.1em;
            font-weight: 600;
            line-height: 1.3;
            margin-bottom: 30px;
            text-transform: uppercase;
        }
        /* --- CORRECTED HEADING STYLE --- */
        h2 {
            font-size: 1.75rem; /* Same size as before */
            margin-bottom: 15px;
            color: #333;
            /* REDUCED FONT WEIGHT to 600 (Semi-Bold) for better balance with the card */
            font-weight: 600; 
        }
        /* ------------------------------- */
        .instruction-text {
            margin-bottom: 25px;
            font-size: 0.95rem;
            color: #555;
            line-height: 1.5;
        }

        /* Alerts */
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: left;
            font-size: 0.9em;
            border: 1px solid transparent;
        }
        .alert-error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .alert-info {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .alert-warning {
             color: #856404;
             background-color: #fff3cd;
             border-color: #ffeeba;
        }
        .alert-security {
             color: #004085;
             background-color: #cce5ff;
             border-color: #b8daff;
        }

        /* Form Elements */
        .input-group {
            margin-bottom: 20px;
            text-align: left;
            position: relative; 
        }
        .input-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .input-field {
            width: 100%;
            padding: 12px 15px; /* Matched padding */
            border: 1px solid #ced4da;
            border-radius: 6px; /* Matched radius */
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .input-field:focus {
            border-color: #8B0000;
            outline: none;
            box-shadow: 0 0 0 3px rgba(139, 0, 0, 0.15);
        }
        
        /* Code Input Specifics (Stage 1) */
        .input-field[name="code"] {
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 5px; 
            padding: 15px 10px; 
            font-weight: bold;
        }
        /* New Password Fields (Stage 2) */
        .input-field[type="password"] {
            padding-right: 45px; /* Space for toggle icon */
        }


        /* Button Style */
        .btn-primary {
            background-color: #8B0000; /* Main button color */
            color: #ffffff;
            padding: 12px 15px;
            border: none;
            border-radius: 6px;
            font-size: 1.05rem;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.2s ease, transform 0.1s;
        }
        .btn-primary:hover {
            background-color: #6a0000;
        }
        .btn-primary:active {
            transform: scale(0.99);
        }
        
        /* Links */
        .link-text {
            color: #8B0000; 
            text-decoration: none;
            font-size: 0.95rem;
            transition: text-decoration 0.2s;
        }
        .link-text:hover {
            text-decoration: underline;
        }
        
        /* Password Toggle Icon */
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 55%; 
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            z-index: 10;
        }
    </style>
</head>
<body>
    <div class="card">
        <img src="../wmsu_logo/wmsu.png" alt="WMSU Logo" class="logo">
        <div class="app-title">CSM LABORATORY BORROWING APPARATUS</div>
        
        <h2>Reset Your Password</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($flash_message): // Display flash message set by forgot_password.php or resend_reset_code.php ?>
            <div class="alert alert-<?php echo htmlspecialchars($flash_message['type']); ?>">
                <?php echo $flash_message['content']; ?>
            </div>
        <?php endif; ?>
        <?php if ($message): // Success Message Stage (after successful reset) ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <p style="margin-top: 25px;"><a href="login.php" class="link-text">Go to Login Page</a></p>
        <?php endif; ?>

        <?php if (!$message): // Show forms only if no success message is present ?>
            
            <?php if ($is_code_validated): // STAGE 2: New Password Entry ?>
                <p class="instruction-text">
                    Set your new password for **<?= htmlspecialchars($email_from_get) ?>**.
                </p>
                
                <form action="reset_password.php?email=<?php echo urlencode($email_from_get); ?>&code=<?php echo urlencode($code_from_get); ?>" method="POST">
                    <input type="hidden" name="action" value="reset_password">
                    
                    <div class="input-group">
                        <label for="password">New Password (Min 8 characters):</label>
                        <input type="password" id="password" name="password" class="input-field" required autocomplete="new-password" placeholder="Enter new password">
                        <i class="fas fa-eye password-toggle" onclick="togglePasswordVisibility('password', this)"></i>
                    </div>
                    
                    <div class="input-group">
                        <label for="confirm_password">Confirm New Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="input-field" required autocomplete="new-password" placeholder="Confirm new password">
                        <i class="fas fa-eye password-toggle" onclick="togglePasswordVisibility('confirm_password', this)"></i>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <button type="submit" class="btn-primary">Set New Password</button>
                    </div>
                    
                    <p style="margin-top: 20px; margin-bottom: 0; font-size: 0.95em;">
                            <a href="login.php" class="link-text">Cancel and return to Login</a>
                    </p>
                </form>
                
            <?php else: // STAGE 1: Code Entry ?>
                <p class="instruction-text">
                    Enter the 6-digit code sent to **<?= htmlspecialchars($email_from_get) ?>**.
                </p>
                
                <div class="alert alert-info" style="font-weight: 600; text-align: center;">
                    This code is valid for only **<?= TOKEN_EXPIRY_MINUTES ?> minutes**.
                </div>

                <form action="reset_password.php" method="POST">
                    <input type="hidden" name="action" value="validate_code">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email_from_get); ?>">
                    
                    <div class="input-group">
                        <input type="text" id="code" name="code" class="input-field" maxlength="6" 
                                 required placeholder="Enter Code" autofocus
                                 inputmode="numeric" pattern="[0-9]*">
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <button type="submit" class="btn-primary">Verify Code</button>
                    </div>
                </form>
                
                <p style="margin-top: 25px; font-size: 0.95em; margin-bottom: 0;">
                    Didn't receive the code? 
                    <a href="resend_reset_code.php?email=<?php echo urlencode($email_from_get); ?>" class="link-text">Request a new code</a>
                    </p>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (!($message || $is_code_validated)): // Only show "Back to Login" if we are in Code Entry and no success message ?>
            <p style="margin-top: 20px; margin-bottom: 0;">
                <a href="login.php" class="link-text">Back to Login</a>
            </p>
        <?php endif; ?>
    </div>

    <script>
        // === JAVASCRIPT FOR STRICTLY NUMERIC INPUT (Only on the code field) ===
        document.addEventListener('DOMContentLoaded', () => {
            const codeInput = document.getElementById('code');
            if (codeInput) {
                // Blocks non-numeric key presses
                codeInput.addEventListener('keydown', (e) => {
                    // Allow: backspace (8), delete (46), tab (9), escape (27), enter (13)
                    if ([8, 9, 13, 27, 46].indexOf(e.keyCode) !== -1 ||
                        // Allow: Ctrl/Cmd+A, Ctrl/Cmd+C, Ctrl/Cmd+X, Ctrl/Cmd+V
                        (e.ctrlKey === true || e.metaKey === true) && [65, 67, 88, 86].indexOf(e.keyCode) !== -1 || 
                        // Allow: home (36), end (35), left (37), right (39)
                        (e.keyCode >= 35 && e.keyCode <= 40)) {
                        return;
                    }
                    // Block letters (A-Z) and symbols on the main keyboard
                    if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && 
                        // Block symbols on the numeric keypad
                        (e.keyCode < 96 || e.keyCode > 105)) {
                        e.preventDefault();
                    }
                });

                // Blocks pasted content that is non-numeric
                codeInput.addEventListener('paste', (e) => {
                    const paste = (e.clipboardData || window.clipboardData).getData('text');
                    const filteredPaste = paste.replace(/[^0-9]/g, '');
                    
                    // If the paste contains non-numeric chars, prevent default and insert filtered
                    if (paste !== filteredPaste) {
                        e.preventDefault();
                        const currentVal = codeInput.value;
                        const selectionStart = codeInput.selectionStart;
                        const selectionEnd = codeInput.selectionEnd;

                        const newVal = currentVal.substring(0, selectionStart) + 
                                             filteredPaste.substring(0, 6 - currentVal.length + (selectionEnd - selectionStart)) + 
                                             currentVal.substring(selectionEnd);
                        
                        codeInput.value = newVal.substring(0, 6);
                    }
                });
                
                   // Autofocus the code input on load
                   codeInput.focus();
            }
        });

        // === PASSWORD TOGGLE ===
        function togglePasswordVisibility(id, iconElement) {
            const input = document.getElementById(id);
            
            if (input.type === "password") {
                input.type = "text";
                iconElement.classList.remove('fa-eye');
                iconElement.classList.add('fa-eye-slash');
            } else {
                input.type = "password";
                iconElement.classList.remove('fa-eye-slash');
                iconElement.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>