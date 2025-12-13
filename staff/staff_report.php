<?php
session_start();
// Include the Transaction class (now BCNF-compliant)
require_once "../classes/Transaction.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'staff') {
    header("Location: ../pages/login.php");
    exit;
}

$transaction = new Transaction();

// --- Determine Mode and Report Type ---
$mode = $_GET['mode'] ?? 'hub';
$report_view_type = $_GET['report_view_type'] ?? 'all';

// --- Helper Functions ---

function isOverdue($expected_return_date) {
    if (!$expected_return_date) return false;
    $expected_date = new DateTime($expected_return_date);
    $today = new DateTime();
    return $expected_date->format('Y-m-d') < $today->format('Y-m-d');
}

/**
 * Helper to generate status badge for history table.
 * This now handles ITEM status display in the new layout.
 */
function getStatusBadgeForItem(string $status, bool $is_late_return = false) {
    $clean_status = strtolower(str_replace(' ', '_', $status));
    $display_status = ucfirst(str_replace('_', ' ', $clean_status));
    
    $color_map = [
        'returned' => 'success', 'approved' => 'info', 'borrowed' => 'primary',
        'overdue' => 'danger', 'damaged' => 'danger', 'rejected' => 'secondary',
        'waiting_for_approval' => 'warning', 'lost' => 'dark'
    ];
    $color = $color_map[$clean_status] ?? 'secondary';
    
    if ($clean_status === 'returned' && $is_late_return) {
        $color = 'danger';
        $display_status = 'Returned (LATE)';
    } elseif ($clean_status === 'damaged') {
        $display_status = 'Damaged';
    } elseif ($clean_status === 'rejected') {
        $display_status = 'Rejected';
    }

    // This badge HTML is what we need to style differently in mobile CSS
    return '<span class="badge bg-' . $color . '">' . $display_status . '</span>';
}

/**
 * NEW FUNCTION: Flattens the forms into item-rows for the Detailed History Table.
 */
function getDetailedItemRows(array $forms, $transaction) {
    $rows = [];
    foreach ($forms as $form) {
        $form_id = $form['id'];
        $detailed_items = $transaction->getFormItems($form_id);

        if (empty($detailed_items)) {
            // Handle early rejected/pending forms that have no items attached yet (or item data is missing)
            $detailed_items = [
                ['name' => '-',
                'quantity' => 1,
                'item_status' => $form['status'], // Use form status as fallback
                'is_late_return' => $form['is_late_return'] ?? 0]
            ];
        }

        // Loop through the items (or the single placeholder if empty)
        foreach ($detailed_items as $index => $item) {
            
            // Determine the Item-Specific Status
            $item_status = strtolower($item['item_status'] ?? $form['status']);
            $is_late_return = $item['is_late_return'] ?? ($form['is_late_return'] ?? 0);
            
            // Build the table row data based on item details
            $row = [
                'form_id' => $form['id'],
                'student_id' => $form['user_id'],
                'borrower_name' => htmlspecialchars($form['firstname'] . ' ' . $form['lastname']),
                'form_type' => htmlspecialchars(ucfirst($form['form_type'])),
                
                'status_badge' => getStatusBadgeForItem($item_status, (bool)$is_late_return),
                
                'borrow_date' => htmlspecialchars($form['borrow_date'] ?? 'N/A'),
                'expected_return' => htmlspecialchars($form['expected_return_date'] ?? 'N/A'),
                'actual_return' => htmlspecialchars($form['actual_return_date'] ?? '-'),
                
                'apparatus' => htmlspecialchars($item['name'] ?? '-') . ' (x' . ($item['quantity'] ?? 1) . ')',
                
                'is_first_item' => ($index === 0),
            ];
            $rows[] = $row;
        }
    }
    return $rows;
}


// --- Data Retrieval and Filtering Logic ---

$allApparatus = $transaction->getAllApparatus();
$allForms = $transaction->getAllForms();

