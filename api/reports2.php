<?php
/**
 * Reports API Handler
 * Generate and export various business reports
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

$db = Database::getInstance();
$response = ['success' => false, 'message' => 'Invalid action'];

switch ($action) {
    case 'generate_daily_report':
        $date = $input['date'] ?? $_GET['date'] ?? date('Y-m-d');
        
        $reportData = [
            'date' => $date,
            'sales_stats' => getSalesStats($date),
            'payment_breakdown' => getPaymentBreakdown($date),
            'order_type_breakdown' => getOrderTypeBreakdown($date),
            'hourly_trend' => getHourlySalesTrend($date),
            'top_products' => getTopSellingProducts($date),
            'staff_performance' => getStaffPerformance($date)
        ];
        
        $response = ['success' => true, 'report_data' => $reportData];
        break;
        
    case 'export_stock':
        // Export inventory report
        if (!User::hasPermission('reports_view')) {
            $response = ['success' => false, 'message' => 'No permission'];
            break;
        }
        
        $product = new Product();
        $inventoryData = $product->getAllProducts();
        
        require_once '../prints/report-templates/inventory-template.php';
        $reportHtml = generateInventoryReport($inventoryData);
        
        // Create temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'inventory_report_');
        file_put_contents($tempFile . '.html', $reportHtml);
        
        $response = [
            'success' => true,
            'download_url' => 'data:text/html;base64,' . base64_encode($reportHtml),
            'filename' => 'inventory_report_' . date('Y-m-d') . '.html'
        ];
        break;
        
    case 'get_financial_summary':
        $startDate = $input['start_date'] ?? $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $input['end_date'] ?? $_GET['end_date'] ?? date('Y-m-d');
        
        $summary = getFinancialSummary($startDate, $endDate);
        $response = ['success' => true, 'summary' => $summary];
        break;
        
    case 'get_product_performance':
        $startDate = $input['start_date'] ?? date('Y-m-01');
        $endDate = $input['end_date'] ?? date('Y-m-d');
        $limit = $input['limit'] ?? 10;
        
        $performance = getProductPerformanceReport($startDate, $endDate, $limit);
        $response = ['success' => true, 'performance' => $performance];
        break;
        
    case 'get_customer_analytics':
        $startDate = $input['start_date'] ?? date('Y-m-01');
        $endDate = $input['end_date'] ?? date('Y-m-d');
        
        $analytics = getCustomerAnalytics($startDate, $endDate);
        $response = ['success' => true, 'analytics' => $analytics];
        break;
}

echo json_encode($response);

// Helper functions
function getSalesStats($date) {
    global $db;
    return $db->fetchOne("
        SELECT 
            COUNT(*) as total_transactions,
            SUM(total) as gross_sales,
            SUM(discount) as total_discounts,
            SUM(total - discount) as net_sales,
            AVG(total) as average_transaction
        FROM sales 
        WHERE DATE(created_at) = ?
    ", [$date]);
}

function getPaymentBreakdown($date) {
    global $db;
    return $db->fetchAll("
        SELECT 
            payment_method,
            COUNT(*) as transaction_count,
            SUM(total) as total_amount
        FROM sales 
        WHERE DATE(created_at) = ?
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ", [$date]);
}

function getOrderTypeBreakdown($date) {
    global $db;
    return $db->fetchAll("
        SELECT 
            order_type,
            COUNT(*) as order_count,
            SUM(total) as total_amount
        FROM sales 
        WHERE DATE(created_at) = ?
        GROUP BY order_type
        ORDER BY total_amount DESC
    ", [$date]);
}

function getHourlySalesTrend($date) {
    global $db;
    return $db->fetchAll("
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as transaction_count,
            SUM(total) as total_amount
        FROM sales 
        WHERE DATE(created_at) = ?
        GROUP BY HOUR(created_at)
        ORDER BY hour
    ", [$date]);
}

function getTopSellingProducts($date, $limit = 10) {
    global $db;
    return $db->fetchAll("
        SELECT 
            si.product_name,
            SUM(si.quantity) as total_quantity,
            SUM(si.total_price) as total_revenue
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        WHERE DATE(s.created_at) = ?
        GROUP BY si.product_id, si.product_name
        ORDER BY total_quantity DESC
        LIMIT ?
    ", [$date, $limit]);
}

function getStaffPerformance($date) {
    global $db;
    return $db->fetchAll("
        SELECT 
            u.name as staff_name,
            COUNT(s.id) as transaction_count,
            SUM(s.total) as total_sales,
            AVG(s.total) as average_transaction
        FROM sales s
        JOIN users u ON s.user_id = u.id
        WHERE DATE(s.created_at) = ?
        GROUP BY s.user_id, u.name
        ORDER BY total_sales DESC
    ", [$date]);
}

function getFinancialSummary($startDate, $endDate) {
    global $db;
    
    $sales = $db->fetchOne("
        SELECT 
            COUNT(*) as total_transactions,
            SUM(total) as total_revenue,
            SUM(discount) as total_discounts,
            AVG(total) as avg_transaction
        FROM sales 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ", [$startDate, $endDate]);
    
    $purchases = $db->fetchOne("
        SELECT 
            COUNT(*) as total_purchases,
            SUM(total) as total_amount
        FROM purchases 
        WHERE DATE(purchase_date) BETWEEN ? AND ?
    ", [$startDate, $endDate]);
    
    $expenses = $db->fetchOne("
        SELECT 
            COUNT(*) as total_expenses,
            SUM(amount) as total_amount
        FROM expenses 
        WHERE DATE(expense_date) BETWEEN ? AND ?
    ", [$startDate, $endDate]);
    
    return [
        'sales' => $sales,
        'purchases' => $purchases,
        'expenses' => $expenses,
        'gross_profit' => ($sales['total_revenue'] ?? 0) - ($purchases['total_amount'] ?? 0),
        'net_profit' => ($sales['total_revenue'] ?? 0) - ($purchases['total_amount'] ?? 0) - ($expenses['total_amount'] ?? 0)
    ];
}

function getProductPerformanceReport($startDate, $endDate, $limit) {
    global $db;
    return $db->fetchAll("
        SELECT 
            p.name as product_name,
            p.code as product_code,
            c.name as category_name,
            SUM(si.quantity) as total_sold,
            SUM(si.total_price) as total_revenue,
            AVG(si.unit_price) as avg_selling_price,
            p.cost_price,
            (AVG(si.unit_price) - p.cost_price) as profit_per_unit
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        JOIN products p ON si.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
        GROUP BY si.product_id
        ORDER BY total_revenue DESC
        LIMIT ?
    ", [$startDate, $endDate, $limit]);
}

function getCustomerAnalytics($startDate, $endDate) {
    global $db;
    return [
        'total_customers' => $db->count('sales', 'customer_name IS NOT NULL AND DATE(created_at) BETWEEN ? AND ?', [$startDate, $endDate]),
        'repeat_customers' => $db->fetchOne("
            SELECT COUNT(*) as count
            FROM (
                SELECT customer_phone
                FROM sales
                WHERE customer_phone IS NOT NULL 
                AND DATE(created_at) BETWEEN ? AND ?
                GROUP BY customer_phone
                HAVING COUNT(*) > 1
            ) repeat_customers
        ", [$startDate, $endDate])['count'],
        'top_customers' => $db->fetchAll("
            SELECT 
                customer_name,
                customer_phone,
                COUNT(*) as order_count,
                SUM(total) as total_spent,
                AVG(total) as avg_order_value
            FROM sales
            WHERE customer_name IS NOT NULL 
            AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY customer_phone
            ORDER BY total_spent DESC
            LIMIT 10
        ", [$startDate, $endDate])
    ];
}
?>