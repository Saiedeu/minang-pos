<?php
/**
 * ERP Sidebar Component - Updated with Restaurant Management
 * Reusable navigation sidebar for ERP system
 */

if (!defined('MINANG_SYSTEM')) {
    exit('Direct access not allowed');
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
$parentDir = basename(dirname(dirname($_SERVER['PHP_SELF'])));

// Determine active states
function isActiveLink($path) {
    global $currentPage, $currentDir, $parentDir;
    
    if (strpos($path, '/') !== false) {
        $pathParts = explode('/', $path);
        return $parentDir === $pathParts[0] && $currentPage === $pathParts[1];
    }
    return $currentPage === $path;
}
?>

<div class="fixed inset-y-0 left-0 w-64 bg-white shadow-lg z-50 overflow-y-auto">
    <!-- Logo Section -->
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

    <!-- Navigation -->
    <nav class="mt-8 px-4">
        <div class="space-y-2">
            <!-- Dashboard -->
            <a href="../dashboard.php" 
               class="flex items-center px-4 py-3 <?php echo isActiveLink('dashboard.php') ? 'text-primary bg-blue-50 border-r-2 border-primary' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg font-medium transition-colors">
                <i class="fas fa-tachometer-alt mr-3"></i>Dashboard
            </a>

            <!-- Restaurant Management Section -->
            <div class="py-2">
                <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Restaurant Management</p>
                <div class="mt-2 space-y-1">
                    <a href="../resto-management/dashboard.php" 
                       class="flex items-center px-4 py-2 <?php echo $currentDir === 'resto-management' && $currentPage === 'dashboard' ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-tachometer-alt mr-3"></i>Overview
                    </a>
                    <a href="../resto-management/customers.php" 
                       class="flex items-center px-4 py-2 <?php echo isActiveLink('resto-management/customers') ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-users mr-3"></i>Customers
                    </a>
                    <a href="../resto-management/todo-list.php" 
                       class="flex items-center px-4 py-2 <?php echo isActiveLink('resto-management/todo-list') ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-tasks mr-3"></i>To-Do List
                    </a>
                    <a href="../resto-management/planner.php" 
                       class="flex items-center px-4 py-2 <?php echo isActiveLink('resto-management/planner') ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-calendar-alt mr-3"></i>Planner
                    </a>
                    <a href="../resto-management/temperature-records.php" 
                       class="flex items-center px-4 py-2 <?php echo isActiveLink('resto-management/temperature-records') ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-thermometer-half mr-3"></i>Temperature Records
                    </a>
                    <a href="../resto-management/expiration-records.php" 
                       class="flex items-center px-4 py-2 <?php echo isActiveLink('resto-management/expiration-records') ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-calendar-times mr-3"></i>Expiration Records
                    </a>
                </div>
            </div>

            <!-- Inventory Section -->
            <div class="py-2">
                <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Inventory</p>
                <div class="mt-2 space-y-1">
                    <a href="../inventory/products.php" 
                       class="flex items-center px-4 py-2 <?php echo $currentDir === 'inventory' && $currentPage === 'products' ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-box mr-3"></i>Products
                    </a>
                    <a href="../inventory/categories.php" 
                       class="flex items-center px-4 py-2 <?php echo $currentDir === 'inventory' && $currentPage === 'categories' ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-tags mr-3"></i>Categories
                    </a>
                    <a href="../inventory/stock-control.php" 
                       class="flex items-center px-4 py-2 <?php echo $currentDir === 'inventory' && $currentPage === 'stock-control' ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-warehouse mr-3"></i>Stock Control
                    </a>
                </div>
            </div>

            <!-- Purchases Section -->
            <div class="py-2">
                <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Purchases</p>
                <div class="mt-2 space-y-1">
                    <a href="../purchases/suppliers.php" 
                       class="flex items-center px-4 py-2 <?php echo $currentDir === 'purchases' && $currentPage === 'suppliers' ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-truck mr-3"></i>Suppliers
                    </a>
                    <a href="../purchases/invoices.php" 
                       class="flex items-center px-4 py-2 <?php echo $currentDir === 'purchases' && $currentPage === 'invoices' ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-file-invoice mr-3"></i>Purchase Invoices
                    </a>
                </div>
            </div>

            <!-- HR Section -->
            <div class="py-2">
                <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Human Resources</p>
                <div class="mt-2 space-y-1">
                    <a href="../hr/staff.php" 
                       class="flex items-center px-4 py-2 <?php echo $currentDir === 'hr' && $currentPage === 'staff' ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-users mr-3"></i>Staff
                    </a>
                    <a href="../hr/attendance.php" 
                       class="flex items-center px-4 py-2 <?php echo $currentDir === 'hr' && $currentPage === 'attendance' ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-clock mr-3"></i>Attendance
                    </a>
                </div>
            </div>

            <!-- Expenses Section -->
            <div class="py-2">
                <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Expenses</p>
                <div class="mt-2 space-y-1">
                    <a href="../expenses/expenses.php" 
                       class="flex items-center px-4 py-2 <?php echo $currentDir === 'expenses' && $currentPage === 'expenses' ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-receipt mr-3"></i>Expenses
                    </a>
                    <a href="../expenses/categories.php" 
                       class="flex items-center px-4 py-2 <?php echo $currentDir === 'expenses' && $currentPage === 'categories' ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-list mr-3"></i>Categories
                    </a>
                </div>
            </div>

            <!-- Reports Section -->
            <div class="py-2">
                <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Reports</p>
                <div class="mt-2 space-y-1">
                    <a href="../reports/sales.php" 
                       class="flex items-center px-4 py-2 <?php echo $currentDir === 'reports' && $currentPage === 'sales' ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-chart-line mr-3"></i>Sales Reports
                    </a>
                    <a href="../reports/financial.php" 
                       class="flex items-center px-4 py-2 <?php echo $currentDir === 'reports' && $currentPage === 'financial' ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-calculator mr-3"></i>Financial Reports
                    </a>
                    <a href="../reports/receipts.php" 
                       class="flex items-center px-4 py-2 <?php echo $currentDir === 'reports' && $currentPage === 'receipts' ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-receipt mr-3"></i>Receipt Archive
                    </a>
                </div>
            </div>

            <!-- Settings Section -->
            <div class="py-2">
                <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Settings</p>
                <div class="mt-2 space-y-1">
                    <a href="../settings/shop.php" 
                       class="flex items-center px-4 py-2 <?php echo $currentDir === 'settings' && $currentPage === 'shop' ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-store mr-3"></i>Shop Settings
                    </a>
                    <a href="../settings/users.php" 
                       class="flex items-center px-4 py-2 <?php echo $currentDir === 'settings' && $currentPage === 'users' ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-user-cog mr-3"></i>User Management
                    </a>
                    <a href="../settings/system.php" 
                       class="flex items-center px-4 py-2 <?php echo $currentDir === 'settings' && $currentPage === 'system' ? 'text-primary bg-blue-50' : 'text-gray-600 hover:bg-gray-50 hover:text-primary'; ?> rounded-lg text-sm transition-colors">
                        <i class="fas fa-cogs mr-3"></i>System Settings
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- User Info -->
    <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-200 bg-white">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-gradient-to-r from-primary to-indigo-600 rounded-full flex items-center justify-center">
                <i class="fas fa-user text-white"></i>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-800"><?php echo User::getCurrentUser()['name'] ?? 'User'; ?></p>
                <p class="text-xs text-gray-500"><?php echo User::getRoleName(User::getCurrentUser()['role'] ?? 4); ?></p>
            </div>
            <div class="flex space-x-2">
                <a href="../../pos/dashboard.php" class="text-gray-400 hover:text-primary" title="Switch to POS">
                    <i class="fas fa-cash-register"></i>
                </a>
                <a href="../logout.php" class="text-gray-400 hover:text-red-500" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>
</div>