$apparatus_filter_id = $_GET['apparatus_id'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';
$form_type_filter = $_GET['form_type_filter'] ?? '';
$type_filter = $_GET['type_filter'] ?? ''; // Apparatus Type Filter

$filteredForms = $allForms;
$filteredApparatus = $allApparatus; // Initialize apparatus filter

// Apply Apparatus Filtering Logic (for the detailed inventory list)
if ($type_filter) {
    $type_filter_lower = strtolower($type_filter);
    $filteredApparatus = array_filter($filteredApparatus, fn($a) =>
        // CORRECTED: Checking for 'apparatus_type' instead of 'type'
        isset($a['apparatus_type']) && strtolower(trim($a['apparatus_type'])) === $type_filter_lower
    );
}


// Apply Filtering Logic for Forms
if ($start_date) {
    $start_dt = new DateTime($start_date);
    $filteredForms = array_filter($filteredForms, fn($f) => (new DateTime($f['created_at']))->format('Y-m-d') >= $start_dt->format('Y-m-d'));
}
if ($end_date) {
    $end_dt = new DateTime($end_date);
    $filteredForms = array_filter($filteredForms, fn($f) => (new DateTime($f['created_at']))->format('Y-m-d') <= $end_dt->format('Y-m-d'));
}
if ($apparatus_filter_id) {
    $apparatus_filter_id = (string)$apparatus_filter_id;
    $forms_with_apparatus = [];
    foreach ($filteredForms as $form) {
        $items = $transaction->getFormItems($form['id']);
        foreach ($items as $item) {
            if ((string)$item['apparatus_id'] === $apparatus_filter_id) {
                $forms_with_apparatus[] = $form;
                break;
            }
        }
    }
    $filteredForms = $forms_with_apparatus;
}
if ($form_type_filter) {
    $form_type_filter = strtolower($form_type_filter);
    $filteredForms = array_filter($filteredForms, fn($f) => strtolower(trim($f['form_type'])) === $form_type_filter);
}
if ($status_filter) {
    $status_filter = strtolower($status_filter);
    if ($status_filter === 'overdue') {
        $filteredForms = array_filter($filteredForms, fn($f) => ($f['status'] === 'borrowed' || $f['status'] === 'approved') && isOverdue($f['expected_return_date']));
    } elseif ($status_filter === 'late_returns') {
        $filteredForms = array_filter($filteredForms, fn($f) => $f['status'] === 'returned' && ($f['is_late_return'] ?? 0) == 1);
    } elseif ($status_filter === 'returned') {
        $filteredForms = array_filter($filteredForms, fn($f) => $f['status'] === 'returned' && ($f['is_late_return'] ?? 0) == 0);
    } elseif ($status_filter === 'borrowed_reserved') {
        $filteredForms = array_filter($filteredForms, fn($f) => $f['status'] !== 'waiting_for_approval' && $f['status'] !== 'rejected');
    } elseif ($status_filter !== 'all') {
        $filteredForms = array_filter($filteredForms, fn($f) => strtolower(str_replace('_', ' ', $f['status'])) === strtolower(str_replace('_', ' ', $status_filter)));
    }
}


// --- Data Assignments for Hub View ---

// Flatten the filtered forms into item-rows for the detailed report display
$detailedItemRows = getDetailedItemRows($filteredForms, $transaction);

$totalForms = count($allForms);
$pendingForms = count(array_filter($allForms, fn($f) => $f['status'] === 'waiting_for_approval'));
$reservedForms = count(array_filter($allForms, fn($f) => $f['status'] === 'approved'));
$borrowedForms = count(array_filter($allForms, fn($f) => $f['status'] === 'borrowed'));
$returnedForms = count(array_filter($allForms, fn($f) => $f['status'] === 'returned'));
$damagedForms = count(array_filter($allForms, fn($f) => $f['status'] === 'damaged'));

$overdueFormsList = array_filter($allForms, fn($f) =>
    ($f['status'] === 'borrowed' || $f['status'] === 'approved') && isOverdue($f['expected_return_date'])
);
$overdueFormsCount = count($overdueFormsList);

$totalApparatusCount = 0;
$availableApparatusCount = 0;
$damagedApparatusCount = 0;
$lostApparatusCount = 0;
foreach ($allApparatus as $app) {
    $totalApparatusCount += (int)$app['total_stock'];
    $availableApparatusCount += (int)$app['available_stock'];
    $damagedApparatusCount += (int)$app['damaged_stock'];
    $lostApparatusCount += (int)$app['lost_stock'];
}

// Get unique apparatus types for the filter dropdown
// CORRECTED: Getting unique values from 'apparatus_type' column
$uniqueApparatusTypes = array_unique(array_column($allApparatus, 'apparatus_type'));

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Reports Hub - WMSU CSM</title>
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
            --card-background: #fcfcfc;
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

        /* NEW CSS for Mobile Toggle */
        .menu-toggle {
            position: fixed;
            top: 15px;
            left: calc(var(--sidebar-width) + 20px);
            z-index: 1060;
            background: var(--msu-red);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1.2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: left 0.3s ease;
            
            display: flex;
            justify-content: center;
            align-items: center;
            width: 44px;
            height: 44px;
        }
        
        /* NEW CLASS: When sidebar is closed (Desktop collapse mode) */
        .sidebar.closed {
            left: calc(var(--sidebar-width) * -1);
        }
        .sidebar.closed ~ .menu-toggle {
            left: 20px;
        }
        .sidebar.closed ~ .top-header-bar {
            left: 0;
        }
        .sidebar.closed ~ .main-content {
            margin-left: 0;
            width: 100%;
        }

        /* NEW: Backdrop for mobile sidebar */
        .sidebar-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .sidebar.active ~ .sidebar-backdrop {
            display: block;
            opacity: 1;
        }

        /* --- Top Header Bar Styles --- */
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
            transition: left 0.3s ease;
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
        .dropdown-item:hover {
            background-color: #f5f5f5;
        }
        .mark-all-link {
            cursor: pointer;
            color: var(--msu-red);
            font-weight: 600;
            padding: 8px 15px;
            display: block;
            text-align: center;
            border-top: 1px solid #eee;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            min-width: var(--sidebar-width);
            height: 100vh;
            background-color: var(--msu-red);
            color: white;
            padding: 0;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2);
            position: fixed;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            z-index: 1010;
            transition: left 0.3s ease;
        }

        .sidebar-header { text-align: center; padding: 25px 15px; font-size: 1.3rem; font-weight: 700; line-height: 1.2; color: #fff; border-bottom: 1px solid rgba(255, 255, 255, 0.4); margin-bottom: 25px; }
        .sidebar-header img { max-width: 100px; height: auto; margin-bottom: 15px; }
        .sidebar-header .title { font-size: 1.4rem; line-height: 1.1; }
        .sidebar-nav { flex-grow: 1; }
        .sidebar-nav .nav-link { color: white; padding: 18px 25px; font-size: 1.05rem; font-weight: 600; transition: background-color 0.2s; border-left: none !important; }
        .sidebar-nav .nav-link:hover { background-color: var(--msu-red-dark); }
        .sidebar-nav .nav-link.active { background-color: var(--msu-red-dark); }
        
        .logout-link {
            margin-top: auto;
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
            transition: margin-left 0.3s ease, width 0.3s ease;
        }
        .content-area {
            background: #fff;
            border-radius: 12px;
            padding: 30px 40px;
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
        
        
        /* --- Report Hub Specific Styles --- */
        .report-section {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 35px;
            background: #fff;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .report-section h3 {
            color: var(--msu-red);
            padding-bottom: 10px;
            border-bottom: 1px dashed #eee;
            margin-bottom: 25px;
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        /* --- Dashboard Stat Card Styling --- */
        .stat-card {
            display: flex;
            align-items: center;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
            height: 100%;
        }
        .stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            font-size: 1.4rem;
            color: white;
            margin-right: 15px;
            flex-shrink: 0;
        }
        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 3px;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
            white-space: normal;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .bg-light-gray { background-color: #f9f9f9 !important; }
        .border-danger { border-left: 5px solid var(--student-logout-red) !important; }

        .print-stat-table-container { display: none; }
        
        /* --- DETAILED HISTORY STYLES (Screen View) --- */
        
        .table-responsive {
            border-radius: 8px;
            overflow-x: auto;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-top: 25px;
        }
        .table {
            min-width: 1200px;
            border-collapse: separate;
        }
        
        .table thead th {
            /* FIX 3: Change table header color from solid black to dark red/gray */
            background-color: var(--msu-red);
            color: white;
            font-weight: 700;
            vertical-align: middle;
            font-size: 1rem;
            padding: 10px 5px;
            white-space: normal;
            text-align: center;
        }
        .table tbody td {
            vertical-align: top;
            padding: 8px 4px;
            font-size: 1rem;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
        }

        /* --- New Styling for One-Item-Per-Row --- */
        
        /* Apply strong border only to the first row of a new form group */
        .table tbody tr.first-item-of-group td {
            border-top: 2px solid #ccc;
        }

        /* Remove top border on the very first row of the table */
        .table tbody tr:first-child.first-item-of-group td {
            border-top: 0;
        }

        /* Set Item/Apparatus column styling */
        .detailed-items-cell {
            white-space: normal !important;
            word-break: break-word;
            overflow: visible;
            text-align: left !important;
            padding-left: 10px !important;
        }
        
        /* Hide unused styles for multi-line cells */
        .detailed-items-cell .d-flex { display: block !important; }
        .detailed-items-cell .badge { display: none !important; }
        
        /* Define Column Widths for Report Table */
        .table th:nth-child(1) { width: 6%; } /* Form ID */
        .table th:nth-child(2) { width: 8%; } /* Student ID */
        .table th:nth-child(3) { width: 14%; } /* Borrower Name */
        .table th:nth-child(4) { width: 8%; } /* Type */
        .table th:nth-child(5) { width: 10%; } /* Status */
        .table th:nth-child(6) { width: 10%; } /* Borrow Date */
        .table th:nth-child(7) { width: 12%; } /* Expected Return */
        .table th:nth-child(8) { width: 10%; } /* Actual Return */
        .table th:nth-child(9) { width: 22%; } /* Items Borrowed */
        
        /* --- Detailed Inventory Table Column Widths (6 Columns) --- */
        .detailed-inventory-table {
            min-width: 100%;
        }
        .detailed-inventory-table th:nth-child(1) { width: 30%; } /* Apparatus Name */
        .detailed-inventory-table th:nth-child(2) { width: 20%; } /* Type */
        .detailed-inventory-table th:nth-child(3) { width: 15%; } /* Total Stock */
        .detailed-inventory-table th:nth-child(4) { width: 15%; } /* Available Stock */
        .detailed-inventory-table th:nth-child(5) { width: 10%; } /* Damaged Stock (New) */
        .detailed-inventory-table th:nth-child(6) { width: 10%; } /* Lost Stock (New) */


        /* Status Badge Styling (Desktop/Default) */
        .table tbody td .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 14px;
            font-weight: 700;
            text-transform: capitalize;
            font-size: 0.8rem;
            white-space: nowrap;
        }
        
        /* FIX 2: Override badge styling for the Inventory Table to remove circles/badges */
        .detailed-inventory-table tbody td .badge {
            /* This is targeting the badges in the inventory list. Remove background and border-radius. */
            background-color: transparent !important;
            color: var(--main-text) !important;
            font-weight: 500 !important;
            padding: 0 !important;
            border-radius: 0 !important;
            border: none !important;
        }


        /* Hide original styles for detailed table now that we use item rows */
        .detailed-items-cell span {
            display: block;
            line-height: 1.4;
        }

        /* --- PRINT STYLING (Monochrome & Unified) --- */
        
        .print-header { display: none; }
        .wmsu-logo-print { display: none; }

        @media print {
            body { margin: 0 !important; padding: 0 !important; background: white !important; color: #000; }
            .sidebar, .page-header, .filter-form, .print-summary-footer, .top-header-bar { display: none !important; }
            @page { size: A4 portrait; margin: 0.7cm; }
            
            /* Print Header */
            .print-header {
                display: flex !important;
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding-bottom: 15px;
                margin-bottom: 25px;
                border-bottom: 3px solid #000;
            }
            .wmsu-logo-print { display: block !important; width: 70px; height: auto; margin-bottom: 5px; }
            .print-header .logo { font-size: 0.9rem; font-weight: 600; margin-bottom: 2px; color: #555; }
            .print-header h1 { font-size: 1.5rem; font-weight: 700; margin: 0; color: #000; }
            
            /* Unified Report Section Styling */
            .report-section { border: none !important; box-shadow: none !important; padding: 0; margin-bottom: 25px; }
            .report-section h3 { color: #333 !important; border-bottom: 1px solid #ccc !important; padding-bottom: 5px; margin-bottom: 15px; font-size: 1.4rem; font-weight: 600; page-break-after: avoid; text-align: left; }

            /* Summary & Inventory Tables for Print */
            .report-section .row { display: none !important; }
            .print-stat-table-container { display: block !important; margin-bottom: 30px; }
            .print-stat-table { width: 100%; border-collapse: collapse !important; font-size: 0.9rem; }
            .print-stat-table th, .print-stat-table td { border: 1px solid #000 !important; padding: 8px 10px !important; vertical-align: middle; color: #000; font-size: 0.9rem; line-height: 1.2; }
            .print-stat-table th { background-color: #eee !important; font-weight: 700; width: 70%; }
            .print-stat-table td { text-align: center; font-weight: 700; width: 30%; color: #000 !important; }
            .print-stat-table tr:nth-child(even) td { background-color: #f9f9f9 !important; }
            
            /* Detailed History Table Styles */
            body[data-print-view="detailed"] @page { size: A4 landscape; }
            .table thead th, .table tbody td { border: 1px solid #000 !important; padding: 6px !important; color: #000 !important; vertical-align: top !important; font-size: 0.85rem !important; }
            .table thead th { background-color: #eee !important; font-weight: 700 !important; white-space: normal; }
            .table tbody tr:nth-child(odd) { background-color: #f9f9f9 !important; }
            
            /* Custom print row grouping borders */
            .table tbody tr.first-item-of-group td {
                border-top: 1px solid #000 !important;
            }
            .table tbody tr:first-child.first-item-of-group td {
                border-top: 1px solid #000 !important;
            }
            .table tbody tr td {
                border-bottom: 1px solid #000 !important;
            }
            .table tbody tr:last-child td {
                border-bottom: 1px solid #000 !important;
            }
            
            /* Status Badge - Set to Monochrome for print */
            .table tbody td .badge {
                color: #000 !important;
                background-color: transparent !important;
                border: 1px solid #000;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                box-shadow: none !important;
            }
            
            /* --- Detailed Apparatus Inventory List Print Layout (Updated for 6 Columns) --- */
            
            /* Force A4 Portrait for the apparatus list print */
            body[data-print-view="apparatus_list"] @page { size: A4 portrait; }

            .print-detailed-inventory .table-responsive {
                overflow: visible !important; /* Ensure table is fully visible */
            }

            .detailed-inventory-table {
                width: 100% !important;
                border-collapse: collapse !important;
                /* Remove screen-only min-width */
                min-width: unset !important;
            }

            .detailed-inventory-table thead th,
            .detailed-inventory-table tbody td {
                border: 1px solid #000 !important;
                padding: 8px 6px !important;
                font-size: 0.9rem !important;
                text-align: center !important;
                vertical-align: middle !important;
            }
            
            .detailed-inventory-table thead th {
                background-color: #eee !important;
                font-weight: 700 !important;
            }
            
            /* Apparatus Name is left-aligned and bold */
            .detailed-inventory-table tbody tr td:first-child {
                text-align: left !important;
                font-weight: 700;
            }

            /* Striped rows for better legibility */
            .detailed-inventory-table tbody tr:nth-child(odd) {
                background-color: #f9f9f9 !important;
            }
            .detailed-inventory-table tbody tr:nth-child(even) {
                background-color: #ffffff !important;
            }

            /* Force column widths for a balanced view (6 columns) */
            .detailed-inventory-table th:nth-child(1), .detailed-inventory-table td:nth-child(1) { width: 35% !important; } /* Apparatus Name */
            .detailed-inventory-table th:nth-child(2), .detailed-inventory-table td:nth-child(2) { width: 20% !important; } /* Type */
            .detailed-inventory-table th:nth-child(3), .detailed-inventory-table td:nth-child(3) { width: 10% !important; } /* Total Stock */
            .detailed-inventory-table th:nth-child(4), .detailed-inventory-table td:nth-child(4) { width: 15% !important; } /* Available Stock */
            .detailed-inventory-table th:nth-child(5), .detailed-inventory-table td:nth-child(5) { width: 10% !important; } /* Damaged Stock */
            .detailed-inventory-table th:nth-child(6), .detailed-inventory-table td:nth-child(6) { width: 10% !important; } /* Lost Stock */


            /* Re-add mobile table styling fixes for proper horizontal display in print */
            .detailed-inventory-table, 
            .print-detailed-inventory .table thead, 
            .print-detailed-inventory .table tbody, 
            .print-detailed-inventory .table tr { 
                display: table !important; 
                width: 100% !important;
                margin-bottom: 0 !important;
            }
            .print-detailed-inventory .table td { 
                display: table-cell !important;
                padding: 6px !important; 
                position: static !important;
                border: 1px solid #000 !important;
                text-align: center !important;
            }
            
            /* Remove the mobile pseudo-element labels */
            .print-detailed-inventory .table td::before { 
                content: none !important; 
            }
            
            .print-detailed-inventory .table tbody tr {
                border: none !important; 
                box-shadow: none !important;
                padding: 0 !important;
                overflow: visible !important;
            }
            
            /* Conditional Section Display (Crucial for Print Fix) */
            .print-target { display: none; }
            body[data-print-view="summary"] .print-summary,
            body[data-print-view="inventory"] .print-inventory,
            body[data-print-view="detailed"] .print-detailed,
            body[data-print-view="apparatus_list"] .print-detailed-inventory, /* NEW PRINT TYPE */
            body[data-print-view="all"] .print-target { display: block !important; }
            body[data-print-view="summary"] .print-summary .print-stat-table-container,
            body[data-print-view="inventory"] .print-inventory .print-stat-table-container,
            body[data-print-view="all"] .print-summary .print-stat-table-container,
            body[data-print-view="all"] .print-inventory .print-stat-table-container { display: block !important; }

            /* Force monochrome for colors */
            .table * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        /* --- MOBILE RESPONSIVE CSS (Stacked Layout) --- */

        /* 1. New Intermediate Breakpoint for Laptops/Large Tablets */
        @media (max-width: 1200px) {
            /* Adjust Filter Form to stack elements two-up on large tablets/small laptops */
            #report-filter-form .col-md-3 {
                width: 50% !important;
            }
            #report-filter-form .col-md-6 {
                width: 100% !important;
            }
            /* Tighten up stat card view */
            .stat-card {
                padding: 10px 15px;
            }
            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }
            .stat-value {
                font-size: 1.4rem;
            }
            /* Adjust main table for slightly smaller landscape view */
            .table {
                min-width: 1000px;
            }
            .table thead th {
                font-size: 0.9rem;
            }
        }

        /* 2. Tablet Portrait and Smaller Laptop */
        @media (max-width: 992px) {
            /* Desktop/Tablet Toggle Position */
            .menu-toggle {
                display: flex;
                left: 20px;
            }
            .sidebar { left: calc(var(--sidebar-width) * -1); transition: left 0.3s ease; box-shadow: none; --sidebar-width: 250px; }
            .sidebar.active { left: 0; box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2); }
            .main-content { margin-left: 0; padding-left: 15px; padding-right: 15px; padding-top: calc(var(--header-height) + 15px); }
            .top-header-bar { left: 0; padding-left: 70px; padding-right: 15px; }
            .content-area { padding: 20px 15px; }
            .page-header { font-size: 1.8rem; }
            
            /* Filter form full stacking on 992px and below (tablet portrait) */
            #report-filter-form .col-md-3,
            #report-filter-form .col-md-6 { width: 100% !important; margin-top: 15px; }
            #report-filter-form > div:first-child { margin-top: 0 !important; }
            .d-flex.justify-content-between.align-items-center { flex-direction: column; align-items: stretch !important; }
            .d-flex.justify-content-between.align-items-center > * { width: 100%; }
            
            /* Adjust stat cards to stack two-up */
            .report-section .row > div {
                width: 50% !important;
            }
            .report-section .row > div:nth-child(odd) {
                padding-left: 0.375rem !important;
            }
            .report-section .row > div:nth-child(even) {
                padding-right: 0.375rem !important;
            }
            .report-section .row { margin-left: -0.375rem !important; margin-right: -0.375rem !important; }
            
        }

        /* 3. Mobile Screens */
        @media (max-width: 768px) {
            .main-content { padding: 10px; padding-top: calc(var(--header-height) + 10px); }
            .content-area { padding: 10px; }

            /* Report Hub Card Styling: Force full stack */
            .report-section .row > div { width: 100% !important; margin-bottom: 15px; padding: 0 0.75rem !important; }
            .report-section h3 { font-size: 1.3rem; }
            .stat-card { border-left: 5px solid #ddd; }
            .stat-label { font-size: 1rem; }
            .stat-value { font-size: 1.8rem; }
            
            /* Detailed History Table Stacking */
            .table-responsive { overflow-x: hidden; }
            .table { min-width: auto; }
            .table thead { display: none; }
            .table tbody, .table tr, .table td { display: block; width: 100%; }
            
            .table tr {
                margin-bottom: 15px;
                border: 1px solid #ccc;
                /* 1. REMOVE RED BAR: Change to a subtle border */
                border-left: 1px solid #ccc;
                border-radius: 8px;
                background-color: var(--card-background);
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
                padding: 0;
                overflow: hidden;
            }
            
            /* Remove desktop grouping borders on mobile */
            .table tbody tr.first-item-of-group td {
                border-top: none;
            }

            .table td {
                text-align: right !important;
                padding-left: 50% !important;
                position: relative;
                border: none;
                border-bottom: 1px solid #eee;
                padding: 10px 10px !important;
            }
            .table td:last-child { border-bottom: none; }

            /* --- Labels (Clean Look) --- */
            .table td::before {
                content: attr(data-label);
                position: absolute;
                left: 0;
                width: 50%;
                height: 100%;
                padding: 10px;
                white-space: nowrap;
                text-align: left;
                font-weight: 600;
                color: var(--main-text);
                font-size: 0.9rem;
                background-color: transparent;
                border-right: none;
                display: flex;
                align-items: center;
            }
            
            /* --- Custom Headers (Data Hierarchy) --- */
            
            .table tbody tr td:nth-child(1) { /* Form ID - Top of card */
                text-align: left !important;
                padding: 10px !important;
                font-weight: 700;
                color: #6c757d;
                background-color: #f8f8f8;
                border-bottom: 1px solid #ddd;
            }
            .table tbody tr td:nth-child(1)::before {
                content: "Form ";
                background: none;
                border: none;
                color: #6c757d;
                font-size: 0.9rem;
                padding: 0;
                position: static;
                width: auto;
                height: auto;
            }
            
            .table tbody tr td:nth-child(3) {
                font-size: 1rem;
                font-weight: 700;
                color: var(--main-text); /* MODIFIED: Set to dark gray/near black */
            }
            .table tbody tr td:nth-child(3)::before {
                content: "Borrower Name";
                background-color: #f8f8f8;
                color: #000; /* MODIFIED: Changed from var(--msu-red-dark) to black (#000) */
                font-weight: 700;
            }
            
            .table tbody tr td:nth-child(5) {
                font-weight: 700;
                /* 2. REMOVE YELLOW HIGHLIGHT ON STATUS: (CSS block below removed in previous step) */
            }
            
            .table tbody tr td:nth-child(9) {
                font-weight: 700;
            }
            
            .table tbody tr td:nth-child(9) {
                text-align: left !important;
                padding-left: 10px !important;
                border-bottom: none;
            }
            .table tbody tr td:nth-child(9)::before {
                content: "Items Borrowed";
                position: static;
                width: 100%;
                height: auto;
                background: #f8f8f8;
                border-right: none;
                border-bottom: 1px solid #eee;
                display: block;
                padding: 10px;
                margin-bottom: 5px;
            }
            /* FIX: Item details need to stack cleanly inside the full-width block */
            .detailed-items-cell span {
                /* Ensure the apparatus text remains block for a clean list */
                display: block !important;
                padding: 5px 0;
            }
            
            /* 3. REMOVE PILL/CIRCLE AROUND STATUS: Override the badge styling for mobile */
             .table tbody tr td:nth-child(5) .badge {
                border-radius: 0 !important; /* Make it square */
                background-color: transparent !important; /* Remove background color */
                color: #333 !important; /* Set text color to default black/dark gray */
                font-weight: 600 !important; /* Adjust font weight to look more like regular text */
                padding: 0 !important;
                border: none !important; /* Remove border */
            }

            /* Detailed Apparatus List: Fix for Mobile View (Needs to be a stacked card, not a table) */
            .detailed-inventory-table thead { display: none; }
            .detailed-inventory-table tbody, .detailed-inventory-table tr, .detailed-inventory-table td { 
                display: block; 
                width: 100%; 
                padding: 0;
            }
            .detailed-inventory-table tr {
                margin-bottom: 15px;
                border: 1px solid #ccc;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
                padding: 0;
                overflow: hidden;
            }
            .detailed-inventory-table td {
                text-align: right !important;
                padding-left: 50% !important;
                position: relative;
                border: none;
                border-bottom: 1px solid #eee;
                padding: 10px 10px !important;
            }
            .detailed-inventory-table td:last-child { border-bottom: none; }

            /* Apparatus Name - Top Header */
            .detailed-inventory-table tbody tr td:nth-child(1) {
                text-align: left !important;
                padding: 10px !important;
                font-weight: 700;
                font-size: 1.1rem;
                color: var(--msu-red);
                background-color: #f8f8f8;
                border-bottom: 1px solid #ddd;
            }
            .detailed-inventory-table tbody tr td:nth-child(1)::before {
                content: "Apparatus Name:";
                background: none;
                border: none;
                color: #6c757d;
                font-size: 0.9rem;
                padding: 0;
                position: static;
                width: auto;
                height: auto;
                display: block;
            }
            
            /* Mobile labels for other columns */
            .detailed-inventory-table tbody tr td:nth-child(2)::before { content: "Type"; }
            .detailed-inventory-table tbody tr td:nth-child(3)::before { content: "Total Stock"; }
            .detailed-inventory-table tbody tr td:nth-child(4)::before { content: "Available Stock"; }
            .detailed-inventory-table tbody tr td:nth-child(5)::before { content: "Damaged Stock"; } /* Updated */
            .detailed-inventory-table tbody tr td:nth-child(6)::before { content: "Lost Stock"; } /* Added */


        }
        
        @media (max-width: 576px) {
            .top-header-bar { padding-left: 65px; }
        }
        
    </style>
</head>
<body data-print-view="<?= htmlspecialchars($report_view_type) ?>">

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
        <a class="nav-link" href="staff_transaction.php">
            <i class="fas fa-list-alt fa-fw me-2"></i>All Transactions
        </a>
        <a class="nav-link active" href="staff_report.php">
            <i class="fas fa-print fa-fw me-2"></i>Generate Reports
        </a>
    </div>
    
    <div class="logout-link">
        <a href="../pages/logout.php" class="nav-link">
            <i class="fas fa-sign-out-alt fa-fw me-2"></i> Logout
        </a>
    </div>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

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
                
                <div class="dynamic-notif-placeholder">
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
            <i class="fas fa-print fa-fw me-2 text-secondary"></i> Printable Reports Hub
        </h2>
        
        <div class="print-header">
            <img src="../wmsu_logo/wmsu.png" alt="WMSU Logo" class="img-fluid wmsu-logo-print">
            <div class="logo">WESTERN MINDANAO STATE UNIVERSITY</div>
            <div class="logo">CSM LABORATORY APPARATUS BORROWING SYSTEM</div>
            <h1>
            <?php
                if ($report_view_type === 'summary') echo 'Transaction Status Summary Report';
                elseif ($report_view_type === 'inventory') echo 'Apparatus Inventory Stock Report';
                elseif ($report_view_type === 'detailed') echo 'Detailed Transaction History Report';
                elseif ($report_view_type === 'apparatus_list') echo 'Detailed Apparatus Inventory List';
                else echo 'All Reports Hub View';
            ?>
            </h1>
            <p>Generated by Staff: <?= date('F j, Y, g:i a') ?></p>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-4 print-summary-footer">
            <p class="text-muted mb-0">Report Date: <?= date('F j, Y, g:i a') ?></p>
            <button class="btn btn-lg btn-danger btn-print" id="main-print-button">
                <i class="fas fa-print me-2"></i> Print Selected Report
            </button>
        </div>

        <div class="report-section filter-form mb-4">
            <h3><i class="fas fa-filter me-2"></i> Filter Report Data</h3>
            <form method="GET" action="staff_report.php" class="row g-3 align-items-end" id="report-filter-form">
                
                <div class="col-md-3">
                    <label for="report_view_type_select" class="form-label">**Select Report View Type**</label>
                    <select name="report_view_type" id="report_view_type_select" class="form-select">
                        <option value="all" <?= ($report_view_type === 'all') ? 'selected' : '' ?>>View/Print: All Sections (Hub View)</option>
                        <option value="summary" <?= ($report_view_type === 'summary') ? 'selected' : '' ?>>View/Print: Transaction Summary Only</option>
                        <option value="inventory" <?= ($report_view_type === 'inventory') ? 'selected' : '' ?>>View/Print: Apparatus Stock Status</option>
                        <option value="apparatus_list" <?= ($report_view_type === 'apparatus_list') ? 'selected' : '' ?>>View/Print: Detailed Apparatus List</option>
                        <option value="detailed" <?= ($report_view_type === 'detailed') ? 'selected' : '' ?>>View/Print: Detailed History</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="apparatus_id" class="form-label">Specific Apparatus (History Filter)</label>
                    <select name="apparatus_id" id="apparatus_id" class="form-select">
                        <option value="">-- All Apparatus --</option>
                        <?php foreach ($allApparatus as $app): ?>
                            <option
                                value="<?= htmlspecialchars($app['id']) ?>"
                                <?= ((string)$apparatus_filter_id === (string)$app['id']) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($app['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="type_filter" class="form-label">Filter Apparatus Type (List Filter)</label>
                    <select name="type_filter" id="type_filter" class="form-select">
                        <option value="">-- All Types --</option>
                        <?php foreach ($uniqueApparatusTypes as $type): ?>
                            <option
                                value="<?= htmlspecialchars($type) ?>"
                                <?= (strtolower($type_filter) === strtolower($type)) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars(ucfirst($type)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="form_type_filter" class="form-label">Filter Form Type (History Filter)</label>
                    <select name="form_type_filter" id="form_type_filter" class="form-select">
                        <option value="">-- All Form Types --</option>
                        <option value="borrow" <?= (strtolower($form_type_filter) === 'borrow') ? 'selected' : '' ?>>Direct Borrow</option>
                        <option value="reserved" <?= (strtolower($form_type_filter) === 'reserved') ? 'selected' : '' ?>>Reservation Request</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="status_filter" class="form-label">Filter Status (History Filter)</label>
                    <select name="status_filter" id="status_filter" class="form-select">
                        <option value="">-- All Statuses --</option>
                        <option value="waiting_for_approval" <?= ($status_filter === 'waiting_for_approval') ? 'selected' : '' ?>>Pending Approval</option>
                        <option value="approved" <?= ($status_filter === 'approved') ? 'selected' : '' ?>>Reserved (Approved)</option>
                        <option value="borrowed" <?= ($status_filter === 'borrowed') ? 'selected' : '' ?>>Currently Borrowed</option>
                        <option value="borrowed_reserved" <?= ($status_filter === 'borrowed_reserved') ? 'selected' : '' ?>>All Completed/Active Forms (Exclude Pending/Rejected)</option>
                        <option value="overdue" <?= ($status_filter === 'overdue') ? 'selected' : '' ?>>** Overdue **</option>
                        <option value="returned" <?= ($status_filter === 'returned') ? 'selected' : '' ?>>Returned (On Time)</option>
                        <option value="late_returns" <?= ($status_filter === 'late_returns') ? 'selected' : '' ?>>Returned (LATE)</option>
                        <option value="damaged" <?= ($status_filter === 'damaged') ? 'selected' : '' ?>>Damaged/Lost</option>
                        <option value="rejected" <?= ($status_filter === 'rejected') ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date (Form Created)</label>
                    <input type="date" name="start_date" id="start_date" class="form-control"
                                             value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date (Form Created)</label>
                    <input type="date" name="end_date" id="end_date" class="form-control"
                                             value="<?= htmlspecialchars($end_date) ?>">
                </div>

                <div class="col-md-3 d-flex align-items-end justify-content-end">
                    <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                    <a href="staff_report.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
            <p class="text-muted small mt-2 mb-0">Note: Filters apply to either the **Detailed Transaction History** or **Detailed Apparatus List** based on your selected view type.</p>
        </div>
        
        <div class="report-section print-summary print-target" id="report-summary">
            <h3><i class="fas fa-clipboard-list me-2"></i> Transaction Status Summary</h3>
            
            <div class="row g-3">
                <div class="col-xl-3 col-md-4 col-sm-6 col-12">
                    <div class="stat-card bg-light-gray">
                        <div class="stat-icon bg-secondary"><i class="fas fa-file-alt"></i></div>
                        <div class="stat-body">
                            <div class="stat-value text-dark"><?= $totalForms ?></div>
                            <div class="stat-label">Total Forms</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-4 col-sm-6 col-12">
                    <div class="stat-card bg-light-gray">
                        <div class="stat-icon bg-warning"><i class="fas fa-hourglass-half"></i></div>
                        <div class="stat-body">
                            <div class="stat-value text-warning"><?= $pendingForms ?></div>
                            <div class="stat-label">Pending Approval</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-4 col-sm-6 col-12">
                    <div class="stat-card bg-light-gray">
                        <div class="stat-icon bg-info"><i class="fas fa-book-reader"></i></div>
                        <div class="stat-body">
                            <div class="stat-value text-info"><?= $reservedForms ?></div>
                            <div class="stat-label">Reserved (Approved)</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-4 col-sm-6 col-12">
                    <div class="stat-card bg-light-gray">
                        <div class="stat-icon bg-primary"><i class="fas fa-hand-holding"></i></div>
                        <div class="stat-body">
                            <div class="stat-value text-primary"><?= $borrowedForms ?></div>
                            <div class="stat-label">Currently Borrowed</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-4 col-sm-6 col-12">
                    <div class="stat-card bg-light-gray border-danger">
                        <div class="stat-icon bg-danger"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="stat-body">
                            <div class="stat-value text-danger"><?= $overdueFormsCount ?></div>
                            <div class="stat-label">Overdue (Active)</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-4 col-sm-6 col-12">
                    <div class="stat-card bg-light-gray">
                        <div class="stat-icon bg-success"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-body">
                            <div class="stat-value text-success"><?= $returnedForms ?></div>
                            <div class="stat-label">Successfully Returned</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-4 col-sm-6 col-12">
                    <div class="stat-card bg-light-gray">
                        <div class="stat-icon bg-dark-monochrome"><i class="fas fa-times-circle"></i></div>
                        <div class="stat-body">
                            <div class="stat-value text-dark"><?= $damagedForms ?></div>
                            <div class="stat-label">Damaged/Lost Forms</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="print-stat-table-container">
                <table class="print-stat-table">
                    <thead>
                        <tr><th>Status Description</th><th>Count</th></tr>
                    </thead>
                    <tbody>
                        <tr><th>Total Forms</th><td class="text-dark"><?= $totalForms ?></td></tr>
                        <tr><th>Pending Approval</th><td class="text-warning"><?= $pendingForms ?></td></tr>
                        <tr><th>Reserved (Approved)</th><td class="text-info"><?= $reservedForms ?></td></tr>
                        <tr><th>Currently Borrowed</th><td class="text-primary"><?= $borrowedForms ?></td></tr>
                        <tr><th>Overdue (Active)</th><td class="text-danger"><?= $overdueFormsCount ?></td></tr>
                        <tr><th>Successfully Returned</th><td class="text-success"><?= $returnedForms ?></td></tr>
                        <tr><th>Damaged/Lost Forms</th><td class="text-danger"><?= $damagedForms ?></td></tr>
                    </tbody>
                </table>
            </div>
            
        </div>
        
        <div class="report-section print-inventory print-target" id="report-inventory">
            <h3><i class="fas fa-flask me-2"></i> Apparatus Inventory Stock Status (Summary)</h3>
            
            <div class="row g-3">
                <div class="col-md-4 col-12">
                    <div class="stat-card bg-light-gray">
                        <div class="stat-icon bg-secondary"><i class="fas fa-boxes"></i></div>
                        <div class="stat-body">
                            <div class="stat-value text-dark"><?= $totalApparatusCount ?></div>
                            <div class="stat-label">Total Inventory Units</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-12">
                    <div class="stat-card bg-light-gray">
                        <div class="stat-icon bg-success"><i class="fas fa-box-open"></i></div>
                        <div class="stat-body">
                            <div class="stat-value text-success"><?= $availableApparatusCount ?></div>
                            <div class="stat-label">Units Available for Borrowing</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-12">
                    <div class="stat-card bg-light-gray">
                        <div class="stat-icon bg-danger"><i class="fas fa-trash-alt"></i></div>
                        <div class="stat-body">
                            <div class="stat-value text-danger"><?= $damagedApparatusCount + $lostApparatusCount ?></div>
                            <div class="stat-label">Units Unavailable (Damaged/Lost)</div>
                        </div>
                    </div>
                </div>
            </div>
            <p class="text-muted small mt-3">*Note: Units marked Unavailable are not available for borrowing until their stock count is adjusted.</p>
            
            <div class="print-stat-table-container">
                <table class="print-stat-table">
                    <thead>
                        <tr><th>Inventory Metric</th><th>Units</th></tr>
                    </thead>
                    <tbody>
                        <tr><th>Total Inventory Units</th><td class="text-dark"><?= $totalApparatusCount ?></td></tr>
                        <tr><th>Units Available for Borrowing</th><td class="text-success"><?= $availableApparatusCount ?></td></tr>
                        <tr><th>Units Unavailable (Damaged/Lost)</th><td class="text-danger"><?= $damagedApparatusCount + $lostApparatusCount ?></td></tr>
                    </tbody>
                </table>
                <p class="text-muted small mt-3">*Note: Units marked Unavailable are not available for borrowing until their stock count is adjusted.</p>
            </div>
        </div>

        <div class="report-section print-detailed-inventory print-target" id="report-apparatus-list">
            <h3><i class="fas fa-list-ul me-2"></i> Detailed Apparatus List (Filtered: <?= count($filteredApparatus) ?> items)</h3>
            <div class="table-responsive">
                <table class="table table-striped table-sm align-middle detailed-inventory-table">
                    <thead>
                        <tr>
                            <th>Apparatus Name</th>
                            <th>Type</th>
                            <th>Total Stock</th>
                            <th>Available Stock</th>
                            <th>Damaged Stock</th>
                            <th>Lost Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($filteredApparatus)):
                            foreach ($filteredApparatus as $app): ?>
                                <tr>
                                    <td data-label="Apparatus Name" class="text-start"><strong><?= htmlspecialchars($app['name']) ?></strong></td>
                                    <td data-label="Type"><?= htmlspecialchars(ucfirst($app['apparatus_type'] ?? 'N/A')) ?></td>
                                    <td data-label="Total Stock"><?= $app['total_stock'] ?></td>
                                    <td data-label="Available Stock">
                                        <?= $app['available_stock'] ?>
                                    </td>
                                    <td data-label="Damaged Stock">
                                        <?= (int)$app['damaged_stock'] ?>
                                    </td>
                                    <td data-label="Lost Stock">
                                        <?= (int)$app['lost_stock'] ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-muted text-center">No apparatus match the current filter criteria.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="report-section print-detailed print-target" id="report-detailed-table">
            <h3><i class="fas fa-history me-2"></i> Detailed Transaction History (Filtered: <?= count($detailedItemRows) ?> Items)</h3>
            <div class="table-responsive">
                <table class="table table-striped table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Form ID</th>
                            <th>Student ID</th>
                            <th>Borrower Name</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Borrow Date</th>
                            <th>Expected Return</th>
                            <th>Actual Return</th>
                            <th>Items Borrowed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($detailedItemRows)):
                            foreach ($detailedItemRows as $row): ?>
                                <tr class="<?= $row['is_first_item'] ? 'first-item-of-group' : '' ?>">
                                    <td data-label="Form ID:"><?= $row['form_id'] ?></td>
                                    <td data-label="Student ID:"><?= $row['student_id'] ?></td>
                                    <td data-label="Borrower Name:">
                                        <strong style="color: black !important;"><?= $row['borrower_name'] ?></strong>
                                    </td>
                                    <td data-label="Type:"><?= $row['form_type'] ?></td>
                                    <td data-label="Status:"><?= $row['status_badge'] ?></td>
                                    <td data-label="Borrow Date:"><?= $row['borrow_date'] ?></td>
                                    <td data-label="Expected Return:"><?= $row['expected_return'] ?></td>
                                    <td data-label="Actual Return:"><?= $row['actual_return'] ?></td>
                                    <td data-label="Items Borrowed:" class="detailed-items-cell text-start">
                                        <span><?= $row['apparatus'] ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="9" class="text-muted text-center">No transactions match the current filter criteria.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- JAVASCRIPT FOR STAFF NOTIFICATION LOGIC ---
    // Function to handle clicking a notification link
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

    // Function to mark ALL staff notifications as read
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
    
    // Function to fetch the count and populate the dropdown
    function fetchStaffNotifications() {
        const apiPath = '../api/get_notifications.php';

        $.getJSON(apiPath, function(response) {
            
            const unreadCount = response.count;
            const notifications = response.alerts || [];
            
            const $badge = $('#notification-bell-badge');
            const $dropdown = $('#notification-dropdown');
            
            const $viewAllLink = $dropdown.find('a[href="staff_pending.php"]').detach();
            const $header = $dropdown.find('.dropdown-header');
            
            $dropdown.find('.dynamic-notif-placeholder').find('.dynamic-notif-item').remove();
            $dropdown.find('.mark-all-btn-wrapper').remove();
            
            $badge.text(unreadCount);
            $badge.toggle(unreadCount > 0);
            
            
            if (notifications.length > 0) {
                
                // Clear the placeholder
                $dropdown.find('.dynamic-notif-placeholder').empty();
                
                if (unreadCount > 0) {
                     $dropdown.find('.dynamic-notif-placeholder').append(`
                                     <a class="dropdown-item text-center small text-muted dynamic-notif-item mark-all-btn-wrapper" href="#" onclick="event.preventDefault(); window.markAllStaffAsRead();">
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

                    $dropdown.find('.dynamic-notif-placeholder').append(`
                        <a class="dropdown-item d-flex align-items-center dynamic-notif-item"
                            href="${notif.link}"
                            data-id="${notif.id}"
                            onclick="handleNotificationClick(event, this, ${notif.id})">
                            <div class="me-3"><i class="${iconClass} fa-fw"></i></div>
                            <div>
                                <div class="small text-gray-500">${notif.created_at.split(' ')[0]}</div>
                                <span class="${itemClass} d-block">${notif.message}</span>
                            </div>
                        </a>
                    `);
                });
            } else {
                $dropdown.find('.dynamic-notif-placeholder').html(`
                    <a class="dropdown-item text-center small text-muted dynamic-notif-item">No New Notifications</a>
                `);
            }
            
            $dropdown.append($viewAllLink);
            

        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error("Error fetching staff notifications:", textStatus, errorThrown);
            $('#notification-bell-badge').text('0').hide();
        });
    }
    // --- END JAVASCRIPT FOR STAFF NOTIFICATION LOGIC ---


    // --- Print Fix Logic ---
    function handlePrint() {
        const viewType = document.getElementById('report_view_type_select').value;
        
        // 1. Set the print mode immediately (before window.print)
        document.body.setAttribute('data-print-view', viewType);
        
        // 2. Trigger the print dialogue
        window.print();

        // 3. Use setTimeout to defer the cleanup.
        setTimeout(() => {
            document.body.removeAttribute('data-print-view');
        }, 100);
    }
    
    // --- Update Hub View Logic (For Screen Display) ---
    function updateHubView() {
        const viewType = document.getElementById('report_view_type_select').value;
        const sections = ['summary', 'inventory', 'apparatus-list', 'detailed-table'];
        
        // Hide all sections first
        sections.forEach(id => {
            const section = document.getElementById(`report-${id}`);
            if (section) section.style.display = 'none';
        });

        // Show relevant sections based on select box value
        if (viewType === 'all') {
            sections.forEach(id => {
                const section = document.getElementById(`report-${id}`);
                if (section) section.style.display = 'block';
            });
            // Show print button
            document.getElementById('main-print-button').style.display = 'block';
            document.getElementById('main-print-button').textContent = 'Print All Sections (Hub View)';
        } else if (viewType === 'summary') {
            document.getElementById('report-summary').style.display = 'block';
            document.getElementById('main-print-button').style.display = 'block';
            document.getElementById('main-print-button').textContent = 'Print Transaction Summary';
        } else if (viewType === 'inventory') {
            document.getElementById('report-inventory').style.display = 'block';
            document.getElementById('main-print-button').style.display = 'block';
            document.getElementById('main-print-button').textContent = 'Print Inventory Stock Status';
        } else if (viewType === 'apparatus_list') {
            document.getElementById('report-apparatus-list').style.display = 'block';
            document.getElementById('main-print-button').style.display = 'block';
            document.getElementById('main-print-button').textContent = 'Print Detailed Apparatus List';
        } else if (viewType === 'detailed') {
            document.getElementById('report-detailed-table').style.display = 'block';
            document.getElementById('main-print-button').style.display = 'block';
            document.getElementById('main-print-button').textContent = 'Print Filtered Detailed History';
        }
    }


    document.addEventListener('DOMContentLoaded', () => {
        // --- Sidebar Activation ---
        const reportsLink = document.querySelector('a[href="staff_report.php"]');
        if (reportsLink) {
            document.querySelectorAll('.sidebar .nav-link').forEach(link => link.classList.remove('active'));
            reportsLink.classList.add('active');
        }
        
        // --- Mobile Toggle Logic ---
        const menuToggle = document.querySelector('.menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        const sidebarBackdrop = document.querySelector('.sidebar-backdrop');
        
        // Function to set the initial state (open on desktop, closed on mobile)
        function setInitialState() {
            if (window.innerWidth > 992) {
                // Ensure it starts open on desktop
                sidebar.classList.remove('closed');
                sidebar.classList.remove('active');
                if (sidebarBackdrop) sidebarBackdrop.style.display = 'none';
            } else {
                // Ensure it starts hidden on mobile
                sidebar.classList.remove('closed');
                sidebar.classList.remove('active');
                if (sidebarBackdrop) sidebarBackdrop.style.display = 'none';
            }
        }
        
        // Function to toggle the state of the sidebar and layout
        function toggleSidebar() {
            if (window.innerWidth <= 992) {
                // Mobile behavior: Toggle 'active' class for overlay/menu
                sidebar.classList.toggle('active');
                if (sidebarBackdrop) {
                    sidebarBackdrop.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
                }
            } else {
                // Desktop behavior: Toggle 'closed' class to collapse it
                sidebar.classList.toggle('closed');
            }
        }

        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', toggleSidebar);
            
            // Backdrop click handler (only for mobile overlay)
            if (sidebarBackdrop) {
                sidebarBackdrop.addEventListener('click', () => {
                    sidebar.classList.remove('active');
                    sidebarBackdrop.style.display = 'none';
                });
            }
            
            // Hide mobile overlay when navigating
            const navLinks = sidebar.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                    link.addEventListener('click', () => {
                       if (window.innerWidth <= 992) {
                           sidebar.classList.remove('active');
                           sidebarBackdrop.style.display = 'none';
                       }
                    });
            });
            
            // Handle window resize (switching between mobile/desktop layouts)
            window.addEventListener('resize', setInitialState);

            // Set initial state on load
            setInitialState();
        }

        // --- Event Listeners and Initial Load ---
        
        // Attach event listener for dynamic changes in the Hub View
        const select = document.getElementById('report_view_type_select');
        if (select) select.addEventListener('change', updateHubView);
        
        // Attach print handler to button
        document.getElementById('main-print-button').addEventListener('click', handlePrint);
        
        // Set initial view state based on PHP variable
        updateHubView();
        
        // --- Notification Initialization ---
        fetchStaffNotifications();
        setInterval(fetchStaffNotifications, 30000);
    });
</script>
</body>
</html>