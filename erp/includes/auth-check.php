<?php
/**
 * Authentication Check for ERP
 * Include this file on pages that require authentication
 */

if (!defined('MINANG_SYSTEM')) {
    exit('Direct access not allowed');
}

// Check if user is logged in
if (!User::isLoggedIn()) {
    header('Location: index.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Update last activity
$_SESSION['last_activity'] = time();

// Get current user for global use
$currentUser = User::getCurrentUser();

// Check specific permissions based on current page
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// Define page permissions
$pagePermissions = [
    'inventory' => 'inventory_manage',
    'purchases' => 'inventory_manage', 
    'hr' => 'user_manage',
    'settings' => 'user_manage',
    'reports' => 'reports_view'
];

// Check permission if required
if (isset($pagePermissions[$currentDir]) && !User::hasPermission($pagePermissions[$currentDir])) {
    header('Location: ../dashboard.php?error=no_permission');
    exit();
}
?>