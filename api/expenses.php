<?php
/**
 * Expenses API Handler
 * Handle AJAX requests for expense operations
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
    case 'create_expense':
        $expenseData = [
            'category_id' => intval($input['category_id'] ?? 0),
            'description' => sanitize($input['description'] ?? ''),
            'amount' => floatval($input['amount'] ?? 0),
            'expense_date' => $input['expense_date'] ?? date('Y-m-d'),
            'payment_method' => intval($input['payment_method'] ?? 1),
            'notes' => sanitize($input['notes'] ?? ''),
            'created_by' => $_SESSION['user_id'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        if (empty($expenseData['description']) || $expenseData['amount'] <= 0) {
            $response = ['success' => false, 'message' => 'Description and amount are required'];
            break;
        }
        
        $expenseId = $db->insert('expenses', $expenseData);
        
        if ($expenseId) {
            $response = ['success' => true, 'expense_id' => $expenseId, 'message' => 'Expense recorded successfully'];
        } else {
            $response = ['success' => false, 'message' => 'Failed to record expense'];
        }
        break;
        
    case 'get_expense_categories':
        $categories = $db->fetchAll("SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY name");
        $response = ['success' => true, 'categories' => $categories];
        break;
        
    case 'get_expenses_by_date':
        $date = $input['date'] ?? $_GET['date'] ?? date('Y-m-d');
        
        $expenses = $db->fetchAll("
            SELECT e.*, ec.name as category_name, u.name as created_by_name
            FROM expenses e
            LEFT JOIN expense_categories ec ON e.category_id = ec.id
            LEFT JOIN users u ON e.created_by = u.id
            WHERE DATE(e.expense_date) = ?
            ORDER BY e.created_at DESC
        ", [$date]);
        
        $response = ['success' => true, 'expenses' => $expenses];
        break;
        
    case 'get_expense_summary':
        $startDate = $input['start_date'] ?? $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $input['end_date'] ?? $_GET['end_date'] ?? date('Y-m-d');
        
        $summary = $db->fetchAll("
            SELECT 
                ec.name as category_name,
                COUNT(e.id) as expense_count,
                SUM(e.amount) as total_amount,
                AVG(e.amount) as avg_amount
            FROM expenses e
            LEFT JOIN expense_categories ec ON e.category_id = ec.id
            WHERE DATE(e.expense_date) BETWEEN ? AND ?
            GROUP BY e.category_id, ec.name
            ORDER BY total_amount DESC
        ", [$startDate, $endDate]);
        
        $totalExpenses = $db->fetchOne("
            SELECT 
                COUNT(*) as total_count,
                SUM(amount) as total_amount,
                SUM(CASE WHEN payment_method = 1 THEN amount ELSE 0 END) as cash_expenses
            FROM expenses
            WHERE DATE(expense_date) BETWEEN ? AND ?
        ", [$startDate, $endDate]);
        
        $response = [
            'success' => true,
            'summary_by_category' => $summary,
            'totals' => $totalExpenses
        ];
        break;
        
    case 'delete_expense':
        if (User::hasPermission('inventory_manage')) {
            $expenseId = $input['expense_id'] ?? $_GET['expense_id'] ?? 0;
            
            if ($expenseId) {
                $deleted = $db->delete('expenses', 'id = ? AND created_by = ?', [$expenseId, $_SESSION['user_id']]);
                $response = $deleted ? 
                    ['success' => true, 'message' => 'Expense deleted successfully'] : 
                    ['success' => false, 'message' => 'Failed to delete expense'];
            } else {
                $response = ['success' => false, 'message' => 'Expense ID required'];
            }
        } else {
            $response = ['success' => false, 'message' => 'No permission'];
        }
        break;
        
    case 'expense_analytics':
        $startDate = $input['start_date'] ?? date('Y-m-01');
        $endDate = $input['end_date'] ?? date('Y-m-d');
        
        // Daily expenses trend
        $dailyTrend = $db->fetchAll("
            SELECT 
                DATE(expense_date) as expense_date,
                COUNT(*) as count,
                SUM(amount) as total
            FROM expenses
            WHERE DATE(expense_date) BETWEEN ? AND ?
            GROUP BY DATE(expense_date)
            ORDER BY expense_date
        ", [$startDate, $endDate]);
        
        // Top expense categories
        $topCategories = $db->fetchAll("
            SELECT 
                ec.name,
                SUM(e.amount) as total_amount,
                COUNT(e.id) as expense_count
            FROM expenses e
            LEFT JOIN expense_categories ec ON e.category_id = ec.id
            WHERE DATE(e.expense_date) BETWEEN ? AND ?
            GROUP BY e.category_id
            ORDER BY total_amount DESC
            LIMIT 5
        ", [$startDate, $endDate]);
        
        $response = [
            'success' => true,
            'daily_trend' => $dailyTrend,
            'top_categories' => $topCategories
        ];
        break;
}

echo json_encode($response);
?>