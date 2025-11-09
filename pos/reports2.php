-- Additional tables for complete system functionality
-- Run this after the main database schema

USE minang_restaurant;

<?php
/**
 * POS System - Quick Reports
 * Quick access to essential reports for POS users
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';
require_once 'includes/auth-check.php';

$user = User::getCurrentUser();
$db = Database::getInstance();

// Get today's data
$today = date('Y-m-d');

// Get today's sales for this user
$todaySales = $db->fetchAll("
    SELECT * FROM sales 
    WHERE user_id = ? AND DATE(created_at) = ? 
    ORDER BY created_at DESC
", [$user['id'], $today]);

// Get today's statistics
$todayStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_transactions,
        COALESCE(SUM(total), 0) as total_amount,
        COALESCE(AVG(total), 0) as average_transaction,
        COALESCE(SUM(CASE WHEN payment_method = 1 THEN total ELSE 0 END), 0) as cash_sales,
        COALESCE(SUM(CASE WHEN payment_method = 2 THEN total ELSE 0 END), 0) as card_sales,
        COALESCE(SUM(CASE WHEN payment_method = 3 THEN total ELSE 0 END), 0) as credit_sales,
        COALESCE(SUM(CASE WHEN payment_method = 4 THEN total ELSE 0 END), 0) as foc_sales
    FROM sales 
    WHERE user_id = ? AND DATE(created_at) = ?
", [$user['id'], $today]);

// Set default values if no sales today
if (!$todayStats) {
    $todayStats = [
        'total_transactions' => 0,
        'total_amount' => 0,
        'average_transaction' => 0,
        'cash_sales' => 0,
        'card_sales' => 0,
        'credit_sales' => 0,
        'foc_sales' => 0
    ];
}

// Get shift data
$currentShift = $db->fetchOne("
    SELECT * FROM shifts 
    WHERE user_id = ? AND is_closed = 0 
    ORDER BY start_time DESC LIMIT 1
", [$user['id']]);

// Update current shift sales if exists
if ($currentShift) {
    $shiftSales = $db->fetchOne("
        SELECT 
            COALESCE(SUM(total), 0) as total_sales,
            COALESCE(SUM(CASE WHEN payment_method = 1 THEN total ELSE 0 END), 0) as cash_sales,
            COALESCE(SUM(CASE WHEN payment_method = 2 THEN total ELSE 0 END), 0) as card_sales,
            COALESCE(SUM(CASE WHEN payment_method = 3 THEN total ELSE 0 END), 0) as credit_sales,
            COALESCE(SUM(CASE WHEN payment_method = 4 THEN total ELSE 0 END), 0) as foc_sales
        FROM sales 
        WHERE shift_id = ?
    ", [$currentShift['id']]);
    
    if ($shiftSales) {
        $currentShift = array_merge($currentShift, $shiftSales);
        $currentShift['expected_cash'] = $currentShift['opening_balance'] + $shiftSales['cash_sales'];
    }
}

// Get recent shifts
$recentShifts = $db->fetchAll("
    SELECT * FROM shifts 
    WHERE user_id = ? AND is_closed = 1 
    ORDER BY end_time DESC LIMIT 5
", [$user['id']]);

$pageTitle = 'Quick Reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo BUSINESS_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#5D5CDE',
                        success: '#10B981',
                        warning: '#F59E0B',
                        danger: '#EF4444'
                    }
                }
            }
        }
        
        // Dark mode support
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        }
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', event => {
            if (event.matches) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        });
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <!-- Header -->
    <header class="bg-gradient-to-r from-primary to-blue-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-white hover:text-blue-200">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold text-white">Quick Reports</h1>
                        <p class="text-blue-100">Your personal sales summary</p>
                    </div>
                </div>
                <div class="text-white text-right">
                    <p class="font-semibold"><?php echo htmlspecialchars($user['name']); ?></p>
                    <p class="text-sm text-blue-100"><?php echo htmlspecialchars(User::getRoleName($user['role'])); ?></p>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Today's Performance -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">Today's Performance</h2>
                <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo formatDate($today); ?></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/50 rounded-lg">
                    <div class="text-3xl font-bold text-blue-600 dark:text-blue-400"><?php echo $todayStats['total_transactions']; ?></div>
                    <div class="text-sm text-blue-800 dark:text-blue-300">Transactions</div>
                </div>

                <div class="text-center p-4 bg-green-50 dark:bg-green-900/50 rounded-lg">
                    <div class="text-3xl font-bold text-green-600 dark:text-green-400"><?php echo formatCurrency($todayStats['total_amount']); ?></div>
                    <div class="text-sm text-green-800 dark:text-green-300">Total Sales</div>
                </div>

                <div class="text-center p-4 bg-purple-50 dark:bg-purple-900/50 rounded-lg">
                    <div class="text-3xl font-bold text-purple-600 dark:text-purple-400"><?php echo formatCurrency($todayStats['average_transaction']); ?></div>
                    <div class="text-sm text-purple-800 dark:text-purple-300">Average Sale</div>
                </div>

                <div class="text-center p-4 bg-orange-50 dark:bg-orange-900/50 rounded-lg">
                    <div class="text-3xl font-bold text-orange-600 dark:text-orange-400"><?php echo formatCurrency($todayStats['cash_sales']); ?></div>
                    <div class="text-sm text-orange-800 dark:text-orange-300">Cash Sales</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Current Shift -->
            <?php if ($currentShift): ?>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                    <i class="fas fa-clock text-success mr-2"></i>
                    Current Shift
                </h2>

                <div class="space-y-4">
                    <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <span class="text-gray-600 dark:text-gray-300">Started:</span>
                        <span class="font-semibold text-gray-800 dark:text-gray-200"><?php echo formatDateTime($currentShift['start_time']); ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center p-3 bg-blue-50 dark:bg-blue-900/50 rounded-lg">
                        <span class="text-gray-600 dark:text-gray-300">Opening Balance:</span>
                        <span class="font-semibold text-blue-600 dark:text-blue-400"><?php echo formatCurrency($currentShift['opening_balance']); ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center p-3 bg-green-50 dark:bg-green-900/50 rounded-lg">
                        <span class="text-gray-600 dark:text-gray-300">Sales This Shift:</span>
                        <span class="font-semibold text-green-600 dark:text-green-400"><?php echo formatCurrency($currentShift['total_sales'] ?? 0); ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center p-3 bg-purple-50 dark:bg-purple-900/50 rounded-lg">
                        <span class="text-gray-600 dark:text-gray-300">Expected Cash:</span>
                        <span class="font-semibold text-purple-600 dark:text-purple-400"><?php echo formatCurrency($currentShift['expected_cash'] ?? $currentShift['opening_balance']); ?></span>
                    </div>
                </div>

                <div class="mt-4 text-center">
                    <a href="shift-close.php" class="bg-danger hover:bg-red-600 text-white font-semibold py-2 px-6 rounded-lg transition-colors">
                        <i class="fas fa-power-off mr-2"></i>Close Shift
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                    <i class="fas fa-exclamation-triangle text-warning mr-2"></i>
                    No Active Shift
                </h2>
                <div class="text-center py-8">
                    <i class="fas fa-clock text-4xl text-gray-300 dark:text-gray-600 mb-4"></i>
                    <p class="text-gray-500 dark:text-gray-400 mb-4">You don't have an active shift.</p>
                    <a href="shift-open.php" class="bg-primary hover:bg-blue-600 text-white font-semibold py-2 px-6 rounded-lg transition-colors">
                        <i class="fas fa-play mr-2"></i>Start New Shift
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Sales -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Today's Sales</h2>
                    <span class="bg-primary text-white px-3 py-1 rounded-full text-sm font-semibold">
                        <?php echo count($todaySales); ?> orders
                    </span>
                </div>

                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php foreach ($todaySales as $sale): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-gray-100"><?php echo htmlspecialchars($sale['receipt_number']); ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                <?php echo date('H:i', strtotime($sale['created_at'])); ?>
                                <?php if ($sale['order_type'] == 1 && $sale['table_number']): ?>
                                - Table <?php echo htmlspecialchars($sale['table_number']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-primary"><?php echo formatCurrency($sale['total']); ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                <?php
                                    $paymentMethods = [1 => 'Cash', 2 => 'Card', 3 => 'Credit', 4 => 'FOC', 5 => 'COD'];
                                    echo $paymentMethods[$sale['payment_method']] ?? 'Unknown';
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($todaySales)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-receipt text-3xl text-gray-300 dark:text-gray-600 mb-4"></i>
                    <p class="text-gray-500 dark:text-gray-400">No sales recorded today</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Shifts -->
        <?php if (!empty($recentShifts)): ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mt-8">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-6">Recent Shifts</h2>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Duration</th>
                            <th class="px-4 py-3">Opening</th>
                            <th class="px-4 py-3">Sales</th>
                            <th class="px-4 py-3">Expected</th>
                            <th class="px-4 py-3">Physical</th>
                            <th class="px-4 py-3">Variance</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php foreach ($recentShifts as $shift): ?>
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100"><?php echo formatDate($shift['start_time']); ?></td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                <?php 
                                    $duration = (strtotime($shift['end_time']) - strtotime($shift['start_time'])) / 3600;
                                    echo number_format($duration, 1) . 'h';
                                ?>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300"><?php echo formatCurrency($shift['opening_balance']); ?></td>
                            <td class="px-4 py-3 text-green-600 dark:text-green-400 font-semibold"><?php echo formatCurrency($shift['total_sales']); ?></td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300"><?php echo formatCurrency($shift['expected_cash']); ?></td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300"><?php echo formatCurrency($shift['physical_cash']); ?></td>
                            <td class="px-4 py-3 <?php echo $shift['shortage_extra'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'; ?> font-semibold">
                                <?php echo ($shift['shortage_extra'] >= 0 ? '+' : '') . formatCurrency($shift['shortage_extra']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mt-8">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-6">Quick Actions</h2>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <button onclick="exportTodaySales()" 
                        class="bg-blue-50 dark:bg-blue-900/50 hover:bg-blue-100 dark:hover:bg-blue-900/70 border border-blue-200 dark:border-blue-700 rounded-lg p-4 text-center transition-colors">
                    <i class="fas fa-download text-2xl text-blue-600 dark:text-blue-400 mb-2"></i>
                    <div class="text-sm font-semibold text-blue-800 dark:text-blue-300">Export Today's Sales</div>
                </button>
                
                <button onclick="printShiftSummary()" 
                        class="bg-green-50 dark:bg-green-900/50 hover:bg-green-100 dark:hover:bg-green-900/70 border border-green-200 dark:border-green-700 rounded-lg p-4 text-center transition-colors">
                    <i class="fas fa-print text-2xl text-green-600 dark:text-green-400 mb-2"></i>
                    <div class="text-sm font-semibold text-green-800 dark:text-green-300">Print Shift Summary</div>
                </button>
                
                <a href="reprint-receipt.php" 
                   class="bg-yellow-50 dark:bg-yellow-900/50 hover:bg-yellow-100 dark:hover:bg-yellow-900/70 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4 text-center transition-colors">
                    <i class="fas fa-receipt text-2xl text-yellow-600 dark:text-yellow-400 mb-2"></i>
                    <div class="text-sm font-semibold text-yellow-800 dark:text-yellow-300">Reprint Receipt</div>
                </a>
                
                <a href="../erp/reports/sales.php" 
                   class="bg-purple-50 dark:bg-purple-900/50 hover:bg-purple-100 dark:hover:bg-purple-900/70 border border-purple-200 dark:border-purple-700 rounded-lg p-4 text-center transition-colors">
                    <i class="fas fa-chart-bar text-2xl text-purple-600 dark:text-purple-400 mb-2"></i>
                    <div class="text-sm font-semibold text-purple-800 dark:text-purple-300">Detailed Reports</div>
                </a>
            </div>
        </div>
    </div>

    <script>
        function printShiftSummary() {
            // Custom modal instead of confirm
            showConfirmDialog('Print current shift summary?', function() {
                window.open('print-shift-summary.php', '_blank');
            });
        }

        function exportTodaySales() {
            showConfirmDialog('Export today\'s sales data?', function() {
                const today = '<?php echo $today; ?>';
                const userId = <?php echo $user['id']; ?>;
                window.open(`../utilities/data-export.php?action=sales&start_date=${today}&end_date=${today}&user_id=${userId}`, '_blank');
            });
        }

        function showConfirmDialog(message, onConfirm) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg max-w-sm w-full mx-4">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-question-circle text-blue-500 text-xl mr-3"></i>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Confirm Action</h3>
                    </div>
                    <p class="text-gray-700 dark:text-gray-300 mb-4">${message}</p>
                    <div class="flex justify-end space-x-3">
                        <button class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded" onclick="this.closest('.fixed').remove()">Cancel</button>
                        <button class="px-4 py-2 bg-blue-500 text-white hover:bg-blue-600 rounded" onclick="this.closest('.fixed').remove(); onConfirm()">Confirm</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
    </script>
</body>
</html>