<?php
/**
 * Purchase Management Class
 * Handles supplier and purchase operations
 */

if (!defined('MINANG_SYSTEM')) {
    exit('Direct access not allowed');
}

class Purchase {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // SUPPLIER MANAGEMENT

    // Get all suppliers
    public function getAllSuppliers($activeOnly = true) {
        $where = $activeOnly ? 'WHERE is_active = 1' : '';
        $sql = "SELECT * FROM suppliers {$where} ORDER BY name";
        return $this->db->fetchAll($sql);
    }

    // Get supplier by ID
    public function getSupplierById($id) {
        return $this->db->fetchOne("SELECT * FROM suppliers WHERE id = ?", [$id]);
    }

    // Create supplier
    public function createSupplier($data) {
        $required = ['name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Field {$field} is required"];
            }
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $data['is_active'] = 1;

        $supplierId = $this->db->insert('suppliers', $data);
        
        if ($supplierId) {
            return ['success' => true, 'supplier_id' => $supplierId];
        }
        return ['success' => false, 'message' => 'Failed to create supplier'];
    }

    // Update supplier
    public function updateSupplier($id, $data) {
        $updated = $this->db->update('suppliers', $data, 'id = ?', [$id]);
        
        if ($updated) {
            return ['success' => true];
        }
        return ['success' => false, 'message' => 'Failed to update supplier'];
    }

    // PURCHASE MANAGEMENT

