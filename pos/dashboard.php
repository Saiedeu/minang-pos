<?php
/**
 * POS System - Main Dashboard
 * Shows shift status and navigation
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

// Check authentication
if (!User::isLoggedIn()) {
    header('Location: index.php');
    exit();
}

// Check POS permission
if (!User::hasPermission('pos_sales')) {
    User::logout();
    header('Location: index.php?error=no_permission');
    exit();
}

$user = User::getCurrentUser();
$db = Database::getInstance();

// Check if user has an active shift
$activeShift = $db->fetchOne(
    "SELECT * FROM shifts WHERE user_id = ? AND is_closed = 0 ORDER BY start_time DESC LIMIT 1", 
    [$user['id']]
);

// Get today's stats
$today = date('Y-m-d');
$todayStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_sales,
        SUM(total) as total_amount,
        SUM(CASE WHEN payment_method = 1 THEN total ELSE 0 END) as cash_sales,
        SUM(CASE WHEN payment_method = 2 THEN total ELSE 0 END) as card_sales
    FROM sales 
    WHERE DATE(created_at) = ? AND user_id = ?
", [$today, $user['id']]);

// Get low stock products
$lowStockProducts = $db->fetchAll("
    SELECT name, quantity, reorder_level 
    FROM products 
    WHERE quantity <= reorder_level AND is_active = 1 
    LIMIT 5
");

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Dashboard - <?php echo BUSINESS_NAME; ?></title>
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
    <!-- Header -->
    <header class="bg-gradient-to-r from-primary to-blue-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo and Title -->
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <i class="fas fa-cash-register text-white text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h1 class="text-xl font-semibold text-white">POS System</h1>
                        <p class="text-blue-100 text-sm"><?php echo BUSINESS_NAME; ?></p>
                    </div>
                </div>

                <!-- User Info and Controls -->
                <div class="flex items-center space-x-4">
                    <!-- Clock -->
                    <div class="text-white text-center">
                        <div id="current-time" class="text-lg font-semibold"></div>
                        <div id="current-date" class="text-xs text-blue-100"></div>
                    </div>
                    
                    <!-- User Menu -->
                    <div class="relative">
                        <button onclick="toggleUserMenu()" class="flex items-center text-white hover:bg-white hover:bg-opacity-10 px-3 py-2 rounded-lg transition-all">
                            <div class="text-right mr-3">
                                <div class="text-sm font-semibold"><?php echo $user['name']; ?></div>
                                <div class="text-xs text-blue-100"><?php echo User::getRoleName($user['role']); ?></div>
                            </div>
                            <i class="fas fa-user-circle text-2xl"></i>
                            <i class="fas fa-chevron-down ml-2 text-xs"></i>
                        </button>
                        
                        <div id="user-menu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-2 z-50">
                            <a href="settings.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-cog mr-3"></i>Settings
                            </a>
                            <a href="../" class="flex items-center px-4 py-2 text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-home mr-3"></i>Main Menu
                            </a>
                            <div class="border-t border-gray-200 my-1"></div>
                            <a href="logout.php" class="flex items-center px-4 py-2 text-red-600 hover:bg-red-50">
                                <i class="fas fa-sign-out-alt mr-3"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Shift Status Card -->
        <?php if (!$activeShift): ?>
        <div class="bg-gradient-to-r from-warning to-orange-500 rounded-xl shadow-lg p-6 mb-8">
            <div class="flex items-center justify-between">
                <div class="text-white">
                    <h2 class="text-2xl font-bold mb-2">Start Your Shift</h2>
                    <p class="text-orange-100 mb-4">You need to start a shift before you can begin making sales</p>
                </div>
                <div class="text-white">
                    <i class="fas fa-clock text-6xl opacity-50"></i>
                </div>
            </div>
            <button onclick="showStartShiftModal()" class="bg-white text-orange-600 font-semibold py-3 px-6 rounded-lg hover:bg-orange-50 transition-colors">
                <i class="fas fa-play mr-2"></i>Start Shift
            </button>
        </div>
        <?php else: ?>
        <div class="bg-gradient-to-r from-success to-green-600 rounded-xl shadow-lg p-6 mb-8">
            <div class="flex items-center justify-between">
                <div class="text-white">
                    <h2 class="text-2xl font-bold mb-2">Shift Active</h2>
                    <p class="text-green-100 mb-2">Started: <?php echo formatDateTime($activeShift['start_time']); ?></p>
                    <p class="text-green-100">Opening Balance: <?php echo formatCurrency($activeShift['opening_balance']); ?></p>
                </div>
                <div class="text-white">
                    <i class="fas fa-check-circle text-6xl opacity-50"></i>
                </div>
            </div>
            <div class="flex space-x-4 mt-4">
                <a href="sales.php" class="bg-white text-green-600 font-semibold py-3 px-6 rounded-lg hover:bg-green-50 transition-colors">
                    <i class="fas fa-shopping-cart mr-2"></i>Start Sales
                </a>
                <?php if (User::hasPermission('shift_close')): ?>
                <button onclick="showCloseShiftModal()" class="bg-green-700 text-white font-semibold py-3 px-6 rounded-lg hover:bg-green-800 transition-colors">
                    <i class="fas fa-stop mr-2"></i>Close Shift
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm mb-1">Today's Sales</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $todayStats['total_sales'] ?? 0; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-receipt text-primary text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm mb-1">Total Amount</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo formatCurrency($todayStats['total_amount'] ?? 0); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-money-bill-wave text-success text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm mb-1">Cash Sales</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo formatCurrency($todayStats['cash_sales'] ?? 0); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-coins text-warning text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm mb-1">Card Sales</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo formatCurrency($todayStats['card_sales'] ?? 0); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-credit-card text-purple-500 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Navigation -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <!-- Sales -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                <div class="bg-gradient-to-r from-primary to-blue-600 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-semibold text-white mb-2">Sales</h3>
                            <p class="text-blue-100">Process transactions</p>
                        </div>
                        <i class="fas fa-cash-register text-4xl text-blue-200"></i>
                    </div>
                </div>
                <div class="p-6">
                    <a href="sales.php" class="w-full bg-primary hover:bg-blue-600 text-white font-semibold py-3 px-4 rounded-lg transition-colors flex items-center justify-center">
                        <i class="fas fa-arrow-right mr-2"></i>Open Sales Interface
                    </a>
                </div>
            </div>

            <!-- Inventory -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-semibold text-white mb-2">Inventory</h3>
                            <p class="text-purple-100">Manage products</p>
                        </div>
                        <i class="fas fa-boxes text-4xl text-purple-200"></i>
                    </div>
                </div>
                <div class="p-6">
                    <a href="inventory.php" class="w-full bg-purple-500 hover:bg-purple-600 text-white font-semibold py-3 px-4 rounded-lg transition-colors flex items-center justify-center">
                        <i class="fas fa-arrow-right mr-2"></i>View Inventory
                    </a>
                </div>
            </div>

            <!-- Attendance -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                <div class="bg-gradient-to-r from-green-500 to-green-600 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-semibold text-white mb-2">Attendance</h3>
                            <p class="text-green-100">Staff check-in/out</p>
                        </div>
                        <i class="fas fa-user-check text-4xl text-green-200"></i>
                    </div>
                </div>
                <div class="p-6">
                    <a href="attendance.php" class="w-full bg-green-500 hover:bg-green-600 text-white font-semibold py-3 px-4 rounded-lg transition-colors flex items-center justify-center">
                        <i class="fas fa-arrow-right mr-2"></i>Manage Attendance
                    </a>
                </div>
            </div>

            <!-- Reports -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-semibold text-white mb-2">Reports</h3>
                            <p class="text-indigo-100">View analytics</p>
                        </div>
                        <i class="fas fa-chart-bar text-4xl text-indigo-200"></i>
                    </div>
                </div>
                <div class="p-6">
                    <a href="reports.php" class="w-full bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-3 px-4 rounded-lg transition-colors flex items-center justify-center">
                        <i class="fas fa-arrow-right mr-2"></i>View Reports
                    </a>
                </div>
            </div>

            <!-- Expenses -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                <div class="bg-gradient-to-r from-red-500 to-red-600 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-semibold text-white mb-2">Expenses</h3>
                            <p class="text-red-100">Payment tracking</p>
                        </div>
                        <i class="fas fa-file-invoice-dollar text-4xl text-red-200"></i>
                    </div>
                </div>
                <div class="p-6">
                    <a href="expenses.php" class="w-full bg-red-500 hover:bg-red-600 text-white font-semibold py-3 px-4 rounded-lg transition-colors flex items-center justify-center">
                        <i class="fas fa-arrow-right mr-2"></i>Manage Expenses
                    </a>
                </div>
            </div>

            <!-- Settings -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                <div class="bg-gradient-to-r from-gray-500 to-gray-600 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-semibold text-white mb-2">Settings</h3>
                            <p class="text-gray-100">System configuration</p>
                        </div>
                        <i class="fas fa-cog text-4xl text-gray-200"></i>
                    </div>
                </div>
                <div class="p-6">
                    <a href="settings.php" class="w-full bg-gray-500 hover:bg-gray-600 text-white font-semibold py-3 px-4 rounded-lg transition-colors flex items-center justify-center">
                        <i class="fas fa-arrow-right mr-2"></i>Open Settings
                    </a>
                </div>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <?php if (!empty($lowStockProducts)): ?>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-exclamation-triangle text-warning mr-2"></i>
                    Low Stock Alert
                </h3>
                <span class="bg-warning text-white text-xs font-semibold px-2 py-1 rounded-full">
                    <?php echo count($lowStockProducts); ?> items
                </span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($lowStockProducts as $product): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <h4 class="font-semibold text-gray-800"><?php echo $product['name']; ?></h4>
                    <p class="text-sm text-gray-600">
                        Stock: <?php echo $product['quantity']; ?> / 
                        Reorder at: <?php echo $product['reorder_level']; ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Start Shift Modal -->
    <div id="start-shift-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-gray-800">Start Shift</h3>
                    <button onclick="hideStartShiftModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="start-shift-form" method="POST" action="start-shift.php">
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Opening Balance / Petty Cash
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-3 text-gray-500"><?php echo CURRENCY_SYMBOL; ?></span>
                            <input type="number" name="opening_balance" step="0.01" min="0" 
                                   class="w-full pl-12 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:border-primary focus:ring-2 focus:ring-primary focus:ring-opacity-20"
                                   placeholder="0.00" required>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Enter the amount of cash in the register to start your shift</p>
                    </div>
                    
                    <div class="flex space-x-4">
                        <button type="button" onclick="hideStartShiftModal()" 
                                class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-primary hover:bg-blue-600 text-white font-semibold py-3 rounded-lg transition-colors">
                            Start Shift
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Update clock
        function updateClock() {
            const now = new Date();
            const timeOptions = { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: true 
            };
            const dateOptions = { 
                weekday: 'short', 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            };
            
            document.getElementById('current-time').textContent = now.toLocaleTimeString('en-US', timeOptions);
            document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', dateOptions);
        }

        // Toggle user menu
        function toggleUserMenu() {
            const menu = document.getElementById('user-menu');
            menu.classList.toggle('hidden');
        }

        // Close user menu when clicking outside
        document.addEventListener('click', function(e) {
            const userMenu = document.getElementById('user-menu');
            const userButton = e.target.closest('button');
            
            if (!userButton || !userButton.onclick.toString().includes('toggleUserMenu')) {
                userMenu.classList.add('hidden');
            }
        });

        // Show start shift modal
        function showStartShiftModal() {
            document.getElementById('start-shift-modal').classList.remove('hidden');
        }

        // Hide start shift modal
        function hideStartShiftModal() {
            document.getElementById('start-shift-modal').classList.add('hidden');
        }

        // Initialize
        updateClock();
        setInterval(updateClock, 1000);
    </script>
</body>
</html>