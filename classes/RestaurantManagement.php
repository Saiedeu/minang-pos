<?php
/**
 * Restaurant Management Class
 * Handles restaurant operational management features
 */

if (!defined('MINANG_SYSTEM')) {
    exit('Direct access not allowed');
}

class RestaurantManagement {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // ==================== TO-DO LIST MANAGEMENT ====================
    
    public function createTask($taskData) {
        try {
            $insertData = [
                'title' => sanitize($taskData['title']),
                'description' => sanitize($taskData['description'] ?? ''),
                'category' => sanitize($taskData['category'] ?? 'general'),
                'priority' => intval($taskData['priority'] ?? 2),
                'due_date' => $taskData['due_date'] ?? null,
                'due_time' => $taskData['due_time'] ?? null,
                'assigned_to' => intval($taskData['assigned_to'] ?? 0) ?: null,
                'status' => 'pending',
                'created_by' => $_SESSION['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ];

            $taskId = $this->db->insert('restaurant_tasks', $insertData);
            return $taskId ? ['success' => true, 'task_id' => $taskId] : ['success' => false, 'message' => 'Failed to create task'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateTaskStatus($taskId, $status, $notes = '') {
        $updateData = [
            'status' => $status,
            'completion_notes' => $notes,
            'completed_by' => $_SESSION['user_id'],
            'completed_at' => $status === 'completed' ? date('Y-m-d H:i:s') : null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $updated = $this->db->update('restaurant_tasks', $updateData, 'id = ?', [$taskId]);
        return $updated ? ['success' => true] : ['success' => false];
    }

    public function getTasks($status = null, $assignedTo = null) {
        $where = ['1=1'];
        $params = [];

        if ($status) {
            $where[] = 'rt.status = ?';
            $params[] = $status;
        }

        if ($assignedTo) {
            $where[] = 'rt.assigned_to = ?';
            $params[] = $assignedTo;
        }

        return $this->db->fetchAll("
            SELECT rt.*, 
                   u1.name as created_by_name,
                   u2.name as assigned_to_name,
                   u3.name as completed_by_name
            FROM restaurant_tasks rt
            LEFT JOIN users u1 ON rt.created_by = u1.id
            LEFT JOIN users u2 ON rt.assigned_to = u2.id
            LEFT JOIN users u3 ON rt.completed_by = u3.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY rt.priority DESC, rt.due_date ASC, rt.created_at DESC
        ", $params);
    }

    // ==================== TEMPERATURE RECORDS ====================
    
    public function recordTemperature($temperatureData) {
        try {
            $insertData = [
                'equipment_type' => sanitize($temperatureData['equipment_type']), // 'chiller' or 'freezer'
                'equipment_number' => sanitize($temperatureData['equipment_number']),
                'record_date' => $temperatureData['record_date'],
                'time_slot' => sanitize($temperatureData['time_slot']), // '6am', '10am', '2pm', '6pm', '10pm'
                'temperature' => floatval($temperatureData['temperature']),
                'remarks' => sanitize($temperatureData['remarks'] ?? ''),
                'corrective_action' => sanitize($temperatureData['corrective_action'] ?? ''),
                'shift_person' => sanitize($temperatureData['shift_person']),
                'recorded_by' => $_SESSION['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ];

            $recordId = $this->db->insert('temperature_records', $insertData);
            return $recordId ? ['success' => true, 'record_id' => $recordId] : ['success' => false];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getTemperatureRecords($equipmentType, $month, $year, $equipmentNumber = null) {
        $where = "equipment_type = ? AND MONTH(record_date) = ? AND YEAR(record_date) = ?";
        $params = [$equipmentType, $month, $year];

        if ($equipmentNumber) {
            $where .= " AND equipment_number = ?";
            $params[] = $equipmentNumber;
        }

        return $this->db->fetchAll("
            SELECT tr.*, u.name as recorded_by_name
            FROM temperature_records tr
            LEFT JOIN users u ON tr.recorded_by = u.id
            WHERE {$where}
            ORDER BY tr.record_date DESC, 
                    FIELD(tr.time_slot, '6am', '10am', '2pm', '6pm', '10pm')
        ", $params);
    }

    // ==================== EXPIRATION RECORDS ====================
    
    public function addExpirationItem($expirationData) {
        try {
            $insertData = [
                'item_name' => sanitize($expirationData['item_name']),
                'product_id' => intval($expirationData['product_id']) ?: null,
                'batch_number' => sanitize($expirationData['batch_number'] ?? ''),
                'expiry_date' => $expirationData['expiry_date'],
                'quantity' => floatval($expirationData['quantity']),
                'unit' => sanitize($expirationData['unit'] ?? 'PCS'),
                'location' => sanitize($expirationData['location'] ?? ''),
                'supplier' => sanitize($expirationData['supplier'] ?? ''),
                'notes' => sanitize($expirationData['notes'] ?? ''),
                'status' => 'active',
                'recorded_by' => $_SESSION['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ];

            $itemId = $this->db->insert('expiration_records', $insertData);
            return $itemId ? ['success' => true, 'item_id' => $itemId] : ['success' => false];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getExpirationAlerts($days = 7) {
        return $this->db->fetchAll("
            SELECT er.*, u.name as recorded_by_name,
                   DATEDIFF(er.expiry_date, CURDATE()) as days_until_expiry
            FROM expiration_records er
            LEFT JOIN users u ON er.recorded_by = u.id
            WHERE er.status = 'active' 
            AND er.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY er.expiry_date ASC
        ", [$days]);
    }

    public function getExpirationRecords($status = 'active') {
        return $this->db->fetchAll("
            SELECT er.*, u.name as recorded_by_name,
                   DATEDIFF(er.expiry_date, CURDATE()) as days_until_expiry,
                   CASE 
                       WHEN er.expiry_date < CURDATE() THEN 'expired'
                       WHEN DATEDIFF(er.expiry_date, CURDATE()) <= 7 THEN 'expiring_soon'
                       ELSE 'safe'
                   END as urgency_level
            FROM expiration_records er
            LEFT JOIN users u ON er.recorded_by = u.id
            WHERE er.status = ?
            ORDER BY er.expiry_date ASC
        ", [$status]);
    }

    // ==================== PLANNING SYSTEM ====================
    
    public function createPlan($planData) {
        try {
            $insertData = [
                'title' => sanitize($planData['title']),
                'description' => sanitize($planData['description'] ?? ''),
                'plan_type' => sanitize($planData['plan_type']), // 'weekly', 'monthly', 'event'
                'start_date' => $planData['start_date'],
                'end_date' => $planData['end_date'],
                'assigned_department' => sanitize($planData['assigned_department'] ?? ''),
                'priority' => intval($planData['priority'] ?? 2),
                'status' => 'draft',
                'created_by' => $_SESSION['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ];

            $planId = $this->db->insert('restaurant_plans', $insertData);
            return $planId ? ['success' => true, 'plan_id' => $planId] : ['success' => false];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getPlans($planType = null) {
        $where = $planType ? "WHERE rp.plan_type = ?" : "";
        $params = $planType ? [$planType] : [];

        return $this->db->fetchAll("
            SELECT rp.*, u.name as created_by_name
            FROM restaurant_plans rp
            LEFT JOIN users u ON rp.created_by = u.id
            {$where}
            ORDER BY rp.start_date DESC, rp.priority DESC
        ", $params);
    }

    // ==================== ANALYTICS ====================
    
    public function getRestaurantAnalytics() {
        return [
            'pending_tasks' => $this->db->count('restaurant_tasks', 'status = ?', ['pending']),
            'overdue_tasks' => $this->db->count('restaurant_tasks', 'status = ? AND due_date < CURDATE()', ['pending']),
            'expiring_items' => $this->db->count('expiration_records', 'status = ? AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)', ['active']),
            'expired_items' => $this->db->count('expiration_records', 'status = ? AND expiry_date < CURDATE()', ['active']),
            'active_plans' => $this->db->count('restaurant_plans', 'status IN (?, ?)', ['active', 'in_progress']),
            'temperature_alerts' => $this->getTemperatureAlerts()
        ];
    }

    private function getTemperatureAlerts() {
        $alerts = 0;
        
        // Check for missing temperature records (should be recorded 5 times daily)
        $todayRecords = $this->db->fetchOne("
            SELECT COUNT(DISTINCT CONCAT(equipment_type, '-', equipment_number, '-', time_slot)) as recorded_count
            FROM temperature_records 
            WHERE record_date = CURDATE()
        ")['recorded_count'] ?? 0;
        
        // Assuming 2 chillers and 1 freezer = 15 records needed daily (3 equipment × 5 times)
        if ($todayRecords < 15) {
            $alerts++;
        }
        
        return $alerts;
    }
}
?>