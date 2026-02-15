<?php

// Utility Functions - UPDATED VERSION 2.0
// Enhanced with categories, prices, and Excel export functionality


require_once '../config/database.php';

// Redirect user based on their role
function redirectBasedOnRole() {
    if ($_SESSION['role'] == 'Admin') {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../user/dashboard.php");
    }
    exit();
}

// Get dashboard URL based on user role
function getDashboardUrl() {
    if ($_SESSION['role'] == 'Admin') {
        return '../admin/dashboard.php';
    } else {
        return '../user/dashboard.php';
    }
}

// Verify user password for sensitive actions
function verifyUserPassword($userId, $password) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch();
            return password_verify($password, $user['password']);
        }
        return false;
    } catch(PDOException $e) {
        return false;
    }
}

// Sanitize input data
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Format date for display
function formatDate($dateString) {
    return date('F j, Y, g:i a', strtotime($dateString));
}

// Get status badge HTML
function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge badge-warning">Pending</span>',
        'approved' => '<span class="badge badge-success">Approved</span>',
        'rejected' => '<span class="badge badge-danger">Rejected</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge badge-secondary">Unknown</span>';
}

// Get all offices for dropdown
function getOffices() {
    global $db;
    
    try {
        $stmt = $db->query("SELECT id, office_name FROM offices ORDER BY office_name");
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

// NEW: Get all categories for dropdown
function getCategories() {
    global $db;
    
    try {
        $stmt = $db->query("SELECT id, category_name FROM item_categories WHERE is_active = 1 ORDER BY category_name");
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

// Get all active items for dropdown (UPDATED with category and price)
function getItems() {
    global $db;
    
    try {
        $stmt = $db->query("
            SELECT i.*, c.category_name 
            FROM items i 
            LEFT JOIN item_categories c ON i.category_id = c.id
            WHERE i.is_active = 1 
            ORDER BY c.category_name, i.item_name
        ");
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

// NEW: Get items by category
function getItemsByCategory($categoryId = null) {
    global $db;
    
    try {
        if ($categoryId) {
            $stmt = $db->prepare("
                SELECT i.*, c.category_name 
                FROM items i 
                LEFT JOIN item_categories c ON i.category_id = c.id
                WHERE i.is_active = 1 AND i.category_id = :category_id
                ORDER BY i.item_name
            ");
            $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);
        } else {
            $stmt = $db->query("
                SELECT i.*, c.category_name 
                FROM items i 
                LEFT JOIN item_categories c ON i.category_id = c.id
                WHERE i.is_active = 1 
                ORDER BY c.category_name, i.item_name
            ");
        }
        $stmt->execute();
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

// Get unit types for a specific item
function getUnitTypes($itemId) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT unit_types FROM items WHERE id = :id");
        $stmt->bindParam(':id', $itemId, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $item = $stmt->fetch();
            $unitTypes = json_decode($item['unit_types'], true);
            return is_array($unitTypes) ? $unitTypes : [];
        }
        return [];
    } catch(PDOException $e) {
        return [];
    }
}

// NEW: Get system setting
function getSystemSetting($key, $default = '') {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = :key");
        $stmt->bindParam(':key', $key);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $result = $stmt->fetch();
            return $result['setting_value'] ?: $default;
        }
        return $default;
    } catch(PDOException $e) {
        return $default;
    }
}

// NEW: Update system setting
function updateSystemSetting($key, $value) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (:key, :value)
            ON DUPLICATE KEY UPDATE setting_value = :value2
        ");
        $stmt->bindParam(':key', $key);
        $stmt->bindParam(':value', $value);
        $stmt->bindParam(':value2', $value);
        return $stmt->execute();
    } catch(PDOException $e) {
        return false;
    }
}

// NEW: Format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// NEW: Check if user can download Excel (only pending requests)
function canDownloadExcel($requestId, $userId) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT status 
            FROM requests 
            WHERE id = :id AND user_id = :user_id AND status = 'pending'
        ");
        $stmt->bindParam(':id', $requestId, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount() == 1;
    } catch(PDOException $e) {
        return false;
    }
}

// NEW: Validate CSV file
function validateCSVFile($file) {
    $errors = [];
    
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        $errors[] = 'No file uploaded';
        return $errors;
    }
    
    // Check file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        $errors[] = 'File size exceeds 5MB limit';
    }
    
    // Check file extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt'])) {
        $errors[] = 'Invalid file type. Only CSV files are allowed';
    }
    
    // Try to open and read the file
    if (($handle = fopen($file['tmp_name'], 'r')) === false) {
        $errors[] = 'Could not open the file';
    } else {
        fclose($handle);
    }
    
    return $errors;
}

