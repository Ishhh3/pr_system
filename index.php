<?php

// Main Entry Point
// Redirects to appropriate dashboard based on user role


require_once 'config/session.php';

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // User is logged in, redirect to appropriate dashboard
    if ($_SESSION['role'] == 'Admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit();
} else {
    // User is not logged in, redirect to login
    header("Location: auth/login.php");
    exit();
}
?>