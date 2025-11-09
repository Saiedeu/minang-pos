<?php
/**
 * POS System - Held Orders Management
 * View and manage held/parked orders
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';
require_once 'includes/auth-check.php';

$user = User::getCurrentUser();
$sale = new Sale();
$error = '';
$success = '';

// Handle order actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $heldId = intval($_POST['held_id'] ?? 0);
    
    if ($action === 'resume' && $heldId) {
        $result = $sale->resumeHeldOrder($heldId);
        if ($result['success']) {
            // Store order data in session for POS
            $_SESSION['resumed_order'] = $result['order'];
            header('Location: sales.php?resumed=1');
            exit();
        } else {
            $error = $result['message'];
        }
    }
    
    if ($action === 'delete' && $heldId) {
        $deleted = $sale->deleteHeldOrder($heldId);
        if ($deleted) {
            $success = 'Held order deleted successfully';
        } else {
            $error = 'Failed to delete held order';
        }
    }
}

// Get held orders
$heldOrders = $sale->getHeldOrders($user['id']);

$pageTitle = 'Held Orders';
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
                        <h1 class="text-2xl font-bold text-white">Held Orders</h1>
                        <p class="text-blue-100">Manage parked and held orders</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="sales.php" class="bg-white bg-opacity-20 text-white px-4 py-2 rounded-lg font-medium hover:bg-opacity-30 transition-colors">
                        <i class="fas fa-plus mr-2"></i>New Order
                    </a>
                    <span class="bg-yellow-500 text-yellow-100 px-3 py-1 rounded-full text-sm font-semibold">
                        <?php echo count($heldOrders); ?> held
                    </span>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Alerts -->
        <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
            <p class="text-red-700"><?php echo $error; ?></p>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
            <p class="text-green-700"><?php echo $success; ?></p>
        </div>
        <?php endif; ?>

        <?php if (empty($heldOrders)): ?>
        <!-- No Held Orders -->
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <div class="w-24 h-24 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-pause text-yellow-500 text-4xl"></i>
            </div>
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">No Held Orders</h2>
            <p class="text-gray-600 mb-8">You don't have any held orders at the moment.</p>
            <a href="sales.php" class="bg-primary hover:bg-blue-600 text-white font-semibold py-3 px-8 rounded-lg transition-colors">
                <i class="fas fa-plus mr-2"></i>Create New Order
            </a>
        </div>

        <?php else: ?>
        <!-- Held Orders Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($heldOrders as $order): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                <div class="p-6">
                    <!-- Order Header -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-pause text-yellow-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-800"><?php echo $order['order_number']; ?></h3>
                                <p class="text-sm text-gray-500">
                                    <?php 
                                        $orderTypes = [1 => 'Dine-In', 2 => 'Take Away', 3 => 'Delivery'];
                                        echo $orderTypes[$order['order_type']] ?? 'Unknown'; 
                                    ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="text-right">
                            <div class="text-lg font-bold text-primary"><?php echo formatCurrency($order['total']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo date('H:i', strtotime($order['held_at'])); ?></div>
                        </div>
                    </div>

                    <!-- Customer Info -->
                    <?php if ($order['customer_name'] || $order['table_number']): ?>
                    <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                        <?php if ($order['customer_name']): ?>
                        <div class="text-sm"><strong>Customer:</strong> <?php echo $order['customer_name']; ?></div>
                        <?php endif; ?>
                        <?php if ($order['customer_phone']): ?>
                        <div class="text-sm"><strong>Phone:</strong> <?php echo $order['customer_phone']; ?></div>
                        <?php endif; ?>
                        <?php if ($order['table_number']): ?>
                        <div class="text-sm"><strong>Table:</strong> <?php echo $order['table_number']; ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Order Items -->
                    <div class="mb-4">
                        <h4 class="font-semibold text-gray-700 mb-2">Order Items</h4>
                        <div class="space-y-1">
                            <?php foreach ($order['items'] as $item): ?>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">
                                    <?php echo $item['quantity']; ?>x <?php echo $item['name']; ?>
                                </span>
                                <span class="font-semibold"><?php echo formatCurrency($item['quantity'] * $item['sell_price']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="border-t border-gray-200 mt-2 pt-2">
                            <div class="flex justify-between font-semibold">
                                <span>Total:</span>
                                <span class="text-primary"><?php echo formatCurrency($order['total']); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex space-x-2">
                        <form method="POST" class="flex-1">
                            <input type="hidden" name="action" value="resume">
                            <input type="hidden" name="held_id" value="<?php echo $order['id']; ?>">
                            <button type="submit" 
                                    class="w-full bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-lg transition-colors">
                                <i class="fas fa-play mr-2"></i>Resume
                            </button>
                        </form>
                        
                        <button onclick="showDeleteConfirm(<?php echo $order['id']; ?>)" 
                                class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>

                <!-- Hold Time Indicator -->
                <div class="px-6 py-3 bg-gradient-to-r from-yellow-100 to-orange-100 border-t">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-yellow-700">Held since:</span>
                        <span class="font-semibold text-orange-700"><?php echo formatDateTime($order['held_at']); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
            <div class="p-6">
                <div class="flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-500 text-2xl"></i>
                </div>
                
                <h3 class="text-xl font-semibold text-gray-800 text-center mb-4">Delete Held Order?</h3>
                <p class="text-gray-600 text-center mb-6">
                    This action cannot be undone. The held order will be permanently deleted.
                </p>

                <form method="POST" id="delete-form">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="held_id" id="delete-held-id">
                    
                    <div class="flex space-x-3">
                        <button type="button" onclick="hideDeleteConfirm()" 
                                class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold py-3 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-red-500 hover:bg-red-600 text-white font-semibold py-3 rounded-lg transition-colors">
                            <i class="fas fa-trash mr-2"></i>Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showDeleteConfirm(heldId) {
            document.getElementById('delete-held-id').value = heldId;
            document.getElementById('delete-modal').classList.remove('hidden');
        }

        function hideDeleteConfirm() {
            document.getElementById('delete-modal').classList.add('hidden');
        }

        // Auto-refresh every 30 seconds to show new held orders
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>