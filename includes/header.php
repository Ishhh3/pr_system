<?php

// Common Header File
// Includes navigation and header for all pages

// ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Item Request System'; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <a href="<?php echo getDashboardUrl(); ?>">
                    <i class="fas fa-boxes"></i>
                    <span>Item Request System</span>
                </a>
            </div>
            
            <div class="nav-menu" id="navMenu">
                <ul class="nav-links">
                    <?php if ($_SESSION['role'] == 'Admin'): ?>
                        <li><a href="../admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="../admin/requests.php"><i class="fas fa-clipboard-list"></i> All Requests</a></li>
                        <li><a href="../admin/view_items.php"><i class="fas fa-cube"></i> Manage Items</a></li>
                        <li><a href="../admin/accounts.php"><i class="fas fa-users-cog"></i> Account Management</a></li>
                        <li><a href="../admin/settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <?php else: ?>
                        <li><a href="../user/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="../user/requests.php"><i class="fas fa-clipboard-list"></i> My Requests</a></li>
                    <?php endif; ?>
                </ul>
                
                <div class="nav-user">
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                        <span class="user-role">
                            <?php echo htmlspecialchars($_SESSION['role']); ?>
                            <?php if ($_SESSION['role'] != 'Admin' && isset($_SESSION['office_name'])): ?>
                                • <?php echo htmlspecialchars($_SESSION['office_name']); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <a href="../auth/logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
            <!-- Mobile menu toggle -->
            <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>
    
    <!-- Main Content Container -->
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h1>
            <?php if (isset($page_subtitle)): ?>
                <p class="page-subtitle"><?php echo $page_subtitle; ?></p>
            <?php endif; ?>
        </div>