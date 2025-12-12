<?php
// student_notifications.php (NOW A CONTENT ENDPOINT FOR MODAL)
session_start();
// Include the PHPMailer autoloader first (assuming it's in vendor)
require_once "../vendor/autoload.php"; 
require_once "../classes/Transaction.php";
require_once "../classes/Database.php"; 

if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] != "student") {
    http_response_code(401);
    exit("Unauthorized");
}

$transaction = new Transaction();
$student_id = $_SESSION["user"]["id"];

$notifications = [];

// Helper to format timestamps nicely (replicate the function here or ensure it's globally available)
function time_ago($timestamp) {
    $datetime = new DateTime($timestamp);
    $now = new DateTime();
    $interval = $now->diff($datetime);
    
    if ($interval->y >= 1) return $interval->y . " year" . ($interval->y > 1 ? "s" : "") . " ago";
    if ($interval->m >= 1) return $interval->m . " month" . ($interval->m > 1 ? "s" : "") . " ago";
    if ($interval->d >= 1) return $interval->d . " day" . ($interval->d > 1 ? "s" : "") . " ago";
    if ($interval->h >= 1) return $interval->h . " hour" . ($interval->h > 1 ? "s" : "") . " ago";
    if ($interval->i >= 1) return $interval->i . " minute" . ($interval->i > 1 ? "s" : "") . " ago";
    return "just now";
}


try {
    $conn = $transaction->connect();
    
    // Fetch alerts ordered by newest first
    $stmt_alerts = $conn->prepare("
        SELECT id, type, message, link, created_at, is_read
        FROM notifications 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC 
        LIMIT 50 
    ");
    $stmt_alerts->execute([':user_id' => $student_id]);
    $notifications = $stmt_alerts->fetchAll(PDO::FETCH_ASSOC);

    // FIX: Calculate unread count by filtering where is_read is 0
    $unread_count = count(array_filter($notifications, fn($n) => $n['is_read'] == 0)); 
    
    // --- 1. Render Mark All Button ---
    if ($unread_count > 0) {
        echo '<div class="mb-3 text-end">';
        // CRITICAL: Button calls the global JS function markAllAsRead()
        echo '  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="markAllAsRead()">';
        echo '    <i class="fas fa-check-double me-1"></i> Mark All ' . $unread_count . ' as Read';
        echo '  </button>';
        echo '</div>';
    } else {
         echo '<div class="mb-3 text-end text-muted small">All messages read.</div>';
    }


    // --- 2. Render HTML Content for the Modal/Overlay ---
    if (!empty($notifications)):
        foreach ($notifications as $n):
            // The is_read status here is based on what the DB returned
            $is_read = $n['is_read'];
            $alert_class = $is_read ? 'alert-read' : 'alert-unread';
            
            // Determine icon and link behavior
            $icon = 'fas fa-info-circle'; 
            if (strpos($n['type'], 'approved') !== false || strpos($n['type'], 'good') !== false) {
                $icon = 'fas fa-check-circle text-success';
            } elseif (strpos($n['type'], 'rejected') !== false || strpos($n['type'], 'damaged') !== false || strpos($n['type'], 'late') !== false || strpos($n['type'], 'overdue') !== false) {
                $icon = 'fas fa-exclamation-triangle text-danger';
            } elseif (strpos($n['type'], 'sent') !== false || strpos($n['type'], 'checking') !== false) {
                $icon = 'fas fa-hourglass-half text-primary';
            }
            ?>
            <a href="<?= htmlspecialchars($n['link']) ?>" 
               class="alert-item <?= $alert_class ?> d-flex align-items-center mb-2" 
               data-notification-id="<?= $n['id'] ?>"
               data-is-read="<?= $is_read ?>"
               onclick="markSingleAsRead(event, <?= $n['id'] ?>)">
                <i class="<?= $icon ?> alert-icon me-3"></i>
                <div class="alert-body flex-grow-1">
                    <p class="alert-message mb-0"><?= htmlspecialchars($n['message']) ?></p>
                    <small class="alert-timestamp text-muted"><?= time_ago($n['created_at']) ?></small>
                </div>
                <?php if (!$is_read): ?>
                <span class="badge bg-danger ms-2 unread-badge">New</span>
                <?php endif; ?>
            </a>
            <?php
        endforeach;
    else:
        echo '<div class="alert alert-info text-center mt-3" role="alert">You have no recent notifications.</div>';
    endif;

} catch (Exception $e) {
    error_log("Notification content generation error: " . $e->getMessage());
    echo '<div class="alert alert-danger text-center mt-3" role="alert">Error loading notifications.</div>';
}
?>