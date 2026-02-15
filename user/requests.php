<?php

// User Requests Management
// Handles bulk request operations for office heads


require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

$page_title = 'My Requests';
$action = $_GET['action'] ?? 'list';
$request_id = $_GET['id'] ?? 0;

global $db;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_bulk_request'])) {
        $items_data = isset($_POST['items']) ? $_POST['items'] : [];
        
        if (empty($items_data)) {
            $error = 'Please add at least one item to the request.';
        } else {
            try {
                $db->beginTransaction();
                
                // Create request
                $stmt = $db->prepare("
                    INSERT INTO requests (user_id, office_id) 
                    VALUES (:user_id, :office_id)
                ");
                
                $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt->bindParam(':office_id', $_SESSION['office_id'], PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $request_id = $db->lastInsertId();
                    
                    // Insert each item
                    $stmt = $db->prepare("
                        INSERT INTO request_items (request_id, item_id, custom_item_name, unit_type, quantity, price_per_unit)
                        VALUES (:request_id, :item_id, :custom_name, :unit_type, :quantity, :price)
                    ");
                    
                    foreach ($items_data as $item) {
                        if (!empty($item['unit_type']) && !empty($item['quantity'])) {
                            $itemId = !empty($item['item_id']) ? $item['item_id'] : null;
                            $customName = empty($item['item_id']) ? $item['custom_name'] : null;
                            $price = floatval($item['price'] ?? 0);
                            
                            $stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
                            $stmt->bindParam(':item_id', $itemId, PDO::PARAM_INT);
                            $stmt->bindParam(':custom_name', $customName);
                            $stmt->bindParam(':unit_type', $item['unit_type']);
                            $stmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
                            $stmt->bindParam(':price', $price);
                            $stmt->execute();
                        }
                    }
                    
                    $db->commit();
                    $success = 'Bulk request submitted successfully!';
                    header("Location: requests.php?success=1");
                    exit();
                }
            } catch(PDOException $e) {
                $db->rollBack();
                $error = 'Error submitting request: ' . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['delete_request'])) {
        // Delete request
        $request_id = intval($_POST['request_id']);
        $password = $_POST['password'] ?? '';
        
        // Verify password
        if (!verifyUserPassword($_SESSION['user_id'], $password)) {
            $error = 'Invalid password. Please try again.';
        } else {
            try {
                // Check if user owns this request and it's pending
                $stmt = $db->prepare("
                    SELECT id FROM requests 
                    WHERE id = :id AND user_id = :user_id AND status = 'pending'
                ");
                $stmt->bindParam(':id', $request_id, PDO::PARAM_INT);
                $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt->execute();
                
                if ($stmt->rowCount() == 1) {
                    // Delete request (cascade will delete request_items)
                    $stmt = $db->prepare("DELETE FROM requests WHERE id = :id");
                    $stmt->bindParam(':id', $request_id, PDO::PARAM_INT);
                    
                    if ($stmt->execute()) {
                        $success = 'Request deleted successfully!';
                        header("Location: requests.php?success=1");
                        exit();
                    }
                } else {
                    $error = 'Request not found or cannot be deleted.';
                }
            } catch(PDOException $e) {
                $error = 'Error deleting request: ' . $e->getMessage();
            }
        }
    }
}

// Handle success parameter
if (isset($_GET['success'])) {
    $success = 'Operation completed successfully!';
}

// Get items for dropdown
$items = getItems();
$categories = getCategories();

// Handle different actions
switch ($action) {
    case 'new':
        $page_title = 'New Bulk Request';
        include '../includes/header.php';
        displayBulkRequestForm();
        break;
        
    case 'view':
        $page_title = 'View Request';
        $request = getRequestDetails($request_id);
        
        if (!$request || $request['office_id'] != $_SESSION['office_id']) {
            header("Location: requests.php");
            exit();
        }
        
        include '../includes/header.php';
        displayRequestView($request);
        break;
        
    case 'delete':
        $page_title = 'Delete Request';
        $request = getRequestDetails($request_id);
        
        if (!$request || $request['office_id'] != $_SESSION['office_id']) {
            header("Location: requests.php");
            exit();
        }
        
        include '../includes/header.php';
        displayDeleteConfirmation($request);
        break;
        
    default:
        // List all requests
        include '../includes/header.php';
        displayRequestsList();
        break;
}

include '../includes/footer.php';

// Helper functions
function getRequestDetails($id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT r.*, o.office_name, u.full_name
            FROM requests r
            JOIN offices o ON r.office_id = o.id
            JOIN users u ON r.user_id = u.id
            WHERE r.id = :id
        ");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch();
    } catch(PDOException $e) {
        return null;
    }
}

function getRequestItems($request_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT ri.*, i.item_name
            FROM request_items ri
            JOIN items i ON ri.item_id = i.id
            WHERE ri.request_id = :request_id
            ORDER BY i.item_name
        ");
        $stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

function displayBulkRequestForm() {
    global $items, $error, $success, $categories;
    ?>
    
    <div class="form-container">
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-plus-circle"></i> Create Bulk Request
                </h3>
                <span class="office-badge">
                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($_SESSION['office_name']); ?>
                </span>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="bulkRequestForm">
                    <div class="bulk-request-header">
                        <h4><i class="fas fa-list"></i> Requested Items</h4>
                        <button type="button" class="btn btn-success" id="addItemBtn">
                            <i class="fas fa-plus"></i> Add Item
                        </button>
                    </div>
                    
                    <div id="itemsList" class="items-list">
                        <!-- Items will be added here dynamically -->
                    </div>
                    
                    <div class="empty-items-message" id="emptyMessage">
                        <i class="fas fa-inbox"></i>
                        <p>No items added yet. Click "Add Item" to start building your request.</p>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="create_bulk_request" class="btn btn-primary" id="submitBtn" disabled>
                            <i class="fas fa-paper-plane"></i> Submit Bulk Request
                        </button>
                        <a href="requests.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Request Guidelines -->
        <div class="card mt-4">
            <div class="card-header">
                <h4><i class="fas fa-info-circle"></i> Request Guidelines</h4>
            </div>
            <div class="card-body">
                <ul class="guidelines">
                    <li>You can add multiple items in a single bulk request</li>
                    <li>Each item must have a name, unit type, and quantity specified</li>
                    <li>All requests are subject to approval by the IT Department</li>
                    <li>You can only delete requests with "Pending" status</li>
                    <li>Track your request status from the dashboard or requests list</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Modal for Adding Item -->
    <div id="itemModal" class="modal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3><i class="fas fa-cube"></i> Add Item to Request</h3>
                <button class="modal-close" id="closeModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="modal_category_id"><i class="fas fa-folder"></i> Category *</label>
                    <select id="modal_category_id" class="form-control" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="others">Others (Custom Item)</option>
                    </select>
                </div>
                
                <div id="predefinedItemSection" style="display:none;">
                    <div class="form-group">
                        <label for="modal_item_id"><i class="fas fa-cube"></i> Item Name *</label>
                        <select id="modal_item_id" class="form-control">
                            <option value="">Select an item</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_unit_type"><i class="fas fa-balance-scale"></i> Unit Type *</label>
                        <select id="modal_unit_type" class="form-control">
                            <option value="">Select unit type</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_price">Price per Unit</label>
                        <input type="number" step="0.01" id="modal_price" class="form-control" readonly>
                    </div>
                </div>

                <div id="customItemSection" style="display:none;">
                    <div class="form-group">
                        <label for="custom_item_name"><i class="fas fa-cube"></i> Custom Item Name *</label>
                        <input type="text" id="custom_item_name" class="form-control" 
                            placeholder="Enter custom item name">
                    </div>
                    
                    <div class="form-group">
                        <label for="custom_unit_type"><i class="fas fa-balance-scale"></i> Unit Type *</label>
                        <input type="text" id="custom_unit_type" class="form-control" 
                            placeholder="e.g., pcs, boxes, sets">
                    </div>
                    
                    <div class="form-group">
                        <label for="custom_price"><i class="fas fa-peso-sign"></i> Estimated Price per Unit *</label>
                        <input type="number" step="0.01" min="0" id="custom_price" class="form-control" 
                            placeholder="0.00">
                    </div>
                </div>

                <div class="form-group">
                    <label for="modal_quantity"><i class="fas fa-hashtag"></i> Quantity *</label>
                    <input type="number" id="modal_quantity" class="form-control" 
                        min="1" max="10000" required placeholder="Enter quantity">
                </div>

                <div id="totalPreview" class="form-group" style="display:none;">
                    <label>Total Cost:</label>
                    <div style="font-size: 1.5em; font-weight: bold; color: #28a745;" id="totalCostDisplay">
                        ₱0.00
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelModal">Cancel</button>
                <button class="btn btn-primary" id="confirmAddItem">
                    <i class="fas fa-check"></i> Add to Request
                </button>
            </div>
        </div>
    </div>
    
    <script>
    // Item management
    let itemCounter = 0;
    const itemsList = document.getElementById('itemsList');
    const emptyMessage = document.getElementById('emptyMessage');
    const submitBtn = document.getElementById('submitBtn');
    const modal = document.getElementById('itemModal');

    // Modal controls
    document.getElementById('addItemBtn').addEventListener('click', openModal);
    document.getElementById('closeModal').addEventListener('click', closeModal);
    document.getElementById('cancelModal').addEventListener('click', closeModal);
    document.getElementById('confirmAddItem').addEventListener('click', addItemFromModal);

    // ==================== NEW: Category Handling ====================

    // Category selection handler
    document.getElementById('modal_category_id').addEventListener('change', function() {
        const categoryId = this.value;
        const predefinedSection = document.getElementById('predefinedItemSection');
        const customSection = document.getElementById('customItemSection');
        const itemSelect = document.getElementById('modal_item_id');
        const totalPreview = document.getElementById('totalPreview');
        
        // Reset everything
        itemSelect.innerHTML = '<option value="">Select an item</option>';
        document.getElementById('modal_unit_type').innerHTML = '<option value="">Select unit type</option>';
        document.getElementById('modal_price').value = '';
        document.getElementById('custom_item_name').value = '';
        document.getElementById('custom_unit_type').value = '';
        document.getElementById('custom_price').value = '';
        document.getElementById('modal_quantity').value = '';
        totalPreview.style.display = 'none';
        
        if (categoryId === 'others') {
            // Show custom item section
            predefinedSection.style.display = 'none';
            customSection.style.display = 'block';
            itemSelect.required = false;
            document.getElementById('custom_item_name').required = true;
            document.getElementById('custom_unit_type').required = true;
            document.getElementById('custom_price').required = true;
        } else if (categoryId) {
            // Show predefined item section
            predefinedSection.style.display = 'block';
            customSection.style.display = 'none';
            itemSelect.required = true;
            document.getElementById('custom_item_name').required = false;
            document.getElementById('custom_unit_type').required = false;
            document.getElementById('custom_price').required = false;
            
            // Load items for this category
            loadItemsByCategory(categoryId);
        } else {
            // Hide both sections
            predefinedSection.style.display = 'none';
            customSection.style.display = 'none';
        }
    });

    // Load items by category
    function loadItemsByCategory(categoryId) {
        const itemSelect = document.getElementById('modal_item_id');
        itemSelect.innerHTML = '<option value="">Loading...</option>';
        
        // Get all items (passed from PHP)
        const allItems = <?php echo json_encode($items); ?>;
        
        // Filter by category
        const filteredItems = allItems.filter(item => item.category_id == categoryId);
        
        // Populate dropdown
        itemSelect.innerHTML = '<option value="">Select an item</option>';
        filteredItems.forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.item_name;
            option.dataset.unitTypes = item.unit_types;
            option.dataset.price = item.price;
            itemSelect.appendChild(option);
        });
        
        if (filteredItems.length === 0) {
            itemSelect.innerHTML = '<option value="">No items in this category</option>';
        }
    }

    // Item selection handler
    document.getElementById('modal_item_id').addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        
        if (!option.value) return;
        
        const unitTypes = option.dataset.unitTypes;
        const price = parseFloat(option.dataset.price || 0);
        
        // Populate unit types
        const unitSelect = document.getElementById('modal_unit_type');
        unitSelect.innerHTML = '<option value="">Select unit type</option>';
        
        if (unitTypes) {
            try {
                const types = JSON.parse(unitTypes);
                types.forEach(type => {
                    const opt = document.createElement('option');
                    opt.value = type;
                    opt.textContent = type;
                    unitSelect.appendChild(opt);
                });
            } catch(e) {
                console.error('Error parsing unit types:', e);
            }
        }
        
        // Set price
        document.getElementById('modal_price').value = price.toFixed(2);
        updateTotalCost();
    });

    // Quantity and price change handlers
    document.getElementById('modal_quantity').addEventListener('input', updateTotalCost);
    document.getElementById('custom_price').addEventListener('input', updateTotalCost);
    document.getElementById('modal_unit_type').addEventListener('change', updateTotalCost);

    // Update total cost preview
    function updateTotalCost() {
        const quantity = parseInt(document.getElementById('modal_quantity').value) || 0;
        let price = 0;
        
        // Check which section is visible
        const predefinedSection = document.getElementById('predefinedItemSection');
        if (predefinedSection.style.display !== 'none') {
            // Predefined item
            price = parseFloat(document.getElementById('modal_price').value) || 0;
        } else {
            // Custom item
            price = parseFloat(document.getElementById('custom_price').value) || 0;
        }
        
        const total = quantity * price;
        const totalDisplay = document.getElementById('totalCostDisplay');
        const totalPreview = document.getElementById('totalPreview');
        
        if (total > 0) {
            totalDisplay.textContent = '₱' + total.toFixed(2);
            totalPreview.style.display = 'block';
        } else {
            totalPreview.style.display = 'none';
        }
    }

    // ==================== END: Category Handling ====================

    // Modified addItemFromModal function
    function addItemFromModal() {
        const categoryId = document.getElementById('modal_category_id').value;
        const quantity = document.getElementById('modal_quantity').value;
        
        if (!categoryId) {
            alert('Please select a category');
            return;
        }
        
        if (!quantity || quantity < 1) {
            alert('Please enter a valid quantity');
            return;
        }
        
        let itemId, itemName, unitType, price;
        
        if (categoryId === 'others') {
            // Custom item
            itemId = null;
            itemName = document.getElementById('custom_item_name').value.trim();
            unitType = document.getElementById('custom_unit_type').value.trim();
            price = parseFloat(document.getElementById('custom_price').value);
            
            if (!itemName || !unitType || !price) {
                alert('Please fill in all custom item fields');
                return;
            }
            
            if (price <= 0) {
                alert('Price must be greater than 0');
                return;
            }
        } else {
            // Predefined item
            const itemSelect = document.getElementById('modal_item_id');
            itemId = itemSelect.value;
            itemName = itemSelect.options[itemSelect.selectedIndex].text;
            unitType = document.getElementById('modal_unit_type').value;
            price = parseFloat(document.getElementById('modal_price').value);
            
            if (!itemId || !unitType) {
                alert('Please select an item and unit type');
                return;
            }
        }
        
        addItem(itemId, itemName, unitType, quantity, price, categoryId === 'others');
        closeModal();
    }

    function openModal() {
        // Reset modal
        document.getElementById('modal_category_id').value = '';
        document.getElementById('predefinedItemSection').style.display = 'none';
        document.getElementById('customItemSection').style.display = 'none';
        document.getElementById('modal_item_id').innerHTML = '<option value="">Select an item</option>';
        document.getElementById('modal_unit_type').innerHTML = '<option value="">Select unit type</option>';
        document.getElementById('modal_price').value = '';
        document.getElementById('custom_item_name').value = '';
        document.getElementById('custom_unit_type').value = '';
        document.getElementById('custom_price').value = '';
        document.getElementById('modal_quantity').value = '';
        document.getElementById('totalPreview').style.display = 'none';
        
        modal.style.display = 'flex';
        document.getElementById('modal_category_id').focus();
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    function addItem(itemId, itemName, unitType, quantity, price, isCustom) {
        const itemDiv = document.createElement('div');
        itemDiv.className = 'request-item';
        itemDiv.dataset.index = itemCounter;
        
        const total = quantity * price;
        const customBadge = isCustom ? '<span class="badge badge-warning" style="margin-left: 10px;">Custom</span>' : '';
        
        itemDiv.innerHTML = `
            <div class="item-info">
                <div class="item-number">#${itemCounter + 1}</div>
                <div class="item-details">
                    <h4>${itemName}${customBadge}</h4>
                    <p>Quantity: ${quantity} ${unitType} @ ₱${parseFloat(price).toFixed(2)} = ₱${total.toFixed(2)}</p>
                </div>
            </div>
            <button type="button" class="btn btn-danger btn-sm remove-item" onclick="removeItem(${itemCounter})">
                <i class="fas fa-trash"></i> Remove
            </button>
            <input type="hidden" name="items[${itemCounter}][item_id]" value="${itemId || ''}">
            <input type="hidden" name="items[${itemCounter}][custom_name]" value="${isCustom ? itemName : ''}">
            <input type="hidden" name="items[${itemCounter}][unit_type]" value="${unitType}">
            <input type="hidden" name="items[${itemCounter}][quantity]" value="${quantity}">
            <input type="hidden" name="items[${itemCounter}][price]" value="${price}">
        `;
        
        itemsList.appendChild(itemDiv);
        itemCounter++;
        
        updateUI();
    }

    function removeItem(index) {
        const item = document.querySelector(`[data-index="${index}"]`);
        if (item) {
            item.remove();
            updateUI();
            renumberItems();
        }
    }

    function renumberItems() {
        const items = itemsList.querySelectorAll('.request-item');
        items.forEach((item, index) => {
            const number = item.querySelector('.item-number');
            if (number) {
                number.textContent = `#${index + 1}`;
            }
        });
    }

    function updateUI() {
        const hasItems = itemsList.children.length > 0;
        emptyMessage.style.display = hasItems ? 'none' : 'flex';
        submitBtn.disabled = !hasItems;
    }

    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });

    // Initialize
    updateUI();
    </script>
    <?php
}

