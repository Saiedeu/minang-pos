<?php
/**
 * System Alert Management Class
 * Handle system notifications and alerts
 */

if (!defined('MINANG_SYSTEM')) {
    exit('Direct access not allowed');
}

class SystemAlert {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Create system alert
    public function createAlert($type, $title, $message, $userId = null, $priority = 'medium') {
        $alertData = [
            'alert_type' => $type,
            'title' => $title,
            'message' => $message,
            'user_id' => $userId, // null for global alerts
            'priority' => $priority,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->insert('system_alerts', $alertData);
    }
    
    // Get alerts for user
    public function getUserAlerts($userId, $limit = 10) {
        $sql = "SELECT * FROM system_alerts 
                WHERE (user_id = ? OR user_id IS NULL) 
                AND is_active = 1
                ORDER BY 
                    FIELD(priority, 'high', 'medium', 'low'),
                    created_at DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$userId, $limit]);
    }
    
    // Mark alert as read
    public function markAsRead($alertId, $userId = null) {
        $where = 'id = ?';
        $params = [$alertId];
        
        if ($userId) {
            $where .= ' AND (user_id = ? OR user_id IS NULL)';
            $params[] = $userId;
        }
        
        return $this->db->update('system_alerts', ['is_read' => 1], $where, $params);
    }
    
    // Auto-generate stock alerts
    public function generateStockAlerts() {
        $product = new Product();
        $lowStockProducts = $product->getLowStockProducts();
        
        foreach ($lowStockProducts as $prod) {
            if ($prod['quantity'] == 0) {
                $this->createAlert(
                    'STOCK_OUT',
                    'Out of Stock Alert',
                    "Product '{$prod['name']}' is out of stock",
                    null,
                    'high'
                );
            } elseif ($prod['quantity'] <= $prod['reorder_level']) {
                $this->createAlert(
                    'STOCK_LOW',
                    'Low Stock Alert', 
                    "Product '{$prod['name']}' is running low (Current: {$prod['quantity']})",
                    null,
                    'medium'
                );
            }
        }
    }
    
    // Check for unpaid purchase alerts
    public function checkUnpaidPurchaseAlerts() {
        $purchase = new Purchase();
        $unpaidPurchases = $purchase->getUnpaidPurchases();
        
        foreach ($unpaidPurchases as $purch) {
            $daysPending = ceil((time() - strtotime($purch['purchase_date'])) / 86400);
            
            if ($daysPending > 30) {
                $this->createAlert(
                    'PAYMENT_OVERDUE',
                    'Payment Overdue',
                    "Purchase invoice {$purch['invoice_number']} from {$purch['supplier_name']} is {$daysPending} days overdue",
                    null,
                    'high'
                );
            } elseif ($daysPending > 15) {
                $this->createAlert(
                    'PAYMENT_DUE',
                    'Payment Due Soon',
                    "Purchase invoice {$purch['invoice_number']} from {$purch['supplier_name']} is due for payment",
                    null,
                    'medium'
                );
            }
        }
    }
    
    // Delete old alerts
    public function cleanupOldAlerts($daysOld = 7) {
        $cutoffDate = date('Y-m-d', strtotime("-{$daysOld} days"));
        return $this->db->delete('system_alerts', 'DATE(created_at) < ? AND is_read = 1', [$cutoffDate]);
    }
    
    // Get alert summary
    public function getAlertSummary($userId) {
        $sql = "SELECT 
                    COUNT(*) as total_alerts,
                    COUNT(CASE WHEN is_read = 0 THEN 1 END) as unread_alerts,
                    COUNT(CASE WHEN priority = 'high' AND is_read = 0 THEN 1 END) as high_priority_unread,
                    MAX(created_at) as latest_alert
                FROM system_alerts 
                WHERE (user_id = ? OR user_id IS NULL) 
                AND is_active = 1";
        
        return $this->db->fetchOne($sql, [$userId]);
    }
}
?>