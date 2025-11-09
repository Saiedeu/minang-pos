<?php
/**
 * POS System - Quick Reports
 * Quick access to essential reports for POS users
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';
require_once 'includes/auth-check.php';

$user = User::getCurrentUser();
$sale = new Sale();
$db = Database::getInstance();

// Get today's data
$today = date('Y-m-d');
$todaySales = $sale->getTodaySales($user['id']);
$todayStats = $sale->getSalesStats($today, $today, $user['id']);

// Get shift data
$currentShift = $db->fetchOne("
    SELECT * FROM shifts 
    WHERE user_id = ? AND is_closed = 0 
    ORDER BY start_time DESC LIMIT 1
", [$user['id']]);

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
                        primary: '<?php echo PRIMARY_COLOR; ?>',
                        success: '<?php echo SUCCESS_COLOR; ?>',
                        warning: '<?php echo WARNING_COLOR; ?>',
                        danger: '<?php echo DANGER_COLOR; ?>'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
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
                    <p class="font-semibold"><?php echo $user['name']; ?></p>
                    <p class="text-sm text-blue-100"><?php echo User::getRoleName($user['role']); ?></p>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Today's Performance -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-semibold text-gray-800">Today's Performance</h2>
                <div class="text-sm text-gray-500"><?php echo formatDate($today); ?></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <div class="text-3xl font-bold text-blue-600"><?php echo $todayStats['total_transactions'] ?? 0; ?></div>
                    <div class="text-sm text-blue-800">Transactions</div>
                </div>

                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <div class="text-3xl font-bold text-green-600"><?php echo formatCurrency($todayStats['total_amount'] ?? 0); ?></div>
                    <div class="text-sm text-green-800">Total Sales</div>
                </div>

                <div class="text-center p-4 bg-purple-50 rounded-lg">
                    <div class="text-3xl font-bold text-purple-600"><?php echo formatCurrency($todayStats['average_transaction'] ?? 0); ?></div>
                    <div class="text-sm text-purple-800">Average Sale</div>
                </div>

                <div class="text-center p-4 bg-orange-50 rounded-lg">
                    <div class="text-3xl font-bold text-orange-600"><?php echo formatCurrency($todayStats['cash_sales'] ?? 0); ?></div>
                    <div class="text-sm text-orange-800">Cash Sales</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Current Shift -->
            <?php if ($currentShift): ?>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-clock text-success mr-2"></i>
                    Current Shift
                </h2>

                <div class="space-y-4">
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600">Started:</span>
                        <span class="font-semibold"><?php echo formatDateTime($currentShift['start_time']); ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                        <span class="text-gray-600">Opening Balance:</span>
                        <span class="font-semibold text-blue-600"><?php echo formatCurrency($currentShift['opening_balance']); ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                        <span class="text-gray-600">Sales This Shift:</span>
                        <span class="font-semibold text-green-600"><?php echo formatCurrency($currentShift['total_sales'] ?? 0); ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center p-3 bg-purple-50 rounded-lg">
                        <span class="text-gray-600">Expected Cash:</span>
                        <span class="font-semibold text-purple-600"><?php echo formatCurrency($currentShift['expected_cash'] ?? $currentShift['opening_balance']); ?></span>
                    </div>
                </div>

                <div class="mt-4 text-center">
                    <a href="shift-close.php" class="bg-danger hover:bg-red-600 text-white font-semibold py-2 px-6 rounded-lg transition-colors">
                        <i class="fas fa-power-off mr-2"></i>Close Shift
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Sales -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Today's Sales</h2>
                    <span class="bg-primary text-white px-3 py-1 rounded-full text-sm font-semibold">
                        <?php echo count($todaySales); ?> orders
                    </span>
                </div>

                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php foreach ($todaySales as $sale): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div>
                            <div class="font-semibold text-gray-900"><?php echo $sale['receipt_number']; ?></div>
                            <div class="text-sm text-gray-600">
                                <?php echo date('H:i', strtotime($sale['created_at'])); ?>
                                <?php if ($sale['order_type'] == 1 && $sale['table_number']): ?>
                                - Table <?php echo $sale['table_number']; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-primary"><?php echo formatCurrency($sale['total']); ?></div>
                            <div class="text-xs text-gray-500">
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
                    <i class="fas fa-receipt text-3xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">No sales recorded today</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Shifts -->
        <?php if (!empty($recentShifts)): ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mt-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-6">Recent Shifts</h2>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase">
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
                        <tr class="border-t border-gray-200">
                            <td class="px-4 py-3 font-medium"><?php echo formatDate($shift['start_time']); ?></td>
                            <td class="px-4 py-3">
                                <?php 
                                    $duration = (strtotime($shift['end_time']) - strtotime($shift['start_time'])) / 3600;
                                    echo number_format($duration, 1) . 'h';
                                ?>
                            </td>
                            <td class="px-4 py-3"><?php echo formatCurrency($shift['opening_balance']); ?></td>
                            <td class="px-4 py-3 text-green-600 font-semibold"><?php echo formatCurrency($shift['total_sales']); ?></td>
                            <td class="px-4 py-3"><?php echo formatCurrency($shift['expected_cash']); ?></td>
                            <td class="px-4 py-3"><?php echo formatCurrency($shift['physical_cash']); ?></td>
                            <td class="px-4 py-3 <?php echo $shift['shortage_extra'] >= 0 ? 'text-green-600' : 'text-red-600'; ?> font-semibold">
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
        <div class="bg-white rounded-xl shadow-lg p-6 mt-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-6">Quick Actions</h2>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="../utilities/data-export.php?action=sales&start_date=<?php echo $today; ?>&end_date=<?php echo $today; ?>&user_id=<?php echo $user['id']; ?>" 
                   class="bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg p-4 text-center transition-colors">
                    <i class="fas fa-download text-2xl text-blue-600 mb-2"></i>
                    <div class="text-sm font-semibold text-blue-800">Export Today's Sales</div>
                </a>
                
                <button onclick="printShiftSummary()" 
                        class="bg-green-50 hover:bg-green-100 border border-green-200 rounded-lg p-4 text-center transition-colors">
                    <i class="fas fa-print text-2xl text-green-600 mb-2"></i>
                    <div class="text-sm font-semibold text-green-800">Print Shift Summary</div>
                </button>
                
                <a href="reprint-receipt.php" 
                   class="bg-yellow-50 hover:bg-yellow-100 border border-yellow-200 rounded-lg p-4 text-center transition-colors">
                    <i class="fas fa-receipt text-2xl text-yellow-600 mb-2"></i>
                    <div class="text-sm font-semibold text-yellow-800">Reprint Receipt</div>
                </a>
                
                <a href="../erp/reports/sales.php" 
                   class="bg-purple-50 hover:bg-purple-100 border border-purple-200 rounded-lg p-4 text-center transition-colors">
                    <i class="fas fa-chart-bar text-2xl text-purple-600 mb-2"></i>
                    <div class="text-sm font-semibold text-purple-800">Detailed Reports</div>
                </a>
            </div>
        </div>
    </div>

    <script>
        function printShiftSummary() {
            if (confirm('Print current shift summary?')) {
                window.open('print-shift-summary.php', '_blank');
            }
        }
    </script>
</body>
</html>