<?php
/**
 * POS System - Start Shift Handler
 * Handles shift initiation with opening balance
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

// Check authentication
if (!User::isLoggedIn()) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = User::getCurrentUser();
    $openingBalance = floatval($_POST['opening_balance'] ?? 0);
    
    // Check if user already has an active shift
    $activeShift = Database::getInstance()->fetchOne(
        "SELECT id FROM shifts WHERE user_id = ? AND is_closed = 0", 
        [$user['id']]
    );
    
    if ($activeShift) {
        header('Location: dashboard.php?error=shift_already_active');
        exit();
    }
    
    // Create new shift
    $shiftData = [
        'user_id' => $user['id'],
        'opening_balance' => $openingBalance,
        'start_time' => date('Y-m-d H:i:s'),
        'expected_cash' => $openingBalance,
        'is_closed' => 0
    ];
    
    $shiftId = Database::getInstance()->insert('shifts', $shiftData);
    
    if ($shiftId) {
        $_SESSION['active_shift_id'] = $shiftId;
        header('Location: sales.php');
    } else {
        header('Location: dashboard.php?error=shift_start_failed');
    }
    exit();
}

// Redirect if accessed directly
header('Location: dashboard.php');
exit();
?>