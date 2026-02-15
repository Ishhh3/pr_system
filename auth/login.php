<?php

// Login System
// Handles user authentication


require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    redirectBasedOnRole();
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Basic validation
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            // Prepare SQL statement to prevent SQL injection
            $stmt = $db->prepare("
                SELECT u.*, r.role_name, o.office_name 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                LEFT JOIN offices o ON u.office_id = o.id 
                WHERE u.username = ? OR u.email = ?
            ");
            $stmt->execute([$username, $username]);

            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch();

                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role_name'];
                    $_SESSION['office_id'] = $user['office_id'];
                    $_SESSION['office_name'] = $user['office_name'];
                    $_SESSION['logged_in'] = true;

                    // Set last login time
                    $_SESSION['last_login'] = time();

                    // Redirect based on role
                    redirectBasedOnRole();
                } else {
                    $error = 'Invalid username or password.';
                }
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = 'Login error. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Purchase Request System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="login-page">
    <div class="login-container bg-white/80">
        <div class="login-box">
            <div class="login-header space-y-3">
    <div class="flex flex-col items-center gap-3">
        <img src="../assets/img/logo-removebg-preview.png" 
             alt="LGU Candelaria Logo" 
             class="h-14 w-auto object-contain">
        <div class="text-center">
            <h1 class="text-xl font-bold">LGU Candelaria</h1>
            <p class="text-sm  font-medium">Purchase Request System</p>
        </div>
    </div>
  
</div>

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

            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i> Username or Email
                    </label>
                    <input type="text" id="username" name="username" class="form-control"
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                        required autofocus>
                </div>

             <div class="form-group">
    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
        <svg class="inline-block w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
        Password
    </label>
   <div class="relative">
    <input 
        type="password" 
        id="password" 
        name="password" 
        class="w-full px-4 py-3 pr-12 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-4 focus:ring-blue-100 outline-none transition-all" 
        required>
    <button 
        type="button" 
        id="togglePassword" 
        class="absolute right-3 top-1/2 -translate-y-1/2 p-1.5 rounded-md hover:bg-gray-100 active:scale-95 transition-all duration-150">
        <svg class="w-5 h-5 transition-transform duration-200" id="eyeIconSvg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path id="eyePath" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        </svg>
    </button>
</div>
</div>


                <div class="form-group">
                    <button type="submit" class="btn signin-btn btn-block">
                        <i class="fas  fa-sign-in-alt"></i> Sign In
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
<script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyePath = document.getElementById('eyePath');
    const eyeIconSvg = document.getElementById('eyeIconSvg');

    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        // Animate and change icon
        eyeIconSvg.style.transform = 'scale(0.8)';
        setTimeout(() => {
            if (type === 'text') {
                eyePath.setAttribute('d', 'M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21');
            } else {
                eyePath.setAttribute('d', 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z');
            }
            eyeIconSvg.style.transform = 'scale(1)';
        }, 150);
    });
  </script>
</body>

</html>