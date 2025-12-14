<?php
session_start();

require_once "../classes/Transaction.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'staff') {
    header("Location: ../pages/login.php");
    exit;
}

$transaction = new Transaction();


$status_filter = $_GET['status_filter'] ?? 'all'; 
$search_term = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';


$apparatus_ids = $_GET['apparatus_ids'] ?? [];
$apparatus_ids = array_filter(is_array($apparatus_ids) ? $apparatus_ids : []);
$apparatus_ids_str = array_map('strval', $apparatus_ids); 

$backend_apparatus_filter = empty($apparatus_ids_str) ? '' : $apparatus_ids_str[0];

$apparatus_type = ''; 



$allApparatus = $transaction->getAllApparatus() ?? [];


$transactions = $transaction->getAllFormsFiltered(
    $status_filter, 
    $search_term, 
    $start_date, 
    $end_date,
    $backend_apparatus_filter, 
    $apparatus_type 
);

function isOverdue($expected_return_date) {
    if (!$expected_return_date) return false;
    $expected_date = new DateTime($expected_return_date);
    $today = new DateTime();
   
    return $expected_date->format('Y-m-d') < $today->format('Y-m-d');
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - Staff</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> 
    <style>
        
        :root {
            --msu-red: #A40404;
            --msu-red-dark: #820303;
            --msu-blue: #007bff;
            --sidebar-width: 280px; 
            --header-height: 60px;
            --student-logout-red: #C62828;
            --base-font-size: 15px; 
            --main-text: #333;
            --label-bg: #e9ecef;

           
            --status-pending-bg: #ffc10730;
            --status-pending-color: #b8860b;
            --status-borrowed-bg: #cce5ff;
            --status-borrowed-color: #004085;
            --status-overdue-bg: #f8d7da;
            --status-overdue-color: #721c24;
            --status-rejected-bg: #6c757d30;
            --status-rejected-color: #6c757d;
        }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f5f6fa;
            min-height: 100vh;
            display: flex; 
            padding: 0;
            margin: 0;
            font-size: var(--base-font-size);
            overflow-x: hidden;
        }
        
      
        .menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 20px;
            z-index: 1060; 
            background: var(--msu-red);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
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
            padding: 0 30px; 
            z-index: 1000;
        }
        .notification-bell-container {
            position: relative;
            list-style: none; 
            padding: 0;
            margin: 0;
        }
        .notification-bell-container .nav-link {
            padding: 0.5rem 0.5rem;
            color: var(--main-text);
        }
        .notification-bell-container .badge-counter {
            position: absolute;
            top: 5px; 
            right: 0px;
            font-size: 0.8em; 
            padding: 0.35em 0.5em;
            background-color: #ffc107; 
            color: var(--main-text);
            font-weight: bold;
        }
        .dropdown-menu {
            min-width: 300px;
            padding: 0;
        }
        .dropdown-item {
            padding: 10px 15px;
            white-space: normal;
            transition: background-color 0.1s;
        }
        .dropdown-item:hover {
            background-color: #f5f5f5;
        }
        .mark-all-link {
            cursor: pointer;
            color: var(--main-text); 
            font-weight: 600;
            padding: 8px 15px;
            display: block;
            text-align: center;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
        }
   
        
        
        .sidebar {
            width: var(--sidebar-width);
            min-width: var(--sidebar-width);
            height: 100vh;
            background-color: var(--msu-red);
            color: white;
            padding: 0;
            position: fixed; 
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2);
            z-index: 1010;
        }

        .sidebar-header {
            text-align: center;
            padding: 25px 15px; 
            font-size: 1.3rem;
            font-weight: 700;
            line-height: 1.2;
            color: #fff;
            border-bottom: 1px solid rgba(255, 255, 255, 0.4);
            margin-bottom: 25px; 
        }

        .sidebar-header img { 
            max-width: 100px; 
            height: auto; 
            margin-bottom: 15px; 
        }
        .sidebar-header .title { 
            font-size: 1.4rem; 
            line-height: 1.1; 
        }
        .sidebar-nav { flex-grow: 1; }
        .sidebar-nav .nav-link { 
            color: white; 
            padding: 18px 25px; 
            font-size: 1.05rem; 
            font-weight: 600; 
            transition: background-color 0.2s; 
        }
        .sidebar-nav .nav-link:hover { background-color: var(--msu-red-dark); }
        .sidebar-nav .nav-link.active { background-color: var(--msu-red-dark); }
        
       
        .logout-link {
            margin-top: auto; 
            padding: 0; 
            border-top: 1px solid rgba(255, 255, 255, 0.1); 
            width: 100%; 
            background-color: var(--msu-red); 
        }
        .logout-link .nav-link { 
            display: flex; 
            align-items: center;
            justify-content: flex-start; 
            background-color: var(--student-logout-red) !important;
            color: white !important;
            padding: 18px 25px; 
            border-radius: 0; 
            text-decoration: none;
            font-weight: 600; 
            font-size: 1.05rem; 
            transition: background 0.3s;
        }
        .logout-link .nav-link:hover {
            background-color: var(--msu-red-dark) !important;
        }
        

        .main-content {
            margin-left: var(--sidebar-width); 
            flex-grow: 1;
            padding: 30px;
            
            padding-top: calc(var(--header-height) + 30px); 
            width: calc(100% - var(--sidebar-width)); 
        }
        .content-area {
            background: #fff; 
            border-radius: 12px; 
            padding: 30px; 
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        
        .page-header {
            color: #333; 
            border-bottom: 2px solid var(--msu-red);
            padding-bottom: 15px; 
            margin-bottom: 30px; 
            font-weight: 600;
            font-size: 2rem; 
        }
        
      
        .filter-group {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            background-color: #f9f9f9;
        }
        .filter-group .form-label {
            font-weight: 600;
            color: var(--main-text);
            margin-bottom: 5px;
        }
        .filter-actions {
            display: flex;
            gap: 10px;
          
            width: 100%; 
        }
        


        
        .table-responsive {
            border-radius: 8px;
          
            overflow-x: auto; 
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-top: 25px; 
        }
        
        
        .table {
             min-width: 1350px;
             border-collapse: separate; 
        }
        
        .table thead th {
            background: var(--msu-red);
            color: white;
            font-weight: 700;
            vertical-align: middle;
            text-align: center;
            font-size: 0.95rem; 
            padding: 10px 5px; 
            white-space: nowrap;
        }
        
        
        .table tbody td {
            vertical-align: top; 
            font-size: 0.95rem; 
            padding: 8px 4px; 
            text-align: center;
            border-bottom: 1px solid #e9ecef; 
        }
        
        
      
        td.item-cell {
            text-align: left !important;
            padding: 8px 10px !important; 
        }

        .table tbody tr.item-row.first-item-of-group td {
            border-top: 2px solid #ccc; 
        }

        
        .table tbody tr:first-child.item-row.first-item-of-group td {
             border-top: 0; 
        }
        
        .table th:nth-child(1) { width: 5%; }
        .table th:nth-child(2) { width: 15%; } 
        .table th:nth-child(3) { width: 7%; } 
        .table th:nth-child(4) { width: 10%; } 
        .table th:nth-child(5) { width: 8%; } 
        .table th:nth-child(6) { width: 10%; } 
        .table th:nth-child(7) { width: 10%; } 
        .table th:nth-child(8) { width: 15%; } 
        .table th:nth-child(9) { width: 20%; } 

        
     
        .status-tag {
            display: inline-block;
            padding: 4px 8px; 
            border-radius: 4px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.8rem; 
            line-height: 1;
            white-space: nowrap;
            border: 1px solid transparent;
        }

        
        .status-tag.returned { 
             background-color: #e9ecef; 
             color: #333; 
             border-color: #ddd; 
             font-weight: 600;
        }
        .status-tag.damaged { 
             background-color: #dc3545 !important;
             color: white !important; 
             font-weight: 800; 
        }
        
        
        .status-tag.waiting_for_approval, .status-tag.pending, .status-tag.reserved { background-color: var(--status-pending-bg); color: var(--status-pending-color); border-color: #ffeeba; }
        .status-tag.approved, .status-tag.borrowed, .status-tag.checking { background-color: var(--status-borrowed-bg); color: var(--status-borrowed-color); border-color: #b8daff; }
        .status-tag.rejected { background-color: var(--status-rejected-bg); color: var(--status-rejected-color); border-color: #ccc; }
        .status-tag.overdue, .status-tag.returned-late { background-color: var(--status-overdue-bg); color: var(--status-overdue-color); border-color: #f5c6cb; }
        
        
     
        
        @media (max-width: 992px) {

            .menu-toggle { display: block; }
            .sidebar { left: calc(var(--sidebar-width) * -1); transition: left 0.3s ease; box-shadow: none; --sidebar-width: 250px; } 
            .sidebar.active { left: 0; box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2); }
            .main-content { margin-left: 0; padding-left: 15px; padding-right: 15px; padding-top: calc(var(--header-height) + 15px); }
            .top-header-bar { left: 0; padding-left: 70px; padding-right: 15px; }
            .content-area { padding: 20px 15px; }
            .page-header { font-size: 1.8rem; }
            
          
            .filter-group .row > div {
                margin-top: 10px;
            }
            .filter-group .row > div:first-child { margin-top: 0; }
            .filter-actions {
                flex-direction: column; 
            }
        }

        @media (max-width: 768px) {
           
             .table { min-width: auto; }
             .table thead { display: none; }
             .table, .table tbody, .table tr, .table td { display: block; width: 100%; }
             
             .table tbody tr {
                 border: 1px solid #ddd;
                 margin-bottom: 15px;
                 border-radius: 8px;
                 box-shadow: 0 2px 5px rgba(0,0,0,0.05);
                 background-color: white !important;
             }
             
             .table tbody tr.item-row.first-item-of-group td {
                 border-top: 1px solid #ddd !important; 
             }

             .table td {
                 text-align: right !important;
                 padding-left: 50% !important;
                 position: relative;
                 border: none !important;
                 border-bottom: 1px dotted #eee !important;
                 white-space: normal;
             }
             
             .table tbody tr td:last-child {
                 border-bottom: none !important;
             }
             
             .table td::before {
                 content: attr(data-label);
                 position: absolute;
                 left: 10px;
                 width: 45%;
                 padding-right: 10px;
                 white-space: nowrap;
                 font-weight: 600;
                 text-align: left;
                 color: var(--main-text);
                 background-color: transparent;
             }
             
             .table tbody tr:first-child.item-row.first-item-of-group {
                 margin-top: 0; 
             }
             
             .table tbody tr.item-row.first-item-of-group {
                 
                 margin-top: 20px; 
             }

             
             .table tbody tr td:nth-child(1) { 
                 font-size: 1rem;
                 font-weight: 700;
                 text-align: left !important;
                 color: var(--msu-red-dark);
                 border-bottom: 1px solid #ddd !important;
             }
             .table tbody tr td:nth-child(1)::before {
                 content: "Form "; 
                 position: static;
                 display: inline;
                 color: #6c757d;
                 font-size: 0.9rem;
                 font-weight: 600;
             }
             .table tbody tr td:nth-child(2) { 
                 font-size: 1.05rem;
                 font-weight: 700;
                 color: var(--main-text);
                 border-bottom: 2px solid var(--msu-red) !important;
             }
             .table tbody tr td:nth-child(2)::before {
                 font-weight: 700;
                 color: var(--msu-red-dark);
                 background-color: #f8d7da; 
                 padding-left: 0;
                 left: 0;
                 width: 50%;
                 text-align: center;
                 display: flex;
                 align-items: center;
                 justify-content: center;
             }
        }

        @media (max-width: 576px) {
             .main-content { padding: 10px; padding-top: calc(var(--header-height) + 10px); }
             .content-area { padding: 10px; }
             .top-header-bar { padding-left: 65px; }
             .filter-actions { flex-direction: column; } 
        }
        
        .multi-select-container { position: relative; z-index: 10; }
        .multi-select-dropdown {
            position: absolute; width: 100%; max-height: 200px; overflow-y: auto;
            border: 1px solid #ced4da; border-top: none; background: #fff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); z-index: 1001; display: none;
        }
        .multi-select-item { padding: 8px 15px; cursor: pointer; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .multi-select-item:hover { background-color: #f8f9fa; }
        .multi-select-item.selected { background-color: #e2e6ea; font-weight: bold; color: #495057; }
        .selected-tags-container {
            height: auto; min-height: 0; padding: 5px; display: flex; flex-wrap: wrap; gap: 5px;
            align-content: flex-start; background-color: #fff; border: 1px solid #ced4da;
            border-radius: .375rem; margin-top: 5px; overflow: hidden;
        }
        .selected-tags-container.is-empty { padding: 0; margin-top: 0; border-width: 0; height: 0; min-height: 0; }
        .selected-tag {
            display: inline-flex; align-items: center; padding: .25em .6em; font-size: .85em;
            font-weight: 600; line-height: 1; color: #fff; border-radius: .25rem;
            background-color: var(--msu-red);
        }
        .selected-tag-remove { margin-left: .5em; cursor: pointer; font-weight: bold; opacity: 0.8; }
        .selected-tag-remove:hover { opacity: 1; }
        .filter-group .col-md-6 input[type="text"] { margin-top: 0 !important; }
    </style>
</head>
<body>

<button class="menu-toggle" id="menuToggle" aria-label="Toggle navigation menu">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar">
    <div class="sidebar-header">
        <img src="../wmsu_logo/wmsu.png" alt="WMSU Logo" class="img-fluid"> 
        <div class="title">
            CSM LABORATORY <br>APPARATUS BORROWING
        </div>
    </div>
    
    <div class="sidebar-nav nav flex-column">
        <a class="nav-link" href="staff_dashboard.php">
            <i class="fas fa-chart-line fa-fw me-2"></i>Dashboard
        </a>
        <a class="nav-link" href="staff_apparatus.php">
            <i class="fas fa-vials fa-fw me-2"></i>Apparatus List
        </a>
        <a class="nav-link" href="staff_pending.php">
            <i class="fas fa-hourglass-half fa-fw me-2"></i>Pending Approvals
        </a>
        <a class="nav-link active" href="staff_transaction.php">
            <i class="fas fa-list-alt fa-fw me-2"></i>All Transactions
        </a>
        <a class="nav-link" href="staff_report.php">
            <i class="fas fa-print fa-fw me-2"></i>Generate Reports
        </a>
    </div>
    
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
                <span class="badge rounded-pill badge-counter" id="notification-bell-badge" style="display:none;"></span>
            </a>
            <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in" 
                aria-labelledby="alertsDropdown" id="notification-dropdown">
                
                <h6 class="dropdown-header text-center">New Requests</h6>
                
                <div class="dynamic-content-area">
                    <a class="dropdown-item text-center small text-muted dynamic-notif-item">Fetching notifications...</a>
                </div>
                
                <a class="dropdown-item text-center small text-muted" href="staff_pending.php">View All Pending Requests</a>
            </div>
        </li>
    </ul>
    </header>
<div class="main-content">
    <div class="content-area">

        <h2 class="page-header">
            <i class="fas fa-list-alt fa-fw me-2 text-secondary"></i> All Transactions History
        </h2>

        <form method="GET" id="transactionFilterForm" class="filter-group">
            
            <div class="row g-3 mb-3">
                
                <div class="col-md-6 col-sm-6 col-12">
                    <label for="statusFilter" class="form-label">Filter by Status:</label>
                    <select name="status_filter" id="statusFilter" class="form-select form-select-sm">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="waiting_for_approval" <?= $status_filter === 'waiting_for_approval' ? 'selected' : '' ?>>Waiting for Approval</option>
                        <option value="borrowed" <?= $status_filter === 'borrowed' ? 'selected' : '' ?>>Currently Borrowed</option>
                        <option value="reserved" <?= $status_filter === 'reserved' ? 'selected' : '' ?>>Reserved (Approved)</option>
                        <option value="returned" <?= $status_filter === 'returned' ? 'selected' : '' ?>>Returned (Completed)</option>
                        <option value="overdue" <?= $status_filter === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                        <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="damaged" <?= $status_filter === 'damaged' ? 'selected' : '' ?>>Damaged Unit</option>
                    </select>
                </div>
                
                <div class="col-md-6 col-sm-6 col-12">
                    <label for="apparatus_search_input" class="form-label">Filter by Apparatus Name (Multi-select):</label>
                    
                    <div class="multi-select-container">
                        <input type="text" id="apparatus_search_input" class="form-control form-control-sm" placeholder="Search and select apparatus..." autocomplete="off">
                        
                        <div id="selected_apparatus_tags" class="selected-tags-container <?= empty($apparatus_ids_str) ? 'is-empty' : '' ?>">
                            <?php 
                            
                            foreach ($allApparatus as $app) {
                                if (in_array((string)$app['id'], $apparatus_ids_str)) {
                                    echo '<span class="selected-tag" data-id="' . htmlspecialchars($app['id']) . '">' . htmlspecialchars($app['name']) . '<span class="selected-tag-remove">&times;</span></span>';
                                  
                                }
                            }
                            ?>
                        </div>

                        <div id="apparatus_dropdown" class="multi-select-dropdown">
                            <?php foreach ($allApparatus as $app): ?>
                                <div 
                                    class="multi-select-item"
                                    data-id="<?= htmlspecialchars($app['id']) ?>"
                                    data-name="<?= htmlspecialchars($app['name']) ?>"
                                    data-selected="<?= in_array((string)$app['id'], $apparatus_ids_str) ? 'true' : 'false' ?>"
                                >
                                    <?= htmlspecialchars($app['name']) ?>
                                </div>
                            <?php endforeach; ?>
                            <div id="no_apparatus_match" class="multi-select-item text-muted" style="display:none;">No matches found.</div>
                        </div>
                    </div>
                    </div>
                
                </div>

            <div class="row g-3 align-items-end">
                <div class="col-md-3 col-sm-6 col-12">
                    <label for="startDateFilter" class="form-label">Start Date (Borrow Date):</label>
                    <input type="date" name="start_date" id="startDateFilter" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                
                <div class="col-md-3 col-sm-6 col-12">
                    <label for="endDateFilter" class="form-label">End Date (Borrow Date):</label>
                    <input type="date" name="end_date" id="endDateFilter" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>">
                </div>

                <div class="col-md-3 col-sm-6 col-12">
                    <label for="search" class="form-label">Search Student/Apparatus:</label>
                    <input type="text" name="search" id="search" class="form-control form-control-sm" placeholder="Search name or ID..." value="<?= htmlspecialchars($search_term) ?>">
                </div>
                
                <div class="col-md-3 col-sm-6 col-12">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill">
                            <i class="fas fa-filter me-1"></i> Apply Filters
                        </button>
                        <a href="staff_transaction.php" class="btn btn-secondary btn-sm flex-fill">
                            <i class="fas fa-undo me-1"></i> Clear Filters
                        </a>
                    </div>
                </div>

            </div>

        </form>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>Form ID</th>
                        <th>Student Details</th> <th>Type</th>
                        <th>Item Status</th>
                        <th>Borrow Date</th>
                        <th>Expected Return</th>
                        <th>Actual Return</th>
                        <th>Apparatus (Name & Unit)</th> <th>Staff Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                        $previous_form_id = null;
                        
                      
                        $transactions_to_display = [];

                        if (!empty($transactions)): 
                            
                            foreach ($transactions as $trans) {
                                $form_id = $trans['id'];
                                
                              
                                $detailed_items = $transaction->getFormItems($form_id); 
                                
                                
                                if (!empty($apparatus_ids_str)) {
                                    $filtered_items = array_filter($detailed_items, function($item) use ($apparatus_ids_str) {
                                        
                                        return isset($item['apparatus_id']) && in_array((string)$item['apparatus_id'], $apparatus_ids_str);
                                    });
                                    
                                    $detailed_items = array_values($filtered_items);
                                }
                                
                               
                                if (empty($detailed_items)) {
                                    continue;
                                }

                              
                                if (empty($detailed_items) && strtolower($trans['status']) === 'rejected') {
                                    
                                    $detailed_items = [null]; 
                                }
                                
                                
                                $transactions_to_display[$form_id] = [
                                    'form' => $trans,
                                    'items' => $detailed_items
                                ];
                            }
                            
                         
                            foreach ($transactions_to_display as $form_id => $data):
                                $trans = $data['form'];
                                $detailed_items = $data['items'];

                                
                                $form_status = strtolower($trans['status']);

                               
                                foreach ($detailed_items as $index => $unit):
                                    
                                  
                                    $name = htmlspecialchars($unit['name'] ?? 'N/A');
                                    $unit_tag = (isset($unit['unit_id'])) ? ' (Unit ' . htmlspecialchars($unit['unit_id']) . ')' : '';
                                    
                                    
                                    $item_status = strtolower($unit['item_status'] ?? $form_status);
                                    
                                    $item_tag_class = $item_status;
                                    $item_tag_text = ucfirst(str_replace('_', ' ', $item_status));
                                    
                                    
                                    if (($item_status === 'borrowed' || $item_status === 'approved') && isOverdue($trans['expected_return_date'])) {
                                        $item_tag_class = 'overdue';
                                        $item_tag_text = 'Overdue';
                                    } elseif ($item_status === 'returned' && (isset($unit['is_late_return']) && $unit['is_late_return'] == 1)) {
                                         $item_tag_class = 'returned-late';
                                         $item_tag_text = 'Returned (Late)';
                                    } elseif ($item_status === 'damaged') {
                                         $item_tag_class = 'damaged';
                                         $item_tag_text = 'Damaged';
                                    }
                                    
                        
                                    $row_classes = 'item-row';
                                    if ($previous_form_id !== $form_id) {
                                         $row_classes .= ' first-item-of-group';
                                         $previous_form_id = $form_id; 
                                    }

                                    ?>
                                    <tr class="<?= $row_classes ?>">
                                        <td data-label="Form ID:"><?= $trans['id'] ?></td>
                                        <td data-label="Student Details:">
                                            <strong><?= htmlspecialchars($trans['firstname'] ?? '') ?> <?= htmlspecialchars($trans['lastname'] ?? '') ?></strong>
                                            <br>
                                            <small class="text-muted">(ID: <?= htmlspecialchars($trans['user_id']) ?>)</small>
                                        </td>
                                        <td data-label="Type:"><?= ucfirst($trans['form_type']) ?></td>
                                        
                                        <td data-label="Status:">
                                            <span class="status-tag <?= $item_tag_class ?>">
                                                <?= $item_tag_text ?>
                                            </span>
                                        </td>
                                        
                                        <td data-label="Borrow Date:"><?= $trans['borrow_date'] ?: '-' ?></td>
                                        <td data-label="Expected Return:"><?= $trans['expected_return_date'] ?: '-' ?></td>
                                        <td data-label="Actual Return:"><?= $trans['actual_return_date'] ?: '-' ?></td>
                                        
                                        <td data-label="Apparatus (Item):" class="item-cell">
                                            <div class="p-0">
                                                <span><?= $name ?> (x<?= $unit['quantity'] ?? 1 ?>)<?= $unit_tag ?></span>
                                            </div>
                                        </td>
                                        
                                        <td data-label="Staff Remarks:"><?= htmlspecialchars($trans['staff_remarks'] ?? '-') ?></td>
                                    </tr>
                                    <?php 
                                        endforeach; 
                            endforeach; 
                                    ?>
                        <?php else: ?>
                            <tr><td colspan="9" class="text-muted py-3">No transactions found matching the selected filter or search term.</td></tr>
                        <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function isOverdue(expected_return_date) {
        if (!expected_return_date || expected_return_date === 'N/A') return false;
        const expected_date = new Date(expected_return_date);
        const today = new Date();
        today.setHours(0, 0, 0, 0); 
        expected_date.setHours(0, 0, 0, 0);
        return expected_date < today;
    }


    function setMaxDateToToday(elementId) {
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0'); 
        const day = String(today.getDate()).padStart(2, '0');
        const maxDate = `${year}-${month}-${day}`;
        
        const dateInput = document.getElementById(elementId);
        if (dateInput) {
            dateInput.setAttribute('max', maxDate);
        }
    }


    // --- JAVASCRIPT FOR STAFF NOTIFICATION LOGIC (Unchanged) ---
    window.handleNotificationClick = function(event, element, notificationId) {
        event.preventDefault(); 
        const linkHref = element.getAttribute('href');

        $.post('../api/mark_notification_as_read.php', { notification_id: notificationId, role: 'staff' }, function(response) {
            if (response.success) {
                window.location.href = linkHref;
            } else {
                console.error("Failed to mark notification as read.");
                window.location.href = linkHref; 
            }
        }).fail(function() {
            console.error("API call failed.");
            window.location.href = linkHref;
        });
    };

    window.markAllStaffAsRead = function() {
        $.post('../api/mark_notification_as_read.php', { mark_all: true, role: 'staff' }, function(response) {
            if (response.success) {
                window.location.reload(); 
            } else {
                alert("Failed to clear all notifications.");
                console.error("Failed to mark all staff notifications as read.");
            }
        }).fail(function() {
            console.error("API call failed.");
        });
    };
    
    function fetchStaffNotifications() {
        const apiPath = '../api/get_notifications.php'; 

        $.getJSON(apiPath, function(response) { 
            
            const unreadCount = response.count; 
            const notifications = response.alerts || []; 
            
            const $badge = $('#notification-bell-badge');
            const $dropdown = $('#notification-dropdown');
            const $header = $dropdown.find('.dropdown-header');

            if (unreadCount > 0) {
                 $badge.text(unreadCount > 99 ? '99+' : unreadCount).show();
            } else {
                 $badge.text('0').hide();
            }

            const $viewAllLink = $dropdown.find('a[href="staff_pending.php"]').detach();
            
            $dropdown.find('.dynamic-content-area').remove();

            const $dynamicArea = $('<div>').addClass('dynamic-content-area');
            let contentToInsert = [];
            
            if (notifications.length > 0) {
                
                if (unreadCount > 0) {
                        contentToInsert.push(`
                            <a class="dropdown-item text-center small text-muted dynamic-notif-item mark-all-link" href="#" onclick="event.preventDefault(); window.markAllStaffAsRead();">
                                <i class="fas fa-check-double me-1"></i> Mark All ${unreadCount} as Read
                            </a>
                        `);
                }
                
                notifications.slice(0, 5).forEach(notif => {
                    
                    let iconClass = 'fas fa-info-circle text-info'; 
                    if (notif.type.includes('form_pending')) {
                            iconClass = 'fas fa-hourglass-half text-warning';
                    } else if (notif.type.includes('checking')) {
                            iconClass = 'fas fa-redo text-primary';
                    }
                    
                    const itemClass = notif.is_read == 0 ? 'fw-bold' : 'text-muted';

                    contentToInsert.push(`
                        <a class="dropdown-item d-flex align-items-center dynamic-notif-item" 
                            href="${notif.link}"
                            data-id="${notif.id}"
                            onclick="handleNotificationClick(event, this, ${notif.id})">
                            <div class="me-3"><i class="${iconClass} fa-fw"></i></div>
                            <div>
                                <div class="small text-gray-500">${notif.created_at.split(' ')[0]}</div>
                                <span class="${itemClass}">${notif.message}</span>
                            </div>
                        </a>
                    `);
                });
                
            } else {
                contentToInsert.push(`
                    <a class="dropdown-item text-center small text-muted dynamic-notif-item">No New Notifications</a>
                `);
            }
            
            $dynamicArea.html(contentToInsert.join(''));
            $header.after($dynamicArea);
            
            $dropdown.append($viewAllLink);
            
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error("Error fetching staff notifications:", textStatus, errorThrown);
            $('#notification-bell-badge').text('0').hide();
        });
    }

    
    function updateApparatusSelection() {
        const tagsContainer = document.getElementById('selected_apparatus_tags');
        const searchInput = document.getElementById('apparatus_search_input');

        document.querySelectorAll('input[name="apparatus_ids[]"]').forEach(input => input.remove());
        
        tagsContainer.innerHTML = ''; 
        const selectedItems = [];

        document.querySelectorAll('#apparatus_dropdown .multi-select-item').forEach(item => {
            if (item.getAttribute('data-selected') === 'true') {
                const id = item.getAttribute('data-id');
                const name = item.getAttribute('data-name');
                selectedItems.push(id);

                const tag = document.createElement('span');
                tag.className = 'selected-tag';
                tag.setAttribute('data-id', id);
                tag.innerHTML = `${name}<span class="selected-tag-remove">&times;</span>`;
                
                tag.querySelector('.selected-tag-remove').addEventListener('click', function(e) {
                    e.stopPropagation();
                    const dropdownItem = document.querySelector(`#apparatus_dropdown .multi-select-item[data-id="${id}"]`);
                    if (dropdownItem) {
                        dropdownItem.classList.remove('selected');
                        dropdownItem.setAttribute('data-selected', 'false');
                    }
                    updateApparatusSelection();
                    searchInput.focus();
                });

                tagsContainer.appendChild(tag);
            }
        });

        if (selectedItems.length === 0) {
            tagsContainer.classList.add('is-empty');
        } else {
            tagsContainer.classList.remove('is-empty');
            selectedItems.forEach(id => {
                const newHiddenInput = document.createElement('input');
                newHiddenInput.type = 'hidden';
                newHiddenInput.name = 'apparatus_ids[]';
                newHiddenInput.value = id;
                tagsContainer.appendChild(newHiddenInput); 
            });
        }
    }
    
    function filterDropdownItems(searchTerm) {
        let matchFound = false;
        const dropdownItems = document.querySelectorAll('#apparatus_dropdown .multi-select-item');
        const noMatchItem = document.getElementById('no_apparatus_match');
        
        dropdownItems.forEach(item => {
            if (item === noMatchItem) return;
            
            const itemName = item.getAttribute('data-name').toLowerCase();
            const isMatch = itemName.includes(searchTerm.toLowerCase());
            item.style.display = isMatch ? 'block' : 'none';
            if (isMatch) {
                matchFound = true;
            }
        });
        noMatchItem.style.display = matchFound ? 'none' : 'block';
    }

    document.addEventListener('DOMContentLoaded', () => {
        
        setMaxDateToToday('startDateFilter');
        setMaxDateToToday('endDateFilter');
        
        const path = window.location.pathname.split('/').pop() || 'staff_dashboard.php';
        const links = document.querySelectorAll('.sidebar .nav-link');
        
        links.forEach(link => {
            const linkPath = link.getAttribute('href').split('/').pop();
            
            if (linkPath === path) {
                link.classList.add('active');
            } else {
                 link.classList.remove('active');
            }
        });
        
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content'); 

        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                if (window.innerWidth <= 992) {
                    const isActive = sidebar.classList.contains('active');
                    if (isActive) {
                        mainContent.style.pointerEvents = 'none';
                        mainContent.style.opacity = '0.5';
                    } else {
                        mainContent.style.pointerEvents = 'auto';
                        mainContent.style.opacity = '1';
                    }
                }
            });
            
            mainContent.addEventListener('click', () => {
                if (sidebar.classList.contains('active') && window.innerWidth <= 992) {
                    sidebar.classList.remove('active');
                    mainContent.style.pointerEvents = 'auto';
                    mainContent.style.opacity = '1';
                }
            });
            
            sidebar.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }
        
        fetchStaffNotifications();
        setInterval(fetchStaffNotifications, 30000); 
        
        const searchInput = document.getElementById('apparatus_search_input');
        const dropdown = document.getElementById('apparatus_dropdown');
        const dropdownItems = document.querySelectorAll('#apparatus_dropdown .multi-select-item');
        

        updateApparatusSelection(); 

        searchInput.addEventListener('focus', () => {
            dropdown.style.display = 'block';
            searchInput.placeholder = 'Type to filter options...';
            filterDropdownItems(searchInput.value); 
        });
        
        document.addEventListener('click', (e) => {
            const container = document.querySelector('.multi-select-container');
            if (container && !container.contains(e.target)) {
                dropdown.style.display = 'none';
                searchInput.placeholder = 'Search and select apparatus...';
            }
        });
        
        searchInput.addEventListener('keyup', () => {
            filterDropdownItems(searchInput.value);
        });

        dropdownItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.stopPropagation();

                const isSelected = item.getAttribute('data-selected') === 'true';
                
                if (isSelected) {
                    item.classList.remove('selected');
                    item.setAttribute('data-selected', 'false');
                } else {
                    item.classList.add('selected');
                    item.setAttribute('data-selected', 'true');
                }
                
                updateApparatusSelection();
                searchInput.focus(); 
                
                searchInput.value = '';
                filterDropdownItems(''); 
            });
        });
        
        document.querySelector('.filter-actions a[href="staff_transaction.php"]').addEventListener('click', () => {
            document.querySelectorAll('#apparatus_dropdown .multi-select-item').forEach(item => {
                item.classList.remove('selected');
                item.setAttribute('data-selected', 'false');
            });
        });
    });
</script>
</body>
</html>