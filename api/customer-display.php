<?php
/**
 * Customer Display API
 * Provides real-time order data for customer display
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Get current order from session or active POS
$response = ['success' => false, 'order' => null];

// Check if customer display is enabled
$db = Database::getInstance();
$displayEnabled = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'customer_display'");

if (($displayEnabled['setting_value'] ?? '0') !== '1') {
    echo json_encode(['success' => false, 'message' => 'Customer display disabled']);
    exit();
}

// Get current order data from session (would be set by POS sales interface)
if (isset($_SESSION['current_order'])) {
    $orderData = $_SESSION['current_order'];
    
    // Add display-friendly data
    $response = [
        'success' => true,
        'order' => [
            'items' => $orderData['items'] ?? [],
            'subtotal' => $orderData['subtotal'] ?? 0,
            'discount' => $orderData['discount'] ?? 0,
            'total' => $orderData['total'] ?? 0,
            'change' => $orderData['change'] ?? 0,
            'payment_method' => $orderData['payment_method'] ?? '',
            'order_type' => $orderData['order_type'] ?? 'dine-in'
        ]
    ];
} else {
    $response = [
        'success' => true,
        'order' => null
    ];
}

echo json_encode($response);
?>