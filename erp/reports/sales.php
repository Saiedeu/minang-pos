<?php
/**
 * ERP System - Sales Reports
 * Comprehensive sales analytics and reporting
 */

define('MINANG_SYSTEM', true);
require_once '../../config/config.php';
require_once '../includes/auth-check.php';

$db = Database::getInstance();

// Get filter parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$reportType = $_GET['report_type'] ?? 'summary';
$staffId = $_GET['staff_id'] ?? '';

// Build where clause for filters
$whereConditions = ["DATE(s.created_at) BETWEEN ? AND ?"];
$params = [$startDate, $endDate];

if ($staffId) {
    $whereConditions[] = "s.user_id = ?";
    $params[] = $staffId;
}

$whereClause = implode(' AND ', $whereConditions);

// Get sales summary
$salesSummary = $db->fetchOne("
    SELECT 
        COUNT(*) as total_transactions,
        COUNT(DISTINCT s.user_id) as active_cashiers,
        SUM(s.total) as total_sales,
        SUM(s.subtotal) as subtotal,
        SUM(s.discount) as total_discounts,
        SUM(s.delivery_fee) as total_delivery_fees,
        SUM(CASE WHEN s.payment_method = 1 THEN s.total ELSE 0 END) as cash_sales,
        SUM(CASE WHEN s.payment_method = 2 THEN s.total ELSE 0 END) as card_sales,
        SUM(CASE WHEN s.payment_method = 3 THEN s.total ELSE 0 END) as credit_sales,
        SUM(CASE WHEN s.payment_method = 4 THEN s.total ELSE 0 END) as foc_sales,
        SUM(CASE WHEN s.payment_method = 5 THEN s.total ELSE 0 END) as cod_sales,
        SUM(CASE WHEN s.order_type = 1 THEN s.total ELSE 0 END) as dine_in_sales,
        SUM(CASE WHEN s.order_type = 2 THEN s.total ELSE 0 END) as takeaway_sales,
        SUM(CASE WHEN s.order_type = 3 THEN s.total ELSE 0 END) as delivery_sales,
        AVG(s.total) as average_transaction
    FROM sales s
    WHERE {$whereClause}
", $params);

// Get sales by date
$salesByDate = $db->fetchAll("
    SELECT 
        DATE(s.created_at) as sale_date,
        COUNT(*) as transactions,
        SUM(s.total) as daily_sales,
        SUM(CASE WHEN s.payment_method = 1 THEN s.total ELSE 0 END) as cash_sales,
        SUM(CASE WHEN s.payment_method = 2 THEN s.total ELSE 0 END) as card_sales
    FROM sales s
    WHERE {$whereClause}
    GROUP BY DATE(s.created_at)
    ORDER BY sale_date DESC
", $params);

// Get top selling products
$topProducts = $db->fetchAll("
    SELECT 
        si.product_name,
        si.product_name_ar,
        SUM(si.quantity) as total_quantity,
        SUM(si.total_price) as total_sales,
        COUNT(DISTINCT si.sale_id) as order_count,
        AVG(si.unit_price) as avg_price
    FROM sale_items si
    INNER JOIN sales s ON si.sale_id = s.id
    WHERE {$whereClause}
    GROUP BY si.product_id, si.product_name
    ORDER BY total_sales DESC
    LIMIT 10
", $params);

// Get staff performance
$staffPerformance = $db->fetchAll("
    SELECT 
        u.name as staff_name,
        u.role,
        COUNT(s.id) as total_transactions,
        SUM(s.total) as total_sales,
        AVG(s.total) as avg_transaction,
        SUM(s.discount) as total_discounts
    FROM sales s
    INNER JOIN users u ON s.user_id = u.id
    WHERE {$whereClause}
    GROUP BY s.user_id, u.name, u.role
    ORDER BY total_sales DESC
", $params);

// Get all staff for filter
$allStaff = $db->fetchAll("SELECT id, name FROM users WHERE is_active = 1 ORDER BY name");

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
            <!-- Page Header with Filters -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Sales Reports</h1>
                        <p class="text-gray-600">Analyze your restaurant's sales performance</p>
                    </div>
                    <div class="flex space-x-3">
                        <button onclick="exportToPDF()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium">
                            <i class="fas fa-file-pdf mr-2"></i>Export PDF
                        </button>
                        <button onclick="exportToExcel()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium">
                            <i class="fas fa-file-excel mr-2"></i>Export Excel
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Staff Member</label>
                        <select name="staff_id" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">All Staff</option>
                            <?php foreach ($allStaff as $staff): ?>
                            <option value="<?php echo $staff['id']; ?>" <?php echo $staffId == $staff['id'] ? 'selected' : ''; ?>>
                                <?php echo $staff['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-primary hover:bg-blue-600 text-white font-semibold py-3 rounded-lg transition-colors">
                            <i class="fas fa-search mr-2"></i>Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Summary Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm">Total Sales</p>
                            <p class="text-2xl font-bold"><?php echo formatCurrency($salesSummary['total_sales'] ?? 0); ?></p>
                            <p class="text-blue-100 text-xs"><?php echo ($salesSummary['total_transactions'] ?? 0) . ' transactions'; ?></p>
                        </div>
                        <i class="fas fa-chart-line text-4xl text-blue-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm">Cash Sales</p>
                            <p class="text-2xl font-bold"><?php echo formatCurrency($salesSummary['cash_sales'] ?? 0); ?></p>
                            <p class="text-green-100 text-xs"><?php echo number_format((($salesSummary['cash_sales'] ?? 0) / max($salesSummary['total_sales'] ?? 1, 1)) * 100, 1); ?>% of total</p>
                        </div>
                        <i class="fas fa-money-bill-wave text-4xl text-green-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm">Card Sales</p>
                            <p class="text-2xl font-bold"><?php echo formatCurrency($salesSummary['card_sales'] ?? 0); ?></p>
                            <p class="text-purple-100 text-xs"><?php echo number_format((($salesSummary['card_sales'] ?? 0) / max($salesSummary['total_sales'] ?? 1, 1)) * 100, 1); ?>% of total</p>
                        </div>
                        <i class="fas fa-credit-card text-4xl text-purple-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm">Avg Transaction</p>
                            <p class="text-2xl font-bold"><?php echo formatCurrency($salesSummary['average_transaction'] ?? 0); ?></p>
                            <p class="text-orange-100 text-xs">Per order value</p>
                        </div>
                        <i class="fas fa-calculator text-4xl text-orange-300"></i>
                    </div>
                </div>
            </div>

            <!-- Charts and Detailed Reports -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Payment Methods Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6">Sales by Payment Method</h3>
                    <div class="h-64">
                        <canvas id="payment-methods-chart"></canvas>
                    </div>
                </div>

                <!-- Order Types Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6">Sales by Order Type</h3>
                    <div class="h-64">
                        <canvas id="order-types-chart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Detailed Tables -->
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                <!-- Top Selling Products -->
                <div class="bg-white rounded-xl shadow-lg">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-xl font-semibold text-gray-800">Top Selling Products</h3>
                    </div>
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                                        <th class="pb-3">Product</th>
                                        <th class="pb-3">Qty Sold</th>
                                        <th class="pb-3">Total Sales</th>
                                        <th class="pb-3">Orders</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm">
                                    <?php foreach ($topProducts as $product): ?>
                                    <tr class="border-t border-gray-200">
                                        <td class="py-3">
                                            <div class="font-semibold text-gray-900"><?php echo $product['product_name']; ?></div>
                                            <?php if ($product['product_name_ar']): ?>
                                            <div class="text-xs text-gray-500" dir="rtl"><?php echo $product['product_name_ar']; ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 text-gray-600"><?php echo number_format($product['total_quantity'], 1); ?></td>
                                        <td class="py-3 font-semibold text-primary"><?php echo formatCurrency($product['total_sales']); ?></td>
                                        <td class="py-3 text-gray-600"><?php echo $product['order_count']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Staff Performance -->
                <div class="bg-white rounded-xl shadow-lg">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-xl font-semibold text-gray-800">Staff Performance</h3>
                    </div>
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                                        <th class="pb-3">Staff</th>
                                        <th class="pb-3">Role</th>
                                        <th class="pb-3">Transactions</th>
                                        <th class="pb-3">Total Sales</th>
                                        <th class="pb-3">Avg Order</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm">
                                    <?php foreach ($staffPerformance as $staff): ?>
                                    <tr class="border-t border-gray-200">
                                        <td class="py-3 font-semibold text-gray-900"><?php echo $staff['staff_name']; ?></td>
                                        <td class="py-3">
                                            <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                                                <?php echo User::getRoleName($staff['role']); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 text-gray-600"><?php echo $staff['total_transactions']; ?></td>
                                        <td class="py-3 font-semibold text-primary"><?php echo formatCurrency($staff['total_sales']); ?></td>
                                        <td class="py-3 text-gray-600"><?php echo formatCurrency($staff['avg_transaction']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily Sales Trend -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold text-gray-800">Daily Sales Trend</h3>
                    <div class="text-sm text-gray-600">
                        Period: <?php echo formatDate($startDate) . ' to ' . formatDate($endDate); ?>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Day</th>
                                <th class="px-4 py-3">Transactions</th>
                                <th class="px-4 py-3">Total Sales</th>
                                <th class="px-4 py-3">Cash Sales</th>
                                <th class="px-4 py-3">Card Sales</th>
                                <th class="px-4 py-3">Avg Order</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <?php foreach ($salesByDate as $dailySale): ?>
                            <tr class="border-t border-gray-200 hover:bg-gray-50">
                                <td class="px-4 py-3 font-semibold text-gray-900"><?php echo formatDate($dailySale['sale_date']); ?></td>
                                <td class="px-4 py-3 text-gray-600"><?php echo date('l', strtotime($dailySale['sale_date'])); ?></td>
                                <td class="px-4 py-3 text-gray-600"><?php echo $dailySale['transactions']; ?></td>
                                <td class="px-4 py-3 font-semibold text-primary"><?php echo formatCurrency($dailySale['daily_sales']); ?></td>
                                <td class="px-4 py-3 text-green-600"><?php echo formatCurrency($dailySale['cash_sales']); ?></td>
                                <td class="px-4 py-3 text-purple-600"><?php echo formatCurrency($dailySale['card_sales']); ?></td>
                                <td class="px-4 py-3 text-gray-600">
                                    <?php echo formatCurrency($dailySale['daily_sales'] / max($dailySale['transactions'], 1)); ?>
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
        // Payment Methods Chart
        const paymentData = {
            cash: <?php echo $salesSummary['cash_sales'] ?? 0; ?>,
            card: <?php echo $salesSummary['card_sales'] ?? 0; ?>,
            credit: <?php echo $salesSummary['credit_sales'] ?? 0; ?>,
            foc: <?php echo $salesSummary['foc_sales'] ?? 0; ?>,
            cod: <?php echo $salesSummary['cod_sales'] ?? 0; ?>
        };

        new Chart(document.getElementById('payment-methods-chart'), {
            type: 'doughnut',
            data: {
                labels: ['Cash', 'Card', 'Credit', 'FOC', 'COD'],
                datasets: [{
                    data: [paymentData.cash, paymentData.card, paymentData.credit, paymentData.foc, paymentData.cod],
                    backgroundColor: ['#059669', '#7c3aed', '#2563eb', '#dc2626', '#ea580c'],
                    borderWidth: 2,
                    borderColor: '#fff'
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

        // Order Types Chart
        const orderData = {
            dineIn: <?php echo $salesSummary['dine_in_sales'] ?? 0; ?>,
            takeaway: <?php echo $salesSummary['takeaway_sales'] ?? 0; ?>,
            delivery: <?php echo $salesSummary['delivery_sales'] ?? 0; ?>
        };

        new Chart(document.getElementById('order-types-chart'), {
            type: 'bar',
            data: {
                labels: ['Dine-In', 'Take Away', 'Delivery'],
                datasets: [{
                    label: 'Sales Amount (QR)',
                    data: [orderData.dineIn, orderData.takeaway, orderData.delivery],
                    backgroundColor: ['#2563eb', '#ea580c', '#059669'],
                    borderRadius: 8,
                    borderSkipped: false,
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

        // Export functions
        function exportToPDF() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'pdf');
            window.open('export.php?' + params.toString(), '_blank');
        }

        function exportToExcel() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'excel');
            window.open('export.php?' + params.toString(), '_blank');
        }
    </script>
</body>
</html>