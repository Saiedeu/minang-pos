<?php
/**
 * ERP System - Stock Control
 * Handle stock adjustments, movements, and inventory control
 */

define('MINANG_SYSTEM', true);
require_once '../../config/config.php';
require_once '../includes/auth-check.php';

$product = new Product();
$db = Database::getInstance();

$action = $_GET['action'] ?? 'list';
$productId = $_GET['product_id'] ?? 0;
$error = '';
$success = '';

// Handle stock adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'adjust') {
    $productId = intval($_POST['product_id']);
    $adjustmentType = $_POST['adjustment_type']; // 'set', 'add', 'subtract'
    $quantity = floatval($_POST['quantity']);
    $reason = sanitize($_POST['reason'] ?? '');
    
    if ($productId && $quantity >= 0 && $reason) {
        $currentProduct = $product->getProductById($productId);
        if ($currentProduct) {
            $newQuantity = $currentProduct['quantity'];
            
            switch ($adjustmentType) {
                case 'set':
                    $newQuantity = $quantity;
                    break;
                case 'add':
                    $newQuantity += $quantity;
                    break;
                case 'subtract':
                    $newQuantity = max(0, $newQuantity - $quantity);
                    break;
            }
            
            $result = $product->updateStock($productId, $newQuantity, $reason);
            if ($result['success']) {
                $success = 'Stock adjusted successfully';
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'Product not found';
        }
    } else {
        $error = 'Please fill all required fields';
    }
}

