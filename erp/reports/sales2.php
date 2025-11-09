<?php
/**
 * ERP System - Sales Reports
 * Generate comprehensive sales analytics and reports
 */

define('MINANG_SYSTEM', true);
require_once '../../config/config.php';
require_once '../includes/auth-check.php';

$db = Database::getInstance();
$sale = new Sale();

// Get filter parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$reportType = $_GET['report_type'] ?? 'summary';
$userId = $_GET['user_id'] ?? '';

// Get sales statistics
$salesStats = $sale->getSalesStats($startDate, $endDate, $userId ?: null);

// Get best selling products
$bestSelling = $sale->getBestSellingProducts(10, $startDate, $endDate);

// Get sales by payment method
$paymentBreakdown = $db->fetchAll("
    SELECT 
        payment_method,
        COUNT(*) as transaction_count,
        SUM(total) as total_amount
    FROM sales 
    WHERE DATE(created_at) BETWEEN ? AND ?
    " . ($userId ? "AND user_id = ?" : "") . "
    GROUP BY payment_method
    ORDER BY total_amount DESC
", array_filter([$startDate, $endDate, $userId ?: null]));

// Get daily sales trend
$dailySales = $db->fetchAll("
    SELECT 
        DATE(created_at) as sale_date,
        COUNT(*) as transaction_count,
        SUM(total) as total_amount,
        SUM(CASE WHEN payment_method = 1 THEN total ELSE 0 END) as cash_sales,
        SUM(CASE WHEN payment_method = 2 THEN total ELSE 0 END) as card_sales
    FROM sales 
    WHERE DATE(created_at) BETWEEN ? AND ?
    " . ($userId ? "AND user_id = ?" : "") . "
    GROUP BY DATE(created_at)
    ORDER BY sale_date DESC
", array_filter([$startDate, $endDate, $userId ?: null]));

// Get all cashiers for filter
$cashiers = $db->fetchAll("
    SELECT DISTINCT u.id, u.name 
    FROM users u
    INNER JOIN sales s ON u.id = s.user_id
    WHERE u.is_active = 1
    ORDER BY u.name
");

$pageTitle = 'Sales Reports';
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
                    <h1 class="text-3xl font-bold text-gray-800">Sales Reports</h1>
                    <p class="text-gray-600">Comprehensive sales analytics and insights</p>
                </div>
                <div class="flex space-x-3">
                    <button onclick="exportReport()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-download mr-2"></i>Export PDF
                    </button>
                    <button onclick="window.print()" class="bg-secondary hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-print mr-2"></i>Print Report
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
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
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Cashier</label>
                        <select name="user_id" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">All Cashiers</option>
                            <?php foreach ($cashiers as $cashier): ?>
                            <option value="<?php echo $cashier['id']; ?>" <?php echo $userId == $cashier['id'] ? 'selected' : ''; ?>>
                                <?php echo $cashier['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-primary hover:bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                            <i class="fas fa-chart-bar mr-2"></i>Generate Report
                        </button>
                    </div>
                </form>
            </div>

            <!-- Sales Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm">Total Transactions</p>
                            <p class="text-3xl font-bold"><?php echo $salesStats['total_transactions'] ?? 0; ?></p>
                        </div>
                        <i class="fas fa-receipt text-4xl text-blue-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm">Total Sales</p>
                            <p class="text-3xl font-bold"><?php echo formatCurrency($salesStats['total_amount'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-chart-line text-4xl text-green-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm">Average Transaction</p>
                            <p class="text-3xl font-bold"><?php echo formatCurrency($salesStats['average_transaction'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-calculator text-4xl text-purple-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm">Total Discounts</p>
                            <p class="text-3xl font-bold"><?php echo formatCurrency($salesStats['total_discounts'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-percent text-4xl text-orange-300"></i>
                    </div>
                </div>
            </div>

            <!-- Charts and Analysis -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Payment Method Breakdown -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Payment Method Breakdown</h2>
                    <div class="space-y-4">
                        <?php
                        $paymentMethods = [1 => 'Cash', 2 => 'Card', 3 => 'Credit', 4 => 'FOC', 5 => 'COD'];
                        $totalAmount = $salesStats['total_amount'] ?? 1;
                        
                        foreach ($paymentBreakdown as $payment):
                            $percentage = ($payment['total_amount'] / $totalAmount) * 100;
                            $methodName = $paymentMethods[$payment['payment_method']] ?? 'Unknown';
                        ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-4 h-4 rounded-full bg-primary"></div>
                                <span class="font-medium text-gray-700"><?php echo $methodName; ?></span>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-gray-900"><?php echo formatCurrency($payment['total_amount']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo number_format($percentage, 1); ?>%</div>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-primary h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Best Selling Products -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Best Selling Products</h2>
                    <div class="space-y-4">
                        <?php foreach ($bestSelling as $index => $product): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center text-white text-sm font-bold">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800"><?php echo $product['product_name']; ?></h4>
                                    <?php if ($product['product_name_ar']): ?>
                                    <p class="text-xs text-gray-500" dir="rtl"><?php echo $product['product_name_ar']; ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-primary"><?php echo $product['total_quantity']; ?> sold</div>
                                <div class="text-sm text-gray-500"><?php echo formatCurrency($product['total_sales']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Daily Sales Trend -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-800">Daily Sales Trend</h2>
                    <div class="text-sm text-gray-500">
                        Period: <?php echo formatDate($startDate); ?> - <?php echo formatDate($endDate); ?>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Date</th>
                                <th class="px-6 py-4">Transactions</th>
                                <th class="px-6 py-4">Total Sales</th>
                                <th class="px-6 py-4">Cash Sales</th>
                                <th class="px-6 py-4">Card Sales</th>
                                <th class="px-6 py-4">Average</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($dailySales as $day): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium text-gray-900"><?php echo formatDate($day['sale_date']); ?></td>
                                <td class="px-6 py-4 text-gray-600"><?php echo $day['transaction_count']; ?></td>
                                <td class="px-6 py-4 font-semibold text-primary"><?php echo formatCurrency($day['total_amount']); ?></td>
                                <td class="px-6 py-4 text-green-600"><?php echo formatCurrency($day['cash_sales']); ?></td>
                                <td class="px-6 py-4 text-blue-600"><?php echo formatCurrency($day['card_sales']); ?></td>
                                <td class="px-6 py-4 text-gray-600">
                                    <?php echo formatCurrency($day['transaction_count'] > 0 ? $day['total_amount'] / $day['transaction_count'] : 0); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        function exportReport() {
            const startDate = '<?php echo $startDate; ?>';
            const endDate = '<?php echo $endDate; ?>';
            const userId = '<?php echo $userId; ?>';
            
            const params = new URLSearchParams({
                action: 'export_pdf',
                start_date: startDate,
                end_date: endDate,
                user_id: userId
            });
            
            window.open(`sales-export.php?${params}`, '_blank');
        }

        // Auto-update date range
        document.querySelector('input[name="start_date"]').addEventListener('change', function() {
            if (this.value > document.querySelector('input[name="end_date"]').value) {
                document.querySelector('input[name="end_date"]').value = this.value;
            }
        });
    </script>
</body>
</html>