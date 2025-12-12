<?php
// classes/Mailer.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class Mailer {
    private $mail;
    private $last_error = ''; // Added a private property to store the last error

    public function __construct() {
        $this->mail = new PHPMailer(true);
        try {
            // *** FINAL CONFIGURATION: GMAIL SMTP (Uses App Password) ***
            $this->mail->isSMTP();
            $this->mail->Host       = 'smtp.gmail.com'; // CORRECT Host for Gmail
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = 'jngiglesia@gmail.com'; // Your Gmail address
            $this->mail->Password   = 'aezq hfjs fbpl fnew'; // Your 16-digit App Password
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // CORRECT: Use SMTPS
            $this->mail->Port       = 465; // CORRECT Port for SMTPS
            // ---------------------------------------------------------------------

            $this->mail->setFrom('jngiglesia@gmail.com', 'CSM Borrowing System');
            $this->mail->isHTML(true);
        } catch (Exception $e) {
            $this->last_error = "Mailer configuration error: " . $e->getMessage();
            error_log($this->last_error);
        }
    }
    
    /**
     * NEW: Public getter for the last PHPMailer error message.
     * Required by forgot_password.php and other calling scripts.
     * @return string
     */
    public function getError() {
        return $this->mail->ErrorInfo;
    }

    /**
     * Loads, processes, and injects a content template into the base layout.
     * @param string $templateName The name of the content template file (e.g., 'body_status').
     * @param array $variables Key-value pairs for replacement (e.g., ['FORM_ID' => 123]).
     * @return string Final HTML body content.
     */
    protected function loadTemplate($templateName, array $variables = []) {
        // Path adjusted to reflect the 'templates/emails' directory structure
        $basePath = __DIR__ . '/../templates/emails/'; 
        $contentPath = $basePath . $templateName . '.html';
        $layoutPath = $basePath . 'base_layout.html';

        if (!file_exists($contentPath) || !file_exists($layoutPath)) {
            error_log("Missing email template file: " . $contentPath);
            return "Error: Template not found."; 
        }

        // 1. Load Content Template
        $content = file_get_contents($contentPath);
        
        // 2. Simple Replacement for all variables (e.g., {{ KEY }})
        foreach ($variables as $key => $value) {
            // Ensure placeholders are replaced in the content template
            $content = str_replace('{{ ' . strtoupper($key) . ' }}', $value, $content);
        }
        
        // 3. --- NEW STEP: Handle conditional tags (like {{ IF ... }}) ---
        // This is a simple processor to remove/keep conditional blocks based on variables.
        // It's specific to the body_return.html conditional tags.
        foreach ($variables as $key => $value) {
            $upperKey = strtoupper($key);
            
            // Handle specific conditional IF blocks
            if ($upperKey == 'CONDITION') {
                $content = preg_replace("/{{ IF CONDITION == \"good\" }}.*?{{ ENDIF }}/s", ($value == 'good' ? '\\0' : ''), $content);
                $content = preg_replace("/{{ IF CONDITION == \"late\" }}.*?{{ ENDIF }}/s", ($value == 'late' ? '\\0' : ''), $content);
                $content = preg_replace("/{{ IF CONDITION == \"damaged\" }}.*?{{ ENDIF }}/s", ($value == 'damaged' ? '\\0' : ''), $content);
            }
        }
        // Remove remaining IF/ENDIF tags, keeping only the content inside the true block.
        $content = str_replace(['{{ IF CONDITION == "good" }}', '{{ IF CONDITION == "late" }}', '{{ IF CONDITION == "damaged" }}', '{{ ENDIF }}'], '', $content);
        
        // 4. Load the Base Layout and inject the finalized content
        $bodyHtml = file_get_contents($layoutPath);
        $bodyHtml = str_replace('{{ BODY_CONTENT }}', $content, $bodyHtml);
        
        // Also replace variables in the layout itself (e.g., {{ SUBJECT }})
        foreach ($variables as $key => $value) {
            $bodyHtml = str_replace('{{ ' . strtoupper($key) . ' }}', $value, $bodyHtml);
        }
        
        return $bodyHtml;
    }

    /**
     * Sends the account verification email using the templating system.
     * (MODIFIED to send a CODE instead of a LINK)
     */
    public function sendVerificationEmail($recipientEmail, $code) {
        try {
            // $code is the 6-digit code
            $verification_code = $code; 
            
            $this->mail->clearAddresses();
            $this->mail->addAddress($recipientEmail);

            $subject = 'Verify Your Account for the CSM Borrowing System';

            $variables = [
                'SUBJECT' => $subject,
                'VERIFICATION_CODE' => $verification_code, // MODIFIED variable
                'RECIPIENT_NAME' => 'User', 
            ];

            $bodyHtml = $this->loadTemplate('body_verification', $variables); 

            $this->mail->Subject = $subject;
            $this->mail->Body    = $bodyHtml;
            $this->mail->AltBody = "Your verification code is: " . $verification_code;

            return $this->mail->send();
        } catch (Exception $e) {
            $this->last_error = $this->mail->ErrorInfo;
            error_log("Verification Email failed for {$recipientEmail}. Mailer Error: {$this->last_error}");
            return false;
        }
    }

    /**
     * NEW: Sends the password reset code email.
     */
    public function sendResetCodeEmail($recipientEmail, $code) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($recipientEmail);

            $subject = 'CSM Borrowing System Password Reset Code';

            $variables = [
                'SUBJECT' => $subject,
                'VERIFICATION_CODE' => $code, // Reuse VERIFICATION_CODE variable for template
                'RECIPIENT_NAME' => 'User',
            ];
            
            // Use body_verification, but replace the title and description for context
            $bodyHtml = $this->loadTemplate('body_verification', $variables); 
            
            $bodyHtml = str_replace('Account Verification', 'Password Reset Code', $bodyHtml);
            $bodyHtml = str_replace('to activate your borrowing privileges:', 'to reset your password. This code is valid for 10 minutes:', $bodyHtml);
            $bodyHtml = str_replace('Verification Link:', 'Code:', $bodyHtml); // If link text appears in template

            $this->mail->Subject = $subject;
            $this->mail->Body    = $bodyHtml;
            $this->mail->AltBody = "Your password reset code is: " . $code;

            return $this->mail->send();

        } catch (Exception $e) {
            $this->last_error = $this->mail->ErrorInfo;
            error_log("Reset Code Email failed for {$recipientEmail}. Mailer Error: {$this->last_error}");
            return false;
        }
    }

    /**
     * Sends generic email (used for raw staff messages).
     */
    public function sendRawEmail($recipientEmail, $subject, $bodyHtml) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($recipientEmail);

            $this->mail->Subject = $subject;
            $this->mail->Body = $bodyHtml;
            $this->mail->AltBody = strip_tags($bodyHtml);

            return $this->mail->send();
        } catch (Exception $e) {
            $this->last_error = $this->mail->ErrorInfo;
            error_log("Raw Email failed for {$recipientEmail}. Error: {$this->last_error}");
            return false;
        }
    }
    
    /**
     * Sends updates for form submission, approval, or rejection.
     */
    public function sendTransactionStatusEmail($recipientEmail, $recipientName, $formId, $status, $remarks = null) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($recipientEmail, $recipientName);

            $statusText = ucwords(str_replace('_', ' ', $status));
            $subject = "Update: Request #{$formId} is {$statusText}";
            $cleanRemarks = $remarks ? nl2br(htmlspecialchars($remarks)) : 'No specific remarks were provided by staff.';
            
            // --- CRITICAL FIX: DETERMINE DYNAMIC CONTENT AND STYLING IN PHP ---
            $dynamic_message = '';
            $status_css_class = 'status-waiting'; // Default
            $template_file = 'body_status'; // Default template name

            switch ($status) {
                case 'approved':
                    $dynamic_message = 'The staff has **APPROVED** your request! You may proceed to collect the apparatus on the scheduled borrow date.';
                    $status_css_class = 'status-approved';
                    break;
                case 'rejected':
                    $dynamic_message = 'Your request has been **REJECTED**. Please review the remarks below for the reason and submit a new request.';
                    $status_css_class = 'status-rejected';
                    break;
                case 'waiting_for_approval': 
                    $dynamic_message = 'Your submission has been successfully received and is **awaiting staff review**. You will receive another email once your request is approved or rejected.';
                    $status_css_class = 'status-waiting'; 
                    break;
                default:
                    $dynamic_message = 'The status of your form is now **' . $statusText . '**.';
                    $status_css_class = 'status-waiting';
            }
            
            // Define variables for template injection
            $variables = [
                'SUBJECT' => $subject,
                'RECIPIENT_NAME' => $recipientName,
                'FORM_ID' => $formId,
                'STATUS_TEXT' => $statusText,
                'DYNAMIC_MESSAGE' => $dynamic_message, 
                'STATUS_CLASS' => $status_css_class,   
                'REMARKS' => $cleanRemarks,
            ];

            $bodyHtml = $this->loadTemplate($template_file, $variables);
            
            $this->mail->Subject = $subject;
            $this->mail->Body    = $bodyHtml;
            $this->mail->AltBody = "Your request #{$formId} status has been updated to {$statusText}.";

            return $this->mail->send();
        } catch (Exception $e) {
            $this->last_error = $this->mail->ErrorInfo;
            error_log("Status Email failed for {$recipientEmail} (Form #{$formId}). Mailer Error: {$this->last_error}");
            return false;
        }
    }
    
    /**
     * Sends the return confirmation email after staff inspection.
     * This handles confirmation for 'good', 'damaged', and 'late' returns.
     */
    public function sendReturnConfirmationEmail($recipientEmail, $recipientName, $formId, $condition, $remarks = null) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($recipientEmail, $recipientName);

            $statusText = '';
            $status_css_class = '';
            $template_file = 'body_return'; // Match your file structure

            switch ($condition) {
                case 'damaged':
                    $statusText = 'RETURNED WITH ISSUE';
                    $status_css_class = 'status-rejected'; // Using red color for damaged
                    break;
                case 'late':
                    $statusText = 'RETURN CONFIRMED - LATE';
                    $status_css_class = 'status-waiting'; // Using warning color for late
                    break;
                case 'good':
                default:
                    $statusText = 'RETURN CONFIRMED';
                    $status_css_class = 'status-approved'; // Using green color for good
                    break;
            }

            $subject = "Confirmation: Apparatus Return (#{$formId}) - {$statusText}";
            $cleanRemarks = $remarks ? nl2br(htmlspecialchars($remarks)) : 'No specific remarks were provided by staff.';
            
            $variables = [
                'SUBJECT' => $subject,
                'RECIPIENT_NAME' => $recipientName,
                'FORM_ID' => $formId,
                'STATUS_TEXT' => $statusText,
                'CONDITION' => $condition, // Pass the condition string for conditional logic in loadTemplate
                'STATUS_CLASS' => $status_css_class,
                'REMARKS' => $cleanRemarks,
            ];
            
            $bodyHtml = $this->loadTemplate($template_file, $variables);
            
            $this->mail->Subject = $subject;
            $this->mail->Body    = $bodyHtml;
            $this->mail->AltBody = "Return Confirmation for #{$formId}: {$statusText}.";

            return $this->mail->send();
        } catch (Exception $e) {
            $this->last_error = $this->mail->ErrorInfo;
            error_log("Return Confirmation Email failed for {$recipientEmail} (Form #{$formId}). Mailer Error: {$this->last_error}");
            return false;
        }
    }
    
    /**
     * Sends the overdue notice email (requires overdue_notice.html).
     */
    public function sendOverdueNotice($recipientEmail, $recipientName, $formId, $returnDate, $itemsList) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($recipientEmail, $recipientName);

            $subject = "URGENT: Apparatus Overdue Notice (#{$formId})";
            $template_file = 'overdue_notice'; // Match your file structure
            $status_css_class = 'status-rejected'; // Use red color for overdue

            $variables = [
                'SUBJECT' => $subject,
                'RECIPIENT_NAME' => $recipientName,
                'FORM_ID' => $formId,
                'RETURN_DATE' => $returnDate,
                'ITEMS_LIST' => $itemsList,
                'STATUS_CLASS' => $status_css_class,
                'GRACE_PERIOD' => (new DateTime($returnDate))->modify('+1 day')->format('Y-m-d'),
            ];
            
            $bodyHtml = $this->loadTemplate($template_file, $variables);
            
            $this->mail->Subject = $subject;
            $this->mail->Body    = $bodyHtml;
            $this->mail->AltBody = "URGENT: Your loan (#{$formId}) is overdue. Expected return date was {$returnDate}.";

            return $this->mail->send();
        } catch (Exception $e) {
            $this->last_error = $this->mail->ErrorInfo;
            error_log("Overdue Email failed for {$recipientEmail} (Form #{$formId}). Mailer Error: {$this->last_error}");
            return false;
        }
    }
}