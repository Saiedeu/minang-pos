<?php
/**
 * ERP System - Financial Reports
 * Comprehensive financial analysis and reporting
 */

define('MINANG_SYSTEM', true);
require_once '../../config/config.php';
require_once '../includes/auth-check.php';

$db = Database::getInstance();
$sale = new Sale();
$purchase = new Purchase();

// Get filter parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$reportType = $_GET['report_type'] ?? 'profit_loss';

// Get financial data
$salesData = $sale->getSalesStats($startDate, $endDate);
$purchaseData = $purchase->getPurchaseStats($startDate, $endDate);

// Get expense data
$expenseData = $db->fetchOne("
    SELECT 
        COUNT(*) as total_expenses,
        COALESCE(SUM(amount), 0) as total_amount,
        COALESCE(SUM(CASE WHEN payment_method = 1 THEN amount ELSE 0 END), 0) as cash_expenses
    FROM expenses 
    WHERE DATE(expense_date) BETWEEN ? AND ?
", [$startDate, $endDate]);

// Calculate profit/loss
$totalRevenue = $salesData['total_amount'] ?? 0;
$totalCosts = ($purchaseData['total_amount'] ?? 0) + ($expenseData['total_amount'] ?? 0);
$grossProfit = $totalRevenue - $totalCosts;
$netProfit = $grossProfit; // Simplified - could include tax calculations

// Get category-wise sales
$categorySales = $db->fetchAll("
    SELECT 
        c.name as category_name,
        COUNT(si.id) as items_sold,
        SUM(si.quantity) as total_quantity,
        SUM(si.total_price) as total_sales
    FROM sale_items si
    INNER JOIN sales s ON si.sale_id = s.id
    INNER JOIN products p ON si.product_id = p.id
    INNER JOIN categories c ON p.category_id = c.id
    WHERE DATE(s.created_at) BETWEEN ? AND ?
    GROUP BY c.id, c.name
    ORDER BY total_sales DESC
", [$startDate, $endDate]);

// Get monthly trend
$monthlyTrend = $db->fetchAll("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as transactions,
        SUM(total) as sales_amount
    FROM sales 
    WHERE created_at >= DATE_SUB(?, INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
", [$endDate]);

$pageTitle = 'Financial Reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo BUSINESS_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="ml-64 min-h-screen">
        <?php include '../includes/header.php'; ?>
        
        <main class="p-8">
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Financial Reports</h1>
                    <p class="text-gray-600">Comprehensive financial analysis and insights</p>
                </div>
                <div class="flex space-x-3">
                    <button onclick="exportFinancialReport()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-download mr-2"></i>Export PDF
                    </button>
                    <button onclick="window.print()" class="bg-secondary hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-print mr-2"></i>Print Report
                    </button>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
                        <input type="date" name="start_date" value="<?php echo $startDate; ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
                        <input type="date" name="end_date" value="<?php echo $endDate; ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-primary hover:bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                            <i class="fas fa-chart-line mr-2"></i>Generate Report
                        </button>
                    </div>
                </form>
            </div>

            <!-- Financial Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm">Total Revenue</p>
                            <p class="text-3xl font-bold"><?php echo formatCurrency($totalRevenue); ?></p>
                            <p class="text-green-100 text-xs"><?php echo $salesData['total_transactions'] ?? 0; ?> transactions</p>
                        </div>
                        <i class="fas fa-arrow-trend-up text-4xl text-green-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-red-100 text-sm">Total Costs</p>
                            <p class="text-3xl font-bold"><?php echo formatCurrency($totalCosts); ?></p>
                            <p class="text-red-100 text-xs">Purchases + Expenses</p>
                        </div>
                        <i class="fas fa-arrow-trend-down text-4xl text-red-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-<?php echo $grossProfit >= 0 ? 'blue' : 'orange'; ?>-500 to-<?php echo $grossProfit >= 0 ? 'blue' : 'orange'; ?>-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-<?php echo $grossProfit >= 0 ? 'blue' : 'orange'; ?>-100 text-sm">Gross Profit</p>
                            <p class="text-3xl font-bold"><?php echo formatCurrency($grossProfit); ?></p>
                            <p class="text-<?php echo $grossProfit >= 0 ? 'blue' : 'orange'; ?>-100 text-xs">
                                <?php echo $totalRevenue > 0 ? number_format(($grossProfit / $totalRevenue) * 100, 1) : 0; ?>% margin
                            </p>
                        </div>
                        <i class="fas fa-chart-line text-4xl text-<?php echo $grossProfit >= 0 ? 'blue' : 'orange'; ?>-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm">Net Profit</p>
                            <p class="text-3xl font-bold"><?php echo formatCurrency($netProfit); ?></p>
                            <p class="text-purple-100 text-xs">After all expenses</p>
                        </div>
                        <i class="fas fa-coins text-4xl text-purple-300"></i>
                    </div>
                </div>
            </div>

            <!-- Charts and Analysis -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Revenue vs Costs Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Revenue vs Costs</h2>
                    <div class="relative h-64">
                        <canvas id="revenue-costs-chart"></canvas>
                    </div>
                </div>

                <!-- Category Sales Breakdown -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Sales by Category</h2>
                    <div class="space-y-4">
                        <?php foreach ($categorySales as $category): ?>
                        <?php 
                            $percentage = $totalRevenue > 0 ? ($category['total_sales'] / $totalRevenue) * 100 : 0; 
                        ?>
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-medium text-gray-700"><?php echo $category['category_name']; ?></span>
                                <span class="font-semibold text-primary"><?php echo formatCurrency($category['total_sales']); ?></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-primary h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                <?php echo $category['items_sold']; ?> items (<?php echo number_format($percentage, 1); ?>%)
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Detailed Financial Breakdown -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Income Statement -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Income Statement</h2>
                    <div class="space-y-4 text-sm">
                        <!-- Revenue Section -->
                        <div>
                            <h3 class="font-semibold text-gray-700 mb-2">REVENUE</h3>
                            <div class="space-y-2 ml-4">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Food Sales:</span>
                                    <span class="font-semibold"><?php echo formatCurrency($salesData['total_amount'] ?? 0); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Delivery Fees:</span>
                                    <span class="font-semibold"><?php echo formatCurrency($db->fetchOne("SELECT COALESCE(SUM(delivery_fee), 0) as total FROM sales WHERE DATE(created_at) BETWEEN ? AND ?", [$startDate, $endDate])['total']); ?></span>
                                </div>
                                <div class="border-t pt-2 flex justify-between font-semibold">
                                    <span>Total Revenue:</span>
                                    <span class="text-green-600"><?php echo formatCurrency($totalRevenue); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Cost Section -->
                        <div>
                            <h3 class="font-semibold text-gray-700 mb-2">COSTS</h3>
                            <div class="space-y-2 ml-4">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Food Purchases:</span>
                                    <span class="font-semibold"><?php echo formatCurrency($purchaseData['total_amount'] ?? 0); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Operating Expenses:</span>
                                    <span class="font-semibold"><?php echo formatCurrency($expenseData['total_amount'] ?? 0); ?></span>
                                </div>
                                <div class="border-t pt-2 flex justify-between font-semibold">
                                    <span>Total Costs:</span>
                                    <span class="text-red-600"><?php echo formatCurrency($totalCosts); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Profit Section -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="flex justify-between text-lg font-bold">
                                <span>NET PROFIT:</span>
                                <span class="<?php echo $netProfit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo formatCurrency($netProfit); ?>
                                </span>
                            </div>
                            <div class="text-xs text-gray-500 text-center mt-1">
                                Profit Margin: <?php echo $totalRevenue > 0 ? number_format(($netProfit / $totalRevenue) * 100, 1) : 0; ?>%
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cash Flow Analysis -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Cash Flow Analysis</h2>
                    <div class="space-y-4 text-sm">
                        <!-- Cash Inflows -->
                        <div>
                            <h3 class="font-semibold text-gray-700 mb-2">CASH INFLOWS</h3>
                            <div class="space-y-2 ml-4">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Cash Sales:</span>
                                    <span class="font-semibold text-green-600"><?php echo formatCurrency($salesData['cash_sales'] ?? 0); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Card Sales:</span>
                                    <span class="font-semibold text-blue-600"><?php echo formatCurrency($salesData['card_sales'] ?? 0); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Cash Outflows -->
                        <div>
                            <h3 class="font-semibold text-gray-700 mb-2">CASH OUTFLOWS</h3>
                            <div class="space-y-2 ml-4">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Purchase Payments:</span>
                                    <span class="font-semibold text-red-600"><?php echo formatCurrency($purchaseData['paid_amount'] ?? 0); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Cash Expenses:</span>
                                    <span class="font-semibold text-red-600"><?php echo formatCurrency($expenseData['cash_expenses'] ?? 0); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Net Cash Flow -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <?php 
                                $cashInflow = ($salesData['cash_sales'] ?? 0);
                                $cashOutflow = ($purchaseData['paid_amount'] ?? 0) + ($expenseData['cash_expenses'] ?? 0);
                                $netCashFlow = $cashInflow - $cashOutflow;
                            ?>
                            <div class="flex justify-between text-lg font-bold">
                                <span>NET CASH FLOW:</span>
                                <span class="<?php echo $netCashFlow >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo formatCurrency($netCashFlow); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Trend Chart -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">12-Month Sales Trend</h2>
                <div class="relative h-64">
                    <canvas id="monthly-trend-chart"></canvas>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Revenue vs Costs Chart
        const revenueCostsCtx = document.getElementById('revenue-costs-chart').getContext('2d');
        new Chart(revenueCostsCtx, {
            type: 'doughnut',
            data: {
                labels: ['Revenue', 'Purchases', 'Expenses', 'Profit'],
                datasets: [{
                    data: [
                        <?php echo $totalRevenue; ?>,
                        <?php echo $purchaseData['total_amount'] ?? 0; ?>,
                        <?php echo $expenseData['total_amount'] ?? 0; ?>,
                        <?php echo max(0, $netProfit); ?>
                    ],
                    backgroundColor: [
                        '#10b981', // green
                        '#f59e0b', // amber  
                        '#ef4444', // red
                        '#8b5cf6'  // violet
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Monthly Trend Chart
        const monthlyTrendCtx = document.getElementById('monthly-trend-chart').getContext('2d');
        new Chart(monthlyTrendCtx, {
            type: 'line',
            data: {
                labels: [<?php echo implode(',', array_map(function($m) { return "'" . date('M Y', strtotime($m['month'] . '-01')) . "'"; }, $monthlyTrend)); ?>],
                datasets: [{
                    label: 'Sales Amount',
                    data: [<?php echo implode(',', array_column($monthlyTrend, 'sales_amount')); ?>],
                    borderColor: '#5d5cde',
                    backgroundColor: 'rgba(93, 92, 222, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'QR ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        function exportFinancialReport() {
            const startDate = '<?php echo $startDate; ?>';
            const endDate = '<?php echo $endDate; ?>';
            
            window.open(`financial-export.php?start_date=${startDate}&end_date=${endDate}`, '_blank');
        }
    </script>
</body>
</html>