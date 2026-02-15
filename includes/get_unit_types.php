<?php

// AJAX Endpoint: Get Unit Types for Item
// Returns JSON array of unit types for a given item ID


require_once '../config/database.php';
require_once '../config/session.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get item ID from request
$item_id = $_GET['item_id'] ?? 0;

if (!$item_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Item ID required']);
    exit();
}

try {
    $stmt = $db->prepare("SELECT unit_types FROM items WHERE id = :id AND is_active = 1");
    $stmt->bindParam(':id', $item_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() == 1) {
        $item = $stmt->fetch();
        $unit_types = json_decode($item['unit_types'], true);
        
        echo json_encode([
            'unit_types' => is_array($unit_types) ? $unit_types : [],
            'price' => floatval($item['price'] ?? 0)
        ]);} else {
        echo json_encode([]);
    }
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>