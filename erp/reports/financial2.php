<?php
/**
 * ERP System - Financial Reports
 * Comprehensive financial analytics and reporting
 */

define('MINANG_SYSTEM', true);
require_once '../../config/config.php';
require_once '../includes/auth-check.php';

$db = Database::getInstance();
$sale = new Sale();
$purchase = new Purchase();

// Get filter parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$reportType = $_GET['report_type'] ?? 'summary';

// Get financial data
$salesStats = $sale->getSalesStats($startDate, $endDate);
$purchaseStats = $purchase->getPurchaseStats($startDate, $endDate);

// Get expense data
$expenseStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_expenses,
        SUM(amount) as total_amount,
        SUM(CASE WHEN payment_method = 1 THEN amount ELSE 0 END) as cash_expenses
    FROM expenses 
    WHERE DATE(expense_date) BETWEEN ? AND ?
", [$startDate, $endDate]);

// Calculate profit/loss
$totalRevenue = $salesStats['total_amount'] ?? 0;
$totalCOGS = $purchaseStats['total_amount'] ?? 0; // Cost of Goods Sold
$totalExpenses = $expenseStats['total_amount'] ?? 0;
$grossProfit = $totalRevenue - $totalCOGS;
$netProfit = $grossProfit - $totalExpenses;

