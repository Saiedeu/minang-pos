<?php
/**
 * Dashboard API
 * Provide real-time dashboard data for both POS and ERP
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

$action = $_GET['action'] ?? '';
$db = Database::getInstance();
$response = ['success' => false, 'message' => 'Invalid action'];

switch ($action) {
    case 'pos_stats':
        $response = getPOSStats();
        break;
        
    case 'erp_stats':
        $response = getERPStats();
        break;
        
    case 'notifications':
        $response = getNotifications();
        break;
        
    case 'active_shifts':
        $response = getActiveShifts();
        break;
        
    case 'recent_sales':
        $limit = $_GET['limit'] ?? 10;
        $response = getRecentSales($limit);
        break;
        
    case 'low_stock_alert':
        $response = getLowStockAlert();
        break;
        
    case 'sales_chart':
        $period = $_GET['period'] ?? '7days';
        $response = getSalesChart($period);
        break;
}

echo json_encode($response);

function getPOSStats() {
    global $db;
    
    $today = date('Y-m-d');
    $currentShift = $db->fetchOne("
        SELECT * FROM shifts 
        WHERE user_id = ? AND is_closed = 0 
        ORDER BY start_time DESC LIMIT 1
    ", [$_SESSION['user_id']]);
    
    $todaySales = $db->fetchOne("
        SELECT 
            COUNT(*) as count, 
            COALESCE(SUM(total), 0) as amount,
            COALESCE(SUM(CASE WHEN payment_method = 1 THEN total ELSE 0 END), 0) as cash_sales
        FROM sales 
        WHERE DATE(created_at) = ? 
        AND user_id = ?
    ", [$today, $_SESSION['user_id']]);
    
    return [
        'success' => true,
        'data' => [
            'active_shift' => $currentShift,
            'today_sales' => $todaySales,
            'shift_hours' => $currentShift ? 
                round((time() - strtotime($currentShift['start_time'])) / 3600, 1) : 0
        ]
    ];
}

function getERPStats() {
    global $db;
    
    $today = date('Y-m-d');
    $thisMonth = date('Y-m');
    
    $stats = [
        'today_sales' => $db->fetchOne("
            SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as amount 
            FROM sales WHERE DATE(created_at) = ?
        ", [$today]),
        
        'monthly_sales' => $db->fetchOne("
            SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as amount 
            FROM sales WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
        ", [$thisMonth]),
        
        'inventory_value' => $db->fetchOne("
            SELECT COALESCE(SUM(quantity * cost_price), 0) as value 
            FROM products WHERE is_active = 1
        ")['value'],
        
        'low_stock_count' => $db->count('products', 'quantity <= reorder_level AND is_active = 1'),
        
        'pending_purchases' => $db->fetchOne("
            SELECT COUNT(*) as count, COALESCE(SUM(total - paid_amount), 0) as amount 
            FROM purchases WHERE payment_status < 2
        "),
        
        'active_staff' => $db->count('users', 'is_active = 1 AND role IN (4,5,6,7)'),
        
        'today_attendance' => $db->count('attendance', 'attendance_date = ? AND sign_in_time IS NOT NULL', [$today])
    ];
    
    return ['success' => true, 'data' => $stats];
}

function getNotifications() {
    global $db;
    
    $notifications = [];
    
    // Low stock notifications
    $lowStock = $db->fetchAll("
        SELECT name, quantity, reorder_level 
        FROM products 
        WHERE quantity <= reorder_level AND is_active = 1 
        ORDER BY (quantity / NULLIF(reorder_level, 0)) ASC 
        LIMIT 5
    ");
    
    foreach ($lowStock as $product) {
        $notifications[] = [
            'type' => 'warning',
            'icon' => 'fas fa-exclamation-triangle',
            'title' => 'Low Stock Alert',
            'message' => "{$product['name']} is running low ({$product['quantity']} remaining)",
            'time' => 'now',
            'action_url' => '/erp/inventory/stock-control.php'
        ];
    }
    
    // Unpaid purchases
    $unpaidPurchases = $db->fetchAll("
        SELECT p.invoice_number, s.name as supplier_name, (p.total - p.paid_amount) as outstanding
        FROM purchases p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.payment_status < 2
        ORDER BY p.purchase_date ASC
        LIMIT 3
    ");
    
    foreach ($unpaidPurchases as $purchase) {
        $notifications[] = [
            'type' => 'danger',
            'icon' => 'fas fa-file-invoice-dollar',
            'title' => 'Unpaid Invoice',
            'message' => "Outstanding payment: {$purchase['invoice_number']} - " . formatCurrency($purchase['outstanding']),
            'time' => 'pending',
            'action_url' => '/pos/expenses.php'
        ];
    }
    
    // Missing attendance
    $absentToday = $db->count("
        SELECT COUNT(*) 
        FROM users u
        LEFT JOIN attendance a ON u.id = a.user_id AND a.attendance_date = CURDATE()
        WHERE u.is_active = 1 
        AND u.role IN (4,5,6,7)
        AND a.user_id IS NULL
    ");
    
    if ($absentToday > 0) {
        $notifications[] = [
            'type' => 'info',
            'icon' => 'fas fa-clock',
            'title' => 'Attendance Alert',
            'message' => "{$absentToday} staff members haven't checked in today",
            'time' => 'today',
            'action_url' => '/pos/attendance.php'
        ];
    }
    
    return ['success' => true, 'data' => $notifications];
}

function getActiveShifts() {
    global $db;
    
    $shifts = $db->fetchAll("
        SELECT s.*, u.name as user_name,
               TIMESTAMPDIFF(HOUR, s.start_time, NOW()) as hours_worked
        FROM shifts s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.is_closed = 0
        ORDER BY s.start_time DESC
    ");
    
    return ['success' => true, 'data' => $shifts];
}

function getRecentSales($limit) {
    global $db;
    
    $sales = $db->fetchAll("
        SELECT s.*, u.name as cashier_name
        FROM sales s
        LEFT JOIN users u ON s.user_id = u.id
        ORDER BY s.created_at DESC
        LIMIT ?
    ", [$limit]);
    
    // Add display names
    $orderTypes = [1 => 'Dine-In', 2 => 'Take Away', 3 => 'Delivery'];
    $paymentMethods = [1 => 'Cash', 2 => 'Card', 3 => 'Credit', 4 => 'FOC', 5 => 'COD'];
    
    foreach ($sales as &$sale) {
        $sale['order_type_name'] = $orderTypes[$sale['order_type']] ?? 'Unknown';
        $sale['payment_method_name'] = $paymentMethods[$sale['payment_method']] ?? 'Unknown';
    }
    
    return ['success' => true, 'data' => $sales];
}

function getLowStockAlert() {
    global $db;
    
    $products = $db->fetchAll("
        SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.quantity <= p.reorder_level AND p.is_active = 1
        ORDER BY (p.quantity / NULLIF(p.reorder_level, 0)) ASC
    ");
    
    return ['success' => true, 'data' => $products];
}

function getSalesChart($period) {
    global $db;
    
    $sql = "";
    $params = [];
    
    switch ($period) {
        case '7days':
            $sql = "
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as transactions,
                    SUM(total) as amount
                FROM sales 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ";
            break;
            
        case '30days':
            $sql = "
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as transactions,
                    SUM(total) as amount
                FROM sales 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ";
            break;
            
        case '12months':
            $sql = "
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as date,
                    COUNT(*) as transactions,
                    SUM(total) as amount
                FROM sales 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY date ASC
            ";
            break;
    }
    
    $data = $db->fetchAll($sql, $params);
    
    return [
        'success' => true,
        'data' => [
            'labels' => array_map(function($row) use ($period) {
                if ($period === '12months') {
                    return date('M Y', strtotime($row['date'] . '-01'));
                } else {
                    return date('M j', strtotime($row['date']));
                }
            }, $data),
            'sales' => array_map(function($row) {
                return floatval($row['amount']);
            }, $data),
            'transactions' => array_map(function($row) {
                return intval($row['transactions']);
            }, $data)
        ]
    ];
}
?>