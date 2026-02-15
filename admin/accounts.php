<?php

// Account Management
// Add offices and users, manage passwords


require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Check if user is admin
if ($_SESSION['role'] != 'Admin') {
    header("Location: ../user/dashboard.php");
    exit();
}

$page_title = 'Account Management';
$page_subtitle = 'Manage offices and user accounts';

global $db;
$error = '';
$success = '';

$action = $_GET['action'] ?? 'list';
$user_id = $_GET['id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new office
    if (isset($_POST['add_office'])) {
        $office_name = trim($_POST['office_name']);
        
        if (empty($office_name)) {
            $error = 'Office name is required';
        } else {
            try {
                // Check if office already exists
                $stmt = $db->prepare("SELECT id FROM offices WHERE office_name = ?");
                $stmt->execute([$office_name]);
                
                if ($stmt->fetch()) {
                    $error = 'Office already exists';
                } else {
                    $stmt = $db->prepare("INSERT INTO offices (office_name) VALUES (?)");
                    $stmt->execute([$office_name]);
                    $success = 'Office added successfully';
                }
            } catch(PDOException $e) {
                $error = 'Error adding office: ' . $e->getMessage();
            }
        }
    }
    
    // Add new user account
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $full_name = trim($_POST['full_name']);
        $office_id = intval($_POST['office_id']);
        
        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($office_id)) {
            $error = 'All fields are required';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format';
        } else {
            try {
                // Check if username or email already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                
                if ($stmt->fetch()) {
                    $error = 'Username or email already exists';
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert user (role_id = 2 for User/Office Head)
                    $stmt = $db->prepare("
                        INSERT INTO users (username, email, password, full_name, office_id, role_id)
                        VALUES (?, ?, ?, ?, ?, 2)
                    ");
                    $stmt->execute([$username, $email, $hashed_password, $full_name, $office_id]);
                    
                    $success = 'User account created successfully';
                }
            } catch(PDOException $e) {
                $error = 'Error creating user: ' . $e->getMessage();
            }
        }
    }
    
    // Change password
    if (isset($_POST['change_password'])) {
        $target_user_id = intval($_POST['user_id']);
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        $admin_password = $_POST['admin_password'];
        
        // Verify admin password
        if (!verifyUserPassword($_SESSION['user_id'], $admin_password)) {
            $error = 'Invalid admin password';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match';
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $target_user_id]);
                
                $success = 'Password changed successfully';
            } catch(PDOException $e) {
                $error = 'Error changing password: ' . $e->getMessage();
            }
        }
    }
    
    // Delete user
    if (isset($_POST['delete_user'])) {
        $target_user_id = intval($_POST['user_id']);
        $admin_password = $_POST['admin_password'];
        
        // Cannot delete admin or self
        if ($target_user_id == $_SESSION['user_id']) {
            $error = 'Cannot delete your own account';
        } elseif (!verifyUserPassword($_SESSION['user_id'], $admin_password)) {
            $error = 'Invalid admin password';
        } else {
            try {
                // Check if user has requests
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM requests WHERE user_id = ?");
                $stmt->execute([$target_user_id]);
                $result = $stmt->fetch();
                
                if ($result['count'] > 0) {
                    $error = 'Cannot delete user with existing requests';
                } else {
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role_id != 1");
                    $stmt->execute([$target_user_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $success = 'User account deleted successfully';
                    } else {
                        $error = 'Cannot delete admin account';
                    }
                }
            } catch(PDOException $e) {
                $error = 'Error deleting user: ' . $e->getMessage();
            }
        }
    }
}

// Get all offices
try {
    $offices = $db->query("SELECT * FROM offices ORDER BY office_name")->fetchAll();
} catch(PDOException $e) {
    $offices = [];
}

