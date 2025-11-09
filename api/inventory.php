<?php
/**
 * Inventory API Handler
 * Handle AJAX requests for inventory operations
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
    case 'bulk_update_stock':
        if (User::hasPermission('inventory_manage')) {
            $updates = $input['updates'] ?? [];
            $successCount = 0;
            $errors = [];
            
            foreach ($updates as $update) {
                $productId = $update['product_id'];
                $newQuantity = floatval($update['quantity']);
                $reason = $update['reason'] ?? 'Bulk stock update';
                
                $result = $product->updateStock($productId, $newQuantity, $reason);
                if ($result['success']) {
                    $successCount++;
                } else {
                    $errors[] = "Product ID {$productId}: " . $result['message'];
                }
            }
            
            $response = [
                'success' => true,
                'updated_count' => $successCount,
                'total_count' => count($updates),
                'errors' => $errors
            ];
        } else {
            $response = ['success' => false, 'message' => 'No permission'];
        }
        break;
        
    case 'get_low_stock_alert':
        $lowStockProducts = $product->getLowStockProducts();
        $outOfStockProducts = array_filter($lowStockProducts, fn($p) => $p['quantity'] == 0);
        
        $response = [
            'success' => true,
            'low_stock_count' => count($lowStockProducts),
            'out_of_stock_count' => count($outOfStockProducts),
            'low_stock_products' => array_slice($lowStockProducts, 0, 5),
            'total_value_at_risk' => array_sum(array_map(fn($p) => $p['quantity'] * $p['cost_price'], $lowStockProducts))
        ];
        break;
        
    case 'reorder_suggestions':
        $suggestions = $product->getReorderSuggestions();
        $response = ['success' => true, 'suggestions' => $suggestions];
        break;
        
    case 'inventory_valuation':
        $valuation = $product->getInventoryValuation();
        $response = ['success' => true, 'valuation' => $valuation];
        break;
        
    case 'abc_analysis':
        // ABC Analysis for inventory classification
        $abcAnalysis = $product->getABCAnalysis();
        $response = ['success' => true, 'analysis' => $abcAnalysis];
        break;
        
    case 'stock_aging_report':
        $agingReport = $product->getStockAgingReport();
        $response = ['success' => true, 'aging_data' => $agingReport];
        break;
        
    case 'export_inventory':
        $format = $input['format'] ?? 'csv';
        $includeValues = $input['include_values'] ?? true;
        
        $exportData = $product->exportInventoryData($format, $includeValues);
        $response = [
            'success' => true,
            'download_url' => $exportData['url'],
            'filename' => $exportData['filename']
        ];
        break;
}

echo json_encode($response);
?>