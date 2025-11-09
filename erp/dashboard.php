<?php
/**
 * ERP System - Main Dashboard
 * Management overview and navigation
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

// Check authentication
if (!User::isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$user = User::getCurrentUser();
$db = Database::getInstance();

// Get dashboard statistics
$today = date('Y-m-d');
$thisMonth = date('Y-m');

// Sales stats
$salesStats = $db->fetchOne("
    SELECT 
        COUNT(*) as today_transactions,
        COALESCE(SUM(total), 0) as today_sales,
        COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_transactions,
        COALESCE(SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN total ELSE 0 END), 0) as week_sales
    FROM sales 
    WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
");

// Inventory stats
$inventoryStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_products,
        COUNT(CASE WHEN quantity <= reorder_level THEN 1 END) as low_stock,
        COUNT(CASE WHEN quantity = 0 THEN 1 END) as out_of_stock,
        COALESCE(SUM(quantity * cost_price), 0) as inventory_value
    FROM products 
    WHERE is_active = 1
");

// Staff stats
$staffStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_staff,
        COUNT(CASE WHEN DATE(joining_date) = CURDATE() THEN 1 END) as new_today
    FROM users 
    WHERE is_active = 1
");

// Recent sales
$recentSales = $db->fetchAll("
    SELECT s.*, u.name as cashier_name 
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    ORDER BY s.created_at DESC 
    LIMIT 10
");

// Active shifts
$activeShifts = $db->fetchAll("
    SELECT s.*, u.name as user_name 
    FROM shifts s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.is_closed = 0
    ORDER BY s.start_time DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP Dashboard - <?php echo BUSINESS_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '<?php echo PRIMARY_COLOR; ?>',
                        secondary: '<?php echo SECONDARY_COLOR; ?>',
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
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 w-64 bg-white shadow-lg z-50" id="sidebar">
        <div class="flex items-center justify-between h-16 px-6 bg-gradient-to-r from-primary to-indigo-600">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-chart-line text-white text-lg"></i>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-white">ERP Portal</h1>
                    <p class="text-xs text-indigo-100"><?php echo BUSINESS_NAME; ?></p>
                </div>
            </div>
        </div>

        <nav class="mt-8 px-4">
            <div class="space-y-2">
                <a href="dashboard.php" class="flex items-center px-4 py-3 text-primary bg-blue-50 rounded-lg font-medium">
                    <i class="fas fa-tachometer-alt mr-3"></i>Dashboard
                </a>
                <a href="inventory/products.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary rounded-lg font-medium transition-colors">
                    <i class="fas fa-boxes mr-3"></i>Inventory
                </a>
                <a href="purchases/invoices.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary rounded-lg font-medium transition-colors">
                    <i class="fas fa-truck mr-3"></i>Purchases
                </a>
                <a href="hr/staff.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary rounded-lg font-medium transition-colors">
                    <i class="fas fa-users mr-3"></i>Human Resources
                </a>
                <a href="expenses/expenses.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary rounded-lg font-medium transition-colors">
                    <i class="fas fa-file-invoice-dollar mr-3"></i>Expenses
                </a>
                <a href="reports/sales.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary rounded-lg font-medium transition-colors">
                    <i class="fas fa-chart-bar mr-3"></i>Reports
                </a>
                <a href="settings/shop.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary rounded-lg font-medium transition-colors">
                    <i class="fas fa-cog mr-3"></i>Settings
                </a>
            </div>
        </nav>

        <!-- User Info -->
        <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-200">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center">
                    <i class="fas fa-user text-white"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-gray-800"><?php echo $user['name']; ?></p>
                    <p class="text-xs text-gray-500"><?php echo User::getRoleName($user['role']); ?></p>
                </div>
                <a href="logout.php" class="text-gray-400 hover:text-red-500" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="ml-64 min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="flex items-center justify-between px-8 py-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
                    <p class="text-gray-600">Welcome back, <?php echo $user['name']; ?>!</p>
                </div>
                <div class="flex items-center space-x-4">
                    <!-- Clock -->
                    <div class="text-right text-gray-600">
                        <div id="current-time" class="text-lg font-semibold"></div>
                        <div id="current-date" class="text-sm"></div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="flex space-x-2">
                        <a href="../pos/sales.php" class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            <i class="fas fa-cash-register mr-2"></i>Open POS
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dashboard Content -->
        <main class="p-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Today's Sales -->
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm mb-1">Today's Sales</p>
                            <p class="text-2xl font-bold"><?php echo formatCurrency($salesStats['today_sales'] ?? 0); ?></p>
                            <p class="text-blue-100 text-xs"><?php echo ($salesStats['today_transactions'] ?? 0) . ' transactions'; ?></p>
                        </div>
                        <i class="fas fa-chart-line text-4xl text-blue-300"></i>
                    </div>
                </div>

                <!-- Inventory Value -->
                <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm mb-1">Inventory Value</p>
                            <p class="text-2xl font-bold"><?php echo formatCurrency($inventoryStats['inventory_value'] ?? 0); ?></p>
                            <p class="text-green-100 text-xs"><?php echo ($inventoryStats['total_products'] ?? 0) . ' products'; ?></p>
                        </div>
                        <i class="fas fa-boxes text-4xl text-green-300"></i>
                    </div>
                </div>

                <!-- Low Stock Alert -->
                <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm mb-1">Low Stock Items</p>
                            <p class="text-2xl font-bold"><?php echo $inventoryStats['low_stock'] ?? 0; ?></p>
                            <p class="text-orange-100 text-xs">Need attention</p>
                        </div>
                        <i class="fas fa-exclamation-triangle text-4xl text-orange-300"></i>
                    </div>
                </div>

                <!-- Active Staff -->
                <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm mb-1">Total Staff</p>
                            <p class="text-2xl font-bold"><?php echo $staffStats['total_staff'] ?? 0; ?></p>
                            <p class="text-purple-100 text-xs">Active employees</p>
                        </div>
                        <i class="fas fa-users text-4xl text-purple-300"></i>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Recent Sales -->
                <div class="lg:col-span-2 bg-white rounded-xl shadow-lg">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-semibold text-gray-800">Recent Sales</h2>
                            <a href="reports/sales.php" class="text-primary hover:text-blue-700 text-sm font-medium">View All</a>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <th class="pb-3">Receipt #</th>
                                        <th class="pb-3">Time</th>
                                        <th class="pb-3">Type</th>
                                        <th class="pb-3">Total</th>
                                        <th class="pb-3">Payment</th>
                                        <th class="pb-3">Cashier</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm">
                                    <?php foreach ($recentSales as $sale): ?>
                                    <tr class="border-t border-gray-200">
                                        <td class="py-3 font-medium text-gray-900"><?php echo $sale['receipt_number']; ?></td>
                                        <td class="py-3 text-gray-600"><?php echo date('H:i', strtotime($sale['created_at'])); ?></td>
                                        <td class="py-3">
                                            <span class="px-2 py-1 text-xs rounded-full <?php 
                                                echo $sale['order_type'] == 1 ? 'bg-blue-100 text-blue-800' : 
                                                    ($sale['order_type'] == 2 ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800'); 
                                            ?>">
                                                <?php 
                                                    $orderTypes = [1 => 'Dine-In', 2 => 'Take Away', 3 => 'Delivery'];
                                                    echo $orderTypes[$sale['order_type']] ?? 'Unknown';
                                                ?>
                                            </span>
                                        </td>
                                        <td class="py-3 font-semibold"><?php echo formatCurrency($sale['total']); ?></td>
                                        <td class="py-3">
                                            <?php
                                                $paymentMethods = [1 => 'Cash', 2 => 'Card', 3 => 'Credit', 4 => 'FOC', 5 => 'COD'];
                                                echo $paymentMethods[$sale['payment_method']] ?? 'Unknown';
                                            ?>
                                        </td>
                                        <td class="py-3 text-gray-600"><?php echo $sale['cashier_name']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Side Panels -->
                <div class="space-y-6">
                    <!-- Active Shifts -->
                    <div class="bg-white rounded-xl shadow-lg">
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-clock text-success mr-2"></i>Active Shifts
                            </h2>
                        </div>
                        <div class="p-6">
                            <?php if (empty($activeShifts)): ?>
                                <p class="text-gray-500 text-center py-4">No active shifts</p>
                            <?php else: ?>
                                <div class="space-y-3">
                                    <?php foreach ($activeShifts as $shift): ?>
                                    <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                        <div>
                                            <p class="font-medium text-gray-800"><?php echo $shift['user_name']; ?></p>
                                            <p class="text-sm text-gray-600">Since <?php echo date('H:i', strtotime($shift['start_time'])); ?></p>
                                        </div>
                                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-lg">
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-800">Quick Actions</h2>
                        </div>
                        <div class="p-6 space-y-3">
                            <a href="inventory/products.php?action=add" class="flex items-center w-full p-3 text-left bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg transition-colors">
                                <i class="fas fa-plus mr-3"></i>Add New Product
                            </a>
                            <a href="purchases/invoices.php?action=add" class="flex items-center w-full p-3 text-left bg-green-50 hover:bg-green-100 text-green-700 rounded-lg transition-colors">
                                <i class="fas fa-file-invoice mr-3"></i>Add Purchase
                            </a>
                            <a href="hr/staff.php?action=add" class="flex items-center w-full p-3 text-left bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-lg transition-colors">
                                <i class="fas fa-user-plus mr-3"></i>Add Staff Member
                            </a>
                            <a href="reports/sales.php" class="flex items-center w-full p-3 text-left bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg transition-colors">
                                <i class="fas fa-chart-line mr-3"></i>View Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Update clock
        function updateClock() {
            const now = new Date();
            document.getElementById('current-time').textContent = now.toLocaleTimeString('en-US', {
                hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
            document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', {
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
            });
        }

        // Initialize
        updateClock();
        setInterval(updateClock, 1000);
    </script>
</body>
</html>