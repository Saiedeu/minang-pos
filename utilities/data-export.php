<?php
/**
 * Data Export Utility
 * Export various data formats (CSV, Excel, PDF)
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

// Check authentication
if (!User::isLoggedIn()) {
    http_response_code(401);
    exit('Unauthorized');
}

class DataExporter {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Export sales data to CSV
    public function exportSalesCSV($startDate, $endDate, $userId = null) {
        $where = "DATE(s.created_at) BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        
        if ($userId) {
            $where .= " AND s.user_id = ?";
            $params[] = $userId;
        }
        
        $sales = $this->db->fetchAll("
            SELECT 
                s.receipt_number,
                s.order_number,
                s.created_at,
                u.name as cashier_name,
                CASE s.order_type 
                    WHEN 1 THEN 'Dine-In'
                    WHEN 2 THEN 'Take Away' 
                    WHEN 3 THEN 'Delivery'
                END as order_type,
                s.table_number,
                s.customer_name,
                s.customer_phone,
                s.subtotal,
                s.discount,
                s.delivery_fee,
                s.total,
                CASE s.payment_method
                    WHEN 1 THEN 'Cash'
                    WHEN 2 THEN 'Card'
                    WHEN 3 THEN 'Credit'
                    WHEN 4 THEN 'FOC'
                    WHEN 5 THEN 'COD'
                END as payment_method,
                s.amount_received,
                s.change_amount
            FROM sales s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE {$where}
            ORDER BY s.created_at DESC
        ", $params);
        
        $filename = 'sales_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, [
            'Receipt Number', 'Order Number', 'Date & Time', 'Cashier', 'Order Type',
            'Table Number', 'Customer Name', 'Customer Phone', 'Subtotal', 'Discount',
            'Delivery Fee', 'Total', 'Payment Method', 'Amount Received', 'Change'
        ]);
        
        // Data rows
        foreach ($sales as $sale) {
            fputcsv($output, [
                $sale['receipt_number'],
                $sale['order_number'],
                $sale['created_at'],
                $sale['cashier_name'],
                $sale['order_type'],
                $sale['table_number'] ?? '',
                $sale['customer_name'] ?? '',
                $sale['customer_phone'] ?? '',
                $sale['subtotal'],
                $sale['discount'],
                $sale['delivery_fee'],
                $sale['total'],
                $sale['payment_method'],
                $sale['amount_received'],
                $sale['change_amount']
            ]);
        }
        
        fclose($output);
        exit();
    }
    
    // Export inventory data to CSV
    public function exportInventoryCSV() {
        $products = $this->db->fetchAll("
            SELECT 
                p.code,
                p.name,
                p.name_ar,
                c.name as category,
                p.cost_price,
                p.sell_price,
                p.quantity,
                p.unit,
                p.reorder_level,
                (p.quantity * p.cost_price) as stock_value,
                CASE 
                    WHEN p.quantity <= 0 THEN 'Out of Stock'
                    WHEN p.quantity <= p.reorder_level THEN 'Low Stock'
                    ELSE 'In Stock'
                END as status,
                p.created_at
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.is_active = 1
            ORDER BY p.name
        ");
        
        $filename = 'inventory_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, [
            'Product Code', 'Name (EN)', 'Name (AR)', 'Category', 'Cost Price', 'Sell Price',
            'Quantity', 'Unit', 'Reorder Level', 'Stock Value', 'Status', 'Created Date'
        ]);
        
        // Data rows
        foreach ($products as $product) {
            fputcsv($output, [
                $product['code'],
                $product['name'],
                $product['name_ar'],
                $product['category'],
                $product['cost_price'],
                $product['sell_price'],
                $product['quantity'],
                $product['unit'],
                $product['reorder_level'],
                $product['stock_value'],
                $product['status'],
                $product['created_at']
            ]);
        }
        
        fclose($output);
        exit();
    }
    
    // Export attendance data to CSV
    public function exportAttendanceCSV($startDate, $endDate, $userId = null) {
        $where = "a.attendance_date BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        
        if ($userId) {
            $where .= " AND a.user_id = ?";
            $params[] = $userId;
        }
        
        $attendance = $this->db->fetchAll("
            SELECT 
                a.attendance_date,
                u.name as staff_name,
                CASE u.role
                    WHEN 1 THEN 'Admin'
                    WHEN 2 THEN 'Manager'
                    WHEN 3 THEN 'Top Management'
                    WHEN 4 THEN 'Cashier'
                    WHEN 5 THEN 'Waiter'
                    WHEN 6 THEN 'Kitchen Staff'
                    WHEN 7 THEN 'Chef'
                END as role,
                TIME(a.sign_in_time) as sign_in,
                TIME(a.sign_out_time) as sign_out,
                a.total_hours,
                CASE 
                    WHEN a.sign_in_time IS NULL THEN 'Absent'
                    WHEN a.sign_out_time IS NULL THEN 'Present'
                    ELSE 'Completed'
                END as status,
                a.notes
            FROM attendance a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE {$where}
            ORDER BY a.attendance_date DESC, u.name
        ", $params);
        
        $filename = 'attendance_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, [
            'Date', 'Staff Name', 'Role', 'Sign In', 'Sign Out', 'Total Hours', 'Status', 'Notes'
        ]);
        
        // Data rows
        foreach ($attendance as $record) {
            fputcsv($output, [
                $record['attendance_date'],
                $record['staff_name'],
                $record['role'],
                $record['sign_in'] ?? 'N/A',
                $record['sign_out'] ?? 'N/A',
                $record['total_hours'] ?? '0',
                $record['status'],
                $record['notes'] ?? ''
            ]);
        }
        
        fclose($output);
        exit();
    }
}

// Handle export requests
$action = $_GET['action'] ?? '';
$exporter = new DataExporter();

switch ($action) {
    case 'sales':
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $userId = $_GET['user_id'] ?? null;
        $exporter->exportSalesCSV($startDate, $endDate, $userId);
        break;
        
    case 'inventory':
        $exporter->exportInventoryCSV();
        break;
        
    case 'attendance':
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $userId = $_GET['user_id'] ?? null;
        $exporter->exportAttendanceCSV($startDate, $endDate, $userId);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid export action']);
}
?>