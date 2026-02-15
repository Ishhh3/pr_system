<?php

// Authentication Check
// Protects pages from unauthorized access


require_once '../config/session.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit();
}

// Check session expiration (8 hours)
$session_timeout = 8 * 60 * 60; // 8 hours in seconds
if (isset($_SESSION['last_login']) && (time() - $_SESSION['last_login'] > $session_timeout)) {
    session_destroy();
    header("Location: ../auth/login.php?expired=1");
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();
?>