// Get all users with their office info
try {
    $users = $db->query("
        SELECT u.*, o.office_name, r.role_name,
               (SELECT COUNT(*) FROM requests WHERE user_id = u.id) as request_count
        FROM users u
        LEFT JOIN offices o ON u.office_id = o.id
        LEFT JOIN roles r ON u.role_id = r.id
        ORDER BY r.id, u.full_name
    ")->fetchAll();
} catch(PDOException $e) {
    $users = [];
}

include '../includes/header.php';
?>

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

<!-- Quick Actions -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-plus-circle"></i> Quick Actions</h3>
    </div>
    <div class="card-body">
        <div class="quick-actions-grid">
            <button onclick="showAddOfficeModal()" class="quick-action">
                <div class="action-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="action-text">
                    <h4>Add Office</h4>
                    <p>Create a new office/department</p>
                </div>
            </button>
            
            <button onclick="showAddUserModal()" class="quick-action">
                <div class="action-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="action-text">
                    <h4>Add User Account</h4>
                    <p>Create new office head account</p>
                </div>
            </button>
        </div>
    </div>
</div>

<!-- Offices List -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-building"></i> Offices (<?php echo count($offices); ?>)</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Office Name</th>
                        <th>Users</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($offices as $office): ?>
                        <?php
                        $user_count = 0;
                        foreach ($users as $user) {
                            if ($user['office_id'] == $office['id']) $user_count++;
                        }
                        ?>
                        <tr>
                            <td><?php echo $office['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($office['office_name']); ?></strong></td>
                            <td><?php echo $user_count; ?> user(s)</td>
                            <td><?php echo formatDate($office['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Users List -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-users"></i> User Accounts (<?php echo count($users); ?>)</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table account-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Office</th>
                        <th>Role</th>
                        <th>Requests</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['office_name'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge <?php echo $user['role_name'] == 'Admin' ? 'badge-danger' : 'badge-primary'; ?>">
                                    <?php echo htmlspecialchars($user['role_name']); ?>
                                </span>
                            </td>
                            <td><?php echo $user['request_count']; ?></td>
                            <td class="actions">
                                <?php if ($user['role_name'] != 'Admin'): ?>
                                    <button onclick="showChangePasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')" 
                                            class="btn-icon" title="Change Password">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <button onclick="showDeleteUserModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')" 
                                            class="btn-icon btn-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Office Modal -->
<div id="addOfficeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-building"></i> Add New Office</h3>
            <button class="modal-close" onclick="closeModal('addOfficeModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <div class="form-group">
                    <label for="office_name"><i class="fas fa-building"></i> Office Name *</label>
                    <input type="text" id="office_name" name="office_name" class="form-control" 
                           required placeholder="Enter office name">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addOfficeModal')">Cancel</button>
                <button type="submit" name="add_office" class="btn btn-primary">
                    <i class="fas fa-save"></i> Add Office
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Add New User Account</h3>
            <button class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="username"><i class="fas fa-user"></i> Username *</label>
                        <input type="text" id="username" name="username" class="form-control" 
                               required placeholder="Enter username">
                    </div>
                    
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               required placeholder="Enter email">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="full_name"><i class="fas fa-id-card"></i> Full Name *</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" 
                           required placeholder="Enter full name">
                </div>
                
                <div class="form-group">
                    <label for="office_id"><i class="fas fa-building"></i> Office *</label>
                    <select id="office_id" name="office_id" class="form-control" required>
                        <option value="">Select office</option>
                        <?php foreach ($offices as $office): ?>
                            <option value="<?php echo $office['id']; ?>">
                                <?php echo htmlspecialchars($office['office_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password *</label>
                    <input type="password" id="password" name="password" class="form-control" 
                           required placeholder="Enter password (min 6 characters)">
                    <small class="form-text">Minimum 6 characters</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" name="add_user" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Change Password Modal -->
<div id="changePasswordModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-key"></i> Change Password</h3>
            <button class="modal-close" onclick="closeModal('changePasswordModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" id="change_user_id" name="user_id">
            <div class="modal-body">
                <p>Changing password for: <strong id="change_username"></strong></p>
                
                <div class="form-group">
                    <label for="new_password"><i class="fas fa-lock"></i> New Password *</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" 
                           required placeholder="Enter new password (min 6 characters)">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                           required placeholder="Re-enter new password">
                </div>
                
                <div class="form-group">
                    <label for="admin_password_change"><i class="fas fa-shield-alt"></i> Your Admin Password *</label>
                    <input type="password" id="admin_password_change" name="admin_password" class="form-control" 
                           required placeholder="Enter your admin password to confirm">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('changePasswordModal')">Cancel</button>
                <button type="submit" name="change_password" class="btn btn-warning">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete User Modal -->
<div id="deleteUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-trash-alt"></i> Delete User Account</h3>
            <button class="modal-close" onclick="closeModal('deleteUserModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" id="delete_user_id" name="user_id">
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action cannot be undone!
                </div>
                
                <p>Are you sure you want to delete user: <strong id="delete_username"></strong>?</p>
                
                <div class="form-group">
                    <label for="admin_password_delete"><i class="fas fa-shield-alt"></i> Your Admin Password *</label>
                    <input type="password" id="admin_password_delete" name="admin_password" class="form-control" 
                           required placeholder="Enter your admin password to confirm">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteUserModal')">Cancel</button>
                <button type="submit" name="delete_user" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete Account
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddOfficeModal() {
    document.getElementById('addOfficeModal').style.display = 'flex';
}

function showAddUserModal() {
    document.getElementById('addUserModal').style.display = 'flex';
}

function showChangePasswordModal(userId, username) {
    document.getElementById('change_user_id').value = userId;
    document.getElementById('change_username').textContent = username;
    document.getElementById('changePasswordModal').style.display = 'flex';
}

function showDeleteUserModal(userId, username) {
    document.getElementById('delete_user_id').value = userId;
    document.getElementById('delete_username').textContent = username;
    document.getElementById('deleteUserModal').style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<style>
.account-table {
    border: 1px solid #dee2e6;
}

.account-table thead {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.account-table th,
.account-table td {
    border: 1px solid #dee2e6;
    padding: 12px 15px;
}

.account-table tbody tr {
    border-bottom: 1px solid #dee2e6;
}

.account-table tbody tr:last-child {
    border-bottom: none;
}

.account-table tbody tr:hover {
    background-color: #f8f9fa;
}
</style>

<?php include '../includes/footer.php'; ?>