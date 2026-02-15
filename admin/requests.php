<?php

// Admin Requests Management
// View and manage all requests in the system


require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Check if user is admin
if ($_SESSION['role'] != 'Admin') {
    header("Location: ../user/dashboard.php");
    exit();
}

$page_title = 'All Requests';
$action = $_GET['action'] ?? 'list';
$request_id = $_GET['id'] ?? 0;

global $db;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Update request status
    if (isset($_POST['update_status'])) {
        $request_id = intval($_POST['request_id']);
        $status = sanitizeInput($_POST['status']);
        $password = $_POST['password'] ?? '';
        
        // Verify admin password
        if (!verifyUserPassword($_SESSION['user_id'], $password)) {
            $error = 'Invalid password. Please try again.';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE requests 
                    SET status = :status,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");
                
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':id', $request_id, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $success = 'Request status updated successfully!';
                    // Note: logActivity function doesn't exist in your functions.php
                    // You can remove this line or create the function
                    // logActivity($_SESSION['user_id'], 'UPDATE_STATUS', "Request ID: $request_id, Status: $status");
                    header("Location: requests.php?success=1&id=$request_id");
                    exit();
                }
            } catch(PDOException $e) {
                $error = 'Error updating request: ' . $e->getMessage();
            }
        }
    }
    
    // Delete request (admin)
    if (isset($_POST['delete_request'])) {
        $request_id = intval($_POST['request_id']);
        $password = $_POST['password'] ?? '';
        
        if (!verifyUserPassword($_SESSION['user_id'], $password)) {
            $error = 'Invalid password. Please try again.';
        } else {
            try {
                // First, delete request_items (cascade might handle this)
                $stmt = $db->prepare("DELETE FROM request_items WHERE request_id = :id");
                $stmt->bindParam(':id', $request_id, PDO::PARAM_INT);
                $stmt->execute();
                
                // Then delete the request
                $stmt = $db->prepare("DELETE FROM requests WHERE id = :id");
                $stmt->bindParam(':id', $request_id, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $success = 'Request deleted successfully!';
                    // Note: logActivity function doesn't exist
                    // logActivity($_SESSION['user_id'], 'DELETE_REQUEST_ADMIN', "Request ID: $request_id");
                    header("Location: requests.php?success=1");
                    exit();
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

// Handle different actions
switch ($action) {
    case 'view':
        $page_title = 'View Request Details';
        $request = getRequestDetails($request_id);
        
        if (!$request) {
            header("Location: requests.php");
            exit();
        }
        
        include '../includes/header.php';
        displayRequestView($request);
        break;
        
    case 'edit':
        $page_title = 'Edit Request';
        $request = getRequestDetails($request_id);
        
        if (!$request) {
            header("Location: requests.php");
            exit();
        }
        
        include '../includes/header.php';
        displayEditForm($request);
        break;
        
    case 'delete':
        $page_title = 'Delete Request';
        $request = getRequestDetails($request_id);
        
        if (!$request) {
            header("Location: requests.php");
            exit();
        }
        
        include '../includes/header.php';
        displayDeleteConfirmation($request);
        break;
        
    default:
        // List all requests with filters
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
            SELECT r.*, o.office_name, u.username, u.full_name, u.email,
                   (SELECT COUNT(*) FROM request_items WHERE request_id = r.id) as item_count
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

function displayRequestsList() {
    global $db;
    
    // Get filter parameters
    $office_filter = $_GET['office'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $item_filter = $_GET['item'] ?? '';
    
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    
    if (!empty($office_filter)) {
        $where_conditions[] = "r.office_id = :office_id";
        $params[':office_id'] = $office_filter;
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "r.status = :status";
        $params[':status'] = $status_filter;
    }
    
    if (!empty($date_from)) {
        $where_conditions[] = "DATE(r.date_requested) >= :date_from";
        $params[':date_from'] = $date_from;
    }
    
    if (!empty($date_to)) {
        $where_conditions[] = "DATE(r.date_requested) <= :date_to";
        $params[':date_to'] = $date_to;
    }
    
    // Note: Item filter is more complex for bulk requests
    // We'll handle it separately if needed
    
    $where_sql = '';
    if (!empty($where_conditions)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // Get all requests
    try {
        $sql = "
            SELECT r.*, o.office_name, u.full_name,
                   (SELECT COUNT(*) FROM request_items WHERE request_id = r.id) as item_count
            FROM requests r
            JOIN offices o ON r.office_id = o.id
            JOIN users u ON r.user_id = u.id
            $where_sql
            ORDER BY r.date_requested DESC
        ";
        
        $stmt = $db->prepare($sql);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $requests = $stmt->fetchAll();
        
        // Get counts for summary
        $summary_sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM requests
            $where_sql
        ";
        
        $stmt = $db->prepare($summary_sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $summary = $stmt->fetch();
        
    } catch(PDOException $e) {
        $requests = [];
        $summary = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
    }
    ?>

    <!-- Summary Cards -->
    <div class="dashboard-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $summary['total']; ?></h3>
                <p>Total Requests</p>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $summary['pending']; ?></h3>
                <p>Pending</p>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $summary['approved']; ?></h3>
                <p>Approved</p>
            </div>
        </div>
        
        <div class="stat-card stat-danger">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $summary['rejected']; ?></h3>
                <p>Rejected</p>
            </div>
        </div>
    </div>
    
    <!-- Requests Table -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> All Bulk Requests</h3>
            <div class="header-actions">
                <span class="filter-info">
                    Showing <?php echo count($requests); ?> request(s)
                </span>
                    <form method="POST" action="export.php" style="display: inline;">
                        <button type="submit" name="export_all" value="1" class="btn btn-success">
                            <i class="fas fa-file-excel"></i> Export All Approved Requests (CSV)
                        </button>
                    </form>
                    
                    <form method="POST" action="export.php" style="display: inline; margin-left: 10px;">
                        <input type="hidden" name="year" value="<?php echo date('Y'); ?>">
                        <button type="submit" name="export_year" value="1" class="btn btn-info">
                            <i class="fas fa-file-excel"></i> Export Current Year
                        </button>
                    </form>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($requests)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>No requests found</h4>
                    <p>No requests match your current filters.</p>
                    <a href="requests.php" class="btn btn-primary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table" id="requestsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Office</th>
                                <th>Items Count</th>
                                <th>Requested By</th>
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
                                    <td><?php echo htmlspecialchars($request['full_name']); ?></td>
                                    <td><?php echo getStatusBadge($request['status']); ?></td>
                                    <td><?php echo formatDate($request['date_requested']); ?></td>
                                    <td><?php echo formatDate($request['updated_at']); ?></td>
                                    <td class="actions">
                                        <a href="requests.php?action=view&id=<?php echo $request['id']; ?>" 
                                           class="btn-icon" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($_SESSION['role'] == 'Admin'): ?>
                                            <a href="requests.php?action=edit&id=<?php echo $request['id']; ?>" 
                                               class="btn-icon btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <a href="requests.php?action=delete&id=<?php echo $request['id']; ?>" 
                                               class="btn-icon btn-danger" title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this request?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination (if needed) -->
                <div class="pagination">
                    <button class="btn btn-secondary" disabled>Previous</button>
                    <span class="page-info">Page 1 of 1</span>
                    <button class="btn btn-secondary" disabled>Next</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    // Toggle filter section
    document.getElementById('toggleFilters').addEventListener('click', function() {
        const filterSection = document.getElementById('filterSection');
        filterSection.style.display = filterSection.style.display === 'none' ? 'block' : 'none';
    });
    
    // DataTable functionality
    document.addEventListener('DOMContentLoaded', function() {
        const table = document.getElementById('requestsTable');
        if (table) {
            // Add search functionality
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.placeholder = 'Search requests...';
            searchInput.className = 'table-search';
            searchInput.style.cssText = `
                margin-bottom: 15px;
                padding: 8px 12px;
                width: 300px;
                max-width: 100%;
                border: 1px solid #ddd;
                border-radius: 4px;
            `;
            
            table.parentNode.insertBefore(searchInput, table);
            
            searchInput.addEventListener('keyup', function() {
                const filter = searchInput.value.toLowerCase();
                const rows = table.getElementsByTagName('tr');
                
                for (let i = 1; i < rows.length; i++) {
                    const row = rows[i];
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(filter) ? '' : 'none';
                }
            });
        }
    });
    </script>
    <?php
}

function displayRequestView($request) {
    global $error, $success;
    $items = getRequestItems($request['id']);
    ?>
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-file-alt"></i> Request Details</h3>
            <div class="header-actions">
                <a href="requests.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <a href="requests.php?action=edit&id=<?php echo $request['id']; ?>" 
                   class="btn btn-warning">
                    <i class="fas fa-edit"></i> Edit Status
                </a>
            </div>
        </div>
        <div class="card-body">
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
            
            <div class="request-details-grid">
                <!-- Left Column: Basic Info -->
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
                            <span class="detail-label">Date Requested:</span>
                            <span class="detail-value"><?php echo formatDate($request['date_requested']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Last Updated:</span>
                            <span class="detail-value"><?php echo formatDate($request['updated_at']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Total Items:</span>
                            <span class="detail-value"><?php echo $request['item_count']; ?> items</span>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h4><i class="fas fa-user"></i> Requester Information</h4>
                        <div class="detail-item">
                            <span class="detail-label">Name:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($request['full_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Username:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($request['username']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($request['email']); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Office and Items Info -->
                <div class="detail-column">
                    <div class="detail-section">
                        <h4><i class="fas fa-building"></i> Office Information</h4>
                        <div class="detail-item">
                            <span class="detail-label">Office:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($request['office_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Office ID:</span>
                            <span class="detail-value"><?php echo $request['office_id']; ?></span>
                        </div>
                    </div>
                    
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
                                                @ <?php echo formatCurrency($item['price_per_unit']); ?> 
                                                = <?php echo formatCurrency($item['quantity'] * $item['price_per_unit']); ?>
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

function displayEditForm($request) {
    global $error, $success;
    $items = getRequestItems($request['id']);
    ?>
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-edit"></i> Update Request Status</h3>
            <a href="requests.php?action=view&id=<?php echo $request['id']; ?>" 
               class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Details
            </a>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="request-preview">
                <h4>Request Details:</h4>
                <div class="preview-grid">
                    <div class="preview-item">
                        <strong>Request ID:</strong> #<?php echo str_pad($request['id'], 5, '0', STR_PAD_LEFT); ?>
                    </div>
                    <div class="preview-item">
                        <strong>Office:</strong> <?php echo htmlspecialchars($request['office_name']); ?>
                    </div>
                    <div class="preview-item">
                        <strong>Requested By:</strong> <?php echo htmlspecialchars($request['full_name']); ?>
                    </div>
                    <div class="preview-item">
                        <strong>Total Items:</strong> <?php echo $request['item_count']; ?> items
                    </div>
                    <div class="preview-item">
                        <strong>Current Status:</strong> <?php echo getStatusBadge($request['status']); ?>
                    </div>
                </div>
            </div>
            
            <form method="POST" action="" class="status-form">
                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="status"><i class="fas fa-tag"></i> Update Status *</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="pending" <?php echo $request['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $request['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $request['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Security Password *</label>
                    <input type="password" id="password" name="password" 
                           class="form-control" required 
                           placeholder="Enter your password to confirm changes">
                    <small class="form-text">For security, please enter your password to update this request.</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_status" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                    <a href="requests.php?action=view&id=<?php echo $request['id']; ?>" 
                       class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
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
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Warning:</strong> You are about to delete a bulk request with <?php echo count($items); ?> items. This action cannot be undone!
            </div>
            
            <div class="request-preview">
                <h4>Request Details:</h4>
                <div class="preview-grid">
                    <div class="preview-item">
                        <strong>Request ID:</strong> #<?php echo str_pad($request['id'], 5, '0', STR_PAD_LEFT); ?>
                    </div>
                    <div class="preview-item">
                        <strong>Office:</strong> <?php echo htmlspecialchars($request['office_name']); ?>
                    </div>
                    <div class="preview-item">
                        <strong>Requested By:</strong> <?php echo htmlspecialchars($request['full_name']); ?>
                    </div>
                    <div class="preview-item">
                        <strong>Total Items:</strong> <?php echo count($items); ?>
                    </div>
                    <div class="preview-item">
                        <strong>Status:</strong> <?php echo getStatusBadge($request['status']); ?>
                    </div>
                    <div class="preview-item">
                        <strong>Date Requested:</strong> <?php echo formatDate($request['date_requested']); ?>
                    </div>
                </div>
                
                <?php if (!empty($items)): ?>
                    <h4>Items to be deleted:</h4>
                    <ol>
                        <?php foreach ($items as $item): ?>
                            <li>
                                <?php echo htmlspecialchars($item['item_name']); ?> - 
                                <?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit_type']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Security Password *</label>
                    <input type="password" id="password" name="password" class="form-control" required 
                           placeholder="Enter your password to confirm deletion">
                    <small class="form-text">For security, please enter your password to delete this request.</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="delete_request" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Confirm Delete
                    </button>
                    <a href="requests.php?action=view&id=<?php echo $request['id']; ?>" 
                       class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    <?php
}
?>