// Get daily financial trend
$dailyFinancials = $db->fetchAll("
    SELECT 
        date_val as financial_date,
        COALESCE(revenue, 0) as daily_revenue,
        COALESCE(purchases, 0) as daily_purchases,
        COALESCE(expenses, 0) as daily_expenses,
        (COALESCE(revenue, 0) - COALESCE(purchases, 0) - COALESCE(expenses, 0)) as daily_profit
    FROM (
        SELECT DATE(?) + INTERVAL seq.seq DAY as date_val
        FROM (
            SELECT 0 as seq UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION 
            SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION
            SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION
            SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION
            SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION
            SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30
        ) seq
        WHERE DATE(?) + INTERVAL seq.seq DAY <= DATE(?)
    ) dates
    LEFT JOIN (
        SELECT DATE(created_at) as sale_date, SUM(total) as revenue
        FROM sales 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
    ) sales_data ON dates.date_val = sales_data.sale_date
    LEFT JOIN (
        SELECT DATE(purchase_date) as purchase_date, SUM(total) as purchases
        FROM purchases 
        WHERE DATE(purchase_date) BETWEEN ? AND ?
        GROUP BY DATE(purchase_date)
    ) purchase_data ON dates.date_val = purchase_data.purchase_date
    LEFT JOIN (
        SELECT DATE(expense_date) as expense_date, SUM(amount) as expenses
        FROM expenses 
        WHERE DATE(expense_date) BETWEEN ? AND ?
        GROUP BY DATE(expense_date)
    ) expense_data ON dates.date_val = expense_data.expense_date
    ORDER BY date_val DESC
", [$startDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);

// Get expense breakdown
$expenseBreakdown = $db->fetchAll("
    SELECT 
        ec.name as category_name,
        COUNT(e.id) as expense_count,
        SUM(e.amount) as total_amount
    FROM expenses e
    LEFT JOIN expense_categories ec ON e.category_id = ec.id
    WHERE DATE(e.expense_date) BETWEEN ? AND ?
    GROUP BY e.category_id, ec.name
    ORDER BY total_amount DESC
", [$startDate, $endDate]);

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
                    <p class="text-gray-600">Comprehensive financial analytics and insights</p>
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
                            <i class="fas fa-chart-bar mr-2"></i>Generate Report
                        </button>
                    </div>
                </form>
            </div>

            <!-- Financial Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm">Total Revenue</p>
                            <p class="text-2xl font-bold"><?php echo formatCurrency($totalRevenue); ?></p>
                        </div>
                        <i class="fas fa-chart-line text-3xl text-green-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm">Total Purchases</p>
                            <p class="text-2xl font-bold"><?php echo formatCurrency($totalCOGS); ?></p>
                        </div>
                        <i class="fas fa-shopping-cart text-3xl text-blue-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-red-100 text-sm">Total Expenses</p>
                            <p class="text-2xl font-bold"><?php echo formatCurrency($totalExpenses); ?></p>
                        </div>
                        <i class="fas fa-receipt text-3xl text-red-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm">Gross Profit</p>
                            <p class="text-2xl font-bold"><?php echo formatCurrency($grossProfit); ?></p>
                        </div>
                        <i class="fas fa-coins text-3xl text-purple-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br <?php echo $netProfit >= 0 ? 'from-emerald-500 to-emerald-600' : 'from-red-500 to-red-600'; ?> rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Net Profit</p>
                            <p class="text-2xl font-bold"><?php echo formatCurrency($netProfit); ?></p>
                        </div>
                        <i class="fas fa-<?php echo $netProfit >= 0 ? 'arrow-up' : 'arrow-down'; ?> text-3xl text-white/60"></i>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Revenue vs Expenses Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Revenue vs Expenses Trend</h2>
                    <div class="relative h-64">
                        <canvas id="financial-trend-chart"></canvas>
                    </div>
                </div>

                <!-- Expense Breakdown -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Expense Breakdown</h2>
                    <div class="space-y-3">
                        <?php foreach ($expenseBreakdown as $expense): ?>
                        <?php $percentage = $totalExpenses > 0 ? ($expense['total_amount'] / $totalExpenses) * 100 : 0; ?>
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-medium text-gray-700"><?php echo $expense['category_name']; ?></span>
                                <span class="text-sm text-gray-500 ml-2">(<?php echo $expense['expense_count']; ?> items)</span>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-gray-900"><?php echo formatCurrency($expense['total_amount']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo number_format($percentage, 1); ?>%</div>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-red-500 h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Daily Financial Trend -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">Daily Financial Summary</h2>
                    <p class="text-gray-600">Period: <?php echo formatDate($startDate); ?> - <?php echo formatDate($endDate); ?></p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Date</th>
                                <th class="px-6 py-4 text-right">Revenue</th>
                                <th class="px-6 py-4 text-right">Purchases</th>
                                <th class="px-6 py-4 text-right">Expenses</th>
                                <th class="px-6 py-4 text-right">Gross Profit</th>
                                <th class="px-6 py-4 text-right">Net Profit</th>
                                <th class="px-6 py-4 text-right">Margin %</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php 
                            $totalDailyRevenue = 0;
                            $totalDailyPurchases = 0;
                            $totalDailyExpenses = 0;
                            
                            foreach ($dailyFinancials as $day): 
                                $dayGrossProfit = $day['daily_revenue'] - $day['daily_purchases'];
                                $dayNetProfit = $dayGrossProfit - $day['daily_expenses'];
                                $profitMargin = $day['daily_revenue'] > 0 ? ($dayNetProfit / $day['daily_revenue']) * 100 : 0;
                                
                                $totalDailyRevenue += $day['daily_revenue'];
                                $totalDailyPurchases += $day['daily_purchases'];
                                $totalDailyExpenses += $day['daily_expenses'];
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium text-gray-900"><?php echo formatDate($day['financial_date']); ?></td>
                                <td class="px-6 py-4 text-right font-semibold text-green-600"><?php echo formatCurrency($day['daily_revenue']); ?></td>
                                <td class="px-6 py-4 text-right text-blue-600"><?php echo formatCurrency($day['daily_purchases']); ?></td>
                                <td class="px-6 py-4 text-right text-red-600"><?php echo formatCurrency($day['daily_expenses']); ?></td>
                                <td class="px-6 py-4 text-right font-semibold <?php echo $dayGrossProfit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo formatCurrency($dayGrossProfit); ?>
                                </td>
                                <td class="px-6 py-4 text-right font-semibold <?php echo $dayNetProfit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo formatCurrency($dayNetProfit); ?>
                                </td>
                                <td class="px-6 py-4 text-right <?php echo $profitMargin >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo number_format($profitMargin, 1); ?>%
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-primary text-white">
                            <?php
                                $totalGrossProfit = $totalDailyRevenue - $totalDailyPurchases;
                                $totalNetProfit = $totalGrossProfit - $totalDailyExpenses;
                                $overallMargin = $totalDailyRevenue > 0 ? ($totalNetProfit / $totalDailyRevenue) * 100 : 0;
                            ?>
                            <tr class="font-bold">
                                <td class="px-6 py-4">TOTALS</td>
                                <td class="px-6 py-4 text-right"><?php echo formatCurrency($totalDailyRevenue); ?></td>
                                <td class="px-6 py-4 text-right"><?php echo formatCurrency($totalDailyPurchases); ?></td>
                                <td class="px-6 py-4 text-right"><?php echo formatCurrency($totalDailyExpenses); ?></td>
                                <td class="px-6 py-4 text-right"><?php echo formatCurrency($totalGrossProfit); ?></td>
                                <td class="px-6 py-4 text-right"><?php echo formatCurrency($totalNetProfit); ?></td>
                                <td class="px-6 py-4 text-right"><?php echo number_format($overallMargin, 1); ?>%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Key Performance Indicators -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Key Performance Indicators</h2>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <span class="text-sm font-medium text-gray-600">Gross Profit Margin</span>
                                <p class="text-xs text-gray-500">Revenue minus Cost of Goods Sold</p>
                            </div>
                            <div class="text-xl font-bold <?php echo $grossProfit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $totalRevenue > 0 ? number_format(($grossProfit / $totalRevenue) * 100, 1) : 0; ?>%
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <span class="text-sm font-medium text-gray-600">Net Profit Margin</span>
                                <p class="text-xs text-gray-500">Final profit after all expenses</p>
                            </div>
                            <div class="text-xl font-bold <?php echo $netProfit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $totalRevenue > 0 ? number_format(($netProfit / $totalRevenue) * 100, 1) : 0; ?>%
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <span class="text-sm font-medium text-gray-600">Operating Expense Ratio</span>
                                <p class="text-xs text-gray-500">Operating expenses as % of revenue</p>
                            </div>
                            <div class="text-xl font-bold text-orange-600">
                                <?php echo $totalRevenue > 0 ? number_format(($totalExpenses / $totalRevenue) * 100, 1) : 0; ?>%
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <span class="text-sm font-medium text-gray-600">Average Transaction</span>
                                <p class="text-xs text-gray-500">Revenue per transaction</p>
                            </div>
                            <div class="text-xl font-bold text-purple-600">
                                <?php echo formatCurrency($salesStats['average_transaction'] ?? 0); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profit/Loss Summary -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Profit & Loss Summary</h2>
                    
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between py-2 border-b border-gray-200">
                            <span class="font-medium text-gray-600">REVENUE</span>
                            <span class="font-semibold text-green-600"><?php echo formatCurrency($totalRevenue); ?></span>
                        </div>
                        
                        <div class="ml-4 space-y-1">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Cash Sales:</span>
                                <span><?php echo formatCurrency($salesStats['cash_sales'] ?? 0); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Card Sales:</span>
                                <span><?php echo formatCurrency($salesStats['card_sales'] ?? 0); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Credit Sales:</span>
                                <span><?php echo formatCurrency($salesStats['credit_sales'] ?? 0); ?></span>
                            </div>
                        </div>
                        
                        <div class="flex justify-between py-2 border-b border-gray-200">
                            <span class="font-medium text-gray-600">COST OF GOODS SOLD</span>
                            <span class="font-semibold text-blue-600">-<?php echo formatCurrency($totalCOGS); ?></span>
                        </div>
                        
                        <div class="flex justify-between py-2 border-b border-gray-200 text-base">
                            <span class="font-semibold text-gray-800">GROSS PROFIT</span>
                            <span class="font-bold <?php echo $grossProfit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo formatCurrency($grossProfit); ?>
                            </span>
                        </div>
                        
                        <div class="flex justify-between py-2 border-b border-gray-200">
                            <span class="font-medium text-gray-600">OPERATING EXPENSES</span>
                            <span class="font-semibold text-red-600">-<?php echo formatCurrency($totalExpenses); ?></span>
                        </div>
                        
                        <div class="flex justify-between py-3 border-t-2 border-gray-800 text-lg">
                            <span class="font-bold text-gray-900">NET PROFIT/LOSS</span>
                            <span class="font-bold <?php echo $netProfit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo formatCurrency($netProfit); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Financial Trend Chart
        const dailyData = <?php echo json_encode($dailyFinancials); ?>;
        
        const ctx = document.getElementById('financial-trend-chart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dailyData.map(d => new Date(d.financial_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'})),
                datasets: [
                    {
                        label: 'Revenue',
                        data: dailyData.map(d => d.daily_revenue),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Expenses',
                        data: dailyData.map(d => d.daily_expenses),
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Net Profit',
                        data: dailyData.map(d => d.daily_profit),
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
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
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': QR ' + context.raw.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        function exportFinancialReport() {
            window.open('../prints/report-templates/financial-export.php?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>', '_blank');
        }
    </script>
</body>
</html>