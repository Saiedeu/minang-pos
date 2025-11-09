<?php
/**
 * POS System - Settings Management
 * Configure POS system settings
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';
require_once 'includes/auth-check.php';

$user = User::getCurrentUser();
$db = Database::getInstance();
$error = '';
$success = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'receipt_printing' => isset($_POST['receipt_printing']) ? '1' : '0',
        'cash_drawer_auto_open' => isset($_POST['cash_drawer_auto_open']) ? '1' : '0',
        'customer_display' => isset($_POST['customer_display']) ? '1' : '0',
        'delivery_fee' => floatval($_POST['delivery_fee'] ?? 15.00),
        'low_stock_alert' => intval($_POST['low_stock_alert'] ?? 5),
        'customer_display_message' => sanitize($_POST['customer_display_message'] ?? 'Welcome to Langit Minang Restaurant!')
    ];
    
    $allUpdated = true;
    foreach ($settings as $key => $value) {
        $updated = $db->query(
            "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?",
            [$key, $value, $value]
        );
        if (!$updated) $allUpdated = false;
    }
    
    if ($allUpdated) {
        $success = 'Settings updated successfully';
    } else {
        $error = 'Failed to update some settings';
    }
}

// Get current settings
$currentSettings = [];
$settingsData = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
foreach ($settingsData as $setting) {
    $currentSettings[$setting['setting_key']] = $setting['setting_value'];
}

$pageTitle = 'POS Settings';
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
                        <h1 class="text-2xl font-bold text-white">POS Settings</h1>
                        <p class="text-blue-100">Configure system preferences</p>
                    </div>
                </div>
                <div class="text-white text-right">
                    <p class="font-semibold"><?php echo $user['name']; ?></p>
                    <p class="text-sm text-blue-100"><?php echo User::getRoleName($user['role']); ?></p>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Alerts -->
        <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
            <div class="flex">
                <i class="fas fa-exclamation-triangle text-red-500 mr-2 mt-0.5"></i>
                <p class="text-red-700"><?php echo $error; ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
            <div class="flex">
                <i class="fas fa-check-circle text-green-500 mr-2 mt-0.5"></i>
                <p class="text-green-700"><?php echo $success; ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Settings Form -->
        <form method="POST" class="space-y-8">
            <!-- Receipt Settings -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center mb-6">
                    <i class="fas fa-receipt text-2xl text-primary mr-3"></i>
                    <h2 class="text-xl font-semibold text-gray-800">Receipt Settings</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div>
                            <h3 class="font-semibold text-gray-800">Auto Print Receipts</h3>
                            <p class="text-sm text-gray-600">Automatically print receipts after payment</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="receipt_printing" class="sr-only peer" 
                                   <?php echo ($currentSettings['receipt_printing'] ?? '1') === '1' ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div>
                            <h3 class="font-semibold text-gray-800">Auto Open Cash Drawer</h3>
                            <p class="text-sm text-gray-600">Open cash drawer after every transaction</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="cash_drawer_auto_open" class="sr-only peer" 
                                   <?php echo ($currentSettings['cash_drawer_auto_open'] ?? '1') === '1' ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Customer Display Settings -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center mb-6">
                    <i class="fas fa-tv text-2xl text-primary mr-3"></i>
                    <h2 class="text-xl font-semibold text-gray-800">Customer Display</h2>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div>
                            <h3 class="font-semibold text-gray-800">Enable Customer Display</h3>
                            <p class="text-sm text-gray-600">Show order information to customers on external display</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="customer_display" class="sr-only peer" 
                                   <?php echo ($currentSettings['customer_display'] ?? '0') === '1' ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Customer Display Message</label>
                        <input type="text" name="customer_display_message" 
                               value="<?php echo $currentSettings['customer_display_message'] ?? 'Welcome to Langit Minang Restaurant!'; ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                               placeholder="Welcome message for customers">
                    </div>
                </div>
            </div>

            <!-- Order & Pricing Settings -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center mb-6">
                    <i class="fas fa-cog text-2xl text-primary mr-3"></i>
                    <h2 class="text-xl font-semibold text-gray-800">Order & Pricing Settings</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Delivery Fee (<?php echo CURRENCY_SYMBOL; ?>)</label>
                        <input type="number" name="delivery_fee" step="0.01" min="0"
                               value="<?php echo $currentSettings['delivery_fee'] ?? '15.00'; ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                               placeholder="15.00">
                        <p class="text-xs text-gray-500 mt-1">Default delivery charge for delivery orders</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Low Stock Alert Level</label>
                        <input type="number" name="low_stock_alert" min="0"
                               value="<?php echo $currentSettings['low_stock_alert'] ?? '5'; ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                               placeholder="5">
                        <p class="text-xs text-gray-500 mt-1">Alert when product stock falls below this level</p>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="flex justify-end">
                <button type="submit" class="bg-primary hover:bg-blue-600 text-white font-semibold py-3 px-8 rounded-lg transition-colors">
                    <i class="fas fa-save mr-2"></i>Save Settings
                </button>
            </div>
        </form>

        <!-- System Information -->
        <div class="bg-white rounded-xl shadow-lg p-6 mt-8">
            <div class="flex items-center mb-6">
                <i class="fas fa-info-circle text-2xl text-primary mr-3"></i>
                <h2 class="text-xl font-semibold text-gray-800">System Information</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">System Version:</span>
                        <span class="font-semibold"><?php echo SYSTEM_VERSION; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Database:</span>
                        <span class="font-semibold"><?php echo DB_NAME; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Timezone:</span>
                        <span class="font-semibold"><?php echo TIMEZONE; ?></span>
                    </div>
                </div>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Currency:</span>
                        <span class="font-semibold"><?php echo CURRENCY_CODE; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Current User:</span>
                        <span class="font-semibold"><?php echo $user['name']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Last Login:</span>
                        <span class="font-semibold"><?php echo date('d/m/Y H:i'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>