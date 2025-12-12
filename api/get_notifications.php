<?php
session_start();
// Assuming Transaction.php has access to the Database connection
require_once "../classes/Transaction.php";

header('Content-Type: application/json');

if (!isset($_SESSION["user"])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit();
}

$user_id = $_SESSION["user"]["id"];
$user_role = $_SESSION["user"]["role"];

$transaction = new Transaction(); // Instantiate the Transaction class

try {
    // 1. Get Unread Count using the dedicated method which forces a non-cached read.
    // This method handles the database connection internally.
    $unread_count = $transaction->getUnreadNotificationCount($user_id);

    // 2. Get the latest 10 Notifications (This query remains as is, as it's less critical)
    $conn = $transaction->connect();
    $stmt_alerts = $conn->prepare("
        SELECT id, type, message, link, created_at, is_read
        FROM notifications 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt_alerts->execute([':user_id' => $user_id]);
    $alerts = $stmt_alerts->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count' => (int)$unread_count,
        'alerts' => $alerts
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Notification API Error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error.']);
}

?>