<?php
/**
 * POS System - Inventory View
 * Quick inventory overview and stock control for POS users
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';
require_once 'includes/auth-check.php';

$user = User::getCurrentUser();
$product = new Product();
$db = Database::getInstance();

$search = $_GET['search'] ?? '';
$categoryId = $_GET['category'] ?? '';

// Get products based on search and filter
if ($search) {
    $products = $product->searchProducts($search, true);
} elseif ($categoryId) {
    $products = $product->getProductsByCategory($categoryId, true);
} else {
    $products = $product->getProductsForPOS();
}

// Get categories for filter
$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name");

// Get low stock products
$lowStockProducts = $product->getLowStockProducts();

// Get inventory statistics
$inventoryStats = $product->getProductStats();

$pageTitle = 'Inventory Overview';
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
                        <h1 class="text-2xl font-bold text-white">Inventory Overview</h1>
                        <p class="text-blue-100">Monitor stock levels and product availability</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <button onclick="syncInventory()" class="bg-white bg-opacity-20 text-white px-4 py-2 rounded-lg font-medium hover:bg-opacity-30 transition-colors">
                        <i class="fas fa-sync-alt mr-2"></i>Sync Data
                    </button>
                    <a href="../erp/inventory/products.php" class="bg-white bg-opacity-20 text-white px-4 py-2 rounded-lg font-medium hover:bg-opacity-30 transition-colors">
                        <i class="fas fa-cog mr-2"></i>Manage Products
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Inventory Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Products</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $inventoryStats['total']; ?></p>
                    </div>
                    <i class="fas fa-boxes text-3xl text-blue-500"></i>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">In POS</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo $inventoryStats['pos_active']; ?></p>
                    </div>
                    <i class="fas fa-cash-register text-3xl text-green-500"></i>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Low Stock</p>
                        <p class="text-2xl font-bold text-orange-600"><?php echo $inventoryStats['low_stock']; ?></p>
                    </div>
                    <i class="fas fa-exclamation-triangle text-3xl text-orange-500"></i>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Out of Stock</p>
                        <p class="text-2xl font-bold text-red-600"><?php echo $inventoryStats['out_of_stock']; ?></p>
                    </div>
                    <i class="fas fa-times-circle text-3xl text-red-500"></i>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Search Products</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Search by name, code, or ingredients..."
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                    <select name="category" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $categoryId == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo $category['name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="w-full bg-primary hover:bg-blue-600 text-white font-semibold py-3 rounded-lg transition-colors">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                </div>
                <div>
                    <?php if ($search || $categoryId): ?>
                    <a href="inventory.php" class="w-full bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold py-3 px-4 rounded-lg text-center block transition-colors">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Low Stock Alert -->
        <?php if (!empty($lowStockProducts) && !$search && !$categoryId): ?>
        <div class="bg-gradient-to-r from-orange-500 to-red-500 rounded-xl shadow-lg p-6 mb-8">
            <div class="flex items-center justify-between text-white">
                <div>
                    <h2 class="text-xl font-bold mb-2">Low Stock Alert!</h2>
                    <p class="text-orange-100"><?php echo count($lowStockProducts); ?> products need restocking</p>
                </div>
                <i class="fas fa-exclamation-triangle text-5xl text-orange-200"></i>
            </div>
            <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-3">
                <?php foreach (array_slice($lowStockProducts, 0, 4) as $product): ?>
                <div class="bg-white bg-opacity-20 rounded-lg p-3">
                    <h4 class="font-semibold text-white text-sm"><?php echo $product['name']; ?></h4>
                    <p class="text-orange-100 text-xs">Stock: <?php echo $product['quantity']; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Products Grid -->
        <div class="bg-white rounded-xl shadow-lg">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-800">
                        Products Inventory 
                        <span class="text-sm font-normal text-gray-500 ml-2">
                            (<?php echo count($products); ?> items)
                        </span>
                    </h2>
                    <div class="text-sm text-gray-500">
                        Last updated: <?php echo date('d/m/Y H:i'); ?>
                    </div>
                </div>
            </div>

            <div class="p-6">
                <?php if (empty($products)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-search text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">No Products Found</h3>
                    <p class="text-gray-500">
                        <?php echo $search ? "No products match your search criteria" : "No products available in inventory"; ?>
                    </p>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach ($products as $product): ?>
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 <?php echo $product['quantity'] <= 0 ? 'opacity-60' : ''; ?>">
                        <!-- Product Image -->
                        <div class="aspect-square bg-white rounded-lg mb-3 flex items-center justify-center overflow-hidden">
                            <?php if ($product['photo']): ?>
                                <img src="../assets/uploads/products/<?php echo $product['photo']; ?>" 
                                     alt="<?php echo $product['name']; ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="fas fa-image text-3xl text-gray-300"></i>
                            <?php endif; ?>
                        </div>

                        <!-- Product Info -->
                        <div class="space-y-2">
                            <div>
                                <h3 class="font-semibold text-gray-900 text-sm"><?php echo $product['name']; ?></h3>
                                <?php if ($product['name_ar']): ?>
                                <p class="text-xs text-gray-500" dir="rtl"><?php echo $product['name_ar']; ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">Code: <?php echo $product['code']; ?></span>
                                <span class="text-sm font-bold text-primary"><?php echo formatCurrency($product['sell_price']); ?></span>
                            </div>

                            <!-- Stock Status -->
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">Stock:</span>
                                <div class="flex items-center space-x-2">
                                    <span class="font-semibold <?php 
                                        echo $product['quantity'] <= 0 ? 'text-red-600' : 
                                            ($product['quantity'] <= $product['reorder_level'] ? 'text-orange-600' : 'text-green-600'); 
                                    ?>">
                                        <?php echo $product['quantity']; ?> <?php echo $product['unit']; ?>
                                    </span>
                                    
                                    <?php if ($product['quantity'] <= 0): ?>
                                    <span class="px-2 py-0.5 bg-red-100 text-red-800 text-xs rounded-full font-medium">OUT</span>
                                    <?php elseif ($product['quantity'] <= $product['reorder_level']): ?>
                                    <span class="px-2 py-0.5 bg-orange-100 text-orange-800 text-xs rounded-full font-medium">LOW</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Category -->
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">Category:</span>
                                <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded-full">
                                    <?php echo $product['category_name']; ?>
                                </span>
                            </div>

                            <!-- POS Status -->
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">POS Status:</span>
                                <span class="px-2 py-0.5 text-xs rounded-full <?php echo $product['list_in_pos'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'; ?>">
                                    <?php echo $product['list_in_pos'] ? 'Active' : 'Hidden'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
            <!-- Low Stock Products -->
            <?php if (!empty($lowStockProducts)): ?>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Low Stock Items</h3>
                    <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded-full text-xs font-medium">
                        <?php echo count($lowStockProducts); ?> items
                    </span>
                </div>
                <div class="space-y-3 max-h-64 overflow-y-auto">
                    <?php foreach ($lowStockProducts as $product): ?>
                    <div class="flex items-center justify-between p-3 bg-orange-50 rounded-lg border border-orange-200">
                        <div>
                            <h4 class="font-semibold text-gray-800 text-sm"><?php echo $product['name']; ?></h4>
                            <p class="text-xs text-gray-600"><?php echo $product['category_name']; ?></p>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-bold text-orange-600"><?php echo $product['quantity']; ?></div>
                            <div class="text-xs text-gray-500">of <?php echo $product['reorder_level']; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Inventory Value</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total Cost Value:</span>
                        <span class="font-bold text-green-600"><?php echo formatCurrency($inventoryStats['total_cost']); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total Sell Value:</span>
                        <span class="font-bold text-primary"><?php echo formatCurrency($inventoryStats['total_value']); ?></span>
                    </div>
                    <div class="border-t pt-3">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Potential Profit:</span>
                            <span class="font-bold text-success">
                                <?php echo formatCurrency($inventoryStats['total_value'] - $inventoryStats['total_cost']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="../erp/inventory/products.php?action=add" 
                       class="w-full bg-blue-500 hover:bg-blue-600 text-white p-3 rounded-lg font-medium text-center block transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add New Product
                    </a>
                    
                    <a href="../erp/purchases/invoices.php?action=add" 
                       class="w-full bg-green-500 hover:bg-green-600 text-white p-3 rounded-lg font-medium text-center block transition-colors">
                        <i class="fas fa-truck mr-2"></i>Record Purchase
                    </a>
                    
                    <a href="../erp/inventory/stock-control.php" 
                       class="w-full bg-purple-500 hover:bg-purple-600 text-white p-3 rounded-lg font-medium text-center block transition-colors">
                        <i class="fas fa-warehouse mr-2"></i>Stock Control
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Sync Loading Modal -->
    <div id="sync-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl p-8 max-w-sm w-full mx-4">
            <div class="text-center">
                <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-primary mx-auto mb-4"></div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Syncing Inventory Data</h3>
                <p class="text-gray-600">Please wait while we sync with the ERP system...</p>
            </div>
        </div>
    </div>

    <script>
        // Sync inventory data
        async function syncInventory() {
            document.getElementById('sync-modal').classList.remove('hidden');
            
            try {
                const response = await fetch('../api/sync.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'sync_inventory'
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert('Sync failed: ' + result.message);
                }
            } catch (error) {
                alert('Sync error: ' + error.message);
            }
            
            document.getElementById('sync-modal').classList.add('hidden');
        }

        // Auto-refresh every 5 minutes
        setInterval(() => {
            if (!document.querySelector('.modal')) {
                location.reload();
            }
        }, 300000);
    </script>
</body>
</html>