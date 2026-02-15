<?php

// View All Items
// Display all items in a table with statistics, search, and pagination


require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Check if user is admin
if ($_SESSION['role'] != 'Admin') {
    header("Location: ../user/dashboard.php");
    exit();
}

$page_title = 'View All Items';
$page_subtitle = 'Manage and view all items in the system';

global $db;
$error = '';
$success = '';

// Pagination and search settings
$items_per_page = 15;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get all items with statistics
try {
    // Build WHERE clause for search
    $where_clause = '';
    $params = [];
    $count_params = [];
    
    if (!empty($search_query)) {
        $where_clause = "WHERE i.item_name LIKE :search";
        $params['search'] = '%' . $search_query . '%';
        $count_params['search'] = '%' . $search_query . '%';
    }
    
    // Get total count for pagination - FIXED: Use separate params array
    $count_sql = "SELECT COUNT(*) as total FROM items i";
    if (!empty($search_query)) {
        $count_sql .= " WHERE i.item_name LIKE :search";
    }
    
    $count_stmt = $db->prepare($count_sql);
    if (!empty($search_query)) {
        $count_stmt->bindValue(':search', '%' . $search_query . '%');
    }
    $count_stmt->execute();
    $count_result = $count_stmt->fetch();
    $total_items = $count_result ? $count_result['total'] : 0;
    $total_pages = $total_items > 0 ? ceil($total_items / $items_per_page) : 1;
    
    // Adjust current page if needed
    if ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
        $offset = ($current_page - 1) * $items_per_page;
    }
    
    // Get items with pagination - FIXED: Bind parameters individually
    $sql = "
        SELECT 
            i.*,
            c.category_name,
            COUNT(DISTINCT ri.request_id) as request_count,
            COUNT(DISTINCT r.office_id) as offices_used,
            COALESCE(SUM(CASE WHEN r.status = 'approved' THEN ri.quantity ELSE 0 END), 0) as approved_quantity,
            COALESCE(SUM(CASE WHEN r.status = 'pending' THEN ri.quantity ELSE 0 END), 0) as pending_quantity
        FROM items i
        LEFT JOIN item_categories c ON i.category_id = c.id
        LEFT JOIN request_items ri ON i.id = ri.item_id
        LEFT JOIN requests r ON ri.request_id = r.id
        $where_clause
        GROUP BY i.id
        ORDER BY i.item_name
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $db->prepare($sql);
    
    // Bind search parameter if exists - FIXED: Bind once and properly
    if (!empty($search_query)) {
        $stmt->bindValue(':search', '%' . $search_query . '%');
    }
    
    // Bind limit and offset
    $stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $items = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = 'Error loading items: ' . $e->getMessage();
    $items = [];
    $total_items = 0;
    $total_pages = 1;
}

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="header-with-search">
            <div class="header-left">
                <h3><i class="fas fa-boxes"></i> All Items</h3>
                <span class="header-subtitle">Manage items available for requests (<?php echo $total_items; ?> total)</span>
            </div>
            <div class="header-right">
                <a href="items.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Item
                </a>
                <a href="import_items.php" class="btn btn-success">
                    <i class="fas fa-file-import"></i> Import from CSV
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
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <!-- Search Form - DESIGN UNCHANGED -->
        <div class="search-container" style="margin-bottom: 20px;">
            <form method="GET" action="" class="search-form">
                <div class="search-input-group">
                    <input type="text" 
                           name="search" 
                           class="form-control" 
                           placeholder="Search by item name or description..." 
                           value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if (!empty($search_query)): ?>
                        <a href="view_items.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <?php if (!empty($search_query)): ?>
            <div class="alert" style="background-color: #e3f2fd; color: #1976d2; border: 1px solid #90caf9; margin-bottom: 15px;">
                <i class="fas fa-info-circle"></i> 
                Showing results for: <strong><?php echo htmlspecialchars($search_query); ?></strong>
                (<?php echo $total_items; ?> item<?php echo $total_items != 1 ? 's' : ''; ?> found)
            </div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="data-table" id="itemsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Item Name</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Available Unit Types</th>
                        <th>Price</th>
                        <th>Total Requests</th>
                        <th>Offices Used</th>
                        <th>Approved Qty</th>
                        <th>Pending Qty</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="9" class="text-center">
                                <?php if (!empty($search_query)): ?>
                                    No items found matching your search. <a href="view_items.php">Show all items</a>
                                <?php else: ?>
                                    No items found. <a href="items.php?action=add">Add your first item</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo $item['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                    <?php if ($item['is_active'] == 0): ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($item['description'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                <td>
                                    <?php 
                                    if (!empty($item['unit_types'])) {
                                        $units = json_decode($item['unit_types'], true);
                                        if (is_array($units)) {
                                            echo implode(', ', $units);
                                        }
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td><?php echo formatCurrency($item['price']); ?></td>
                                <td><?php echo $item['request_count']; ?></td>
                                <td><?php echo $item['offices_used']; ?></td>
                                <td><span class="text-success"><?php echo $item['approved_quantity']; ?></span></td>
                                <td><span class="text-warning"><?php echo $item['pending_quantity']; ?></span></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="items.php?action=edit&id=<?php echo $item['id']; ?>" 
                                           class="btn btn-sm btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="items.php?action=delete&id=<?php echo $item['id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this item?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php
                // Build query parameters array
                $query_params = [];
                if (!empty($search_query)) {
                    $query_params['search'] = $search_query;
                }
                
                // Previous button
                if ($current_page > 1):
                    $prev_params = $query_params;
                    $prev_params['page'] = $current_page - 1;
                    $prev_url = 'view_items.php?' . http_build_query($prev_params);
                ?>
                    <a href="<?php echo htmlspecialchars($prev_url); ?>" class="btn btn-secondary">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php else: ?>
                    <button class="btn btn-secondary" disabled>
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                <?php endif; ?>
                
                <!-- Page info -->
                <span class="page-info">
                    Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                    (Showing <?php echo count($items); ?> of <?php echo $total_items; ?> items)
                </span>
                
                <!-- Page numbers -->
                <div class="page-numbers">
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                        $page_params = $query_params;
                        $page_params['page'] = $i;
                        $page_url = 'view_items.php?' . http_build_query($page_params);
                        $active_class = ($i == $current_page) ? 'active' : '';
                    ?>
                        <a href="<?php echo htmlspecialchars($page_url); ?>" 
                           class="page-number <?php echo $active_class; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                
                <!-- Next button -->
                <?php
                if ($current_page < $total_pages):
                    $next_params = $query_params;
                    $next_params['page'] = $current_page + 1;
                    $next_url = 'view_items.php?' . http_build_query($next_params);
                ?>
                    <a href="<?php echo htmlspecialchars($next_url); ?>" class="btn btn-secondary">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <button class="btn btn-secondary" disabled>
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.header-with-search {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    width: 100%;
    gap: 20px;
}

.header-left {
    flex: 1;
}

.header-right {
    flex-shrink: 0;
}

.search-form {
    width: 100%;
}

.search-input-group {
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-input-group .form-control {
    flex: 1;
    max-width: 500px;
}

.stat-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 15px;
}

.stat-card .stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: #007bff;
}

.stat-card .stat-label {
    color: #6c757d;
    font-size: 0.9rem;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

.action-buttons .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.search-container {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.page-numbers {
    display: flex;
    gap: 5px;
}

.page-number {
    padding: 8px 12px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    text-decoration: none;
    color: #495057;
    transition: all 0.3s;
}

.page-number:hover {
    background-color: #e9ecef;
}

.page-number.active {
    background-color: #007bff;
    color: white;
    border-color: #007bff;
}

.text-center {
    text-align: center;
}

.text-success {
    color: #27ae60;
}

.text-warning {
    color: #f39c12;
}

@media (max-width: 768px) {
    .header-with-search {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-input-group {
        flex-direction: column;
    }
    
    .search-input-group .form-control {
        max-width: 100%;
    }
    
    .search-input-group .btn {
        width: 100%;
    }
}
</style>

<?php include '../includes/footer.php'; ?>