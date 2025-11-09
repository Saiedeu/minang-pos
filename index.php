<?php
/**
 * Minang Restaurant System - Main Entry Point
 * This file determines which system (POS or ERP) to load based on the domain
 */

// Define system constant
define('MINANG_SYSTEM', true);

// Include configuration
require_once 'config/config.php';

// Get current domain/host
$current_host = $_SERVER['HTTP_HOST'];
$request_uri = $_SERVER['REQUEST_URI'];

// Determine which system to load based on subdomain or path
if (strpos($current_host, 'pos.') === 0 || strpos($request_uri, '/pos') !== false) {
    // Load POS System
    header('Location: pos/index.php');
    exit();
} elseif (strpos($current_host, 'portal.') === 0 || strpos($request_uri, '/erp') !== false) {
    // Load ERP System  
    header('Location: erp/index.php');
    exit();
} else {
    // Show system selection page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo SYSTEM_NAME; ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            primary: '#2563eb',
                            secondary: '#64748b',
                            success: '#059669',
                            warning: '#d97706',
                            danger: '#dc2626'
                        }
                    }
                }
            }
        </script>
    </head>
    <body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
        <div class="container mx-auto px-4 py-8">
            <!-- Header -->
            <div class="text-center mb-12">
                <div class="flex justify-center items-center mb-6">
                    <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center shadow-lg">
                        <i class="fas fa-utensils text-white text-2xl"></i>
                    </div>
                </div>
                <h1 class="text-4xl font-bold text-gray-800 mb-2"><?php echo BUSINESS_NAME; ?></h1>
                <p class="text-xl text-gray-600 mb-2"><?php echo BUSINESS_NAME_AR; ?></p>
                <p class="text-gray-500"><?php echo BUSINESS_ADDRESS; ?></p>
            </div>

            <!-- System Selection Cards -->
            <div class="max-w-4xl mx-auto">
                <div class="grid md:grid-cols-2 gap-8">
                    <!-- POS System Card -->
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:scale-105">
                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6 text-white">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-2xl font-bold mb-2">POS System</h2>
                                    <p class="text-blue-100">Point of Sale Interface</p>
                                </div>
                                <i class="fas fa-cash-register text-4xl text-blue-100"></i>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4 mb-6">
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-shopping-cart w-5 mr-3 text-primary"></i>
                                    <span>Sales & Transactions</span>
                                </div>
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-receipt w-5 mr-3 text-primary"></i>
                                    <span>Receipt Generation</span>
                                </div>
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-clock w-5 mr-3 text-primary"></i>
                                    <span>Shift Management</span>
                                </div>
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-box w-5 mr-3 text-primary"></i>
                                    <span>Inventory Control</span>
                                </div>
                            </div>
                            <a href="pos/index.php" class="w-full bg-primary hover:bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-300 flex items-center justify-center">
                                <i class="fas fa-sign-in-alt mr-2"></i>
                                Access POS System
                            </a>
                        </div>
                    </div>

                    <!-- ERP System Card -->
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:scale-105">
                        <div class="bg-gradient-to-r from-indigo-500 to-purple-600 p-6 text-white">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-2xl font-bold mb-2">ERP Portal</h2>
                                    <p class="text-indigo-100">Management Dashboard</p>
                                </div>
                                <i class="fas fa-chart-line text-4xl text-indigo-100"></i>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4 mb-6">
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-chart-bar w-5 mr-3 text-indigo-500"></i>
                                    <span>Reports & Analytics</span>
                                </div>
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-users w-5 mr-3 text-indigo-500"></i>
                                    <span>HR Management</span>
                                </div>
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-truck w-5 mr-3 text-indigo-500"></i>
                                    <span>Purchase Management</span>
                                </div>
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-cog w-5 mr-3 text-indigo-500"></i>
                                    <span>System Settings</span>
                                </div>
                            </div>
                            <a href="erp/index.php" class="w-full bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-300 flex items-center justify-center">
                                <i class="fas fa-sign-in-alt mr-2"></i>
                                Access ERP Portal
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Status -->
            <div class="max-w-2xl mx-auto mt-12">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-server mr-2 text-primary"></i>
                        System Status
                    </h3>
                    <div class="grid md:grid-cols-3 gap-4">
                        <?php
                        $status_items = [
                            'database' => ['Database', 'fas fa-database'],
                            'uploads_writable' => ['File System', 'fas fa-folder'],
                            'session_active' => ['Sessions', 'fas fa-user-check']
                        ];
                        
                        foreach ($status_items as $key => $item) {
                            $is_ok = $SYSTEM_STATUS[$key];
                            $status_class = $is_ok ? 'text-success bg-green-50' : 'text-danger bg-red-50';
                            $status_icon = $is_ok ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle';
                            echo "<div class='flex items-center justify-between p-3 rounded-lg {$status_class}'>
                                    <div class='flex items-center'>
                                        <i class='{$item[1]} mr-2'></i>
                                        <span class='font-medium'>{$item[0]}</span>
                                    </div>
                                    <i class='{$status_icon}'></i>
                                  </div>";
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center mt-12">
                <p class="text-gray-500 mb-2">
                    <?php echo SYSTEM_NAME; ?> v<?php echo SYSTEM_VERSION; ?>
                </p>
                <p class="text-sm text-gray-400">
                    © <?php echo date('Y'); ?> <?php echo BUSINESS_NAME; ?>. All rights reserved.
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>