<?php
// File: ../pages/signup.php
session_start(); // Start session to store messages
require_once "../classes/Student.php"; 
require_once '../vendor/autoload.php'; 
require_once '../classes/Mailer.php'; 

// --- SETUP FOR MODAL LOGIC ---
$errors = [];
$global_message = "";
$global_message_type = ""; // 'error' or 'success'
$show_verification_modal = false; // NEW: Control the modal display
$email_to_verify = ""; // NEW: Store the email for the modal form

// Initialize values from POST or empty string
$student_id = $_POST["student_id"] ?? '';
$firstname = $_POST["firstname"] ?? '';
$lastname = $_POST["lastname"] ?? '';
$course = $_POST["course"] ?? '';
$contact_number = $_POST["contact_number"] ?? ''; 
$full_contact_number = ''; 
$email = $_POST["email"] ?? '';
$password = $_POST["password"] ?? '';
$confirm_password = $_POST["confirm_password"] ?? '';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check if a verification code was submitted (Modal submission)
    if (isset($_POST['verification_code_submit'])) {
        $email_to_verify = $_POST['email_to_verify'] ?? '';
        $submitted_code = $_POST['verification_code'] ?? '';
        
        $student = new Student();
        
        // *****************************************************************
        // ** FIX 1: Corrected method call to match Student.php **
        // *****************************************************************
        if ($student->verifyStudentAccountByCode($email_to_verify, $submitted_code)) {
            // Success! Redirect to login page with a success flash message
            $_SESSION['flash_message'] = ['type' => 'success', 'content' => "Account successfully verified! You may now log in."];
            header("Location: login.php");
            exit;
            
        } else {
            // Failure
            $global_message = "Verification failed. The code is incorrect or the account is already verified.";
            $global_message_type = 'error';
            // Keep modal visible and pass back the email
            $show_verification_modal = true;
        }
        
    } else { // Standard registration form submission
        
        // Capture input values safely (re-trimmed)
        $student_id = trim($student_id);
        $firstname = trim($firstname);
        $lastname = trim($lastname);
        $course = trim($course);
        $contact_number = trim($contact_number);
        $email = trim($email);
        
        // Instantiate the class
        $student = new Student();

        // --- VALIDATION LOGIC (UNCHANGED) ---
        if (empty($student_id)) {
            $errors["student_id"] = "Student ID is required.";
        } elseif (!preg_match("/^[0-9]{4}-[0-9]{5}$/", $student_id)) {
            $errors["student_id"] = "Student ID must follow the pattern YYYY-##### (e.g., 2024-01203).";
        }

        if (empty($firstname)) $errors["firstname"] = "First name is required.";
        if (empty($lastname)) $errors["lastname"] = "Last name is required.";
        if (empty($course)) $errors["course"] = "Course is required.";

        // --- UPDATED CONTACT NUMBER VALIDATION ---
        if (empty($contact_number)) {
            $errors["contact_number"] = "Contact number is required.";
        } else {
            $clean_number = $contact_number;
            if (substr($clean_number, 0, 1) !== '+') {
                $full_contact_number = '+63' . preg_replace('/[^0-9]/', '', $clean_number);
            } else {
                $full_contact_number = preg_replace('/[^0-9+]/', '', $clean_number);
            }

            $digits_only = preg_replace('/[^0-9]/', '', $full_contact_number);
            
            if (strlen($digits_only) < 10 || strlen($digits_only) > 15) {
                $errors["contact_number"] = "Enter a valid full mobile number (e.g. +63917... or 0917...).";
            }
        }
        // ------------------------------------------

        if (empty($email)) {
            $errors["email"] = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors["email"] = "Invalid email format.";
        }

        if (empty($password)) {
            $errors["password"] = "Password is required.";
        } elseif (strlen($password) < 8) {
            $errors["password"] = "Password must be at least 8 characters."; 
        }
        
        if ($password !== $confirm_password) $errors["confirm_password"] = "Passwords do not match.";
        
        
        if (empty($errors)) {
            
            // --- DUPLICATE CHECK LOGIC ---
            $id_exists = $student->isStudentIdExist($student_id);
            $email_exists = $student->isEmailExist($email);

            if ($id_exists || $email_exists) {
                $global_message = "Registration failed. Please correct the errors below.";
                $global_message_type = 'error';
                
                if ($id_exists) {
                    $errors['student_id'] = "An account with this Student ID already exists.";
                }
                if ($email_exists) {
                    $errors['email'] = "An account with this Email address already exists.";
                }

            } else {
                // --- SUCCESS PATH WITH EMAIL VERIFICATION ---
                
                // 1. Generate the 6-digit verification code
                $code = strval(rand(100000, 999999));
                
                // 2. Register the student, passing the NEW $code
                $result = $student->registerStudent(
                    $student_id, $firstname, $lastname, $course, $full_contact_number, $email, $password, $code
                );

                if ($result) {
                    // 3. Send the Verification Email
                    $mailer = new Mailer();
                    $email_sent = $mailer->sendVerificationEmail($email, $code);

                    if ($email_sent) {
                        $global_message = "Registration successful! A 6-digit verification code has been sent to **{$email}**. Please enter it below.";
                        $global_message_type = 'success';
                        $show_verification_modal = true; 
                        $email_to_verify = $email; 
                    } else {
                        $global_message = "Registration successful, but the verification email failed to send. Please contact support.";
                        $global_message_type = 'warning';
                    }
                    
                    // *****************************************************************
                    // ** FIX 2: Implement immediate redirect to clear POST data **
                    // *****************************************************************
                    if ($show_verification_modal) {
                         $_SESSION['temp_email_to_verify'] = $email_to_verify;
                         $_SESSION['temp_global_message'] = $global_message;
                         $_SESSION['temp_global_message_type'] = $global_message_type;
                         header("Location: signup.php"); 
                         exit;
                    }
                    
                } else {
                    $global_message = "Registration failed due to a database error. Please check server logs.";
                    $global_message_type = 'error';
                }
            }
        } 
    }
}

