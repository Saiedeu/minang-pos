<?php
/**
 * Products API Handler
 * Handle AJAX requests for product operations
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

$product = new Product();
$response = ['success' => false, 'message' => 'Invalid action'];

switch ($action) {
    case 'search':
        $searchTerm = $input['search'] ?? $_GET['search'] ?? '';
        $posOnly = $input['pos_only'] ?? $_GET['pos_only'] ?? false;
        
        if ($searchTerm) {
            $products = $product->searchProducts($searchTerm, $posOnly);
            $response = ['success' => true, 'data' => $products];
        } else {
            $response = ['success' => false, 'message' => 'Search term required'];
        }
        break;
        
    case 'get_by_category':
        $categoryId = $input['category_id'] ?? $_GET['category_id'] ?? 0;
        $posOnly = $input['pos_only'] ?? $_GET['pos_only'] ?? false;
        
        if ($categoryId) {
            $products = $product->getProductsByCategory($categoryId, $posOnly);
            $response = ['success' => true, 'data' => $products];
        } else {
            $products = $product->getAllProducts($posOnly);
            $response = ['success' => true, 'data' => $products];
        }
        break;
        
    case 'get_by_code':
        $code = $input['code'] ?? $_GET['code'] ?? '';
        
        if ($code) {
            $productData = $product->getProductByCode($code);
            if ($productData) {
                $response = ['success' => true, 'data' => $productData];
            } else {
                $response = ['success' => false, 'message' => 'Product not found'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Product code required'];
        }
        break;
        
    case 'update_stock':
        if (User::hasPermission('inventory_manage')) {
            $productId = $input['product_id'] ?? 0;
            $newQuantity = floatval($input['quantity'] ?? 0);
            $reason = $input['reason'] ?? 'Stock adjustment';
            
            $result = $product->updateStock($productId, $newQuantity, $reason);
            $response = $result;
        } else {
            $response = ['success' => false, 'message' => 'No permission'];
        }
        break;
        
    case 'get_low_stock':
        $lowStockProducts = $product->getLowStockProducts();
        $response = ['success' => true, 'data' => $lowStockProducts];
        break;
        
    case 'toggle_pos_listing':
        if (User::hasPermission('inventory_manage')) {
            $productId = $input['product_id'] ?? 0;
            $listInPos = $input['list_in_pos'] ?? 0;
            
            $updated = Database::getInstance()->update('products', 
                ['list_in_pos' => $listInPos], 
                'id = ?', 
                [$productId]
            );
            
            $response = $updated ? ['success' => true] : ['success' => false, 'message' => 'Update failed'];
        } else {
            $response = ['success' => false, 'message' => 'No permission'];
        }
        break;
        
    case 'get_stock_movements':
        $productId = $input['product_id'] ?? $_GET['product_id'] ?? null;
        $limit = $input['limit'] ?? $_GET['limit'] ?? 50;
        
        $movements = $product->getStockMovements($productId, $limit);
        $response = ['success' => true, 'data' => $movements];
        break;
        
    case 'generate_code':
        $categoryId = $input['category_id'] ?? 0;
        $code = $product->generateProductCode($categoryId);
        $response = ['success' => true, 'code' => $code];
        break;
        
    case 'get_stats':
        $stats = $product->getProductStats();
        $response = ['success' => true, 'data' => $stats];
        break;
}

echo json_encode($response);
?>