// Get products with stock information
$products = $db->fetchAll("
    SELECT p.*, c.name as category_name,
           (SELECT COUNT(*) FROM stock_movements WHERE product_id = p.id) as movement_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.is_active = 1
    ORDER BY p.quantity ASC, p.name
");

// Get recent stock movements
$recentMovements = $product->getStockMovements(null, 50);

// Get current product for adjustment
$currentProduct = null;
if ($productId) {
    $currentProduct = $product->getProductById($productId);
}

$pageTitle = 'Stock Control';
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
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="ml-64 min-h-screen">
        <?php include '../includes/header.php'; ?>
        
        <main class="p-8">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Stock Control</h1>
                    <p class="text-gray-600">Monitor and adjust inventory stock levels</p>
                </div>
                <button onclick="showBulkAdjustment()" class="bg-warning hover:bg-yellow-600 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-edit mr-2"></i>Bulk Adjustment
                </button>
            </div>

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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Products Stock Status -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-lg">
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-xl font-semibold text-gray-800">Product Stock Status</h2>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <th class="px-6 py-4">Product</th>
                                        <th class="px-6 py-4">Category</th>
                                        <th class="px-6 py-4">Current Stock</th>
                                        <th class="px-6 py-4">Reorder Level</th>
                                        <th class="px-6 py-4">Status</th>
                                        <th class="px-6 py-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($products as $prod): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4">
                                            <div class="font-semibold text-gray-900"><?php echo $prod['name']; ?></div>
                                            <div class="text-sm text-gray-500">Code: <?php echo $prod['code']; ?></div>
                                        </td>
                                        <td class="px-6 py-4 text-gray-600"><?php echo $prod['category_name']; ?></td>
                                        <td class="px-6 py-4">
                                            <span class="font-bold <?php 
                                                echo $prod['quantity'] <= 0 ? 'text-red-600' : 
                                                    ($prod['quantity'] <= $prod['reorder_level'] ? 'text-orange-600' : 'text-green-600'); 
                                            ?>">
                                                <?php echo $prod['quantity']; ?> <?php echo $prod['unit']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-600"><?php echo $prod['reorder_level']; ?> <?php echo $prod['unit']; ?></td>
                                        <td class="px-6 py-4">
                                            <?php if ($prod['quantity'] <= 0): ?>
                                            <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full font-medium">OUT OF STOCK</span>
                                            <?php elseif ($prod['quantity'] <= $prod['reorder_level']): ?>
                                            <span class="px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded-full font-medium">LOW STOCK</span>
                                            <?php else: ?>
                                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full font-medium">IN STOCK</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex space-x-2">
                                                <button onclick="showStockAdjustment(<?php echo $prod['id']; ?>, '<?php echo addslashes($prod['name']); ?>', <?php echo $prod['quantity']; ?>)" 
                                                        class="text-primary hover:text-blue-800 text-sm" title="Adjust Stock">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="viewMovements(<?php echo $prod['id']; ?>)" 
                                                        class="text-green-600 hover:text-green-800 text-sm" title="View Movements">
                                                    <i class="fas fa-history"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Stock Movements -->
                <div>
                    <div class="bg-white rounded-xl shadow-lg">
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-800">Recent Stock Movements</h2>
                        </div>
                        
                        <div class="p-6">
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                <?php foreach ($recentMovements as $movement): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div>
                                        <h4 class="font-semibold text-gray-800 text-sm"><?php echo $movement['product_name']; ?></h4>
                                        <p class="text-xs text-gray-600"><?php echo $movement['reason']; ?></p>
                                        <p class="text-xs text-gray-500"><?php echo formatDateTime($movement['created_at']); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold <?php echo $movement['type'] === 'IN' ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $movement['type'] === 'IN' ? '+' : '-'; ?><?php echo $movement['quantity']; ?>
                                        </div>
                                        <div class="text-xs text-gray-500"><?php echo strtoupper($movement['reference_type'] ?? 'MANUAL'); ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Stock Adjustment Modal -->
    <div id="stock-adjustment-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Adjust Stock</h3>
                
                <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                    <h4 id="adjustment-product-name" class="font-semibold text-gray-800"></h4>
                    <p class="text-sm text-gray-600">Current Stock: <span id="adjustment-current-stock" class="font-semibold"></span></p>
                </div>

                <form method="POST" action="?action=adjust">
                    <input type="hidden" name="product_id" id="adjustment-product-id">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Adjustment Type</label>
                            <select name="adjustment_type" id="adjustment-type" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="set">Set Exact Quantity</option>
                                <option value="add">Add to Stock</option>
                                <option value="subtract">Remove from Stock</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Quantity</label>
                            <input type="number" name="quantity" step="0.01" min="0" required
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
                                   placeholder="Enter quantity">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Reason *</label>
                            <select name="reason" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary" onchange="handleReasonChange(this)">
                                <option value="">Select reason</option>
                                <option value="Physical count adjustment">Physical count adjustment</option>
                                <option value="Wastage/Damage">Wastage/Damage</option>
                                <option value="Theft/Loss">Theft/Loss</option>
                                <option value="Expired products">Expired products</option>
                                <option value="Return to supplier">Return to supplier</option>
                                <option value="Kitchen usage">Kitchen usage</option>
                                <option value="Custom">Custom reason...</option>
                            </select>
                            
                            <textarea name="custom_reason" id="custom-reason" rows="2" 
                                      class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary mt-2 hidden"
                                      placeholder="Enter custom reason"></textarea>
                        </div>
                    </div>

                    <div class="flex space-x-3 mt-6">
                        <button type="button" onclick="hideStockAdjustment()" 
                                class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold py-3 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-primary hover:bg-blue-600 text-white font-semibold py-3 rounded-lg transition-colors">
                            <i class="fas fa-save mr-2"></i>Adjust Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showStockAdjustment(productId, productName, currentStock) {
            document.getElementById('adjustment-product-id').value = productId;
            document.getElementById('adjustment-product-name').textContent = productName;
            document.getElementById('adjustment-current-stock').textContent = currentStock;
            document.getElementById('stock-adjustment-modal').classList.remove('hidden');
        }

        function hideStockAdjustment() {
            document.getElementById('stock-adjustment-modal').classList.add('hidden');
            document.querySelector('form').reset();
            document.getElementById('custom-reason').classList.add('hidden');
        }

        function handleReasonChange(select) {
            const customReason = document.getElementById('custom-reason');
            if (select.value === 'Custom') {
                customReason.classList.remove('hidden');
                customReason.required = true;
            } else {
                customReason.classList.add('hidden');
                customReason.required = false;
            }
        }

        function viewMovements(productId) {
            window.open(`../reports/stock-movements.php?product_id=${productId}`, '_blank');
        }

        function showBulkAdjustment() {
            window.open('bulk-adjustment.php', '_blank');
        }
    </script>
</body>
</html>