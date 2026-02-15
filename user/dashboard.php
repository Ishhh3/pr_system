<?php

// User Dashboard
// Main interface for office heads


require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

$page_title = 'User Dashboard';
$page_subtitle = 'Welcome back, ' . $_SESSION['full_name'];

// Get user statistics
global $db;

try {
    // Total requests
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_requests,
               SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
               SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
               SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests
        FROM requests 
        WHERE office_id = :office_id

    ");
    $stmt->bindParam(':office_id', $_SESSION['office_id'], PDO::PARAM_INT);
    $stmt->execute();
    $stats = $stmt->fetch();
    
    // Recent requests
    $stmt = $db->prepare("
        SELECT r.*, o.office_name,
               (SELECT COUNT(*) FROM request_items WHERE request_id = r.id) as item_count
        FROM requests r
        JOIN offices o ON r.office_id = o.id
        WHERE office_id = :office_id
        ORDER BY r.date_requested DESC
        LIMIT 5
    ");
    $stmt->bindParam(':office_id', $_SESSION['office_id'], PDO::PARAM_INT);
    $stmt->execute();
    $recent_requests = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error loading dashboard data: " . $e->getMessage();
}

include '../includes/header.php';
?>

<div class="dashboard-grid">
    <!-- Statistics Cards -->
    <div class="stat-card stat-primary">
        <div class="stat-icon">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_requests'] ?? 0; ?></h3>
            <p>Total Requests</p>
        </div>
    </div>
    
    <div class="stat-card stat-warning">
        <div class="stat-icon">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['pending_requests'] ?? 0; ?></h3>
            <p>Pending</p>
        </div>
    </div>
    
    <div class="stat-card stat-success">
        <div class="stat-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['approved_requests'] ?? 0; ?></h3>
            <p>Approved</p>
        </div>
    </div>
    
    <div class="stat-card stat-danger">
        <div class="stat-icon">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['rejected_requests'] ?? 0; ?></h3>
            <p>Rejected</p>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
    </div>
    <div class="card-body">
        <div class="quick-actions">
            <a href="requests.php?action=new" class="btn btn-primary btn-lg">
                <i class="fas fa-plus-circle"></i> Create New Bulk Request
            </a>
            <a href="requests.php" class="btn btn-secondary btn-lg">
                <i class="fas fa-history"></i> View All My Requests
            </a>
        </div>
    </div>
</div>

<!-- Recent Requests -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history"></i> Recent Requests</h3>
        <a href="requests.php" class="btn-link">View All</a>
    </div>
    <div class="card-body">
        <?php if (empty($recent_requests)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h4>No requests yet</h4>
                <p>Create your first bulk item request to get started.</p>
                <a href="requests.php?action=new" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Request
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Office</th>
                            <th>Items Count</th>
                            <th>Status</th>
                            <th>Date Requested</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_requests as $request): ?>
                            <tr>
                                <td>#<?php echo str_pad($request['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars($request['office_name']); ?></td>
                                <td><?php echo $request['item_count']; ?> items</td>
                                <td><?php echo getStatusBadge($request['status']); ?></td>
                                <td><?php echo formatDate($request['date_requested']); ?></td>
                                <td class="actions">
                                    <a href="requests.php?action=view&id=<?php echo $request['id']; ?>" 
                                       class="btn-icon" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Office Information -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-building"></i> Office Information</h3>
    </div>
    <div class="card-body">
        <div class="dept-info">
            <div class="dept-detail">
                <h4><?php echo htmlspecialchars($_SESSION['office_name']); ?></h4>
                <p>You are the head of this office.</p>
                <ul class="dept-notes">
                    <li><i class="fas fa-check-circle"></i> Submit bulk requests for multiple items at once</li>
                    <li><i class="fas fa-check-circle"></i> All requests are tagged to your office automatically</li>
                    <li><i class="fas fa-check-circle"></i> Track status of all your office requests</li>
                    <li><i class="fas fa-check-circle"></i> Contact IT Department for any issues</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>