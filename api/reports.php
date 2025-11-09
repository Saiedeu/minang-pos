<?php
/**
 * Reports API Handler
 * Generate and export various system reports
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

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = Database::getInstance();

switch ($action) {
    case 'export_stock':
        exportStockReport();
        break;
        
    case 'export_sales':
        exportSalesReport();
        break;
        
    case 'export_attendance':
        exportAttendanceReport();
        break;
        
    case 'dashboard_stats':
        echo json_encode(getDashboardStats());
        break;
        
    case 'sales_chart_data':
        echo json_encode(getSalesChartData());
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function exportStockReport() {
    global $db;
    
    $products = $db->fetchAll("
        SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1
        ORDER BY c.name, p.name
    ");
    
    // Generate CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="stock_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    fputcsv($output, [
        'Product Code',
        'Product Name',
        'Category',
        'Current Stock',
        'Unit',
        'Reorder Level',
        'Cost Price',
        'Sell Price',
        'Stock Value',
        'Status'
    ]);
    
    // Data rows
    foreach ($products as $product) {
        $stockValue = $product['quantity'] * $product['cost_price'];
        $status = $product['quantity'] <= $product['reorder_level'] ? 
                 ($product['quantity'] == 0 ? 'Out of Stock' : 'Low Stock') : 'In Stock';
        
        fputcsv($output, [
            $product['code'],
            $product['name'],
            $product['category_name'],
            $product['quantity'],
            $product['unit'],
            $product['reorder_level'],
            $product['cost_price'],
            $product['sell_price'],
            $stockValue,
            $status
        ]);
    }
    
    fclose($output);
    exit();
}

function exportSalesReport() {
    global $db;
    
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    $sales = $db->fetchAll("
        SELECT s.*, u.name as cashier_name
        FROM sales s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
        ORDER BY s.created_at DESC
    ", [$startDate, $endDate]);
    
    // Generate CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    fputcsv($output, [
        'Receipt Number',
        'Order Number',
        'Date',
        'Time',
        'Order Type',
        'Customer Name',
        'Table Number',
        'Subtotal',
        'Discount',
        'Delivery Fee',
        'Total',
        'Payment Method',
        'Cashier'
    ]);
    
    // Data rows
    $orderTypes = [1 => 'Dine-In', 2 => 'Take Away', 3 => 'Delivery'];
    $paymentMethods = [1 => 'Cash', 2 => 'Card', 3 => 'Credit', 4 => 'FOC', 5 => 'COD'];
    
    foreach ($sales as $sale) {
        fputcsv($output, [
            $sale['receipt_number'],
            $sale['order_number'],
            date('Y-m-d', strtotime($sale['created_at'])),
            date('H:i:s', strtotime($sale['created_at'])),
            $orderTypes[$sale['order_type']] ?? 'Unknown',
            $sale['customer_name'] ?? '',
            $sale['table_number'] ?? '',
            $sale['subtotal'],
            $sale['discount'],
            $sale['delivery_fee'],
            $sale['total'],
            $paymentMethods[$sale['payment_method']] ?? 'Unknown',
            $sale['cashier_name']
        ]);
    }
    
    fclose($output);
    exit();
}

function exportAttendanceReport() {
    global $db;
    
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    $attendance = $db->fetchAll("
        SELECT a.*, u.name, u.role
        FROM attendance a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.attendance_date BETWEEN ? AND ?
        ORDER BY a.attendance_date DESC, a.sign_in_time ASC
    ", [$startDate, $endDate]);
    
    // Generate CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    fputcsv($output, [
        'Date',
        'Staff Name',
        'Role',
        'Sign In Time',
        'Sign Out Time',
        'Total Hours',
        'Status',
        'Notes'
    ]);
    
    // Data rows
    foreach ($attendance as $record) {
        $status = !$record['sign_in_time'] ? 'Absent' : 
                 (!$record['sign_out_time'] ? 'Present' : 'Completed');
        
        fputcsv($output, [
            $record['attendance_date'],
            $record['name'],
            User::getRoleName($record['role']),
            $record['sign_in_time'] ? date('H:i:s', strtotime($record['sign_in_time'])) : '',
            $record['sign_out_time'] ? date('H:i:s', strtotime($record['sign_out_time'])) : '',
            $record['total_hours'] ?? '',
            $status,
            $record['notes'] ?? ''
        ]);
    }
    
    fclose($output);
    exit();
}

function getDashboardStats() {
    global $db;
    
    $today = date('Y-m-d');
    $thisMonth = date('Y-m');
    
    return [
        'today_sales' => $db->fetchOne("
            SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as amount 
            FROM sales WHERE DATE(created_at) = ?
        ", [$today]),
        
        'monthly_sales' => $db->fetchOne("
            SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as amount 
            FROM sales WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
        ", [$thisMonth]),
        
        'low_stock_count' => $db->fetchOne("
            SELECT COUNT(*) as count 
            FROM products WHERE quantity <= reorder_level AND is_active = 1
        ")['count'],
        
        'active_shifts' => $db->fetchOne("
            SELECT COUNT(*) as count 
            FROM shifts WHERE is_closed = 0
        ")['count'],
        
        'pending_purchases' => $db->fetchOne("
            SELECT COUNT(*) as count, COALESCE(SUM(total - paid_amount), 0) as amount 
            FROM purchases WHERE payment_status < 2
        ")
    ];
}

function getSalesChartData() {
    global $db;
    
    // Last 7 days sales data
    $chartData = $db->fetchAll("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as transactions,
            SUM(total) as amount
        FROM sales 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    
    return [
        'labels' => array_map(function($row) {
            return date('M j', strtotime($row['date']));
        }, $chartData),
        'sales' => array_map(function($row) {
            return floatval($row['amount']);
        }, $chartData),
        'transactions' => array_map(function($row) {
            return intval($row['transactions']);
        }, $chartData)
    ];
}
?>