<?php
/**
 * POS System - Inventory View
 * Display products and stock levels for POS users
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';
require_once 'includes/auth-check.php';

$product = new Product();
$action = $_GET['action'] ?? 'list';

// Get products data
$products = $product->getAllProducts();
$categories = Database::getInstance()->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");

// Handle stock sync
if ($action === 'sync') {
    // Force refresh products data (simulating sync)
    $products = $product->getAllProducts();
    $success = 'Inventory synchronized successfully';
}

// Filter by category
$categoryFilter = $_GET['category'] ?? '';
if ($categoryFilter) {
    $products = array_filter($products, function($p) use ($categoryFilter) {
        return $p['category_id'] == $categoryFilter;
    });
}

// Search functionality
$search = $_GET['search'] ?? '';
if ($search) {
    $products = array_filter($products, function($p) use ($search) {
        return stripos($p['name'], $search) !== false || 
               stripos($p['code'], $search) !== false ||
               stripos($p['name_ar'], $search) !== false;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - <?php echo BUSINESS_NAME; ?></title>
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
    <header class="bg-gradient-to-r from-purple-500 to-purple-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-white hover:text-purple-200">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <div>
                        <h1 class="text-xl font-semibold text-white">Inventory Overview</h1>
                        <p class="text-purple-100 text-sm">Product stock levels and information</p>
                    </div>
                </div>

                <div class="flex items-center space-x-4">
                    <div class="text-white text-center">
                        <div id="current-time" class="text-lg font-semibold"></div>
                        <div id="current-date" class="text-xs text-purple-100"></div>
                    </div>
                    <a href="?action=sync" class="bg-white text-purple-600 px-4 py-2 rounded-lg font-medium hover:bg-purple-50 transition-colors">
                        <i class="fas fa-sync-alt mr-2"></i>Sync with ERP
                    </a>
                    <a href="../erp/inventory/products.php" class="bg-purple-700 text-white px-4 py-2 rounded-lg font-medium hover:bg-purple-800 transition-colors">
                        <i class="fas fa-external-link-alt mr-2"></i>Open ERP Inventory
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Success Message -->
        <?php if (isset($success)): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
            <p class="text-green-700"><?php echo $success; ?></p>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Search Products</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
                           placeholder="Search by name, code, or Arabic name">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                    <select name="category" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $categoryFilter == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo $category['name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Stock Status</label>
                    <select name="stock_status" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">All Stock Levels</option>
                        <option value="in_stock">In Stock</option>
                        <option value="low_stock">Low Stock</option>
                        <option value="out_of_stock">Out of Stock</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-primary hover:bg-blue-600 text-white font-semibold py-3 rounded-lg transition-colors">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Products Grid -->
        <div class="bg-white rounded-xl shadow-lg">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-800">Product Inventory</h2>
                    <div class="text-sm text-gray-600">
                        Showing <?php echo count($products); ?> products
                    </div>
                </div>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach ($products as $prod): ?>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 hover:shadow-md transition-shadow">
                        <!-- Product Image -->
                        <div class="aspect-square bg-white rounded-lg mb-3 flex items-center justify-center overflow-hidden border">
                            <?php if ($prod['photo']): ?>
                                <img src="../assets/uploads/products/<?php echo $prod['photo']; ?>" 
                                     alt="<?php echo $prod['name']; ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="fas fa-image text-4xl text-gray-300"></i>
                            <?php endif; ?>
                        </div>

                        <!-- Product Info -->
                        <div class="space-y-2">
                            <div>
                                <h3 class="font-semibold text-gray-900 text-sm"><?php echo $prod['name']; ?></h3>
                                <?php if ($prod['name_ar']): ?>
                                <p class="text-xs text-gray-500" dir="rtl"><?php echo $prod['name_ar']; ?></p>
                                <?php endif; ?>
                                <p class="text-xs text-gray-500">Code: <?php echo $prod['code']; ?></p>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-lg font-bold text-primary"><?php echo formatCurrency($prod['sell_price']); ?></span>
                                <div class="text-right">
                                    <span class="<?php echo $prod['quantity'] <= $prod['reorder_level'] ? 'text-red-600 font-bold' : 'text-green-600 font-semibold'; ?>">
                                        <?php echo $prod['quantity']; ?> <?php echo $prod['unit']; ?>
                                    </span>
                                    <p class="text-xs text-gray-500">in stock</p>
                                </div>
                            </div>

                            <!-- Status Indicators -->
                            <div class="flex items-center justify-between">
                                <div class="flex space-x-1">
                                    <?php if ($prod['list_in_pos']): ?>
                                    <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">POS</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($prod['quantity'] <= 0): ?>
                                    <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full">Out of Stock</span>
                                    <?php elseif ($prod['quantity'] <= $prod['reorder_level']): ?>
                                    <span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full">Low Stock</span>
                                    <?php endif; ?>
                                </div>
                                
                                <span class="text-xs text-gray-500"><?php echo $prod['category_name']; ?></span>
                            </div>

                            <!-- Quick Actions -->
                            <div class="flex space-x-2 pt-2">
                                <a href="../erp/inventory/products.php?action=edit&id=<?php echo $prod['id']; ?>" 
                                   class="flex-1 bg-blue-500 hover:bg-blue-600 text-white text-xs font-medium py-2 px-3 rounded text-center transition-colors">
                                    <i class="fas fa-edit mr-1"></i>Edit
                                </a>
                                <a href="../erp/inventory/stock-control.php?product_id=<?php echo $prod['id']; ?>" 
                                   class="flex-1 bg-green-500 hover:bg-green-600 text-white text-xs font-medium py-2 px-3 rounded text-center transition-colors">
                                    <i class="fas fa-warehouse mr-1"></i>Stock
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Empty State -->
                <?php if (empty($products)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Products Found</h3>
                    <p class="text-gray-500 mb-6">Try adjusting your search criteria or add new products</p>
                    <a href="../erp/inventory/products.php?action=add" 
                       class="bg-primary hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add New Product
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Update clock
        function updateClock() {
            const now = new Date();
            document.getElementById('current-time').textContent = now.toLocaleTimeString('en-US', {
                hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
            document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', {
                weekday: 'short', year: 'numeric', month: 'short', day: 'numeric'
            });
        }

        // Initialize
        updateClock();
        setInterval(updateClock, 1000);
    </script>
</body>
</html>