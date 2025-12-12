<?php
// File: WD123/api/get_student_alerts.php

session_start();
header('Content-Type: application/json');

// We need a class that can provide the database connection (Database.php is usually sufficient)
require_once '../classes/Database.php'; 
$db = new Database(); 
$db_conn = $db->connect(); 

// --- Authentication Check ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    echo json_encode(['count' => 0, 'notifications' => []]);
    http_response_code(403);
    exit;
}

$current_student_id = $_SESSION['user']['id']; 

$response = [
    'count' => 0,          // Count of unread notifications
    'notifications' => []  // List of notifications
];

try {
    // 1. Fetch the 5 most recent unread notifications for the logged-in student
    $notif_sql = "SELECT message, link, created_at FROM notifications 
                  WHERE user_id = :user_id AND is_read = 0 
                  ORDER BY created_at DESC 
                  LIMIT 5";

    $notif_stmt = $db_conn->prepare($notif_sql);
    $notif_stmt->bindParam(":user_id", $current_student_id, PDO::PARAM_INT);
    $notif_stmt->execute();
    $notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Set the count and notifications array
    $response['count'] = count($notifications);
    $response['notifications'] = $notifications;
    
    // Return the combined response as JSON
    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Student Alert fetch error: " . $e->getMessage());
    echo json_encode(['count' => 0, 'notifications' => []]);
}
?>