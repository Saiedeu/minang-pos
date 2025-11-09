<?php
/**
 * Authentication Check for POS
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

// Check if user has POS access
if (!User::hasPermission('pos_sales')) {
    User::logout();
    header('Location: index.php?error=no_permission');
    exit();
}

// Check if shift is active (for sales pages)
$currentFile = basename($_SERVER['PHP_SELF'], '.php');
$salesPages = ['sales', 'checkout'];

if (in_array($currentFile, $salesPages)) {
    $db = Database::getInstance();
    $activeShift = $db->fetchOne(
        "SELECT id FROM shifts WHERE user_id = ? AND is_closed = 0", 
        [$_SESSION['user_id']]
    );
    
    if (!$activeShift) {
        header('Location: dashboard.php?error=no_active_shift');
        exit();
    }
    
    // Store active shift ID in session
    $_SESSION['active_shift_id'] = $activeShift['id'];
}

// Update last activity
$_SESSION['last_activity'] = time();
?>