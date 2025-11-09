<?php
/**
 * Restaurant Management API Handler
 * Handle AJAX requests for restaurant management operations
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

$restoMgmt = new RestaurantManagement();
$response = ['success' => false, 'message' => 'Invalid action'];

switch ($action) {
    case 'update_task_status':
        $taskId = $input['task_id'] ?? 0;
        $status = $input['status'] ?? '';
        $notes = $input['notes'] ?? '';
        
        if ($taskId && $status) {
            $response = $restoMgmt->updateTaskStatus($taskId, $status, $notes);
        } else {
            $response = ['success' => false, 'message' => 'Task ID and status required'];
        }
        break;
        
    case 'get_overdue_tasks':
        $overdueTasks = $restoMgmt->getTasks('pending');
        $overdueTasks = array_filter($overdueTasks, fn($task) => $task['due_date'] && $task['due_date'] < date('Y-m-d'));
        
        $response = ['success' => true, 'tasks' => array_values($overdueTasks)];
        break;
        
    case 'add_temperature_record':
        $temperatureData = $input['temperature_data'] ?? [];
        $response = $restoMgmt->recordTemperature($temperatureData);
        break;
        
    case 'get_temperature_alerts':
        $alerts = [];
        
        // Check for missing today's temperature records
        $expectedRecords = 15; // 3 equipment × 5 time slots
        $todayRecords = Database::getInstance()->fetchOne("
            SELECT COUNT(*) as count 
            FROM temperature_records 
            WHERE record_date = CURDATE()
        ")['count'] ?? 0;
        
        if ($todayRecords < $expectedRecords) {
            $alerts[] = [
                'type' => 'missing_records',
                'message' => 'Missing temperature records for today',
                'severity' => 'warning',
                'missing_count' => $expectedRecords - $todayRecords
            ];
        }
        
        // Check for out-of-range temperatures
        $outOfRangeRecords = Database::getInstance()->fetchAll("
            SELECT * FROM temperature_records 
            WHERE record_date = CURDATE()
            AND (
                (equipment_type = 'chiller' AND temperature > 8) OR
                (equipment_type = 'freezer' AND temperature > -12)
            )
        ");
        
        if (!empty($outOfRangeRecords)) {
            $alerts[] = [
                'type' => 'temperature_violation',
                'message' => count($outOfRangeRecords) . ' temperature violation(s) today',
                'severity' => 'critical',
                'records' => $outOfRangeRecords
            ];
        }
        
        $response = ['success' => true, 'alerts' => $alerts];
        break;
        
    case 'update_expiration_status':
        $recordId = $input['record_id'] ?? 0;
        $status = $input['status'] ?? '';
        $disposalReason = $input['disposal_reason'] ?? '';
        
        if ($recordId && $status) {
            $updateData = [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($status === 'disposed') {
                $updateData['disposal_date'] = date('Y-m-d');
                $updateData['disposal_reason'] = $disposalReason;
            }
            
            $updated = Database::getInstance()->update('expiration_records', $updateData, 'id = ?', [$recordId]);
            $response = $updated ? ['success' => true] : ['success' => false, 'message' => 'Failed to update'];
        } else {
            $response = ['success' => false, 'message' => 'Record ID and status required'];
        }
        break;
        
    case 'get_expiration_alerts':
        $expirationAlerts = $restoMgmt->getExpirationAlerts();
        $response = ['success' => true, 'alerts' => $expirationAlerts];
        break;
        
    case 'update_plan_progress':
        $planId = $input['plan_id'] ?? 0;
        $progress = intval($input['progress'] ?? 0);
        $status = $input['status'] ?? '';
        
        if ($planId) {
            $updateData = ['completion_percentage' => $progress];
            if ($status) {
                $updateData['status'] = $status;
            }
            
            $updated = Database::getInstance()->update('restaurant_plans', $updateData, 'id = ?', [$planId]);
            $response = $updated ? ['success' => true] : ['success' => false];
        } else {
            $response = ['success' => false, 'message' => 'Plan ID required'];
        }
        break;
        
    case 'get_dashboard_alerts':
        $analytics = $restoMgmt->getRestaurantAnalytics();
        $response = ['success' => true, 'analytics' => $analytics];
        break;
}

echo json_encode($response);
?>