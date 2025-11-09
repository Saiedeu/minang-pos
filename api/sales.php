<?php
/**
 * Sales API Handler
 * Handles AJAX requests for sales operations
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

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

$sale = new Sale();
$response = ['success' => false, 'message' => 'Invalid action'];

switch ($action) {
    case 'create_sale':
        if (User::hasPermission('pos_sales')) {
            $saleData = $input['sale_data'] ?? [];
            $saleItems = $input['sale_items'] ?? [];
            
            if (empty($saleItems)) {
                $response = ['success' => false, 'message' => 'No items in sale'];
                break;
            }
            
            // Ensure required fields are set
            $saleData['user_id'] = $_SESSION['user_id'];
            $saleData['created_at'] = date('Y-m-d H:i:s');
            
            // Generate receipt number if not provided
            if (empty($saleData['receipt_number'])) {
                $saleData['receipt_number'] = $sale->generateReceiptNumber();
            }
            
            $response = $sale->createSale($saleData, $saleItems);
        } else {
            $response = ['success' => false, 'message' => 'No permission'];
        }
        break;
        
    case 'hold_order':
        $orderData = $input['order_data'] ?? [];
        $response = $sale->holdOrder($orderData);
        break;
        
    case 'get_held_orders':
        $heldOrders = $sale->getHeldOrders($_SESSION['user_id']);
        $response = ['success' => true, 'data' => $heldOrders];
        break;
        
    case 'resume_held_order':
        $heldId = $input['held_id'] ?? 0;
        $response = $sale->resumeHeldOrder($heldId);
        break;
        
    case 'delete_held_order':
        $heldId = $input['held_id'] ?? 0;
        $deleted = $sale->deleteHeldOrder($heldId);
        $response = $deleted ? ['success' => true] : ['success' => false, 'message' => 'Failed to delete'];
        break;
        
    case 'get_products':
        $product = new Product();
        $products = $product->getProductsForPOS();
        $response = ['success' => true, 'data' => $products];
        break;
        
    case 'search_products':
        $searchTerm = $input['search'] ?? '';
        $product = new Product();
        $products = $product->searchProducts($searchTerm, true);
        $response = ['success' => true, 'data' => $products];
        break;
}

echo json_encode($response);
?>