// Check for temporary session data after successful registration redirect
if (isset($_SESSION['temp_email_to_verify'])) {
    $email_to_verify = $_SESSION['temp_email_to_verify'];
    $global_message = $_SESSION['temp_global_message'];
    $global_message_type = $_SESSION['temp_global_message_type'];
    $show_verification_modal = true;

    // Clear session variables after retrieving
    unset($_SESSION['temp_email_to_verify']);
    unset($_SESSION['temp_global_message']);
    unset($_SESSION['temp_global_message_type']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Account - CSM Laboratory</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        /* --- CARD THEME MATCHING (Consistent Theme) --- */
        
        :root {
            --primary-color: #A40404; /* Dark Red / Maroon (WMSU-inspired) */
            --secondary-color: #f4b400; /* Gold/Yellow Accent */
            --text-dark: #2c3e50;
            --text-light: #ecf0f1;
            --background-light: #f8f9fa;
            --success-color: #28a745;
        }
        
        /* Global & Layout Styles */
        body {
            /* Consistent background image and overlay from index.php/login.php */
            background: 
                linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), 
                url("../uploads/Western_Mindanao_State_University_College_of_Teacher_Education_(Normal_Road,_Baliwasan,_Zamboanga_City;_10-06-2023).jpg") 
                no-repeat center center fixed; 
            background-size: cover;
            
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-dark);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 12px; /* Consistent rounded corners */
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4); /* Stronger, modern shadow */
            padding: 40px;
            width: 100%;
            max-width: 500px; /* Slightly wider card for longer registration form */
            text-align: center;
            z-index: 10; 
            animation: fadeIn 0.8s ease-out; /* Subtle animation */
        }
        
        /* Header and Branding */
        .logo {
            max-width: 100px; /* Consistent logo size */
            margin: 0 auto 5px auto;
        }
        .app-title {
            color: var(--primary-color); 
            font-size: 1.1rem; /* Consistent font size */
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        h2 {
            font-size: 1.75rem; 
            margin-bottom: 25px;
            color: var(--text-dark);
            font-weight: 600;
        }
        
        .section-title {
            color: var(--primary-color); 
            font-size: 1.15em; 
            font-weight: 700; 
            text-align: left;
            padding-bottom: 8px; 
            border-bottom: 2px solid var(--secondary-color); /* Use accent color for separator */
            margin: 30px 0 20px 0;
        }

        /* Alerts */
        .message-box {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            text-align: left;
            font-size: 0.95em;
            border: 1px solid transparent;
            font-weight: 600;
        }
        .message-box.error {
            color: #721c24; /* Dark red text */
            background-color: #f8d7da; /* Light red background */
            border-color: #f5c6cb;
        }
        .message-box.success {
            color: #155724; /* Dark green text */
            background-color: #d4edda; /* Light green background */
            border-color: #c3e6cb;
        }
        .message-box.warning {
             color: #856404;
             background-color: #fff3cd;
             border-color: #ffeeba;
        }


        /* Form Elements */
        .input-group {
            margin-bottom: 20px;
            text-align: left;
            position: relative; 
        }
        .input-group label {
            display: block;
            color: var(--text-dark);
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.95em;
        }
        .input-field {
            width: 100%;
            padding: 12px 15px; 
            height: 48px; /* Consistent height */
            border: 1px solid #ddd;
            border-radius: 6px; 
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .input-field:focus {
            border-color: var(--secondary-color); /* Consistent focus color */
            outline: none;
            box-shadow: 0 0 0 3px rgba(244, 180, 0, 0.2);
        }
        .input-field.error {
            border-color: var(--primary-color) !important;
        }
        
        /* Password Toggle Icon */
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%; 
            transform: translateY(-50%); 
            cursor: pointer;
            color: #666;
            font-size: 1.1rem;
            z-index: 10;
        }
        
        /* Error Text */
        .error-text {
            color: var(--primary-color); 
            font-size: 0.85em;
            margin-top: 5px; 
            display: block;
            font-weight: 600;
        }

        /* Button Style (Updated to pill shape and new class) */
        .btn-continue, .btn-primary {
            background-color: var(--primary-color); 
            color: #ffffff;
            padding: 15px 15px; /* Consistent button padding */
            border: none;
            border-radius: 50px; /* Pill shape */
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s ease, transform 0.2s, box-shadow 0.3s;
            margin-top: 30px; 
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .btn-continue:hover, .btn-primary:hover {
            background-color: #820303; 
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
        }
        .btn-continue:active, .btn-primary:active {
            transform: translateY(0);
        }
        
        /* Links */
        .bottom-link-container {
            text-align: center;
            margin-top: 25px;
            font-size: 0.95em;
        }
        .link-text {
            color: var(--primary-color); 
            text-decoration: none;
            font-weight: 600;
            transition: text-decoration 0.2s;
        }
        .link-text:hover {
            text-decoration: underline;
        }
        
        /* Back to Home Link (Consistent with login.php) */
        .back-to-home-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-to-home-link a {
            color: var(--text-dark);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.2s;
        }
        .back-to-home-link a:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }

        @media (max-width: 550px) {
            .card {
                margin: 20px;
                padding: 30px;
            }
        }
        
        /* --- NEW MODAL STYLES --- */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }

        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 400px;
            text-align: center;
            transform: scale(0.9);
            transition: transform 0.3s;
        }
        
        .modal-overlay.show .modal-content {
            transform: scale(1);
        }
        
        .modal-content h3 {
            color: var(--primary-color);
            margin-top: 0;
            font-size: 1.5rem;
        }
        
        .modal-content p {
            margin-bottom: 25px;
            line-height: 1.4;
        }
        
        .modal-input-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .modal-input-group input[type="text"] {
            font-size: 1.5rem;
            text-align: center;
            letter-spacing: 15px; /* Spacing for 6 digits */
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            width: 100%;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.3s;
        }
        
        .modal-input-group input[type="text"]:focus {
            border-color: var(--secondary-color);
        }
        
        .modal-input-group .error-text {
            text-align: center;
        }
        
        /* Minor override for button alignment inside modal */
        .modal-content .btn-primary {
            margin-top: 0;
        }
    </style>