    // Create purchase invoice
    public function createPurchase($purchaseData, $purchaseItems) {
        try {
            $this->db->beginTransaction();
            
            // Set creation data
            $purchaseData['created_by'] = $_SESSION['user_id'];
            $purchaseData['created_at'] = date('Y-m-d H:i:s');
            
            // Calculate totals
            $subtotal = 0;
            foreach ($purchaseItems as $item) {
                $itemTotal = ($item['quantity'] * $item['unit_price']) - $item['discount'];
                $subtotal += $itemTotal;
            }
            
            $purchaseData['subtotal'] = $subtotal;
            $purchaseData['total'] = $subtotal - ($purchaseData['discount'] ?? 0);
            
            // Set payment status
            $paidAmount = $purchaseData['paid_amount'] ?? 0;
            if ($paidAmount >= $purchaseData['total']) {
                $purchaseData['payment_status'] = 2; // Fully paid
            } elseif ($paidAmount > 0) {
                $purchaseData['payment_status'] = 1; // Partially paid
            } else {
                $purchaseData['payment_status'] = 0; // Unpaid
            }
            
            // Insert purchase record
            $purchaseId = $this->db->insert('purchases', $purchaseData);
            
            if (!$purchaseId) {
                throw new Exception('Failed to create purchase record');
            }
            
            // Insert purchase items and update inventory
            foreach ($purchaseItems as $item) {
                $itemData = [
                    'purchase_id' => $purchaseId,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'] ?? 0,
                    'total_price' => ($item['quantity'] * $item['unit_price']) - ($item['discount'] ?? 0)
                ];
                
                $itemId = $this->db->insert('purchase_items', $itemData);
                
                if (!$itemId) {
                    throw new Exception('Failed to create purchase item');
                }
                
                // Update product stock
                $product = new Product();
                $product->increaseStock($item['product_id'], $item['quantity']);
                
                // Log stock movement
                $product->logStockMovement(
                    $item['product_id'], 
                    'IN', 
                    'PURCHASE', 
                    $purchaseId, 
                    $item['quantity'],
                    'Purchase from supplier'
                );
            }
            
            $this->db->commit();
            
            return [
                'success' => true, 
                'purchase_id' => $purchaseId
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Purchase creation error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get purchase by ID with items
    public function getPurchaseById($id) {
        $sql = "SELECT p.*, s.name as supplier_name, u.name as created_by_name 
                FROM purchases p 
                LEFT JOIN suppliers s ON p.supplier_id = s.id
                LEFT JOIN users u ON p.created_by = u.id
                WHERE p.id = ?";
        
        $purchase = $this->db->fetchOne($sql, [$id]);
        
        if (!$purchase) {
            return null;
        }
        
        // Get purchase items
        $sql = "SELECT pi.*, pr.name as product_name, pr.code as product_code 
                FROM purchase_items pi 
                LEFT JOIN products pr ON pi.product_id = pr.id 
                WHERE pi.purchase_id = ?";
        
        $purchase['items'] = $this->db->fetchAll($sql, [$id]);
        
        return $purchase;
    }

    // Get all purchases
    public function getAllPurchases($limit = 100) {
        $sql = "SELECT p.*, s.name as supplier_name, u.name as created_by_name 
                FROM purchases p 
                LEFT JOIN suppliers s ON p.supplier_id = s.id
                LEFT JOIN users u ON p.created_by = u.id
                ORDER BY p.created_at DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }

    // Get unpaid purchases
    public function getUnpaidPurchases() {
        $sql = "SELECT p.*, s.name as supplier_name 
                FROM purchases p 
                LEFT JOIN suppliers s ON p.supplier_id = s.id
                WHERE p.payment_status < 2
                ORDER BY p.purchase_date ASC";
        
        return $this->db->fetchAll($sql);
    }

    // Make payment for purchase
    public function makePayment($purchaseId, $paymentAmount, $paymentMethod = 1) {
        $purchase = $this->getPurchaseById($purchaseId);
        if (!$purchase) {
            return ['success' => false, 'message' => 'Purchase not found'];
        }
        
        $newPaidAmount = $purchase['paid_amount'] + $paymentAmount;
        $remainingAmount = $purchase['total'] - $newPaidAmount;
        
        // Determine payment status
        if ($remainingAmount <= 0) {
            $paymentStatus = 2; // Fully paid
        } elseif ($newPaidAmount > 0) {
            $paymentStatus = 1; // Partially paid
        } else {
            $paymentStatus = 0; // Unpaid
        }
        
        $updateData = [
            'paid_amount' => $newPaidAmount,
            'payment_status' => $paymentStatus
        ];
        
        $updated = $this->db->update('purchases', $updateData, 'id = ?', [$purchaseId]);
        
        if ($updated) {
            // Log cash expense for POS shift tracking
            if ($paymentMethod == 1) { // Cash payment
                $this->logCashExpense($purchaseId, $paymentAmount);
            }
            
            return [
                'success' => true, 
                'new_paid_amount' => $newPaidAmount,
                'remaining_amount' => max(0, $remainingAmount)
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to record payment'];
    }

    // Log cash expense for shift tracking
    private function logCashExpense($purchaseId, $amount) {
        // This will be counted as H_cash in shift closing
        $expenseData = [
            'category_id' => 1, // Purchases category
            'description' => "Purchase payment - Invoice #" . $purchaseId,
            'amount' => $amount,
            'expense_date' => date('Y-m-d'),
            'payment_method' => 1, // Cash
            'notes' => "Cash payment for purchase invoice",
            'created_by' => $_SESSION['user_id']
        ];
        
        $this->db->insert('expenses', $expenseData);
    }

    // Get purchase statistics
    public function getPurchaseStats($startDate = null, $endDate = null) {
        $startDate = $startDate ?? date('Y-m-01'); // First day of current month
        $endDate = $endDate ?? date('Y-m-d');
        
        $sql = "SELECT 
                    COUNT(*) as total_purchases,
                    SUM(total) as total_amount,
                    SUM(paid_amount) as paid_amount,
                    SUM(total - paid_amount) as outstanding_amount,
                    COUNT(CASE WHEN payment_status = 0 THEN 1 END) as unpaid_count,
                    COUNT(CASE WHEN payment_status = 1 THEN 1 END) as partial_count,
                    COUNT(CASE WHEN payment_status = 2 THEN 1 END) as paid_count
                FROM purchases 
                WHERE purchase_date BETWEEN ? AND ?";
        
        return $this->db->fetchOne($sql, [$startDate, $endDate]);
    }

    // Search purchases
    public function searchPurchases($search, $limit = 50) {
        $sql = "SELECT p.*, s.name as supplier_name 
                FROM purchases p
                LEFT JOIN suppliers s ON p.supplier_id = s.id
                WHERE (p.invoice_number LIKE ? OR s.name LIKE ? OR p.notes LIKE ?)
                ORDER BY p.created_at DESC
                LIMIT ?";
        
        $searchTerm = "%{$search}%";
        return $this->db->fetchAll($sql, [$searchTerm, $searchTerm, $searchTerm, $limit]);
    }
}
?>