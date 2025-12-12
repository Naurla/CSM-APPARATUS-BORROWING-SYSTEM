<?php
// /api/mark_notification_as_read.php
session_start();
require_once "../classes/Transaction.php";

header('Content-Type: application/json');

// --- CRITICAL: Check for ANY logged-in user (Staff or Student) ---
if (!isset($_SESSION["user"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit();
}

$user_id = $_SESSION["user"]["id"];
$transaction = new Transaction(); 

try {
    $result = false;
    $notification_ids_to_mark = [];

    if (isset($_POST['mark_all']) && $_POST['mark_all'] === 'true') {
        // --- 1. MARK ALL LOGIC ---
        // Fetch all unread IDs for the current user
        $conn = $transaction->connect();
        // Use the default connection for simple read to get IDs
        $stmt = $conn->prepare("SELECT id FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $notification_ids_to_mark = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
    } elseif (isset($_POST['notification_id'])) {
        // --- 2. MARK SINGLE LOGIC ---
        $notification_id = (int)$_POST['notification_id'];
        if ($notification_id > 0) {
            $notification_ids_to_mark[] = $notification_id;
        }
    }
    
    // Execute the database update
    if (!empty($notification_ids_to_mark)) {
        // markNotificationsAsRead checks if the notification belongs to $user_id
        $result = $transaction->markNotificationsAsRead($notification_ids_to_mark, $user_id);
    } else {
        $result = true; // Success if there were no notifications to mark
    }
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Database update failed.");
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Mark Read API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>