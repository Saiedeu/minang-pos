<?php
/**
 * Logout Handler
 * Handles user logout for both POS and ERP
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

// Logout user
User::logout();

// Redirect to appropriate login page
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
if (strpos($referrer, '/pos/') !== false) {
    header('Location: index.php?message=logged_out');
} else {
    header('Location: ../index.php?message=logged_out');
}
exit();
?>