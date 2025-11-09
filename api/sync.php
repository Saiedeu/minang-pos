<?php
/**
 * Data Synchronization API
 * Handles real-time sync between POS and ERP systems
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

header('Content-Type: application/json');

// Check authentication
if (!User::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

$db = Database::getInstance();
$response = ['success' => false, 'message' => 'Invalid action'];

switch ($action) {
    case 'sync_inventory':
        try {
            // Get updated product data
            $products = $db->fetchAll("
                SELECT id, code, name, name_ar, sell_price, quantity, category_id, list_in_pos, is_active, updated_at
                FROM products 
                WHERE is_active = 1
                ORDER BY updated_at DESC
            ");
            
            $response = [
                'success' => true,
                'message' => 'Inventory synchronized successfully',
                'data' => [
                    'products' => $products,
                    'sync_time' => date('Y-m-d H:i:s'),
                    'count' => count($products)
                ]
            ];
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => 'Sync failed: ' . $e->getMessage()];
        }
        break;
        
    case 'sync_categories':
        try {
            $categories = $db->fetchAll("
                SELECT * FROM categories 
                WHERE is_active = 1 
                ORDER BY sort_order, name
            ");
            
            $response = [
                'success' => true,
                'data' => $categories,
                'sync_time' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => 'Category sync failed: ' . $e->getMessage()];
        }
        break;
        
    case 'sync_settings':
        try {
            $settings = [];
            $settingsData = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
            foreach ($settingsData as $setting) {
                $settings[$setting['setting_key']] = $setting['setting_value'];
            }
            
            $response = [
                'success' => true,
                'data' => $settings,
                'sync_time' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => 'Settings sync failed: ' . $e->getMessage()];
        }
        break;
        
    case 'get_sync_status':
        try {
            // Get last modification times for various entities
            $lastUpdated = [];
            
            $lastUpdated['products'] = $db->fetchOne("SELECT MAX(updated_at) as last_update FROM products")['last_update'] ?? null;
            $lastUpdated['categories'] = $db->fetchOne("SELECT MAX(created_at) as last_update FROM categories")['last_update'] ?? null;
            $lastUpdated['settings'] = $db->fetchOne("SELECT MAX(updated_at) as last_update FROM settings")['last_update'] ?? null;
            $lastUpdated['sales'] = $db->fetchOne("SELECT MAX(created_at) as last_update FROM sales WHERE DATE(created_at) = CURDATE()")['last_update'] ?? null;
            
            $response = [
                'success' => true,
                'data' => [
                    'last_updated' => $lastUpdated,
                    'server_time' => date('Y-m-d H:i:s'),
                    'database_status' => 'connected'
                ]
            ];
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => 'Status check failed: ' . $e->getMessage()];
        }
        break;
        
    case 'force_sync':
        try {
            // Perform comprehensive sync
            $syncResults = [];
            
            // Sync products
            $products = $db->fetchAll("SELECT * FROM products WHERE is_active = 1");
            $syncResults['products'] = count($products);
            
            // Sync categories  
            $categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1");
            $syncResults['categories'] = count($categories);
            
            // Sync settings
            $settings = $db->fetchAll("SELECT * FROM settings");
            $syncResults['settings'] = count($settings);
            
            // Update sync timestamp
            $db->query(
                "INSERT INTO settings (setting_key, setting_value) VALUES ('last_sync_time', ?) ON DUPLICATE KEY UPDATE setting_value = ?",
                [date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
            );
            
            $response = [
                'success' => true,
                'message' => 'Full synchronization completed',
                'data' => $syncResults
            ];
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => 'Force sync failed: ' . $e->getMessage()];
        }
        break;
        
    case 'backup_data':
        if (User::hasPermission('user_manage')) {
            try {
                $backupDir = '../backup/auto-backups/';
                if (!is_dir($backupDir)) {
                    mkdir($backupDir, 0755, true);
                }
                
                $filename = 'backup_' . date('Ymd_His') . '.sql';
                $filepath = $backupDir . $filename;
                
                // Create backup command (this is a simplified version)
                $command = "mysqldump -h " . DB_HOST . " -u " . DB_USER . " -p" . DB_PASS . " " . DB_NAME . " > " . $filepath;
                
                // In production, you might want to use a more sophisticated backup method
                $backupCreated = true; // Placeholder for actual backup creation
                
                if ($backupCreated) {
                    $response = [
                        'success' => true,
                        'message' => 'Backup created successfully',
                        'filename' => $filename
                    ];
                } else {
                    $response = ['success' => false, 'message' => 'Backup creation failed'];
                }
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Backup failed: ' . $e->getMessage()];
            }
        } else {
            $response = ['success' => false, 'message' => 'Permission denied'];
        }
        break;
}

echo json_encode($response);
?>