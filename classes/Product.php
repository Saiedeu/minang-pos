<?php
/**
 * Product Management Class
 * Handles product operations and inventory
 */

if (!defined('MINANG_SYSTEM')) {
    exit('Direct access not allowed');
}

class Product {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Get all products
    public function getAllProducts($posOnly = false) {
        $where = $posOnly ? 'WHERE p.list_in_pos = 1 AND p.is_active = 1' : 'WHERE p.is_active = 1';
        
        $sql = "SELECT p.*, c.name as category_name, c.name_ar as category_name_ar 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                {$where}
                ORDER BY p.name";
        
        return $this->db->fetchAll($sql);
    }

    // Get products by category
    public function getProductsByCategory($categoryId, $posOnly = false) {
        $where = "p.category_id = ? AND p.is_active = 1";
        if ($posOnly) {
            $where .= " AND p.list_in_pos = 1";
        }
        
        $sql = "SELECT p.*, c.name as category_name, c.name_ar as category_name_ar 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE {$where}
                ORDER BY p.name";
        
        return $this->db->fetchAll($sql, [$categoryId]);
    }

    // Search products
    public function searchProducts($search, $posOnly = false) {
        $where = "(p.code LIKE ? OR p.name LIKE ? OR p.name_ar LIKE ? OR p.ingredients LIKE ?) AND p.is_active = 1";
        if ($posOnly) {
            $where .= " AND p.list_in_pos = 1";
        }
        
        $sql = "SELECT p.*, c.name as category_name, c.name_ar as category_name_ar 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE {$where}
                ORDER BY p.name";
        
        $searchTerm = "%{$search}%";
        return $this->db->fetchAll($sql, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }

    // Get product by ID
    public function getProductById($id) {
        $sql = "SELECT p.*, c.name as category_name, c.name_ar as category_name_ar 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.id = ?";
        
        return $this->db->fetchOne($sql, [$id]);
    }

    // Get product by code
    public function getProductByCode($code) {
        $sql = "SELECT p.*, c.name as category_name, c.name_ar as category_name_ar 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.code = ? AND p.is_active = 1";
        
        return $this->db->fetchOne($sql, [$code]);
    }

    // Generate product code
    public function generateProductCode($categoryId = null) {
        $prefix = 'PRD';
        
        if ($categoryId) {
            $category = $this->db->fetchOne("SELECT name FROM categories WHERE id = ?", [$categoryId]);
            if ($category) {
                $prefix = strtoupper(substr($category['name'], 0, 3));
            }
        }
        
        // Get last product number
        $sql = "SELECT MAX(CAST(SUBSTRING(code, 4) AS UNSIGNED)) as max_num FROM products WHERE code LIKE ?";
        $result = $this->db->fetchOne($sql, [$prefix . '%']);
        
        $nextNum = ($result && $result['max_num']) ? $result['max_num'] + 1 : 1;
        
        return $prefix . sprintf('%03d', $nextNum);
    }

    // Create product
    public function createProduct($data) {
        // Validate required fields
        $required = ['name', 'category_id', 'sell_price'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Field {$field} is required"];
            }
        }
        
        // Generate code if not provided
        if (empty($data['code'])) {
            $data['code'] = $this->generateProductCode($data['category_id']);
        }
        
        // Check if code exists
        if ($this->codeExists($data['code'])) {
            return ['success' => false, 'message' => 'Product code already exists'];
        }
        
        // Set defaults
        $data['quantity'] = $data['quantity'] ?? 0;
        $data['cost_price'] = $data['cost_price'] ?? 0;
        $data['reorder_level'] = $data['reorder_level'] ?? 5;
        $data['unit'] = $data['unit'] ?? 'PCS';
        $data['list_in_pos'] = $data['list_in_pos'] ?? 1;
        $data['is_active'] = 1;
        $data['created_at'] = date('Y-m-d H:i:s');
        
        $productId = $this->db->insert('products', $data);
        
        if ($productId) {
            // Log initial stock if quantity > 0
            if ($data['quantity'] > 0) {
                $this->logStockMovement($productId, 'IN', 'ADJUSTMENT', null, $data['quantity'], 'Initial stock');
            }
            
            return ['success' => true, 'product_id' => $productId];
        }
        return ['success' => false, 'message' => 'Failed to create product'];
    }

    // Update product
    public function updateProduct($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $updated = $this->db->update('products', $data, 'id = ?', [$id]);
        
        if ($updated) {
            return ['success' => true];
        }
        return ['success' => false, 'message' => 'Failed to update product'];
    }

