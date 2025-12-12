<?php
// WD123/scripts/check_overdue.php

// This script is designed to be run via a cron job/task scheduler, NOT directly in a browser.

// Define the full path to the project root for includes
define('ROOT_PATH', __DIR__ . '/../');

// --- 1. Include necessary classes ---
require_once ROOT_PATH . 'classes/Transaction.php';
require_once ROOT_PATH . 'classes/Mailer.php';
require_once ROOT_PATH . 'vendor/autoload.php'; // Composer Autoloader

$transaction = new Transaction();
$mailer = new Mailer();
$today = date('Y-m-d');

// --- 2. Fetch Overdue Loans ---
$overdue_loans = $transaction->getOverdueLoansForNotification(); 

if (empty($overdue_loans)) {
    // Keep silent output for cron job success
    exit;
}

// --- 3. Load HTML Template (CRITICAL STEP) ---
$template_path = ROOT_PATH . 'templates/overdue_notice.html';
if (!file_exists($template_path)) {
    error_log("CRITICAL ERROR: Overdue email template not found at: {$template_path}");
    exit; 
}
$html_template = file_get_contents($template_path);

// --- 4. Process and Send Notifications ---
foreach ($overdue_loans as $loan) {
    $user_email = $loan['email'];
    $user_name = htmlspecialchars($loan['firstname']);
    $form_id = $loan['id'];
    $expected_date = $loan['expected_return_date'];

    // --- Dynamic Template Population ---
    $body = str_replace(
        // Placeholders to replace in the HTML file:
        ['{USER_NAME}', '{FORM_ID}', '{EXPECTED_DATE}'], 
        // Actual data to use:
        [$user_name, $form_id, $expected_date], 
        $html_template
    );
    // -----------------------------------

    $subject = "🚨 URGENT: Overdue Item Notice - Form ID {$form_id}";

    // Send the email (now using the populated HTML body)
    $email_sent = $mailer->sendRawEmail($user_email, $subject, $body);

    if ($email_sent) {
        // Log the notification date to prevent duplicates
        $transaction->logOverdueNotice($form_id, $today); 
    } else {
        error_log("Failed to send overdue notice for Form ID {$form_id} to {$user_email}.");
    }
}
?>