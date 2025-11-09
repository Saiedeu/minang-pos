<?php
/**
 * ERP System - Stock Control
 * Monitor and adjust inventory levels
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
    $newQuantity = floatval($_POST['new_quantity']);
    $reason = sanitize($_POST['reason'] ?? 'Manual adjustment');
    
    $result = $product->updateStock($productId, $newQuantity, $reason);
    if ($result['success']) {
        $success = "Stock updated successfully. Changed from {$result['old_quantity']} to {$result['new_quantity']}";
    } else {
        $error = $result['message'];
    }
}

// Get inventory data
$inventoryProducts = $product->getAllProducts();
$lowStockProducts = $product->getLowStockProducts();

// Get stock movements
$recentMovements = $product->getStockMovements($productId, 20);

// Filter for specific product if requested
$currentProduct = null;
if ($productId) {
    $currentProduct = $product->getProductById($productId);
    $productMovements = $product->getStockMovements($productId, 100);
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
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Stock Control</h1>
                    <p class="text-gray-600">Monitor inventory levels and stock movements</p>
                </div>
                <div class="flex space-x-3">
                    <button onclick="exportStockReport()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-download mr-2"></i>Export Report
                    </button>
                </div>
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

            <!-- Low Stock Alert -->
            <?php if (!empty($lowStockProducts)): ?>
            <div class="bg-red-50 border border-red-200 rounded-xl p-6 mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-red-800 flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Low Stock Alert
                    </h2>
                    <span class="bg-red-500 text-white px-3 py-1 rounded-full text-sm font-semibold">
                        <?php echo count($lowStockProducts); ?> items
                    </span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($lowStockProducts as $lowProduct): ?>
                    <div class="bg-white border border-red-300 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-semibold text-gray-800"><?php echo $lowProduct['name']; ?></h4>
                                <p class="text-sm text-gray-600"><?php echo $lowProduct['category_name']; ?></p>
                            </div>
                            <button onclick="showStockAdjustModal(<?php echo $lowProduct['id']; ?>, '<?php echo $lowProduct['name']; ?>', <?php echo $lowProduct['quantity']; ?>)"
                                    class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">
                                <i class="fas fa-plus mr-1"></i>Restock
                            </button>
                        </div>
                        <div class="mt-2 text-sm">
                            <span class="text-red-600 font-semibold">Current: <?php echo $lowProduct['quantity']; ?></span>
                            <span class="text-gray-500 ml-2">/ Reorder at: <?php echo $lowProduct['reorder_level']; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($currentProduct): ?>
            <!-- Individual Product Stock Control -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 bg-gray-200 rounded-lg flex items-center justify-center">
                            <?php if ($currentProduct['photo']): ?>
                            <img src="../../assets/uploads/products/<?php echo $currentProduct['photo']; ?>" 
                                 alt="<?php echo $currentProduct['name']; ?>" class="w-full h-full object-cover rounded-lg">
                            <?php else: ?>
                            <i class="fas fa-box text-gray-400 text-2xl"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800"><?php echo $currentProduct['name']; ?></h2>
                            <p class="text-gray-600"><?php echo $currentProduct['code']; ?> - <?php echo $currentProduct['category_name']; ?></p>
                        </div>
                    </div>
                    <a href="stock-control.php" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-2xl"></i>
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="text-center p-4 bg-blue-50 rounded-lg">
                        <div class="text-2xl font-bold text-blue-600"><?php echo $currentProduct['quantity']; ?></div>
                        <div class="text-sm text-blue-800">Current Stock</div>
                    </div>
                    <div class="text-center p-4 bg-orange-50 rounded-lg">
                        <div class="text-2xl font-bold text-orange-600"><?php echo $currentProduct['reorder_level']; ?></div>
                        <div class="text-sm text-orange-800">Reorder Level</div>
                    </div>
                    <div class="text-center p-4 bg-green-50 rounded-lg">
                        <div class="text-2xl font-bold text-green-600"><?php echo formatCurrency($currentProduct['cost_price']); ?></div>
                        <div class="text-sm text-green-800">Cost Price</div>
                    </div>
                    <div class="text-center p-4 bg-purple-50 rounded-lg">
                        <div class="text-2xl font-bold text-purple-600"><?php echo formatCurrency($currentProduct['sell_price']); ?></div>
                        <div class="text-sm text-purple-800">Sell Price</div>
                    </div>
                </div>

                <div class="flex space-x-4">
                    <button onclick="showStockAdjustModal(<?php echo $currentProduct['id']; ?>, '<?php echo $currentProduct['name']; ?>', <?php echo $currentProduct['quantity']; ?>)"
                            class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-edit mr-2"></i>Adjust Stock
                    </button>
                </div>
            </div>

            <!-- Stock Movement History -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">Stock Movement History</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Date</th>
                                <th class="px-6 py-4">Type</th>
                                <th class="px-6 py-4">Reference</th>
                                <th class="px-6 py-4">Quantity</th>
                                <th class="px-6 py-4">Reason</th>
                                <th class="px-6 py-4">Created By</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($productMovements as $movement): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-gray-900"><?php echo formatDateTime($movement['created_at']); ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $movement['type'] === 'IN' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <i class="fas fa-arrow-<?php echo $movement['type'] === 'IN' ? 'up' : 'down'; ?> mr-1"></i>
                                        <?php echo $movement['type']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <?php echo $movement['reference_type']; ?>
                                    <?php if ($movement['reference_id']): ?>
                                    <span class="text-xs">#<?php echo $movement['reference_id']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 font-semibold <?php echo $movement['type'] === 'IN' ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo $movement['type'] === 'IN' ? '+' : '-'; ?><?php echo $movement['quantity']; ?>
                                </td>
                                <td class="px-6 py-4 text-gray-600"><?php echo $movement['reason']; ?></td>
                                <td class="px-6 py-4 text-gray-600"><?php echo $movement['user_name']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php else: ?>
            <!-- All Products Stock Overview -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-800">Stock Overview</h2>
                        <input type="text" id="stock-search" placeholder="Search products..."
                               class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full" id="stock-table">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Product</th>
                                <th class="px-6 py-4">Category</th>
                                <th class="px-6 py-4">Current Stock</th>
                                <th class="px-6 py-4">Reorder Level</th>
                                <th class="px-6 py-4">Stock Value</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($inventoryProducts as $prod): ?>
                            <?php
                                $stockValue = $prod['quantity'] * $prod['cost_price'];
                                $isLowStock = $prod['quantity'] <= $prod['reorder_level'];
                                $isOutOfStock = $prod['quantity'] == 0;
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-gray-200 rounded-lg flex items-center justify-center">
                                            <?php if ($prod['photo']): ?>
                                            <img src="../../assets/uploads/products/<?php echo $prod['photo']; ?>" 
                                                 alt="<?php echo $prod['name']; ?>" class="w-full h-full object-cover rounded-lg">
                                            <?php else: ?>
                                            <i class="fas fa-box text-gray-400"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-900"><?php echo $prod['name']; ?></div>
                                            <div class="text-sm text-gray-500"><?php echo $prod['code']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-gray-600"><?php echo $prod['category_name']; ?></td>
                                <td class="px-6 py-4">
                                    <span class="font-semibold <?php echo $isOutOfStock ? 'text-red-600' : ($isLowStock ? 'text-orange-600' : 'text-gray-900'); ?>">
                                        <?php echo $prod['quantity']; ?> <?php echo $prod['unit']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-600"><?php echo $prod['reorder_level']; ?> <?php echo $prod['unit']; ?></td>
                                <td class="px-6 py-4 font-semibold text-primary"><?php echo formatCurrency($stockValue); ?></td>
                                <td class="px-6 py-4">
                                    <?php if ($isOutOfStock): ?>
                                    <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full font-semibold">Out of Stock</span>
                                    <?php elseif ($isLowStock): ?>
                                    <span class="px-2 py-1 text-xs bg-orange-100 text-orange-800 rounded-full font-semibold">Low Stock</span>
                                    <?php else: ?>
                                    <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <button onclick="showStockAdjustModal(<?php echo $prod['id']; ?>, '<?php echo $prod['name']; ?>', <?php echo $prod['quantity']; ?>)"
                                                class="text-blue-600 hover:text-blue-800 text-sm" title="Adjust Stock">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?product_id=<?php echo $prod['id']; ?>" 
                                           class="text-green-600 hover:text-green-800 text-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Stock Adjustment Modal -->
    <div id="stock-adjust-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Adjust Stock</h3>
                
                <form method="POST" action="?action=adjust">
                    <input type="hidden" name="product_id" id="adjust-product-id">
                    
                    <div class="space-y-4">
                        <div>
                            <div class="font-semibold text-gray-800" id="adjust-product-name"></div>
                            <div class="text-sm text-gray-500">Current Stock: <span id="adjust-current-stock"></span></div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">New Quantity</label>
                            <input type="number" name="new_quantity" step="0.01" min="0" required
                                   id="adjust-new-quantity"
                                   class="w-full p-3 text-xl text-center border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Reason</label>
                            <select name="reason" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="Stock received">Stock received</option>
                                <option value="Stock damaged">Stock damaged</option>
                                <option value="Stock expired">Stock expired</option>
                                <option value="Stock transfer">Stock transfer</option>
                                <option value="Manual correction">Manual correction</option>
                                <option value="Inventory count">Inventory count</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex space-x-3 mt-6">
                        <button type="button" onclick="hideStockAdjustModal()" 
                                class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold py-3 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-primary hover:bg-blue-600 text-white font-semibold py-3 rounded-lg transition-colors">
                            <i class="fas fa-save mr-2"></i>Update Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('stock-search')?.addEventListener('input', function() {
            const search = this.value.toLowerCase();
            const rows = document.querySelectorAll('#stock-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(search) ? '' : 'none';
            });
        });

        // Stock adjustment modal
        function showStockAdjustModal(productId, productName, currentStock) {
            document.getElementById('adjust-product-id').value = productId;
            document.getElementById('adjust-product-name').textContent = productName;
            document.getElementById('adjust-current-stock').textContent = currentStock;
            document.getElementById('adjust-new-quantity').value = currentStock;
            document.getElementById('stock-adjust-modal').classList.remove('hidden');
            
            setTimeout(() => {
                document.getElementById('adjust-new-quantity').focus();
                document.getElementById('adjust-new-quantity').select();
            }, 100);
        }

        function hideStockAdjustModal() {
            document.getElementById('stock-adjust-modal').classList.add('hidden');
        }

        function exportStockReport() {
            window.open('../api/reports.php?action=export_stock', '_blank');
        }
    </script>
</body>
</html>