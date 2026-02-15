<?php

// Direct Export Handler
// Exports all approved items to CSV


require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Check if user is admin
if ($_SESSION['role'] != 'Admin') {
    header("Location: ../user/dashboard.php");
    exit();
}

// Handle export request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date_from = $_POST['date_from'] ?? '';
    $date_to = $_POST['date_to'] ?? '';
    $year = $_POST['year'] ?? '';
    
    // Build WHERE clause - ONLY APPROVED ITEMS
    $where_conditions = ["r.status = 'approved'"];
    $params = [];
    
    if (!empty($date_from)) {
        $where_conditions[] = "DATE(r.date_requested) >= :date_from";
        $params[':date_from'] = $date_from;
    }
    
    if (!empty($date_to)) {
        $where_conditions[] = "DATE(r.date_requested) <= :date_to";
        $params[':date_to'] = $date_to;
    }
    
    // Export current year if export_year is clicked
    if (isset($_POST['export_year']) && !empty($year)) {
        $where_conditions[] = "YEAR(r.date_requested) = :year";
        $params[':year'] = $year;
    }
    
    $where_sql = '';
    if (!empty($where_conditions)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    try {
        // Export approved items with aggregation
        $sql = "
            SELECT 
                i.item_name,
                ri.unit_type,
                SUM(ri.quantity) as total_quantity,
                COUNT(DISTINCT r.office_id) as offices_count,
                GROUP_CONCAT(DISTINCT o.office_name ORDER BY o.office_name SEPARATOR ', ') as offices_list,
                COUNT(DISTINCT r.id) as request_count,
                MIN(r.date_requested) as first_request_date,
                MAX(r.date_requested) as last_request_date
            FROM requests r
            JOIN request_items ri ON r.id = ri.request_id
            JOIN items i ON ri.item_id = i.id
            JOIN offices o ON r.office_id = o.id
            $where_sql
            GROUP BY i.item_name, ri.unit_type
            ORDER BY i.item_name, ri.unit_type
        ";
        
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $data = $stmt->fetchAll();
        
        // Generate filename
        $filename = 'approved_items_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Item Name', 
            'Unit Type', 
            'Total Quantity', 
            'Offices Count',
            'Offices Involved', 
            'Number of Requests',
            'First Request Date',
            'Last Request Date'
        ];
        
        // Generate CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel compatibility
        fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
        
        // Write headers
        fputcsv($output, $headers);
        
        // Write data
        foreach ($data as $row) {
            // Format dates
            $row['first_request_date'] = $row['first_request_date'] ? date('Y-m-d', strtotime($row['first_request_date'])) : '';
            $row['last_request_date'] = $row['last_request_date'] ? date('Y-m-d', strtotime($row['last_request_date'])) : '';
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit();
        
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Error generating export: ' . $e->getMessage();
        header("Location: view_items.php");
        exit();
    }
} else {
    // If accessed directly, redirect
    header("Location: view_items.php");
    exit();
}