<?php
/**
 * Sales Management Class - ENHANCED VERSION
 * Handles all sales operations and transactions with improved error handling
 */

if (!defined('MINANG_SYSTEM')) {
    exit('Direct access not allowed');
}

class Sale {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Generate order number with LMR-MMDDSL format
    public function generateOrderNumber() {
        $date = date('md'); // MMDD format
        
        // Get today's order count
        $today = date('Y-m-d');
        $count = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM sales WHERE DATE(created_at) = ?", 
            [$today]
        );
        
        $orderSerial = sprintf('%02d', ($count['count'] ?? 0) + 1);
        return "LMR-{$date}{$orderSerial}";
    }

    // Generate receipt number
    public function generateReceiptNumber() {
        return 'R' . date('Ymd') . '-' . sprintf('%06d', mt_rand(100000, 999999));
    }

    // Create new sale - ENHANCED VERSION
    public function createSale($saleData, $saleItems) {
        try {
            $this->db->beginTransaction();
            
            // Validate required fields
            $requiredFields = ['order_number', 'order_type', 'subtotal', 'total', 'payment_method'];
            foreach ($requiredFields as $field) {
                if (!isset($saleData[$field])) {
                    throw new Exception("Missing required field: {$field}");
                }
            }
            
            // Ensure user_id is set
            if (!isset($saleData['user_id'])) {
                if (!isset($_SESSION['user_id'])) {
                    throw new Exception("User not authenticated");
                }
                $saleData['user_id'] = $_SESSION['user_id'];
            }
            
            // Validate numeric fields
            $numericFields = ['subtotal', 'total', 'discount', 'delivery_fee', 'amount_received', 'change_amount'];
            foreach ($numericFields as $field) {
                if (isset($saleData[$field])) {
                    $saleData[$field] = is_numeric($saleData[$field]) ? floatval($saleData[$field]) : 0;
                }
            }
            
            // Set defaults for optional fields
            $saleData['receipt_number'] = $saleData['receipt_number'] ?? $this->generateReceiptNumber();
            $saleData['customer_name'] = $saleData['customer_name'] ?? '';
            $saleData['customer_phone'] = $saleData['customer_phone'] ?? '';
            $saleData['customer_address'] = $saleData['customer_address'] ?? '';
            $saleData['table_number'] = $saleData['table_number'] ?? '';
            $saleData['discount'] = $saleData['discount'] ?? 0;
            $saleData['delivery_fee'] = $saleData['delivery_fee'] ?? 0;
            $saleData['amount_received'] = $saleData['amount_received'] ?? 0;
            $saleData['change_amount'] = $saleData['change_amount'] ?? 0;
            $saleData['notes'] = $saleData['notes'] ?? '';
            $saleData['is_printed'] = 0;
            $saleData['created_at'] = date('Y-m-d H:i:s');
            
            // Get active shift
            $activeShift = $this->db->fetchOne(
                "SELECT id FROM shifts WHERE user_id = ? AND is_closed = 0 ORDER BY start_time DESC LIMIT 1",
                [$saleData['user_id']]
            );
            
            if ($activeShift) {
                $saleData['shift_id'] = $activeShift['id'];
            }
            
            // Validate sale items
            if (empty($saleItems)) {
                throw new Exception('No sale items provided');
            }
            
            // Insert sale record
            $saleId = $this->db->insert('sales', $saleData);
            
            if (!$saleId) {
                throw new Exception('Failed to create sale record');
            }
            
            // Insert sale items and update inventory
            foreach ($saleItems as $item) {
                // Validate required item fields
                $requiredItemFields = ['product_id', 'quantity', 'unit_price'];
                foreach ($requiredItemFields as $field) {
                    if (!isset($item[$field])) {
                        throw new Exception("Missing required item field: {$field}");
                    }
                }
                
                // Validate numeric fields
                $item['quantity'] = is_numeric($item['quantity']) ? floatval($item['quantity']) : 0;
                $item['unit_price'] = is_numeric($item['unit_price']) ? floatval($item['unit_price']) : 0;
                $item['total_price'] = isset($item['total_price']) ? floatval($item['total_price']) : ($item['quantity'] * $item['unit_price']);
                
                $itemData = [
                    'sale_id' => $saleId,
                    'product_id' => intval($item['product_id']),
                    'product_name' => $item['product_name'] ?? '',
                    'product_name_ar' => $item['product_name_ar'] ?? '',
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'notes' => $item['notes'] ?? ''
                ];
                
                $itemId = $this->db->insert('sale_items', $itemData);
                
                if (!$itemId) {
                    throw new Exception('Failed to create sale item');
                }
                
                // Update product stock
                $updateResult = $this->db->query(
                    "UPDATE products SET quantity = quantity - ? WHERE id = ? AND quantity >= ?",
                    [$item['quantity'], $item['product_id'], $item['quantity']]
                );
                
                if (!$updateResult) {
                    throw new Exception('Failed to update product stock for product ID: ' . $item['product_id']);
                }
                
                // Log stock movement
                $this->db->insert('stock_movements', [
                    'product_id' => $item['product_id'],
                    'type' => 'OUT',
                    'reference_type' => 'SALE',
                    'reference_id' => $saleId,
                    'quantity' => $item['quantity'],
                    'reason' => 'Sale transaction',
                    'created_by' => $saleData['user_id'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // Update shift totals if shift is active
            if ($activeShift) {
                $this->updateShiftTotals($activeShift['id'], $saleData);
            }
            
            $this->db->commit();
            
            return [
                'success' => true, 
                'sale_id' => $saleId,
                'receipt_number' => $saleData['receipt_number'],
                'order_number' => $saleData['order_number']
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Sale creation error: " . $e->getMessage());
            error_log("Sale data: " . print_r($saleData, true));
            error_log("Sale items: " . print_r($saleItems, true));
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Update shift totals
    private function updateShiftTotals($shiftId, $saleData) {
        $paymentMethod = $saleData['payment_method'];
        $amount = $saleData['total'];
        $discount = $saleData['discount'] ?? 0;
        
        $updateFields = ['total_sales = total_sales + ?'];
        $params = [$amount];
        
        // Update payment method totals
        switch ($paymentMethod) {
            case 1: // PAYMENT_CASH
                $updateFields[] = 'cash_sales = cash_sales + ?';
                $params[] = $amount;
                break;
            case 2: // PAYMENT_CARD
                $updateFields[] = 'card_sales = card_sales + ?';
                $params[] = $amount;
                break;
            case 3: // PAYMENT_CREDIT
                $updateFields[] = 'credit_sales = credit_sales + ?';
                $params[] = $amount;
                break;
            case 4: // PAYMENT_FOC
                $updateFields[] = 'foc_sales = foc_sales + ?';
                $params[] = $amount;
                break;
            case 5: // PAYMENT_COD
                $updateFields[] = 'cash_sales = cash_sales + ?';
                $params[] = $amount;
                break;
        }
        
        if ($discount > 0) {
            $updateFields[] = 'discount_amount = discount_amount + ?';
            $params[] = $discount;
        }
        
        $params[] = $shiftId;
        
        $sql = "UPDATE shifts SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $this->db->query($sql, $params);
    }

    // Hold order - ENHANCED VERSION
    public function holdOrder($orderData) {
        try {
            // Ensure user_id is set
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                throw new Exception("User not authenticated");
            }
            
            $heldOrderData = [
                'order_number' => $orderData['order_number'],
                'user_id' => $userId,
                'order_type' => intval($orderData['order_type']),
                'table_number' => $orderData['table_number'] ?? null,
                'customer_name' => $orderData['customer_name'] ?? null,
                'customer_phone' => $orderData['customer_phone'] ?? null,
                'customer_address' => $orderData['customer_address'] ?? null,
                'subtotal' => floatval($orderData['subtotal'] ?? 0),
                'discount' => floatval($orderData['discount'] ?? 0),
                'total' => floatval($orderData['total'] ?? 0),
                'order_data' => json_encode($orderData['items'] ?? []),
                'held_at' => date('Y-m-d H:i:s')
            ];
            
            $heldId = $this->db->insert('held_orders', $heldOrderData);
            
            return $heldId ? ['success' => true, 'held_id' => $heldId] : ['success' => false, 'message' => 'Failed to hold order'];
            
        } catch (Exception $e) {
            error_log("Hold order error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to hold order: ' . $e->getMessage()];
        }
    }

    // Get held orders - ENHANCED VERSION
    public function getHeldOrders($userId = null) {
        try {
            $where = $userId ? 'WHERE ho.user_id = ?' : '';
            $params = $userId ? [$userId] : [];
            
            $sql = "SELECT ho.*, u.name as user_name 
                    FROM held_orders ho
                    LEFT JOIN users u ON ho.user_id = u.id
                    {$where}
                    ORDER BY ho.held_at DESC";
            
            $heldOrders = $this->db->fetchAll($sql, $params);
            
            // Decode order data
            foreach ($heldOrders as &$order) {
                $order['items'] = json_decode($order['order_data'], true) ?: [];
            }
            
            return $heldOrders;
            
        } catch (Exception $e) {
            error_log("Get held orders error: " . $e->getMessage());
            return [];
        }
    }

    // Resume held order - ENHANCED VERSION
    public function resumeHeldOrder($heldId) {
        try {
            $heldOrder = $this->db->fetchOne("SELECT * FROM held_orders WHERE id = ?", [$heldId]);
            
            if (!$heldOrder) {
                return ['success' => false, 'message' => 'Held order not found'];
            }
            
            // Delete held order
            $deleted = $this->db->delete('held_orders', 'id = ?', [$heldId]);
            
            if ($deleted) {
                $heldOrder['items'] = json_decode($heldOrder['order_data'], true) ?: [];
                return ['success' => true, 'order' => $heldOrder];
            }
            
            return ['success' => false, 'message' => 'Failed to resume order'];
            
        } catch (Exception $e) {
            error_log("Resume held order error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to resume order: ' . $e->getMessage()];
        }
    }

    // Delete held order - ENHANCED VERSION
    public function deleteHeldOrder($heldId) {
        try {
            $result = $this->db->delete('held_orders', 'id = ?', [$heldId]);
            return $result > 0;
        } catch (Exception $e) {
            error_log("Delete held order error: " . $e->getMessage());
            return false;
        }
    }

    // Get sale by ID with items
    public function getSaleById($id) {
        // Get sale data
        $sql = "SELECT s.*, u.name as cashier_name 
                FROM sales s 
                LEFT JOIN users u ON s.user_id = u.id 
                WHERE s.id = ?";
        
        $sale = $this->db->fetchOne($sql, [$id]);
        
        if (!$sale) {
            return null;
        }
        
        // Get sale items
        $sql = "SELECT si.*, p.code as product_code 
                FROM sale_items si 
                LEFT JOIN products p ON si.product_id = p.id 
                WHERE si.sale_id = ?";
        
        $sale['items'] = $this->db->fetchAll($sql, [$id]);
        
        return $sale;
    }

    // Reprint receipt by receipt number
    public function reprintReceipt($receiptNumber) {
        $sql = "SELECT s.*, u.name as cashier_name 
                FROM sales s 
                LEFT JOIN users u ON s.user_id = u.id 
                WHERE s.receipt_number = ?";
        
        $sale = $this->db->fetchOne($sql, [$receiptNumber]);
        
        if (!$sale) {
            return null;
        }
        
        // Get sale items
        $sql = "SELECT si.*, p.code as product_code 
                FROM sale_items si 
                LEFT JOIN products p ON si.product_id = p.id 
                WHERE si.sale_id = ?";
        
        $sale['items'] = $this->db->fetchAll($sql, [$sale['id']]);
        
        return $sale;
    }

    // Get sales statistics
    public function getSalesStats($startDate = null, $endDate = null, $userId = null) {
        $startDate = $startDate ?? date('Y-m-d');
        $endDate = $endDate ?? date('Y-m-d');
        
        $where = "DATE(created_at) BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        
        if ($userId) {
            $where .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $sql = "SELECT 
                    COUNT(*) as total_transactions,
                    SUM(total) as total_amount,
                    SUM(CASE WHEN payment_method = 1 THEN total ELSE 0 END) as cash_sales,
                    SUM(CASE WHEN payment_method = 2 THEN total ELSE 0 END) as card_sales,
                    SUM(CASE WHEN payment_method = 3 THEN total ELSE 0 END) as credit_sales,
                    SUM(CASE WHEN payment_method = 4 THEN total ELSE 0 END) as foc_sales,
                    SUM(CASE WHEN payment_method = 5 THEN total ELSE 0 END) as cod_sales,
                    SUM(discount) as total_discounts,
                    AVG(total) as average_transaction
                FROM sales 
                WHERE {$where}";
        
        return $this->db->fetchOne($sql, $params);
    }
}
?>