    // Update stock quantity
    public function updateStock($productId, $newQuantity, $reason = 'Manual adjustment') {
        $product = $this->getProductById($productId);
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }
        
        $oldQuantity = $product['quantity'];
        $difference = $newQuantity - $oldQuantity;
        
        // Update product quantity
        $updated = $this->db->update('products', ['quantity' => $newQuantity], 'id = ?', [$productId]);
        
        if ($updated) {
            // Log stock movement
            $movementType = $difference > 0 ? 'IN' : 'OUT';
            $this->logStockMovement($productId, $movementType, 'ADJUSTMENT', null, abs($difference), $reason);
            
            return ['success' => true, 'old_quantity' => $oldQuantity, 'new_quantity' => $newQuantity];
        }
        
        return ['success' => false, 'message' => 'Failed to update stock'];
    }

    // Reduce stock (for sales)
    public function reduceStock($productId, $quantity) {
        $product = $this->getProductById($productId);
        if (!$product) {
            return false;
        }
        
        $newQuantity = $product['quantity'] - $quantity;
        if ($newQuantity < 0) {
            return false; // Insufficient stock
        }
        
        $updated = $this->db->update('products', ['quantity' => $newQuantity], 'id = ?', [$productId]);
        return $updated > 0;
    }

    // Increase stock (for purchases/returns)
    public function increaseStock($productId, $quantity) {
        $product = $this->getProductById($productId);
        if (!$product) {
            return false;
        }
        
        $newQuantity = $product['quantity'] + $quantity;
        $updated = $this->db->update('products', ['quantity' => $newQuantity], 'id = ?', [$productId]);
        return $updated > 0;
    }

    // Log stock movement
    public function logStockMovement($productId, $type, $referenceType, $referenceId, $quantity, $reason) {
        $data = [
            'product_id' => $productId,
            'type' => $type,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'quantity' => $quantity,
            'reason' => $reason,
            'created_by' => $_SESSION['user_id'] ?? 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->insert('stock_movements', $data);
    }

    // Get stock movements
    public function getStockMovements($productId = null, $limit = 100) {
        $where = $productId ? 'WHERE sm.product_id = ?' : '';
        $params = $productId ? [$productId] : [];
        
        $sql = "SELECT sm.*, p.name as product_name, u.name as user_name 
                FROM stock_movements sm
                LEFT JOIN products p ON sm.product_id = p.id
                LEFT JOIN users u ON sm.created_by = u.id
                {$where}
                ORDER BY sm.created_at DESC
                LIMIT ?";
        
        $params[] = $limit;
        return $this->db->fetchAll($sql, $params);
    }

    // Get low stock products
    public function getLowStockProducts() {
        $sql = "SELECT p.*, c.name as category_name 
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.quantity <= p.reorder_level AND p.is_active = 1
                ORDER BY p.quantity ASC";
        
        return $this->db->fetchAll($sql);
    }

    // Check if code exists
    public function codeExists($code, $excludeId = null) {
        $sql = "SELECT id FROM products WHERE code = ?";
        $params = [$code];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return $result !== false;
    }

    // Delete product (soft delete)
    public function deleteProduct($id) {
        return $this->db->update('products', ['is_active' => 0], 'id = ?', [$id]);
    }

    // Get product statistics
    public function getProductStats() {
        $stats = [];
        
        // Total products
        $stats['total'] = $this->db->count('products', 'is_active = 1');
        
        // Products in POS
        $stats['pos_active'] = $this->db->count('products', 'is_active = 1 AND list_in_pos = 1');
        
        // Low stock products
        $stats['low_stock'] = $this->db->count('products', 'is_active = 1 AND quantity <= reorder_level');
        
        // Out of stock products
        $stats['out_of_stock'] = $this->db->count('products', 'is_active = 1 AND quantity = 0');
        
        // Total inventory value
        $sql = "SELECT SUM(quantity * cost_price) as total_cost, SUM(quantity * sell_price) as total_value FROM products WHERE is_active = 1";
        $values = $this->db->fetchOne($sql);
        $stats['total_cost'] = $values['total_cost'] ?? 0;
        $stats['total_value'] = $values['total_value'] ?? 0;
        
        return $stats;
    }

    // Get products for POS (optimized)
    public function getProductsForPOS() {
        $sql = "SELECT id, code, name, name_ar, sell_price, quantity, category_id, photo
                FROM products 
                WHERE is_active = 1 AND list_in_pos = 1 AND quantity > 0
                ORDER BY name";
        
        return $this->db->fetchAll($sql);
    }
}
?>