<?php
/**
 * Items Management - UPDATED VERSION 2.0
 * Features: Categories, Prices, CSV Import
 * Add, edit, delete items with category and price management
 */

require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Check if user is admin
if ($_SESSION['role'] != 'Admin') {
    header("Location: ../user/dashboard.php");
    exit();
}

$page_title = 'Manage Items';
global $db;
$error = '';
$success = '';

// Handle actions
$action = $_GET['action'] ?? '';
$item_id = $_GET['id'] ?? 0;

// Default unit types
$default_unit_types = ['units','reams', 'pcs', 'boxes', 'packs', 'sets', 'dozens', 'kg', 'liters', 'meters', 'rolls', 'bottles', 'can','gallons'];

// Get categories for dropdown
$categories = getCategories();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_name = trim($_POST['item_name']);
    $description = trim($_POST['description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $unit_types = $_POST['unit_types'] ?? [];
    $price = floatval($_POST['price'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate
    if (empty($item_name)) {
        $error = 'Item name is required';
    } elseif (count($unit_types) === 0) {
        $error = 'At least one unit type is required';
    } elseif ($price < 0) {
        $error = 'Price cannot be negative';
    } else {
        try {
            // Prepare unit types as JSON
            $unit_types_json = json_encode($unit_types);
            
            if ($action == 'edit' && $item_id > 0) {
                // Update existing item
                $stmt = $db->prepare("
                    UPDATE items 
                    SET item_name = ?, description = ?, category_id = ?, 
                        unit_types = ?, price = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$item_name, $description, $category_id, $unit_types_json, $price, $is_active, $item_id]);
                $success = 'Item updated successfully';
            } else {
                // Add new item
                // Check if item already exists
                $check = $db->prepare("SELECT id FROM items WHERE item_name = ?");
                $check->execute([$item_name]);
                if ($check->fetch()) {
                    $error = 'Item with this name already exists';
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO items (item_name, description, category_id, unit_types, price, is_active, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([$item_name, $description, $category_id, $unit_types_json, $price, $is_active]);
                    $item_id = $db->lastInsertId();
                    $success = 'Item added successfully';
                }
            }
            
            if ($success) {
                $_SESSION['success'] = $success;
                header("Location: view_items.php");
                exit();
            }
            
        } catch(PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Load item data for editing
$item = null;
if ($action == 'edit' && $item_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM items WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();
        
        if (!$item) {
            $error = 'Item not found';
            $action = 'add';
        }
    } catch(PDOException $e) {
        $error = 'Error loading item: ' . $e->getMessage();
    }
} elseif ($action == 'delete' && $item_id > 0) {
    // Handle delete
    try {
        // Check if item is used in any requests
        $check = $db->prepare("
            SELECT COUNT(*) as count 
            FROM request_items 
            WHERE item_id = ?
        ");
        $check->execute([$item_id]);
        $result = $check->fetch();
        
        if ($result['count'] > 0) {
            $error = 'Cannot delete item. It is used in ' . $result['count'] . ' request(s).';
            $_SESSION['error'] = $error;
            header("Location: view_items.php");
            exit();
        } else {
            $stmt = $db->prepare("DELETE FROM items WHERE id = ?");
            $stmt->execute([$item_id]);
            $_SESSION['success'] = 'Item deleted successfully';
            header("Location: view_items.php");
            exit();
        }
    } catch(PDOException $e) {
        $error = 'Error deleting item: ' . $e->getMessage();
        $_SESSION['error'] = $error;
        header("Location: view_items.php");
        exit();
    }
}

// If no action specified, redirect to view
if (empty($action) || $action == 'view') {
    header("Location: view_items.php");
    exit();
}

// Set page title based on action
if ($action == 'edit') {
    $page_title = 'Edit Item';
    $page_subtitle = 'Update item details';
} else {
    $page_title = 'Add New Item';
    $page_subtitle = 'Create a new item for requests';
}

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3><i class="fas fa-box"></i> <?php echo $page_title; ?></h3>
                <span class="header-subtitle"><?php echo $page_subtitle; ?></span>
            </div>
            <div>
                <a href="view_items.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Items List
                </a>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="itemForm">
            <div class="form-row">
                <div class="form-group">
                    <label for="item_name"><i class="fas fa-tag"></i> Item Name *</label>
                    <input type="text" 
                           id="item_name" 
                           name="item_name" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($item['item_name'] ?? ''); ?>"
                           required 
                           maxlength="100"
                           placeholder="Enter item name">
                    <small class="form-text">Enter a descriptive name for the item</small>
                </div>
                
                <div class="form-group">
                    <label for="category_id"><i class="fas fa-folder"></i> Category *</label>
                    <select id="category_id" name="category_id" class="form-control" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"
                                    <?php echo (isset($item['category_id']) && $item['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text">Select the category for this item</small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="description"><i class="fas fa-align-left"></i> Description</label>
                    <textarea id="description" 
                              name="description" 
                              class="form-control" 
                              rows="3"
                              placeholder="Optional description or specifications"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                    <small class="form-text">Optional: Add details, specifications, or notes</small>
                </div>
                
                <div class="form-group">
                    <label for="price"><i class="fas fa-peso-sign"></i> Price per Unit *</label>
                    <input type="number" 
                           id="price" 
                           name="price" 
                           class="form-control" 
                           step="0.01"
                           min="0"
                           value="<?php echo number_format($item['price'] ?? 0, 2, '.', ''); ?>"
                           required
                           placeholder="0.00">
                    <small class="form-text">Enter the price per unit (e.g., per piece, per ream)</small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-balance-scale"></i> Available Unit Types *</label>
                    <div class="unit-types-container">
                        <div class="unit-types-list">
                            <?php
                            // Get current unit types
                            $current_units = [];
                            if (!empty($item['unit_types'])) {
                                $current_units = json_decode($item['unit_types'], true);
                            }
                            
                            // Display default unit types
                            foreach ($default_unit_types as $unit):
                                $checked = in_array($unit, $current_units) ? 'checked' : '';
                            ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           name="unit_types[]" 
                                           value="<?php echo htmlspecialchars($unit); ?>" 
                                           id="unit_<?php echo strtolower($unit); ?>"
                                           <?php echo $checked; ?>>
                                    <label class="form-check-label" for="unit_<?php echo strtolower($unit); ?>">
                                        <?php echo $unit; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            
                            <!-- Custom unit type -->
                            <div class="custom-unit mt-2">
                                <div class="input-group">
                                    <input type="text" 
                                           id="custom_unit" 
                                           class="form-control" 
                                           placeholder="Add custom unit type">
                                    <div class="input-group-append">
                                        <button type="button" 
                                                class="btn btn-outline-secondary" 
                                                id="addCustomUnit">
                                            <i class="fas fa-plus"></i> Add
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Display current custom units -->
                            <?php if (!empty($current_units)): ?>
                                <?php foreach ($current_units as $unit): ?>
                                    <?php if (!in_array($unit, $default_unit_types)): ?>
                                        <div class="custom-unit-tag">
                                            <input type="checkbox" 
                                                   name="unit_types[]" 
                                                   value="<?php echo htmlspecialchars($unit); ?>" 
                                                   id="custom_<?php echo md5($unit); ?>" 
                                                   checked>
                                            <label for="custom_<?php echo md5($unit); ?>">
                                                <?php echo htmlspecialchars($unit); ?>
                                                <button type="button" class="remove-unit" data-unit="<?php echo htmlspecialchars($unit); ?>">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <small class="form-text">Select or add unit types available for this item</small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <div class="form-check">
                        <input class="form-check-input" 
                               type="checkbox" 
                               name="is_active" 
                               id="is_active" 
                               value="1"
                               <?php echo (isset($item['is_active']) && $item['is_active'] == 0) ? '' : 'checked'; ?>>
                        <label class="form-check-label" for="is_active">
                            <i class="fas fa-toggle-on"></i> Item is Active
                        </label>
                    </div>
                    <small class="form-text">Inactive items won't appear in request forms</small>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> 
                    <?php echo $action == 'edit' ? 'Update Item' : 'Add Item'; ?>
                </button>
                <a href="view_items.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add custom unit type
    document.getElementById('addCustomUnit').addEventListener('click', function() {
        const customUnitInput = document.getElementById('custom_unit');
        const unitValue = customUnitInput.value.trim();
        
        if (unitValue) {
            // Check if unit already exists
            const existingUnits = document.querySelectorAll('input[name="unit_types[]"]');
            let exists = false;
            
            existingUnits.forEach(unit => {
                if (unit.value === unitValue) {
                    exists = true;
                    unit.checked = true;
                }
            });
            
            if (!exists) {
                // Create custom unit tag
                const unitTypesList = document.querySelector('.unit-types-list');
                const unitId = 'custom_' + btoa(unitValue).replace(/[^a-zA-Z0-9]/g, '');
                
                const tagHtml = `
                    <div class="custom-unit-tag">
                        <input type="checkbox" 
                               name="unit_types[]" 
                               value="${unitValue}" 
                               id="${unitId}" 
                               checked>
                        <label for="${unitId}">
                            ${unitValue}
                            <button type="button" class="remove-unit" data-unit="${unitValue}">
                                <i class="fas fa-times"></i>
                            </button>
                        </label>
                    </div>
                `;
                
                // Insert before custom unit input
                const customUnitDiv = document.querySelector('.custom-unit');
                customUnitDiv.insertAdjacentHTML('beforebegin', tagHtml);
                
                // Add remove event listener
                const newRemoveBtn = document.querySelector(`[data-unit="${unitValue}"]`);
                newRemoveBtn.addEventListener('click', function() {
                    this.closest('.custom-unit-tag').remove();
                });
            }
            
            // Clear input
            customUnitInput.value = '';
        }
    });
    
    // Handle Enter key in custom unit input
    document.getElementById('custom_unit').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('addCustomUnit').click();
        }
    });
    
    // Remove unit tag
    document.querySelectorAll('.remove-unit').forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.custom-unit-tag').remove();
        });
    });
    
    // Form validation
    document.getElementById('itemForm').addEventListener('submit', function(e) {
        const itemName = document.getElementById('item_name').value.trim();
        const categoryId = document.getElementById('category_id').value;
        const price = parseFloat(document.getElementById('price').value);
        const unitTypes = document.querySelectorAll('input[name="unit_types[]"]:checked');
        
        if (!itemName) {
            e.preventDefault();
            alert('Item name is required');
            document.getElementById('item_name').focus();
            return;
        }
        
        if (!categoryId) {
            e.preventDefault();
            alert('Please select a category');
            document.getElementById('category_id').focus();
            return;
        }
        
        if (isNaN(price) || price < 0) {
            e.preventDefault();
            alert('Please enter a valid price (0 or greater)');
            document.getElementById('price').focus();
            return;
        }
        
        if (unitTypes.length === 0) {
            e.preventDefault();
            alert('Please select at least one unit type');
            return;
        }
    });
});
</script>

<style>
.unit-types-container {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.unit-types-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

.custom-unit-tag {
    display: inline-flex;
    align-items: center;
    background: #007bff;
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    margin: 2px;
}

.custom-unit-tag input[type="checkbox"] {
    display: none;
}

.custom-unit-tag label {
    margin: 0;
    display: flex;
    align-items: center;
    cursor: pointer;
}

.custom-unit-tag .remove-unit {
    background: none;
    border: none;
    color: white;
    margin-left: 5px;
    cursor: pointer;
    padding: 0;
    font-size: 0.8rem;
}

.custom-unit-tag .remove-unit:hover {
    color: #ffc107;
}

.custom-unit {
    margin-top: 10px;
}

.form-check-inline {
    margin-right: 15px;
}

.form-check-input:checked {
    background-color: #007bff;
    border-color: #007bff;
}
</style>

<?php include '../includes/footer.php'; ?>