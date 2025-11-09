<?php
/**
 * Customer API Handler
 * Handle AJAX requests for customer operations
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

$customer = new Customer();
$response = ['success' => false, 'message' => 'Invalid action'];

switch ($action) {
    case 'create_customer':
        $customerData = $input['customer_data'] ?? [];
        $response = $customer->createCustomer($customerData);
        break;
        
    case 'update_customer':
        $customerId = $input['customer_id'] ?? 0;
        $customerData = $input['customer_data'] ?? [];
        
        if ($customerId) {
            $response = $customer->updateCustomer($customerId, $customerData);
        } else {
            $response = ['success' => false, 'message' => 'Customer ID required'];
        }
        break;
        
    case 'delete_customer':
        $customerId = $input['customer_id'] ?? $_GET['customer_id'] ?? 0;
        
        if ($customerId) {
            $response = $customer->deleteCustomer($customerId);
        } else {
            $response = ['success' => false, 'message' => 'Customer ID required'];
        }
        break;
        
    case 'search_customers':
        $searchTerm = $input['search'] ?? $_GET['search'] ?? '';
        
        if ($searchTerm) {
            $customers = $customer->searchCustomers($searchTerm);
            $response = ['success' => true, 'customers' => $customers];
        } else {
            $customers = $customer->getAllCustomers();
            $response = ['success' => true, 'customers' => $customers];
        }
        break;
        
    case 'get_customer_details':
        $customerId = $input['customer_id'] ?? $_GET['customer_id'] ?? 0;
        
        if ($customerId) {
            $customerDetails = $customer->getCustomerById($customerId);
            if ($customerDetails) {
                $salesHistory = $customer->getCustomerSalesHistory($customerId, 5);
                $response = [
                    'success' => true, 
                    'customer' => $customerDetails,
                    'sales_history' => $salesHistory
                ];
            } else {
                $response = ['success' => false, 'message' => 'Customer not found'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Customer ID required'];
        }
        break;
        
    case 'get_customer_stats':
        $stats = $customer->getCustomerStats();
        $response = ['success' => true, 'stats' => $stats];
        break;
        
    case 'get_top_customers':
        $limit = $input['limit'] ?? $_GET['limit'] ?? 10;
        $topCustomers = $customer->getTopCustomers($limit);
        $response = ['success' => true, 'customers' => $topCustomers];
        break;
}

echo json_encode($response);
?>