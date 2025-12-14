<?php
session_start();
require_once "../vendor/autoload.php"; 
require_once "../classes/Transaction.php";
require_once "../classes/Database.php";
require_once "../classes/Mailer.php"; 

if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] != "student") {
    header("Location: ../pages/login.php");
    exit;
}

$transaction = new Transaction();
$mailer = new Mailer(); 
$student_id = $_SESSION["user"]["id"];
$student_email = $_SESSION["user"]["email"];
$student_name = $_SESSION["user"]["firstname"];


$isBanned = $transaction->isStudentBanned($student_id); 
$activeCount = $transaction->getActiveTransactionCount($student_id); 

$transactions = $transaction->getStudentActiveTransactions($student_id);
$current_datetime = new DateTime();
$today_date_str = $current_datetime->format("Y-m-d");
$overdue_count = 0;
$critical_date_passed = false;
$next_suspension_date = null;

$is_any_overdue_found = false; 

foreach ($transactions as &$t) {
    $expected_return_date = new DateTime($t['expected_return_date']);
    
    if (in_array(strtolower($t['status']), ['borrowed', 'approved', 'checking', 'reserved']) && $t['expected_return_date'] < $today_date_str) {
        $t['is_overdue'] = true;
        $overdue_count++;
        $is_any_overdue_found = true; 
        
        $suspension_trigger_date = clone $expected_return_date;
        $suspension_trigger_date->modify('+2 days');
        
        if (!$next_suspension_date || $suspension_trigger_date < $next_suspension_date) {
             $next_suspension_date = $suspension_trigger_date;
        }

        $grace_period_end = clone $expected_return_date;
        $grace_period_end->modify('+1 day');
        
        if ($current_datetime > $grace_period_end) {
            $critical_date_passed = true;
        }

    } else {
        $t['is_overdue'] = false;
    }
}
unset($t);



