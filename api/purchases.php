<?php
/**
 * Purchase API Handler
 * Handle AJAX requests for purchase operations
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

$purchase = new Purchase();
$response = ['success' => false, 'message' => 'Invalid action'];

switch ($action) {
    case 'create_purchase_order':
        if (User::hasPermission('inventory_manage')) {
            $orderData = $input['order_data'] ?? [];
            $orderItems = $input['order_items'] ?? [];
            
            $response = $purchase->createPurchaseOrder($orderData, $orderItems);
        } else {
            $response = ['success' => false, 'message' => 'No permission'];
        }
        break;
        
    case 'get_supplier_products':
        $supplierId = $input['supplier_id'] ?? $_GET['supplier_id'] ?? 0;
        
        if ($supplierId) {
            $products = $purchase->getSupplierProducts($supplierId);
            $response = ['success' => true, 'products' => $products];
        } else {
            $response = ['success' => false, 'message' => 'Supplier ID required'];
        }
        break;
        
    case 'get_purchase_history':
        $supplierId = $input['supplier_id'] ?? $_GET['supplier_id'] ?? null;
        $limit = $input['limit'] ?? $_GET['limit'] ?? 10;
        
        $history = $purchase->getPurchaseHistory($supplierId, $limit);
        $response = ['success' => true, 'history' => $history];
        break;
        
    case 'make_bulk_payment':
        if (User::hasPermission('inventory_manage')) {
            $payments = $input['payments'] ?? [];
            $results = [];
            
            foreach ($payments as $payment) {
                $result = $purchase->makePayment(
                    $payment['purchase_id'], 
                    $payment['amount'], 
                    $payment['method'] ?? 1
                );
                $results[] = [
                    'purchase_id' => $payment['purchase_id'],
                    'success' => $result['success'],
                    'message' => $result['message'] ?? ''
                ];
            }
            
            $response = ['success' => true, 'results' => $results];
        } else {
            $response = ['success' => false, 'message' => 'No permission'];
        }
        break;
        
    case 'get_payment_schedule':
        $schedule = $purchase->getPaymentSchedule();
        $response = ['success' => true, 'schedule' => $schedule];
        break;
        
    case 'supplier_performance':
        $supplierId = $input['supplier_id'] ?? $_GET['supplier_id'] ?? null;
        $performance = $purchase->getSupplierPerformance($supplierId);
        $response = ['success' => true, 'performance' => $performance];
        break;
}

echo json_encode($response);
?>