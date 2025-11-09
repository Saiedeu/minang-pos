<?php
/**
 * Connection Test Endpoint
 * Simple endpoint to test server connectivity
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Basic connectivity test
$response = [
    'success' => true,
    'server_time' => date('Y-m-d H:i:s'),
    'status' => 'online',
    'version' => SYSTEM_VERSION
];

// Test database connection
try {
    $db = Database::getInstance();
    $db->fetchOne("SELECT 1");
    $response['database'] = 'connected';
} catch (Exception $e) {
    $response['database'] = 'error';
    $response['success'] = false;
}

// Check if user is logged in
$response['authenticated'] = User::isLoggedIn();

echo json_encode($response);
?>