</head>
<body>

<div class="card">
    <img src="../wmsu_logo/wmsu.png" alt="WMSU Logo" class="logo">
    <div class="app-title">CSM LABORATORY BORROWING APPARATUS</div>
    
    <h2>Create a New Account</h2>

    <?php if (!empty($global_message) && !$show_verification_modal): ?>
        <div class="message-box <?= $global_message_type ?>">
            <i class="fas fa-<?= ($global_message_type === 'success' ? 'check-circle' : 'exclamation-triangle') ?>"></i> <?= htmlspecialchars($global_message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        
        <div class="section-title"><i class="fas fa-lock"></i> Account Details</div>

        <div class="input-group">
            <label for="student_id">Student ID</label>
            <input type="text" id="student_id" name="student_id" class="input-field <?= isset($errors["student_id"]) ? 'error' : '' ?>" 
                    value="<?= htmlspecialchars($student_id) ?>" placeholder="e.g., 2024-01203">
            <?php if (isset($errors["student_id"])): ?><span class="error-text"><i class="fas fa-exclamation-circle"></i> <?= $errors["student_id"] ?></span><?php endif; ?>
        </div>
        
        <div class="input-group">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" class="input-field <?= isset($errors["email"]) ? 'error' : '' ?>" 
                    value="<?= htmlspecialchars($email) ?>" placeholder="e.g., email@gmail.com" >
            <?php if (isset($errors["email"])): ?><span class="error-text"><i class="fas fa-exclamation-circle"></i> <?= $errors["email"] ?></span><?php endif; ?>
        </div>

        <div class="input-group">
            <label for="password">Password (Min 8 characters)</label>
            <div style="position: relative;">
                <input type="password" id="password" name="password" class="input-field <?= isset($errors["password"]) ? 'error' : '' ?>" >
                <i class="fas fa-eye toggle-password" onclick="togglePasswordVisibility('password', this)"></i>
            </div>
            <?php if (isset($errors["password"])): ?><span class="error-text"><i class="fas fa-exclamation-circle"></i> <?= $errors["password"] ?></span><?php endif; ?>
        </div>

        <div class="input-group">
            <label for="confirm_password">Confirm Password</label>
            <div style="position: relative;">
                <input type="password" id="confirm_password" name="confirm_password" class="input-field <?= isset($errors["confirm_password"]) ? 'error' : '' ?>" >
                <i class="fas fa-eye toggle-password" onclick="togglePasswordVisibility('confirm_password', this)"></i>
            </div>
            <?php if (isset($errors["confirm_password"])): ?><span class="error-text"><i class="fas fa-exclamation-circle"></i> <?= $errors["confirm_password"] ?></span><?php endif; ?>
        </div>

        <div class="section-title"><i class="fas fa-user"></i> Personal Details</div>

        <div class="input-group">
            <label for="firstname">First name</label>
            <input type="text" id="firstname" name="firstname" class="input-field <?= isset($errors["firstname"]) ? 'error' : '' ?>" 
                    value="<?= htmlspecialchars($firstname) ?>" >
            <?php if (isset($errors["firstname"])): ?><span class="error-text"><i class="fas fa-exclamation-circle"></i> <?= $errors["firstname"] ?></span><?php endif; ?>
        </div>

        <div class="input-group">
            <label for="lastname">Last name</label>
            <input type="text" id="lastname" name="lastname" class="input-field <?= isset($errors["lastname"]) ? 'error' : '' ?>" 
                    value="<?= htmlspecialchars($lastname) ?>" >
            <?php if (isset($errors["lastname"])): ?><span class="error-text"><i class="fas fa-exclamation-circle"></i> <?= $errors["lastname"] ?></span><?php endif; ?>
        </div>
        
        <div class="input-group">
            <label for="course">Course</label>
            <input type="text" id="course" name="course" class="input-field <?= isset($errors["course"]) ? 'error' : '' ?>" 
                    value="<?= htmlspecialchars($course) ?>" >
            <?php if (isset($errors["course"])): ?><span class="error-text"><i class="fas fa-exclamation-circle"></i> <?= $errors["course"] ?></span><?php endif; ?>
        </div>

        <div class="input-group">
            <label for="contact_number">Contact Number</label>
            <input type="text" id="contact_number" name="contact_number" 
                    value="<?= htmlspecialchars($contact_number) ?>" class="input-field <?= isset($errors["contact_number"]) ? 'error' : '' ?>" 
                    placeholder="e.g., +639171234567 or 09171234567">
            <?php if (isset($errors["contact_number"])): ?><span class="error-text"><i class="fas fa-exclamation-circle"></i> <?= $errors["contact_number"] ?></span><?php endif; ?>
        </div>
        
        <button type="submit" class="btn-continue">
            <i class="fas fa-check-circle"></i> Create my new account
        </button>
    </form>
    
    <div class="bottom-link-container">
        Already have an account? <a href="login.php" class="link-text">Login here</a>
    </div>

    <div class="back-to-home-link">
        <a href="index.php">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>
    </div>
</div>

<div id="verificationModal" class="modal-overlay <?= $show_verification_modal ? 'show' : '' ?>">
    <div class="modal-content">
        <form method="POST" action="">
            <h3><i class="fas fa-envelope-open-text"></i> Verify Your Email</h3>
            
            <?php if (!empty($global_message) && $show_verification_modal): ?>
                <div class="message-box <?= $global_message_type ?>">
                    <i class="fas fa-<?= ($global_message_type === 'success' ? 'check-circle' : 'exclamation-triangle') ?>"></i> <?= htmlspecialchars($global_message) ?>
                </div>
            <?php endif; ?>

            <p>We sent a 6-digit code to **<?= htmlspecialchars($email_to_verify) ?>**. Please enter it below to activate your account.</p>

            <div class="modal-input-group">
                <label for="verification_code" style="display:none;">Verification Code</label>
                <input type="text" id="verification_code" name="verification_code" maxlength="6" 
                       required placeholder="000000" pattern="\d{6}">
            </div>
            
            <input type="hidden" name="email_to_verify" value="<?= htmlspecialchars($email_to_verify) ?>">
            <button type="submit" name="verification_code_submit" class="btn-primary">
                <i class="fas fa-unlock-alt"></i> Verify Account
            </button>
        </form>
    </div>
</div>

<script>
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

    // === MODAL DISPLAY JAVASCRIPT ===
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('verificationModal');
        // This PHP block ensures the JS knows whether to show the modal or not
        const showModal = <?= $show_verification_modal ? 'true' : 'false' ?>;

        if (showModal) {
            // Add a slight delay to ensure the DOM is fully ready and for a smoother effect
            setTimeout(() => {
                 modal.classList.add('show');
                 // Focus the input field when the modal appears
                 document.getElementById('verification_code').focus();
            }, 100);
        }
    });

    // Prevent closing the modal by clicking outside
    document.getElementById('verificationModal').addEventListener('click', function(e) {
        if (e.target === this) {
            // Keep modal open
        }
    });
</script>

</body>
</html>