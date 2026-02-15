<?php

// Admin Dashboard
// Overview and statistics for IT Department


require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Check if user is admin
if ($_SESSION['role'] != 'Admin') {
    header("Location: ../user/dashboard.php");
    exit();
}

$page_title = 'IT Department Dashboard';
$page_subtitle = 'System Overview and Analytics';

global $db;
$error = '';
$success = '';

// Get system statistics
try {
    // Basic counts
    $counts = $db->query("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE role_id = 2) as total_users,
            (SELECT COUNT(*) FROM offices) as total_offices,
            (SELECT COUNT(*) FROM items WHERE is_active = 1) as total_items,
            (SELECT COUNT(*) FROM requests) as total_requests
    ")->fetch();
    
    // Request status breakdown
    $status_counts = $db->query("
        SELECT status, COUNT(*) as count 
        FROM requests 
        GROUP BY status
    ")->fetchAll();
    
    // Office-wise request counts
    $office_counts = $db->query("
        SELECT o.office_name, COUNT(r.id) as request_count
        FROM offices o
        LEFT JOIN requests r ON o.id = r.office_id
        GROUP BY o.id
        ORDER BY request_count DESC
        LIMIT 5
    ")->fetchAll();
    
    // Recent requests
    $recent_requests = $db->query("
        SELECT r.*, o.office_name, u.full_name,
               (SELECT COUNT(*) FROM request_items WHERE request_id = r.id) as item_count
        FROM requests r
        JOIN offices o ON r.office_id = o.id
        JOIN users u ON r.user_id = u.id
        ORDER BY r.date_requested DESC
        LIMIT 10
    ")->fetchAll();
    
} catch(PDOException $e) {
    $error = "Error loading dashboard data: " . $e->getMessage();
}

include '../includes/header.php';
?>

<div class="dashboard-grid">
    <!-- Statistics Cards -->
    <div class="stat-card stat-primary">
        <div class="stat-icon">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $counts['total_users'] ?? 0; ?></h3>
            <p>Office Heads</p>
        </div>
    </div>
    
    <div class="stat-card stat-info">
        <div class="stat-icon">
            <i class="fas fa-building"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $counts['total_offices'] ?? 0; ?></h3>
            <p>Offices</p>
        </div>
    </div>
    
    <div class="stat-card stat-success">
        <div class="stat-icon">
            <i class="fas fa-cube"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $counts['total_items'] ?? 0; ?></h3>
            <p>Active Items</p>
        </div>
    </div>
    
    <div class="stat-card stat-warning">
        <div class="stat-icon">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $counts['total_requests'] ?? 0; ?></h3>
            <p>Total Requests</p>
        </div>
    </div>
</div>

<!-- Two Column Layout -->
<div class="two-column">
    <!-- Left Column: Charts and Stats -->
    <div class="column">
        <!-- Request Status Chart -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-pie"></i> Request Status Distribution</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="statusChart" height="200"></canvas>
                </div>
                <div class="chart-legend">
                    <?php 
                    // Define status-color mapping
                    $status_colors = [
                        'approved' => 'success',
                        'pending' => 'warning', 
                        'rejected' => 'danger'
                    ];
                    
                    foreach ($status_counts as $status): 
                        $color = $status_colors[$status['status']] ?? 'secondary';
                    ?>
                        <div class="legend-item">
                            <span class="legend-color bg-<?php echo $color; ?>"></span>
                            <span class="legend-label"><?php echo ucfirst($status['status']); ?></span>
                            <span class="legend-value"><?php echo $status['count']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Office Requests -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> Top Offices by Requests</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="officeChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Right Column: Quick Actions and Recent Activity -->
    <div class="column">
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="quick-actions-grid">
                    <a href="requests.php" class="quick-action">
                        <div class="action-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="action-text">
                            <h4>Manage Requests</h4>
                            <p>View and approve all requests</p>
                        </div>
                    </a>
                    
                    <a href="items.php" class="quick-action">
                        <div class="action-icon">
                            <i class="fas fa-cube"></i>
                        </div>
                        <div class="action-text">
                            <h4>Manage Items</h4>
                            <p>Add, edit, or remove items</p>
                        </div>
                    </a>

                    <a href="settings.php" class="quick-action">
                        <div class="action-icon">
                            <i class="fas fa-cog"></i>
                        </div>
                        <div class="action-text">
                            <h4>System Settings</h4>
                            <p>Configure signatures and more</p>
                        </div>
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
                <div class="recent-requests">
                    <?php if (empty($recent_requests)): ?>
                        <div class="empty-state-sm">
                            <i class="fas fa-inbox"></i>
                            <p>No requests found</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_requests as $request): ?>
                            <div class="recent-item">
                                <div class="recent-info">
                                    <h4>Request #<?php echo str_pad($request['id'], 5, '0', STR_PAD_LEFT); ?></h4>
                                    <p>
                                        <span class="badge <?php echo $request['status'] == 'approved' ? 'badge-success' : 
                                                           ($request['status'] == 'pending' ? 'badge-warning' : 'badge-danger'); ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                        • <?php echo htmlspecialchars($request['office_name']); ?>
                                        • <?php echo $request['item_count']; ?> items
                                        • <?php echo formatDate($request['date_requested']); ?>
                                    </p>
                                </div>
                                <div class="recent-actions">
                                    <a href="requests.php?action=view&id=<?php echo $request['id']; ?>" 
                                       class="btn-icon" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Prepare data for status chart
const statusData = {
    labels: [<?php 
        $labels = [];
        foreach ($status_counts as $status) {
            $labels[] = "'" . ucfirst($status['status']) . "'";
        }
        echo implode(', ', $labels);
    ?>],
    datasets: [{
        data: [<?php 
            $values = [];
            foreach ($status_counts as $status) {
                $values[] = $status['count'];
            }
            echo implode(', ', $values);
        ?>],
        backgroundColor: [
            <?php
            // Map colors based on status
            $colors = [];
            foreach ($status_counts as $status) {
                if ($status['status'] == 'approved') {
                    $colors[] = "'rgba(40, 167, 69, 0.8)'";   // Green
                } elseif ($status['status'] == 'pending') {
                    $colors[] = "'rgba(255, 193, 7, 0.8)'";   // Yellow
                } elseif ($status['status'] == 'rejected') {
                    $colors[] = "'rgba(220, 53, 69, 0.8)'";   // Red
                } else {
                    $colors[] = "'rgba(108, 117, 125, 0.8)'"; // Gray for others
                }
            }
            echo implode(', ', $colors);
            ?>
        ],
        borderColor: [
            <?php
            // Map border colors based on status
            $borderColors = [];
            foreach ($status_counts as $status) {
                if ($status['status'] == 'approved') {
                    $borderColors[] = "'rgb(40, 167, 69)'";   // Green
                } elseif ($status['status'] == 'pending') {
                    $borderColors[] = "'rgb(255, 193, 7)'";   // Yellow
                } elseif ($status['status'] == 'rejected') {
                    $borderColors[] = "'rgb(220, 53, 69)'";   // Red
                } else {
                    $borderColors[] = "'rgb(108, 117, 125)'"; // Gray for others
                }
            }
            echo implode(', ', $borderColors);
            ?>
        ],
        borderWidth: 1
    }]
};

// Prepare data for office chart
const officeData = {
    labels: [<?php 
        $officeLabels = [];
        foreach ($office_counts as $office) {
            $officeLabels[] = "'" . addslashes($office['office_name']) . "'";
        }
        echo implode(', ', $officeLabels);
    ?>],
    datasets: [{
        label: 'Number of Requests',
        data: [<?php 
            $officeValues = [];
            foreach ($office_counts as $office) {
                $officeValues[] = $office['request_count'];
            }
            echo implode(', ', $officeValues);
        ?>],
        backgroundColor: 'rgba(54, 162, 235, 0.6)',
        borderColor: 'rgb(54, 162, 235)',
        borderWidth: 1
    }]
};

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Status Pie Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'pie',
        data: statusData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true
                }
            }
        }
    });
    
    // Office Bar Chart
    const officeCtx = document.getElementById('officeChart').getContext('2d');
    new Chart(officeCtx, {
        type: 'bar',
        data: officeData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>