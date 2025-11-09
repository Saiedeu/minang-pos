<?php
/**
 * Kitchen Operations API
 * Handle kitchen display and order status updates
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
    case 'update_status':
        $orderId = $input['order_id'] ?? 0;
        $kitchenStatus = $input['kitchen_status'] ?? 0;
        
        if ($orderId) {
            $updated = $db->update('sales', 
                ['kitchen_status' => $kitchenStatus, 'kitchen_updated_at' => date('Y-m-d H:i:s')], 
                'id = ?', 
                [$orderId]
            );
            
            if ($updated) {
                // Log kitchen activity
                $db->insert('kitchen_log', [
                    'order_id' => $orderId,
                    'status' => $kitchenStatus,
                    'updated_by' => $_SESSION['user_id'],
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
                $statusNames = [0 => 'Pending', 1 => 'Cooking', 2 => 'Ready', 3 => 'Cancelled'];
                $response = [
                    'success' => true, 
                    'message' => 'Order status updated to: ' . $statusNames[$kitchenStatus]
                ];
            } else {
                $response = ['success' => false, 'message' => 'Failed to update status'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Order ID required'];
        }
        break;
        
    case 'get_pending_orders':
        $orders = $db->fetchAll("
            SELECT s.*, 
                   CASE 
                       WHEN s.order_type = 1 THEN CONCAT('Table ', s.table_number)
                       WHEN s.order_type = 2 THEN 'Take Away'
                       WHEN s.order_type = 3 THEN CONCAT('Delivery - ', s.customer_name)
                   END as order_display,
                   TIMESTAMPDIFF(MINUTE, s.created_at, NOW()) as minutes_ago
            FROM sales s
            WHERE s.kitchen_status IN (0, 1)
            AND DATE(s.created_at) = CURDATE()
            ORDER BY s.created_at ASC
        ");
        
        // Get items for each order
        foreach ($orders as &$order) {
            $order['items'] = $db->fetchAll("
                SELECT * FROM sale_items 
                WHERE sale_id = ? 
                ORDER BY id
            ", [$order['id']]);
        }
        
        $response = ['success' => true, 'orders' => $orders];
        break;
        
    case 'get_kitchen_stats':
        $today = date('Y-m-d');
        
        $stats = $db->fetchOne("
            SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN kitchen_status = 0 THEN 1 END) as pending_orders,
                COUNT(CASE WHEN kitchen_status = 1 THEN 1 END) as cooking_orders,
                COUNT(CASE WHEN kitchen_status = 2 THEN 1 END) as ready_orders,
                COUNT(CASE WHEN kitchen_status = 3 THEN 1 END) as cancelled_orders,
                AVG(CASE WHEN kitchen_status = 2 THEN TIMESTAMPDIFF(MINUTE, created_at, kitchen_updated_at) END) as avg_prep_time
            FROM sales
            WHERE DATE(created_at) = ?
        ", [$today]);
        
        $response = ['success' => true, 'stats' => $stats];
        break;
        
    case 'get_order_timeline':
        $orderId = $input['order_id'] ?? $_GET['order_id'] ?? 0;
        
        if ($orderId) {
            $timeline = $db->fetchAll("
                SELECT kl.*, u.name as updated_by_name
                FROM kitchen_log kl
                LEFT JOIN users u ON kl.updated_by = u.id
                WHERE kl.order_id = ?
                ORDER BY kl.updated_at ASC
            ", [$orderId]);
            
            $response = ['success' => true, 'timeline' => $timeline];
        } else {
            $response = ['success' => false, 'message' => 'Order ID required'];
        }
        break;
        
    case 'emergency_alert':
        // Send alert for urgent orders or kitchen issues
        $message = $input['message'] ?? '';
        $orderId = $input['order_id'] ?? null;
        
        if ($message) {
            $alertData = [
                'message' => $message,
                'order_id' => $orderId,
                'alert_type' => 'KITCHEN_EMERGENCY',
                'created_by' => $_SESSION['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $inserted = $db->insert('system_alerts', $alertData);
            $response = $inserted ? ['success' => true] : ['success' => false, 'message' => 'Failed to create alert'];
        } else {
            $response = ['success' => false, 'message' => 'Message required'];
        }
        break;
}

echo json_encode($response);
?>