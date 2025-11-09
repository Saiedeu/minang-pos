<?php
/**
 * Dashboard Data API
 * Provide real-time data for dashboard widgets
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
$user = User::getCurrentUser();
$db = Database::getInstance();

$response = ['success' => false, 'message' => 'Invalid action'];

switch ($action) {
    case 'get_today_stats':
        $today = date('Y-m-d');
        
        $stats = [
            'sales' => $db->fetchOne("
                SELECT 
                    COUNT(*) as transactions,
                    COALESCE(SUM(total), 0) as revenue,
                    COALESCE(SUM(CASE WHEN payment_method = 1 THEN total ELSE 0 END), 0) as cash_sales,
                    COALESCE(AVG(total), 0) as avg_transaction
                FROM sales 
                WHERE DATE(created_at) = ?
            ", [$today]),
            
            'attendance' => $db->fetchOne("
                SELECT 
                    COUNT(DISTINCT user_id) as present_staff,
                    COUNT(*) as total_records,
                    COUNT(CASE WHEN sign_out_time IS NULL THEN 1 END) as currently_working
                FROM attendance 
                WHERE attendance_date = ?
            ", [$today]),
            
            'inventory' => $db->fetchOne("
                SELECT 
                    COUNT(*) as total_products,
                    COUNT(CASE WHEN quantity <= reorder_level THEN 1 END) as low_stock_count,
                    COUNT(CASE WHEN quantity = 0 THEN 1 END) as out_of_stock_count
                FROM products 
                WHERE is_active = 1
            "),
            
            'kitchen' => $db->fetchOne("
                SELECT 
                    COUNT(CASE WHEN kitchen_status = 0 THEN 1 END) as pending_orders,
                    COUNT(CASE WHEN kitchen_status = 1 THEN 1 END) as cooking_orders,
                    AVG(CASE WHEN kitchen_status = 2 THEN TIMESTAMPDIFF(MINUTE, created_at, kitchen_updated_at) END) as avg_prep_time
                FROM sales 
                WHERE DATE(created_at) = ?
            ", [$today])
        ];
        
        $response = ['success' => true, 'stats' => $stats];
        break;
        
    case 'get_recent_sales':
        $limit = $_GET['limit'] ?? 10;
        $userId = $_GET['user_only'] ? $user['id'] : null;
        
        $where = "1=1";
        $params = [];
        
        if ($userId) {
            $where .= " AND s.user_id = ?";
            $params[] = $userId;
        }
        
        $recentSales = $db->fetchAll("
            SELECT s.*, u.name as cashier_name
            FROM sales s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE {$where}
            ORDER BY s.created_at DESC
            LIMIT ?
        ", array_merge($params, [$limit]));
        
        $response = ['success' => true, 'sales' => $recentSales];
        break;
        
    case 'get_low_stock_products':
        $lowStockProducts = $db->fetchAll("
            SELECT p.*, c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.is_active = 1 
            AND p.quantity <= p.reorder_level
            ORDER BY 
                CASE WHEN p.quantity = 0 THEN 0 ELSE 1 END,
                p.quantity ASC
            LIMIT 10
        ");
        
        $response = ['success' => true, 'products' => $lowStockProducts];
        break;
        
    case 'get_pending_orders':
        $pendingOrders = $db->fetchAll("
            SELECT s.*,
                   CASE 
                       WHEN s.order_type = 1 THEN CONCAT('Table ', s.table_number)
                       WHEN s.order_type = 2 THEN 'Take Away'
                       WHEN s.order_type = 3 THEN CONCAT('Delivery - ', s.customer_name)
                   END as order_display,
                   TIMESTAMPDIFF(MINUTE, s.created_at, NOW()) as minutes_ago
            FROM sales s
            WHERE s.kitchen_status IN (0, 1)
            AND DATE(s.created_at) = CURDATE()
            ORDER BY s.created_at ASC
            LIMIT 20
        ");
        
        $response = ['success' => true, 'orders' => $pendingOrders];
        break;
        
    case 'get_shift_summary':
        if ($user['role'] <= ROLE_MANAGER) {
            $shiftSummary = $db->fetchAll("
                SELECT 
                    u.name as cashier_name,
                    s.start_time,
                    s.end_time,
                    s.opening_balance,
                    s.total_sales,
                    s.cash_sales,
                    s.expected_cash,
                    s.physical_cash,
                    s.shortage_extra,
                    s.is_closed
                FROM shifts s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE DATE(s.start_time) = CURDATE()
                ORDER BY s.start_time DESC
            ");
            
            $response = ['success' => true, 'shifts' => $shiftSummary];
        } else {
            // Regular users see only their own shift
            $userShift = $db->fetchOne("
                SELECT * FROM shifts 
                WHERE user_id = ? 
                AND (is_closed = 0 OR DATE(start_time) = CURDATE())
                ORDER BY start_time DESC
                LIMIT 1
            ", [$user['id']]);
            
            $response = ['success' => true, 'shift' => $userShift];
        }
        break;
        
    case 'get_sales_trend':
        $days = $_GET['days'] ?? 7;
        
        $salesTrend = $db->fetchAll("
            SELECT 
                DATE(created_at) as sale_date,
                COUNT(*) as transactions,
                SUM(total) as revenue,
                AVG(total) as avg_transaction
            FROM sales
            WHERE DATE(created_at) >= DATE(NOW() - INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY sale_date DESC
        ", [$days]);
        
        $response = ['success' => true, 'trend' => $salesTrend];
        break;
        
    case 'get_alerts':
        $alerts = [];
        
        // Low stock alerts
        $lowStockCount = $db->count('products', 'is_active = 1 AND quantity <= reorder_level');
        if ($lowStockCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'fas fa-exclamation-triangle',
                'title' => 'Low Stock Alert',
                'message' => "{$lowStockCount} products are running low",
                'action' => 'View Inventory',
                'url' => '../erp/inventory/stock-control.php'
            ];
        }
        
        // Unpaid purchases
        $unpaidPurchases = $db->count('purchases', 'payment_status < 2');
        if ($unpaidPurchases > 0) {
            $alerts[] = [
                'type' => 'info',
                'icon' => 'fas fa-file-invoice-dollar',
                'title' => 'Outstanding Payments',
                'message' => "{$unpaidPurchases} purchase invoices pending payment",
                'action' => 'View Purchases',
                'url' => '../erp/purchases/invoices.php'
            ];
        }
        
        // Active shifts without closure
        if ($user['role'] <= ROLE_MANAGER) {
            $openShifts = $db->count('shifts', 'is_closed = 0 AND DATE(start_time) < CURDATE()');
            if ($openShifts > 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'icon' => 'fas fa-clock',
                    'title' => 'Unclosed Shifts',
                    'message' => "{$openShifts} shifts from previous days are still open",
                    'action' => 'Review Shifts',
                    'url' => '../erp/reports/sales.php'
                ];
            }
        }
        
        $response = ['success' => true, 'alerts' => $alerts];
        break;
}

function getProductPerformanceReport($startDate, $endDate, $limit) {
    global $db;
    return $db->fetchAll("
        SELECT 
            p.name,
            p.code,
            SUM(si.quantity) as units_sold,
            SUM(si.total_price) as revenue,
            COUNT(DISTINCT s.id) as orders_count,
            (SUM(si.total_price) - (SUM(si.quantity) * p.cost_price)) as profit
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        JOIN products p ON si.product_id = p.id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
        GROUP BY si.product_id
        ORDER BY revenue DESC
        LIMIT ?
    ", [$startDate, $endDate, $limit]);
}

function getCustomerAnalytics($startDate, $endDate) {
    global $db;
    return [
        'new_customers' => $db->count('sales', 'customer_name IS NOT NULL AND DATE(created_at) BETWEEN ? AND ?', [$startDate, $endDate]),
        'average_order_value' => $db->fetchOne("
            SELECT AVG(total) as avg_value
            FROM sales 
            WHERE customer_name IS NOT NULL 
            AND DATE(created_at) BETWEEN ? AND ?
        ", [$startDate, $endDate])['avg_value'] ?? 0,
        'delivery_orders' => $db->count('sales', 'order_type = 3 AND DATE(created_at) BETWEEN ? AND ?', [$startDate, $endDate]),
        'customer_satisfaction' => 85.5 // This would come from customer feedback system
    ];
}
?>