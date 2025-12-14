<?php

session_start();
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


echo '<style>
    /* Theme Variables for consistency */
    :root {
        --primary-color: #A40404; 
        --primary-color-dark: #820303; 
        --secondary-color: #f4b400;
        --text-dark: #2c3e50;
        --danger-color: #dc3545;
        --success-color: #28a745;
    }

    .alert-item {
        padding: 15px;
        border-radius: 8px;
        text-decoration: none;
        color: var(--text-dark);
        transition: background-color 0.1s, box-shadow 0.2s;
        border: 1px solid #eee;
    }
    
    /* Highlight UNREAD items with primary color border */
    .alert-unread {
        background-color: #fff9f9; /* Very light red tint */
        font-weight: 600;
        border-left: 5px solid var(--primary-color);
    }
    .alert-unread:hover {
        background-color: #faeaea;
    }
    .alert-read {
        background-color: #fff;
        font-weight: normal;
    }
    .alert-read:hover {
        background-color: #f9f9f9;
        box-shadow: 0 1px 5px rgba(0,0,0,0.05); 
    }
    .alert-icon {
        font-size: 1.3rem;
        flex-shrink: 0;
        width: 30px;
    }
    .alert-message {
        font-size: 1rem;
        line-height: 1.4;
        word-wrap: break-word;
        white-space: normal;
    }
    .alert-timestamp {
        display: block;
        font-size: 0.8em;
        color: #999;
        margin-top: 2px;
    }
    .unread-badge {
        font-size: 0.75em;
        padding: 0.4em 0.7em;
        background-color: var(--primary-color) !important; 
    }
    /* Mark All Button Styling - Uses accent color */
    .btn-mark-all {
        border: 1px solid var(--secondary-color);
        color: var(--text-dark);
        background-color: #fff;
        font-weight: 600;
        border-radius: 6px;
        transition: all 0.2s;
        padding: 5px 10px;
        font-size: 0.9rem;
    }
    .btn-mark-all:hover {
        background-color: var(--secondary-color);
        color: var(--text-dark);
    }

    /* Override Bootstrap text colors to use theme primary for specific states */
    .text-danger { color: var(--danger-color) !important; }
    .text-success { color: var(--success-color) !important; }
    .text-primary { color: var(--primary-color) !important; }
    .text-warning { color: var(--secondary-color) !important; }
</style>';


try {
    $conn = $transaction->connect();
    

    $stmt_alerts = $conn->prepare("
        SELECT id, type, message, link, created_at, is_read
        FROM notifications 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC 
        LIMIT 50 
    ");
    $stmt_alerts->execute([':user_id' => $student_id]);
    $notifications = $stmt_alerts->fetchAll(PDO::FETCH_ASSOC);

    
    $unread_count = count(array_filter($notifications, fn($n) => $n['is_read'] == 0)); 
    
    
    if ($unread_count > 0) {
        echo '<div class="mb-3 text-end">';
       
        echo '  <button type="button" class="btn-mark-all" onclick="markAllAsRead()">';
        echo '      <i class="fas fa-check-double me-1"></i> Mark All ' . $unread_count . ' as Read';
        echo '  </button>';
        echo '</div>';
    } else {
        echo '<div class="mb-3 text-center text-muted small">All caught up! No unread messages.</div>';
    }


  
    if (!empty($notifications)):
        foreach ($notifications as $n):
            
            $is_read = $n['is_read'];
            $alert_class = $is_read ? 'alert-read' : 'alert-unread';
            
            
            $icon = 'fas fa-info-circle text-secondary'; 
            if (strpos($n['type'], 'approved') !== false || strpos($n['type'], 'good') !== false) {
              
                $icon = 'fas fa-check-circle text-success';
            } elseif (strpos($n['type'], 'rejected') !== false || strpos($n['type'], 'damaged') !== false || strpos($n['type'], 'late') !== false || strpos($n['type'], 'overdue') !== false) {
                
                $icon = 'fas fa-exclamation-triangle text-danger';
            } elseif (strpos($n['type'], 'sent') !== false || strpos($n['type'], 'checking') !== false || strpos($n['type'], 'verification') !== false) {
              
                $icon = 'fas fa-hourglass-half text-primary';
            }
            ?>
            <a href="<?= htmlspecialchars($n['link']) ?>" 
                class="alert-item <?= $alert_class ?> d-flex align-items-start mb-2" 
                data-notification-id="<?= $n['id'] ?>"
                data-is-read="<?= $is_read ?>"
                onclick="markSingleAsRead(event, <?= $n['id'] ?>)">
                <i class="<?= $icon ?> alert-icon me-3 mt-1"></i>
                <div class="alert-body flex-grow-1">
                    <p class="alert-message mb-0"><?= htmlspecialchars($n['message']) ?></p>
                    <small class="alert-timestamp"><?= time_ago($n['created_at']) ?></small>
                </div>
                <?php if (!$is_read): ?>
                <span class="badge ms-2 unread-badge">New</span>
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