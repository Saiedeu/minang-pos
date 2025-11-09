<?php
/**
 * Tables API Handler
 * Handle AJAX requests for table operations
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
    case 'get_available_tables':
        $availableTables = $db->fetchAll("
            SELECT t.* 
            FROM tables t
            LEFT JOIN sales s ON t.table_number = s.table_number 
                AND s.kitchen_status < 2 
                AND DATE(s.created_at) = CURDATE()
            WHERE t.is_active = 1 
            AND t.status = 'available'
            AND s.id IS NULL
            ORDER BY CAST(t.table_number AS UNSIGNED)
        ");
        
        $response = ['success' => true, 'tables' => $availableTables];
        break;
        
    case 'get_table_status':
        $tableNumber = $input['table_number'] ?? $_GET['table_number'] ?? '';
        
        if ($tableNumber) {
            $tableInfo = $db->fetchOne("
                SELECT t.*, 
                       COUNT(s.id) as active_orders,
                       SUM(s.total) as table_total,
                       MAX(s.created_at) as last_order_time
                FROM tables t
                LEFT JOIN sales s ON t.table_number = s.table_number 
                    AND s.kitchen_status < 2 
                    AND DATE(s.created_at) = CURDATE()
                WHERE t.table_number = ? AND t.is_active = 1
                GROUP BY t.id
            ", [$tableNumber]);
            
            if ($tableInfo) {
                $response = ['success' => true, 'table' => $tableInfo];
            } else {
                $response = ['success' => false, 'message' => 'Table not found'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Table number required'];
        }
        break;
        
    case 'update_table_status':
        $tableId = $input['table_id'] ?? 0;
        $status = $input['status'] ?? 'available';
        $notes = $input['notes'] ?? '';
        
        if ($tableId) {
            $updated = $db->update('tables', 
                ['status' => $status, 'status_notes' => $notes, 'updated_at' => date('Y-m-d H:i:s')], 
                'id = ?', 
                [$tableId]
            );
            
            if ($updated) {
                // Log table status change
                $db->insert('table_log', [
                    'table_id' => $tableId,
                    'status' => $status,
                    'notes' => $notes,
                    'updated_by' => $_SESSION['user_id'],
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
                $response = ['success' => true, 'message' => 'Table status updated'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to update table status'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Table ID required'];
        }
        break;
        
    case 'get_table_orders':
        $tableNumber = $input['table_number'] ?? $_GET['table_number'] ?? '';
        
        if ($tableNumber) {
            $orders = $db->fetchAll("
                SELECT s.*, u.name as cashier_name
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.table_number = ?
                AND DATE(s.created_at) = CURDATE()
                ORDER BY s.created_at DESC
            ", [$tableNumber]);
            
            // Get items for each order
            foreach ($orders as &$order) {
                $order['items'] = $db->fetchAll("
                    SELECT * FROM sale_items 
                    WHERE sale_id = ?
                ", [$order['id']]);
            }
            
            $response = ['success' => true, 'orders' => $orders];
        } else {
            $response = ['success' => false, 'message' => 'Table number required'];
        }
        break;
        
    case 'table_analytics':
        $date = $input['date'] ?? $_GET['date'] ?? date('Y-m-d');
        
        $analytics = $db->fetchAll("
            SELECT 
                t.table_number,
                t.table_name,
                t.capacity,
                COUNT(s.id) as order_count,
                SUM(s.total) as revenue,
                AVG(s.total) as avg_order_value,
                COUNT(DISTINCT TIME_FORMAT(s.created_at, '%H')) as busy_hours
            FROM tables t
            LEFT JOIN sales s ON t.table_number = s.table_number 
                AND DATE(s.created_at) = ?
            WHERE t.is_active = 1
            GROUP BY t.id
            ORDER BY revenue DESC
        ", [$date]);
        
        $response = ['success' => true, 'analytics' => $analytics];
        break;
        
    case 'reserve_table':
        $tableId = $input['table_id'] ?? 0;
        $customerName = $input['customer_name'] ?? '';
        $customerPhone = $input['customer_phone'] ?? '';
        $reservationTime = $input['reservation_time'] ?? date('Y-m-d H:i:s');
        $notes = $input['notes'] ?? '';
        
        if ($tableId) {
            $reservationData = [
                'table_id' => $tableId,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'reservation_time' => $reservationTime,
                'notes' => $notes,
                'status' => 'active',
                'created_by' => $_SESSION['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $reservationId = $db->insert('table_reservations', $reservationData);
            
            if ($reservationId) {
                // Update table status
                $db->update('tables', ['status' => 'reserved'], 'id = ?', [$tableId]);
                
                $response = ['success' => true, 'reservation_id' => $reservationId];
            } else {
                $response = ['success' => false, 'message' => 'Failed to create reservation'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Table ID required'];
        }
        break;
}

echo json_encode($response);
?>