function displayRequestsList() {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT r.*, o.office_name,
                   (SELECT COUNT(*) FROM request_items WHERE request_id = r.id) as item_count
            FROM requests r
            JOIN offices o ON r.office_id = o.id
            WHERE r.office_id = :office_id
            ORDER BY r.date_requested DESC
        ");
        $stmt->bindParam(':office_id', $_SESSION['office_id'], PDO::PARAM_INT);
        $stmt->execute();
        $requests = $stmt->fetchAll();
    } catch(PDOException $e) {
        $requests = [];
    }
    ?>
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clipboard-list"></i> My Bulk Requests</h3>
            <a href="requests.php?action=new" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Bulk Request
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($requests)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>No requests found</h4>
                    <p>You haven't submitted any bulk requests yet.</p>
                    <a href="requests.php?action=new" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Your First Request
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table" id="requestsTable">
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Office</th>
                                <th>Items Count</th>
                                <th>Status</th>
                                <th>Date Requested</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td>#<?php echo str_pad($request['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($request['office_name']); ?></td>
                                    <td><?php echo $request['item_count']; ?> items</td>
                                    <td><?php echo getStatusBadge($request['status']); ?></td>
                                    <td><?php echo formatDate($request['date_requested']); ?></td>
                                    <td><?php echo formatDate($request['updated_at']); ?></td>
                                    <td class="actions">
                                        <a href="requests.php?action=view&id=<?php echo $request['id']; ?>" 
                                           class="btn-icon" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($request['status'] == 'pending'): ?>
                                            <a href="requests.php?action=delete&id=<?php echo $request['id']; ?>" 
                                               class="btn-icon btn-danger" title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this entire request?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