// NEW: Parse CSV items
function parseCSVItems($file) {
    $items = [];
    $errors = [];
    $row = 0;
    
    if (($handle = fopen($file['tmp_name'], 'r')) !== false) {
        // Skip header row
        $header = fgetcsv($handle);
        
        // Validate header
        $expectedHeaders = ['item_name', 'description', 'category', 'unit_types', 'price'];
        if (!$header || array_map('strtolower', array_map('trim', $header)) != $expectedHeaders) {
            $errors[] = 'Invalid CSV format. Expected headers: ' . implode(', ', $expectedHeaders);
            fclose($handle);
            return ['items' => [], 'errors' => $errors];
        }
        
        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            
            // Skip empty rows
            if (empty(array_filter($data))) {
                continue;
            }
            
            // Validate required fields
            if (empty(trim($data[0]))) {
                $errors[] = "Row $row: Item name is required";
                continue;
            }
            
            if (empty(trim($data[3]))) {
                $errors[] = "Row $row: Unit types are required";
                continue;
            }
            
            // Parse unit types (comma-separated)
            $unitTypes = array_map('trim', explode(',', $data[3]));
            if (empty($unitTypes)) {
                $errors[] = "Row $row: At least one unit type is required";
                continue;
            }
            
            // Validate price
            $price = floatval($data[4] ?? 0);
            if ($price < 0) {
                $errors[] = "Row $row: Price cannot be negative";
                continue;
            }
            
            $items[] = [
                'item_name' => trim($data[0]),
                'description' => trim($data[1] ?? ''),
                'category' => trim($data[2] ?? ''),
                'unit_types' => $unitTypes,
                'price' => $price
            ];
        }
        
        fclose($handle);
    }
    
    return ['items' => $items, 'errors' => $errors];
}

// NEW: Import items from parsed CSV data
function importCSVItems($items) {
    global $db;
    
    $imported = 0;
    $skipped = 0;
    $errors = [];
    
    foreach ($items as $item) {
        try {
            // Get or create category
            $categoryId = null;
            if (!empty($item['category'])) {
                $stmt = $db->prepare("SELECT id FROM item_categories WHERE category_name = :name");
                $stmt->bindParam(':name', $item['category']);
                $stmt->execute();
                
                if ($stmt->rowCount() == 1) {
                    $category = $stmt->fetch();
                    $categoryId = $category['id'];
                } else {
                    // Create new category
                    $stmt = $db->prepare("INSERT INTO item_categories (category_name) VALUES (:name)");
                    $stmt->bindParam(':name', $item['category']);
                    if ($stmt->execute()) {
                        $categoryId = $db->lastInsertId();
                    }
                }
            }
            
            // Check if item already exists
            $stmt = $db->prepare("SELECT id FROM items WHERE item_name = :name");
            $stmt->bindParam(':name', $item['item_name']);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $skipped++;
                continue; // Skip duplicate items
            }
            
            // Insert item
            $unitTypesJson = json_encode($item['unit_types']);
            $stmt = $db->prepare("
                INSERT INTO items (item_name, description, category_id, unit_types, price)
                VALUES (:name, :desc, :category, :units, :price)
            ");
            $stmt->bindParam(':name', $item['item_name']);
            $stmt->bindParam(':desc', $item['description']);
            $stmt->bindParam(':category', $categoryId, PDO::PARAM_INT);
            $stmt->bindParam(':units', $unitTypesJson);
            $stmt->bindParam(':price', $item['price']);
            
            if ($stmt->execute()) {
                $imported++;
            }
        } catch(PDOException $e) {
            $errors[] = "Error importing {$item['item_name']}: " . $e->getMessage();
        }
    }
    
    return [
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors
    ];
}