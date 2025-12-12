<?php
// WD123/api/get_staff_alerts.php - CRITICAL FIX

session_start();
header('Content-Type: application/json');

// Include your database connection class.
require_once '../classes/Student.php'; 
$student_db = new Student();
$db_conn = $student_db->connect(); 

// --- Authentication Check ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'staff') {
    echo json_encode(['count' => 0, 'notifications' => []]);
    http_response_code(403);
    exit;
}

$current_staff_id = $_SESSION['user']['id']; 

$response = [
    'count' => 0,          // This will hold the count of UNREAD NOTIFICATIONS
    'notifications' => [] 
];

try {
    // 1. FETCH THE 5 MOST RECENT UNREAD NOTIFICATIONS FOR THE DROPDOWN LIST (Execute this first)
    $notif_sql = "SELECT id, message, link, created_at FROM notifications 
                  WHERE user_id = :user_id AND is_read = 0 
                  ORDER BY created_at DESC 
                  LIMIT 5";

    $notif_stmt = $db_conn->prepare($notif_sql);
    $notif_stmt->bindParam(":user_id", $current_staff_id, PDO::PARAM_INT);
    $notif_stmt->execute();
    $notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. CALCULATE THE BELL BADGE COUNT BASED ON THE RESULTS OF STEP 1
    // The count of unread alerts for the dropdown is the best source for the badge number.
    $response['count'] = count($notifications);
    $response['notifications'] = $notifications;
    
    // Return the combined response as JSON
    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Alert fetch error: " . $e->getMessage());
    echo json_encode(['count' => 0, 'notifications' => []]);
}

?>