if ($is_any_overdue_found && !isset($_SESSION['overdue_notified'])) {
    $system_message = "URGENT: You have {$overdue_count} overdue item(s). Your borrowing privileges are at risk.";
    $notification_link = "student_return.php";
    $_SESSION['overdue_notified'] = true; 

} elseif (!$is_any_overdue_found) {
    unset($_SESSION['overdue_notified']);
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Current Activity</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> 
    
   <style>
    :root {
        --primary-color: #A40404; 
        --primary-color-dark: #820303;
        --secondary-color: #f4b400; 
        --text-dark: #2c3e50;
        --sidebar-width: 280px; 
        --bg-light: #f5f6fa;
        --header-height: 60px; 
        --danger-color: #dc3545; 
        --warning-color: #ffc107; 
        --success-color: #28a745;
        --info-color: #0d6efd;
    }
    
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: var(--bg-light); 
        padding: 0;
        margin: 0;
        display: flex; 
        min-height: 100vh;
        font-size: 1.05rem; 
        color: var(--text-dark);
    }

    
    .menu-toggle {
        display: none; 
        position: fixed;
        top: 15px;
        left: 20px;
        z-index: 1060; 
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 1.2rem;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }

    
    .alert-overdue-urgent {
        border: 3px solid var(--danger-color);
        box-shadow: 0 4px 10px rgba(220, 53, 69, 0.4); 
        background-color: #fff0f0;
        font-weight: 600;
        color: var(--text-dark);
    }
    
   
    .top-header-bar {
        position: fixed;
        top: 0;
        left: var(--sidebar-width);
        right: 0;
        height: var(--header-height);
        background-color: #fff;
        border-bottom: 1px solid #ddd;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        justify-content: flex-end; 
        padding: 0 20px;
        z-index: 1000;
        transition: left 0.3s ease;
    }
    .edit-profile-link {
        color: var(--primary-color);
        font-weight: 600;
        text-decoration: none;
        transition: color 0.2s;
    }

   
    .notification-bell-container {
        position: relative;
        margin-right: 25px; 
        list-style: none;
        padding: 0;
    }
    .notification-bell-container .badge-counter {
        position: absolute;
        top: 5px; 
        right: 0px;
        font-size: 0.7em;
        padding: 0.35em 0.5em;
        background-color: var(--secondary-color); 
        color: var(--text-dark);
        font-weight: bold;
    }

    .dropdown-menu { 
        min-width: 320px; 
        padding: 0; 
        border-radius: 8px; 
    }
    .dropdown-header { 
        font-size: 1rem; 
        color: #6c757d; 
        padding: 10px 15px; 
        text-align: center; 
        border-bottom: 1px solid #eee; 
        margin-bottom: 0; 
    }
    .dropdown-item {
        padding: 8px 15px;
        white-space: normal;
        transition: background-color 0.1s;
        border-bottom: 1px dotted #eee; 
    }
    .dropdown-item:last-child {
        border-bottom: none;
    }
    .dropdown-item.unread-item { 
        font-weight: 600; 
        background-color: #f8f8ff; 
    }
    .dropdown-item small { 
        display: block; 
        font-size: 0.8em; 
        color: #999; 
    }
    .mark-read-hover-btn { 
        opacity: 0; 
        font-size: 0.9rem; 
    }
    .dropdown-item:hover .mark-read-hover-btn { 
        opacity: 1; 
    }
    .dropdown-item.mark-all-link-wrapper {
        border-top: none; 
        border-bottom: 1px solid #ddd; 
        padding-top: 10px;
        padding-bottom: 10px;
        font-weight: 600;
        color: var(--primary-color) !important;
        background-color: #fcfcfc;
    }
    .dropdown-item.mark-all-link-wrapper:hover {
        background-color: #f0f0f0;
    }

    .sidebar {
        width: var(--sidebar-width);
        min-width: var(--sidebar-width);
        background-color: var(--primary-color);
        color: white;
        padding: 0;
        position: fixed;
        height: 100%;
        top: 0;
        left: 0;
        display: flex;
        flex-direction: column;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2);
        z-index: 1050;
        transition: left 0.3s ease;
    }
    .sidebar-header {
        text-align: center;
        padding: 20px 15px; 
        font-size: 1.2rem;
        font-weight: 700;
        line-height: 1.15; 
        color: #fff;
        border-bottom: 1px solid rgba(255, 255, 255, 0.4);
        margin-bottom: 20px;
    }
    .sidebar-header img { 
        width: 90px;
        height: 90px;
        object-fit: contain; 
        margin-bottom: 15px; 
    }
    .sidebar-header .title { font-size: 1.3rem; line-height: 1.1; }
    .sidebar .nav-link {
        color: white;
        padding: 15px 20px; 
        font-size: 1.1rem; 
        font-weight: 600;
        transition: background-color 0.3s;
        display: flex; 
        align-items: center;
    }
    .sidebar .nav-link:hover, .sidebar .nav-link.active { 
        background-color: var(--primary-color-dark); 
    }
    .sidebar .nav-link.banned { 
        background-color: #5a2624; 
        opacity: 0.8; 
        cursor: pointer; 
        pointer-events: auto; 
    }
    .logout-link { 
        margin-top: auto; 
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
    .logout-link .nav-link { 
        background-color: #C62828 !important; 
        color: white !important;
        transition: background-color 0.3s;
    }
    .logout-link .nav-link:hover {
        background-color: var(--primary-color-dark) !important;
    }
    
    
    .main-wrapper {
        margin-left: var(--sidebar-width); 
        padding: 20px;
        padding-top: calc(var(--header-height) + 20px); 
        flex-grow: 1;
        transition: margin-left 0.3s ease;
    }
    .container {
        background: #fff;
        border-radius: 12px;
        padding: 40px 50px; 
        box-shadow: 0 8px 25px rgba(0,0,0,0.1); 
        max-width: none; 
        width: 95%; 
        margin: 0 auto; 
    }
    h2 { 
        border-bottom: 2px solid var(--primary-color); 
        padding-bottom: 10px; 
        font-size: 2rem; 
        font-weight: 700; 
        color: var(--text-dark);
    }
    .lead {
        font-size: 1.15rem; 
        color: #555;
    }
    
    
    .transaction-list {
        gap: 20px; 
        margin-top: 30px;
        display: flex;
        flex-direction: column;
    }
    .transaction-card {
        display: flex;
        align-items: center; 
        border: 1px solid #ddd;
        border-left: 8px solid var(--primary-color); 
        border-radius: 10px; 
        padding: 20px 25px; 
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); 
        background-color: #ffffff;
        transition: transform 0.2s ease, box-shadow 0.2s;
        flex-wrap: wrap; 
    }
    .transaction-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.12);
    }
    .transaction-card.card-critical {
        border-left-color: var(--danger-color);
        background-color: #fff8f8;
    }

    /
    .card-col-details { display: flex; align-items: center; flex-basis: 35%; min-width: 250px; }
    .card-col-dates { flex-basis: 30%; padding-left: 30px; border-left: 1px solid #eee; min-width: 200px; }
    .card-col-status { flex-basis: 15%; text-align: center; min-width: 120px; }
    .card-col-action { flex-basis: 20%; text-align: right; min-width: 150px; }

    .app-image { width: 55px !important; height: 55px !important; object-fit: contain !important; border-radius: 8px; margin-right: 20px; border: 1px solid #ddd; padding: 5px; }
    .trans-id-text { font-size: 1.1rem; font-weight: 700; color: var(--text-dark); display: block; margin-bottom: 2px; }
    .trans-type-text { font-size: 0.8rem; color: #777; display: block; }
    
    .date-item { 
        display: flex; 
        align-items: center; 
        margin-bottom: 6px; 
        font-size: 0.95rem; 
        color: #555; 
    }
    .date-label { 
        font-weight: 600; 
        width: 140px; 
        white-space: nowrap; 
    }
    .date-value { 
        font-weight: 500; 
        margin-left: 5px; 
    }
    .date-item i {
        color: var(--secondary-color); 
        width: 20px;
    }
    .date-item.expected-date.overdue {
        color: var(--danger-color); 
        font-weight: 700;
    }
    
    .status { 
        display: inline-block; 
        padding: 8px 16px; 
        border-radius: 20px; 
        font-weight: 700; 
        text-transform: uppercase; 
        font-size: 0.85rem; 
        color: white; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
        letter-spacing: 0.5px;
    }
    
  
    .status.waitingforapproval, .status.checking, .status.reserved { 
        background-color: var(--warning-color); 
        color: var(--text-dark); 
    }
    .status.approved, .status.borrowed { 
        background-color: var(--info-color); 
    }
    .status.rejected, .status.overdue, .status.damaged { 
        background-color: var(--danger-color); 
    }
    
    .btn-view-items { 
        background: var(--primary-color); 
        color: white; 
        padding: 10px 20px; 
        font-size: 1rem; 
        border-radius: 50px; 
        border: none; 
        transition: background-color 0.2s, transform 0.2s, box-shadow 0.2s;
        font-weight: 600;
        box-shadow: 0 2px 5px rgba(0,0,0,0.15);
    }
    .btn-view-items:hover {
        background: var(--primary-color-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }

    .alert-info {
        background-color: #e0f7fa;
        color: #00796b;
        border: 1px solid #b2ebf2;
    }


    
    @media (max-width: 992px) {
        
        .menu-toggle { display: block; }
        .sidebar { left: calc(var(--sidebar-width) * -1); transition: left 0.3s ease; box-shadow: none; --sidebar-width: 250px; }
        .sidebar.active { left: 0; box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2); }

       
        .main-wrapper { margin-left: 0; padding-left: 15px; padding-right: 15px; }
        .top-header-bar { left: 0; padding-left: 70px; }
        .container { padding: 25px; }
    }
    
    @media (max-width: 768px) {
    
        .transaction-card {
            flex-direction: column;
            align-items: flex-start;
            padding: 20px;
        }
        .card-col-details {
            flex-basis: 100%;
            min-width: auto;
            margin-bottom: 15px;
        }
        .card-col-dates, .card-col-status, .card-col-action {
            flex-basis: 100%;
            min-width: auto;
            padding: 0;
            margin-top: 10px;
            border-left: none;
            text-align: left;
        }
        .card-col-action {
            text-align: left;
            margin-top: 20px;
        }
        .card-col-action .btn-view-items {
            width: 100%; 
            text-align: center;
        }
        .date-label {
            width: 150px; 
        }
        .card-col-status {
            order: 3; 
        }
    }

    @media (max-width: 576px) {
       
        .top-header-bar {
            padding: 0 15px;
            justify-content: flex-end;
            padding-left: 65px;
        }
        .top-header-bar .notification-bell-container {
             margin-right: 15px;
        }
        
        
        .date-item {
            font-size: 0.9rem;
        }
        .date-label {
            font-size: 0.9rem;
            width: 130px;
        }
    }
</style>
</head>
<body>

<button class="menu-toggle" id="menuToggle" aria-label="Toggle navigation menu">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar">
    <div class="sidebar-header">
        <img src="../wmsu_logo/wmsu.png" alt="WMSU Logo"> 
        <div class="title">
            CSM LABORATORY <br>APPARATUS <br>BORROWING
        </div>
    </div>

    <ul class="nav flex-column mb-4 sidebar-nav">
        <li class="nav-item">
            <a href="student_dashboard.php" class="nav-link active">
                <i class="fas fa-clock fa-fw me-2"></i> Current Activity
            </a>
        </li>
        <li class="nav-item">
            <a href="student_borrow.php" class="nav-link <?= $isBanned ? 'banned' : '' ?>">
                <i class="fas fa-plus-circle fa-fw me-2"></i> Borrow/Reserve <?= $isBanned ? ' (BANNED)' : '' ?>
            </a>
        </li>
        <li class="nav-item">
            <a href="student_return.php" class="nav-link">
                <i class="fas fa-redo fa-fw me-2"></i> Initiate Return
            </a>
        </li>
        <li class="nav-item">
            <a href="student_transaction.php" class="nav-link">
                <i class="fas fa-history fa-fw me-2"></i> Transaction History
            </a>
        </li>
    </ul>

    <div class="logout-link">
        <a href="../pages/logout.php" class="nav-link">
            <i class="fas fa-sign-out-alt fa-fw me-2"></i> Logout
        </a>
    </div>
</div>

<header class="top-header-bar">
    <ul class="navbar-nav mb-2 mb-lg-0">
        <li class="nav-item dropdown notification-bell-container">
            <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" 
               data-bs-toggle="dropdown" aria-expanded="false"> 
                <i class="fas fa-bell fa-lg"></i>
                <span class="badge rounded-pill badge-counter" id="notification-bell-badge" style="display:none;">0</span>
            </a>
            
            <div class="dropdown-menu dropdown-menu-end shadow" 
                 aria-labelledby="alertsDropdown" id="notification-dropdown">
                
                <h6 class="dropdown-header">Your Alerts</h6>
                
                <a class="dropdown-item text-center small text-muted" href="student_transaction.php">View All History</a>
            </div>
            
        </li>
    </ul>
    <a href="student_edit.php" class="edit-profile-link">
        <i class="fas fa-user-circle me-1"></i> Profile
    </a>
</header>
<div class="main-wrapper">
    <div class="container">
        <h2 class="mb-4"><i class="fas fa-clock me-2 text-secondary"></i> Current & Pending Activity</h2>
        <p class="lead text-start">Welcome, <?= htmlspecialchars($_SESSION["user"]["firstname"]) ?>! Below are your active, pending, or overdue
            transactions requiring attention.</p>
        
        <?php if ($overdue_count > 0): ?>
            <div class="alert alert-overdue mt-4 alert-overdue-urgent" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> 
                URGENT: You have <?= $overdue_count ?> item(s) past the expected return date. Please Initiate Return immediately!
                <?php if ($critical_date_passed): ?>
                    <span class="d-block mt-2">
                        Your account is currently eligible for suspension. Please contact staff immediately.
                    </span>
                <? elseif ($next_suspension_date): ?>
                    <span class="d-block mt-2">
                        If not returned by <?= $next_suspension_date->format('M j, Y') ?>, your borrowing privileges may be suspended.
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($transactions)): ?>
            <div class="alert alert-info text-center mt-4">
                <i class="fas fa-info-circle me-2"></i> You have no current or pending transactions. Ready to place a new request?
            </div>
        <?php else: ?>
            <div class="transaction-list">
                <?php
                foreach ($transactions as $t):
                    $status_class = strtolower($t['status']);
                    $is_overdue = $t['is_overdue'] ?? false;
                    
                   
                    $base_status_for_border = $is_overdue ? 'overdue' : $status_class;
                    $card_border_class = 'status-border-' . $base_status_for_border;

                 
                    $card_class = (in_array($status_class, ['damaged']) || $is_overdue) ? 'card-critical ' . $card_border_class : $card_border_class;
                    
                    $apparatusList = $transaction->getFormApparatus($t["id"]); 
                    $firstApparatus = $apparatusList[0] ?? null;
                    
                    $imageFile = $firstApparatus["image"] ?? "default.jpg";
                    $imagePath = "../uploads/apparatus_images/" . $imageFile;
                    $display_status_class = $is_overdue ? 'overdue' : str_replace('_', '', strtolower($t["status"]));
                    $display_status_text = $is_overdue ? 'OVERDUE' : str_replace('_', ' ', $t["status"]);
                    
                    $borrow_date_formatted = (new DateTime($t["borrow_date"]))->format('M j, Y');
                    $expected_return_date_formatted = (new DateTime($t["expected_return_date"]))->format('M j, Y');
                ?>
                    <div class="transaction-card <?= $card_class ?>">
                        
                        <div class="card-col-details">
                            <img src="<?= $imagePath ?>" 
                                alt="Apparatus Image"
                                title="<?= htmlspecialchars($firstApparatus["name"] ?? 'N/A') ?>"
                                class="app-image">
                            <div>
                                <span class="trans-id-text">Transaction ID: <?= htmlspecialchars($t["id"]) ?></span>
                                <span class="trans-type-text"><?= htmlspecialchars(ucfirst($t["form_type"])) ?> Request</span>
                            </div>
                        </div>

                        <div class="card-col-dates">
                            <span class="date-item">
                                <span class="date-label"><i class="fas fa-calendar-check fa-fw me-2"></i> Borrow Date:</span> 
                                <span class="date-value"><?= htmlspecialchars($borrow_date_formatted) ?></span>
                            </span>
                            <span class="date-item expected-date <?= $is_overdue ? 'overdue' : '' ?>">
                                <span class="date-label"><i class="fas fa-calendar-times fa-fw me-2"></i> Expected Return:</span> 
                                <span class="date-value"><?= htmlspecialchars($expected_return_date_formatted) ?></span>
                            </span>
                        </div>

                        <div class="card-col-status">
                            <span class="status <?= $display_status_class ?>">
                                <?= htmlspecialchars(ucfirst($display_status_text)) ?>
                            </span>
                            <?php if ($t['staff_remarks']): ?>
                                <span class="text-muted small d-block mt-1" title="Staff Remark">Remarks: <?= htmlspecialchars(substr($t['staff_remarks'], 0, 30)) . (strlen($t['staff_remarks']) > 30 ? '...' : '') ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="card-col-action">
                            <a href="student_view_items.php?form_id=<?= $t["id"] ?>&context=dashboard" class="btn-view-items">
                                <i class="fas fa-eye me-1"></i> View Details
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  
    window.markSingleAlertAndGo = function(event, element, isHoverClick = false) {
        event.preventDefault();
        
        const $element = $(element);
        const item = isHoverClick ? $element.closest('.dropdown-item') : $element;

        const notifId = item.data('id');
        const linkHref = item.attr('href');
        const isRead = item.data('isRead');
        
        
        if (isHoverClick || isRead === 0) {
             event.preventDefault();
        }

        if (isRead === 0) {
         
            $.post('../api/mark_notification_as_read.php', { notification_id: notifId }, function(response) {
                if (response.success) {

                    if (!isHoverClick) {

                        window.location.href = linkHref;
                    } else {
                      
                        window.location.reload(); 
                    }
                } else {
                    console.error("Failed to mark notification as read.");
                }
            }).fail(function() {
                console.error("API call failed.");
            });
        } else if (isRead === 1 && !isHoverClick) {
          
            window.location.href = linkHref;
        }
    }
    
  
    window.markAllAsRead = function() {
        $.post('../api/mark_notification_as_read.php', { mark_all: true }, function(response) {
            if (response.success) {

                window.location.reload();
            } else {
                console.error("Failed to mark all notifications as read.");
            }
        }).fail(function() {
            console.error("API call failed.");
        });
    };
    
  
    function fetchStudentAlerts() {
        const apiPath = '../api/get_notifications.php'; 
        
        $.getJSON(apiPath, function(response) { 
            
            const unreadCount = response.count; 
            const notifications = response.alerts || []; 
            
            const $badge = $('#notification-bell-badge');
            const $dropdown = $('#notification-dropdown');
            const $header = $dropdown.find('.dropdown-header');
            
           
            const $viewAllLink = $dropdown.find('a[href="student_transaction.php"]').detach(); 
            
           
            $dropdown.children().not($header).not($viewAllLink).remove();
            $badge.text(unreadCount);
            $badge.toggle(unreadCount > 0); 

            let contentToInsert = [];
            
            if (notifications.length > 0) {
                
        
                if (unreadCount > 0) {
                    contentToInsert.push(`
                         <a class="dropdown-item text-center small text-muted dynamic-notif-item mark-all-link-wrapper" href="#" onclick="event.preventDefault(); window.markAllAsRead();">
                             <i class="fas fa-check-double me-1"></i> Mark All ${unreadCount} as Read
                         </a>
                    `);
                }

                notifications.slice(0, 5).forEach(notif => {
                    
                    let iconClass = 'fas fa-info-circle text-secondary'; 
                    if (notif.message.includes('rejected') || notif.message.includes('OVERDUE') || notif.message.includes('URGENT')) {
                          iconClass = 'fas fa-exclamation-triangle text-danger';
                    } else if (notif.message.includes('approved') || notif.message.includes('confirmed in good')) {
                          iconClass = 'fas fa-check-circle text-success';
                    } else if (notif.message.includes('sent') || notif.message.includes('awaiting') || notif.message.includes('Return requested')) {
                          iconClass = 'fas fa-hourglass-half text-warning';
                    }
                    
                    const is_read = notif.is_read == 1;
                    const itemClass = is_read ? 'text-muted' : 'fw-bold';
                    const link = notif.link || 'student_transaction.php';
                    
                    const cleanMessage = notif.message.replace(/\*\*/g, '');
                    const datePart = new Date(notif.created_at.split(' ')[0]).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

                    contentToInsert.push(`
                         <a class="dropdown-item d-flex align-items-center dynamic-notif-item ${itemClass}" 
                             href="${link}" 
                             data-id="${notif.id}"
                             data-is-read="${notif.is_read}"
                             onclick="window.markSingleAlertAndGo(event, this)">
                             <div class="me-3"><i class="${iconClass} fa-fw"></i></div>
                             <div class="flex-grow-1">
                                 <div class="small text-gray-500">${datePart}</div>
                                 <span class="d-block">${cleanMessage}</span>
                             </div>
                             ${notif.is_read == 0 ? 
                                 `<button type="button" class="mark-read-hover-btn" 
                                          title="Mark as Read" 
                                          data-id="${notif.id}"
                                          onclick="event.stopPropagation(); window.markSingleAlertAndGo(event, this, true)">
                                      <i class="fas fa-check-circle"></i>
                                  </button>` : ''}
                         </a>
                     `);
                });
            } else {

                contentToInsert.push(`
                    <a class="dropdown-item text-center small text-muted dynamic-notif-item">No Recent Notifications</a>
                `);
            }
            
            $header.after(contentToInsert.join(''));
            
            $dropdown.append($viewAllLink);
            

        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error("Error fetching student alerts:", textStatus, errorThrown);
            $('#notification-bell-badge').text('0').hide();
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
      
        const path = window.location.pathname.split('/').pop() || 'student_dashboard.php';
        const links = document.querySelectorAll('.sidebar .nav-link');
        
        links.forEach(link => {
            const linkPath = link.getAttribute('href').split('/').pop();
            
            if (linkPath === path) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
        
        fetchStudentAlerts();
        
       
        setInterval(fetchStudentAlerts, 30000); 

       
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.querySelector('.sidebar');
        const mainWrapper = document.querySelector('.main-wrapper');

        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                if (sidebar.classList.contains('active')) {
                     mainWrapper.addEventListener('click', closeSidebarOnce);
                } else {
                     mainWrapper.removeEventListener('click', closeSidebarOnce);
                }
            });
            
            function closeSidebarOnce() {
                 sidebar.classList.remove('active');
                 mainWrapper.removeEventListener('click', closeSidebarOnce);
            }
            
            
            const navLinks = sidebar.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                 link.addEventListener('click', () => {
                     
                     if (window.innerWidth <= 992) {
                          sidebar.classList.remove('active');
                     }
                 });
            });
        }
    });
</script>
</body>
</html>