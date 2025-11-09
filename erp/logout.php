<?php
/**
 * ERP Logout Handler
 * Handles user logout for ERP system
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

// Logout user
User::logout();

// Redirect to ERP login
header('Location: index.php?message=logged_out');
exit();
?>