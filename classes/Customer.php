<?php
/**
 * Customer Management Class
 * Handles customer data and operations
 */

if (!defined('MINANG_SYSTEM')) {
    exit('Direct access not allowed');
}

class Customer {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Create new customer
    public function createCustomer($customerData) {
        try {
            // Validate required fields
            if (empty($customerData['name']) || empty($customerData['phone'])) {
                return ['success' => false, 'message' => 'Customer name and phone are required'];
            }

            // Check if phone number already exists
            $existingCustomer = $this->db->fetchOne(
                "SELECT id FROM customers WHERE phone = ? AND is_active = 1",
                [$customerData['phone']]
            );

            if ($existingCustomer) {
                return ['success' => false, 'message' => 'Customer with this phone number already exists'];
            }

            // Generate customer ID if not provided
            if (empty($customerData['customer_id'])) {
                $customerData['customer_id'] = $this->generateCustomerId();
            }

            $insertData = [
                'customer_id' => $customerData['customer_id'],
                'name' => sanitize($customerData['name']),
                'phone' => sanitize($customerData['phone']),
                'email' => sanitize($customerData['email'] ?? ''),
                'address' => sanitize($customerData['address'] ?? ''),
                'notes' => sanitize($customerData['notes'] ?? ''),
                'is_active' => 1,
                'created_by' => $_SESSION['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ];

            $customerId = $this->db->insert('customers', $insertData);
            
            if ($customerId) {
                return [
                    'success' => true, 
                    'customer_id' => $customerId,
                    'customer_data' => $insertData
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to create customer'];
            }

        } catch (Exception $e) {
            error_log("Customer creation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }

    // Generate unique customer ID
    public function generateCustomerId() {
        $prefix = 'CUS';
        $date = date('Ymd');
        
        // Get count of customers created today
        $count = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM customers WHERE DATE(created_at) = ?",
            [date('Y-m-d')]
        );
        
        $sequence = sprintf('%03d', ($count['count'] ?? 0) + 1);
        return "{$prefix}{$date}{$sequence}";
    }

    // Get customer by ID
    public function getCustomerById($customerId) {
        return $this->db->fetchOne("SELECT * FROM customers WHERE id = ? AND is_active = 1", [$customerId]);
    }

    // Get customer by customer_id (business ID)
    public function getCustomerByCustomerId($customerBusinessId) {
        return $this->db->fetchOne("SELECT * FROM customers WHERE customer_id = ? AND is_active = 1", [$customerBusinessId]);
    }

    // Search customers
    public function searchCustomers($searchTerm) {
        return $this->db->fetchAll("
            SELECT * FROM customers 
            WHERE is_active = 1 
            AND (name LIKE ? OR phone LIKE ? OR customer_id LIKE ? OR email LIKE ?)
            ORDER BY name ASC
            LIMIT 20
        ", ["%{$searchTerm}%", "%{$searchTerm}%", "%{$searchTerm}%", "%{$searchTerm}%"]);
    }

    // Get all customers
    public function getAllCustomers($activeOnly = true) {
        $where = $activeOnly ? "WHERE is_active = 1" : "";
        return $this->db->fetchAll("SELECT * FROM customers {$where} ORDER BY name ASC");
    }

    // Update customer
    public function updateCustomer($customerId, $customerData) {
        try {
            // Validate required fields
            if (empty($customerData['name']) || empty($customerData['phone'])) {
                return ['success' => false, 'message' => 'Customer name and phone are required'];
            }

            // Check if phone number already exists (excluding current customer)
            $existingCustomer = $this->db->fetchOne(
                "SELECT id FROM customers WHERE phone = ? AND id != ? AND is_active = 1",
                [$customerData['phone'], $customerId]
            );

            if ($existingCustomer) {
                return ['success' => false, 'message' => 'Customer with this phone number already exists'];
            }

            $updateData = [
                'name' => sanitize($customerData['name']),
                'phone' => sanitize($customerData['phone']),
                'email' => sanitize($customerData['email'] ?? ''),
                'address' => sanitize($customerData['address'] ?? ''),
                'notes' => sanitize($customerData['notes'] ?? ''),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $updated = $this->db->update('customers', $updateData, 'id = ?', [$customerId]);
            
            return $updated ? ['success' => true] : ['success' => false, 'message' => 'Failed to update customer'];

        } catch (Exception $e) {
            error_log("Customer update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }

    // Delete customer (soft delete)
    public function deleteCustomer($customerId) {
        // Check if customer has sales history
        $salesCount = $this->db->count('sales', 'customer_id = ?', [$customerId]);
        
        if ($salesCount > 0) {
            return ['success' => false, 'message' => 'Cannot delete customer with sales history'];
        }

        $updated = $this->db->update('customers', 
            ['is_active' => 0, 'deleted_at' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$customerId]
        );

        return $updated ? ['success' => true] : ['success' => false, 'message' => 'Failed to delete customer'];
    }

    // Get customer statistics
    public function getCustomerStats() {
        return $this->db->fetchOne("
            SELECT 
                COUNT(*) as total_customers,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_customers,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as new_today,
                COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as new_this_week
            FROM customers
        ");
    }

    // Get customer sales history
    public function getCustomerSalesHistory($customerId, $limit = 10) {
        return $this->db->fetchAll("
            SELECT s.*, u.name as cashier_name
            FROM sales s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.customer_id = ?
            ORDER BY s.created_at DESC
            LIMIT ?
        ", [$customerId, $limit]);
    }

    // Get top customers by sales
    public function getTopCustomers($limit = 10) {
        return $this->db->fetchAll("
            SELECT c.*, 
                   COUNT(s.id) as total_orders,
                   SUM(s.total) as total_spent,
                   AVG(s.total) as avg_order_value,
                   MAX(s.created_at) as last_order_date
            FROM customers c
            LEFT JOIN sales s ON c.id = s.customer_id
            WHERE c.is_active = 1
            GROUP BY c.id
            HAVING total_orders > 0
            ORDER BY total_spent DESC
            LIMIT ?
        ", [$limit]);
    }
}
?>