function displayRequestView($request) {
    $items = getRequestItems($request['id']);
    ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-file-alt"></i> Request Details</h3>
            <div class="header-actions">
                <a href="requests.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <?php if ($request['status'] == 'pending'): ?>
                    <a href="export_excel.php?id=<?php echo $request['id']; ?>" 
                    class="btn btn-success" target="_blank">
                        <i class="fas fa-file-excel"></i> Download Excel
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="request-details-grid">
                <div class="detail-column">
                    <div class="detail-section">
                        <h4><i class="fas fa-info-circle"></i> Request Information</h4>
                        <div class="detail-item">
                            <span class="detail-label">Request ID:</span>
                            <span class="detail-value">#<?php echo str_pad($request['id'], 5, '0', STR_PAD_LEFT); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value"><?php echo getStatusBadge($request['status']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Office:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($request['office_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Requested By:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($request['full_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Date Requested:</span>
                            <span class="detail-value"><?php echo formatDate($request['date_requested']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Last Updated:</span>
                            <span class="detail-value"><?php echo formatDate($request['updated_at']); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="detail-column">
                    <div class="detail-section">
                        <h4><i class="fas fa-list"></i> Requested Items (<?php echo count($items); ?>)</h4>
                        <?php if (empty($items)): ?>
                            <p class="text-muted">No items found.</p>
                        <?php else: ?>
                            <div class="items-list-view">
                                <?php foreach ($items as $index => $item): ?>
                                    <div class="item-row">
                                        <div class="item-number"><?php echo $index + 1; ?>.</div>
                                        <div class="item-details">
                                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                            <span class="item-qty">
                                                <?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit_type']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function displayDeleteConfirmation($request) {
    $items = getRequestItems($request['id']);
    ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-trash-alt"></i> Delete Request</h3>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Warning:</strong> This action cannot be undone. This will delete the entire request and all its items.
            </div>
            
            <div class="request-preview">
                <h4>Request Details:</h4>
                <ul>
                    <li><strong>Request ID:</strong> #<?php echo str_pad($request['id'], 5, '0', STR_PAD_LEFT); ?></li>
                    <li><strong>Office:</strong> <?php echo htmlspecialchars($request['office_name']); ?></li>
                    <li><strong>Total Items:</strong> <?php echo count($items); ?></li>
                    <li><strong>Date Requested:</strong> <?php echo formatDate($request['date_requested']); ?></li>
                </ul>
                
                <h4>Items to be deleted:</h4>
                <ol>
                    <?php foreach ($items as $item): ?>
                        <li>
                            <?php echo htmlspecialchars($item['item_name']); ?> - 
                            <?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit_type']); ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Security Password *</label>
                    <input type="password" id="password" name="password" class="form-control" required 
                           placeholder="Enter your password to confirm deletion">
                    <small class="form-text">For security, please enter your password to delete this request</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="delete_request" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Confirm Delete
                    </button>
                    <a href="requests.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    <?php
}
?>