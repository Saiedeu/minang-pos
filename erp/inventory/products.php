[file name]: product.php
[file content begin]
<?php
/**
 * ERP System - Product Management
 * Manage all products, categories, and inventory
 */

define('MINANG_SYSTEM', true);
require_once '../../config/config.php';

// Check authentication
if (!User::isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

$user = User::getCurrentUser();
$db = Database::getInstance();
$product = new Product();

$action = $_GET['action'] ?? 'list';
$productId = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $productData = [
            'code' => sanitize($_POST['code'] ?? ''),
            'name' => sanitize($_POST['name'] ?? ''),
            'name_ar' => sanitize($_POST['name_ar'] ?? ''),
            'description' => sanitize($_POST['description'] ?? ''),
            'ingredients' => sanitize($_POST['ingredients'] ?? ''),
            'category_id' => intval($_POST['category_id'] ?? 0),
            'cost_price' => floatval($_POST['cost_price'] ?? 0),
            'sell_price' => floatval($_POST['sell_price'] ?? 0),
            'quantity' => floatval($_POST['quantity'] ?? 0),
            'unit' => sanitize($_POST['unit'] ?? 'PCS'),
            'reorder_level' => floatval($_POST['reorder_level'] ?? 5),
            'list_in_pos' => isset($_POST['list_in_pos']) ? 1 : 0
        ];
        
        // Handle photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../assets/uploads/products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = uniqid() . '_' . $_FILES['photo']['name'];
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
                $productData['photo'] = $fileName;
            }
        }
        
        $result = $product->createProduct($productData);
        if ($result['success']) {
            $success = 'Product created successfully';
            header('Location: products.php?success=created');
            exit();
        } else {
            $error = $result['message'];
        }
    }
    
    if ($action === 'edit' && $productId) {
        $productData = [
            'name' => sanitize($_POST['name'] ?? ''),
            'name_ar' => sanitize($_POST['name_ar'] ?? ''),
            'description' => sanitize($_POST['description'] ?? ''),
            'ingredients' => sanitize($_POST['ingredients'] ?? ''),
            'category_id' => intval($_POST['category_id'] ?? 0),
            'cost_price' => floatval($_POST['cost_price'] ?? 0),
            'sell_price' => floatval($_POST['sell_price'] ?? 0),
            'unit' => sanitize($_POST['unit'] ?? 'PCS'),
            'reorder_level' => floatval($_POST['reorder_level'] ?? 5),
            'list_in_pos' => isset($_POST['list_in_pos']) ? 1 : 0
        ];
        
        // Handle photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../assets/uploads/products/';
            $fileName = uniqid() . '_' . $_FILES['photo']['name'];
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
                $productData['photo'] = $fileName;
            }
        }
        
        $result = $product->updateProduct($productId, $productData);
        if ($result['success']) {
            $success = 'Product updated successfully';
        } else {
            $error = $result['message'];
        }
    }
    
    // Handle bulk operations
    if (isset($_POST['bulk_action']) && !empty($_POST['selected_products'])) {
        $selectedProducts = $_POST['selected_products'];
        $bulkAction = $_POST['bulk_action'];
        
        if ($bulkAction === 'delete') {
            $successCount = 0;
            foreach ($selectedProducts as $productId) {
                if ($product->deleteProduct($productId)) {
                    $successCount++;
                }
            }
            $success = "Successfully deleted $successCount products";
        } 
        elseif ($bulkAction === 'enable_pos') {
            $successCount = 0;
            foreach ($selectedProducts as $productId) {
                if ($product->updateProduct($productId, ['list_in_pos' => 1])) {
                    $successCount++;
                }
            }
            $success = "Successfully enabled POS listing for $successCount products";
        }
        elseif ($bulkAction === 'disable_pos') {
            $successCount = 0;
            foreach ($selectedProducts as $productId) {
                if ($product->updateProduct($productId, ['list_in_pos' => 0])) {
                    $successCount++;
                }
            }
            $success = "Successfully disabled POS listing for $successCount products";
        }
    }
}

// Get data for display
$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
$products = $product->getAllProducts();
$currentProduct = null;

// Handle sorting
$sort = $_GET['sort'] ?? 'name';
$order = $_GET['order'] ?? 'asc';
$categoryFilter = $_GET['category'] ?? '';

// Apply sorting
if ($sort && in_array($sort, ['name', 'code', 'category_name', 'cost_price', 'sell_price', 'quantity'])) {
    usort($products, function($a, $b) use ($sort, $order) {
        if ($order === 'asc') {
            return $a[$sort] <=> $b[$sort];
        } else {
            return $b[$sort] <=> $a[$sort];
        }
    });
}

// Apply category filter
if ($categoryFilter) {
    $products = array_filter($products, function($product) use ($categoryFilter) {
        return $product['category_id'] == $categoryFilter;
    });
}

if ($action === 'edit' && $productId) {
    $currentProduct = $product->getProductById($productId);
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'delete' && isset($_GET['id'])) {
        $deleted = $product->deleteProduct($_GET['id']);
        echo json_encode(['success' => $deleted]);
    }
    exit();
}

$pageTitle = $action === 'add' ? 'Add Product' : ($action === 'edit' ? 'Edit Product' : 'Products');
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
                    <h1 class="text-3xl font-bold text-gray-800"><?php echo $pageTitle; ?></h1>
                    <p class="text-gray-600">Manage your restaurant's product inventory</p>
                </div>
                
                <?php if ($action === 'list'): ?>
                <div class="flex space-x-3">
                    <a href="categories.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        <i class="fas fa-tags mr-2"></i>Manage Categories
                    </a>
                    <a href="?action=add" class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add New Product
                    </a>
                </div>
                <?php endif; ?>
            </div>

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

            <?php if ($action === 'list'): ?>
            <!-- Bulk Actions -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6" id="bulk-actions" style="display: none;">
                <div class="p-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <span id="selected-count" class="font-medium text-gray-700">0 products selected</span>
                            <button id="select-all" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Select All</button>
                            <button id="clear-selection" class="text-gray-600 hover:text-gray-800 text-sm font-medium">Clear Selection</button>
                        </div>
                        <div class="flex items-center space-x-3">
                            <form method="POST" id="bulk-action-form" class="flex items-center space-x-3">
                                <input type="hidden" name="selected_products" id="selected-products-input">
                                <select name="bulk_action" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                    <option value="">Bulk Actions</option>
                                    <option value="enable_pos">Enable POS Listing</option>
                                    <option value="disable_pos">Disable POS Listing</option>
                                    <option value="delete">Delete</option>
                                </select>
                                <button type="submit" class="px-4 py-2 bg-primary hover:bg-blue-600 text-white rounded-lg font-medium transition-colors">
                                    Apply
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products List -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                        <h2 class="text-xl font-semibold text-gray-800">All Products</h2>
                        <div class="flex flex-col md:flex-row items-start md:items-center space-y-3 md:space-y-0 md:space-x-4">
                            <input type="text" id="search" placeholder="Search products..." 
                                   class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary w-full md:w-auto">
                            <select id="category-filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary w-full md:w-auto">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $categoryFilter == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo $category['name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <a href="?action=list" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-medium transition-colors text-center">
                                Clear Filters
                            </a>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full" id="products-table">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-4 w-12">
                                    <input type="checkbox" id="select-all-checkbox" class="h-4 w-4 text-primary border-gray-300 rounded focus:ring-primary">
                                </th>
                                <th class="px-6 py-4">Photo</th>
                                <th class="px-6 py-4 cursor-pointer hover:bg-gray-100" onclick="sortTable('code')">
                                    Code 
                                    <?php if ($sort === 'code'): ?>
                                        <i class="fas fa-sort-<?php echo $order === 'asc' ? 'up' : 'down'; ?> ml-1"></i>
                                    <?php else: ?>
                                        <i class="fas fa-sort ml-1 text-gray-400"></i>
                                    <?php endif; ?>
                                </th>
                                <th class="px-6 py-4 cursor-pointer hover:bg-gray-100" onclick="sortTable('name')">
                                    Name 
                                    <?php if ($sort === 'name'): ?>
                                        <i class="fas fa-sort-<?php echo $order === 'asc' ? 'up' : 'down'; ?> ml-1"></i>
                                    <?php else: ?>
                                        <i class="fas fa-sort ml-1 text-gray-400"></i>
                                    <?php endif; ?>
                                </th>
                                <th class="px-6 py-4">Arabic Name</th>
                                <th class="px-6 py-4 cursor-pointer hover:bg-gray-100" onclick="sortTable('category_name')">
                                    Category 
                                    <?php if ($sort === 'category_name'): ?>
                                        <i class="fas fa-sort-<?php echo $order === 'asc' ? 'up' : 'down'; ?> ml-1"></i>
                                    <?php else: ?>
                                        <i class="fas fa-sort ml-1 text-gray-400"></i>
                                    <?php endif; ?>
                                </th>
                                <th class="px-6 py-4 cursor-pointer hover:bg-gray-100" onclick="sortTable('cost_price')">
                                    Cost Price 
                                    <?php if ($sort === 'cost_price'): ?>
                                        <i class="fas fa-sort-<?php echo $order === 'asc' ? 'up' : 'down'; ?> ml-1"></i>
                                    <?php else: ?>
                                        <i class="fas fa-sort ml-1 text-gray-400"></i>
                                    <?php endif; ?>
                                </th>
                                <th class="px-6 py-4 cursor-pointer hover:bg-gray-100" onclick="sortTable('sell_price')">
                                    Sell Price 
                                    <?php if ($sort === 'sell_price'): ?>
                                        <i class="fas fa-sort-<?php echo $order === 'asc' ? 'up' : 'down'; ?> ml-1"></i>
                                    <?php else: ?>
                                        <i class="fas fa-sort ml-1 text-gray-400"></i>
                                    <?php endif; ?>
                                </th>
                                <th class="px-6 py-4 cursor-pointer hover:bg-gray-100" onclick="sortTable('quantity')">
                                    Stock 
                                    <?php if ($sort === 'quantity'): ?>
                                        <i class="fas fa-sort-<?php echo $order === 'asc' ? 'up' : 'down'; ?> ml-1"></i>
                                    <?php else: ?>
                                        <i class="fas fa-sort ml-1 text-gray-400"></i>
                                    <?php endif; ?>
                                </th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($products as $prod): ?>
                            <tr class="hover:bg-gray-50" data-category="<?php echo $prod['category_id']; ?>">
                                <td class="px-6 py-4">
                                    <input type="checkbox" name="selected_products[]" value="<?php echo $prod['id']; ?>" 
                                           class="product-checkbox h-4 w-4 text-primary border-gray-300 rounded focus:ring-primary">
                                </td>
                                <td class="px-6 py-4">
                                    <div class="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center overflow-hidden">
                                        <?php if ($prod['photo']): ?>
                                            <img src="../../assets/uploads/products/<?php echo $prod['photo']; ?>" 
                                                 alt="<?php echo $prod['name']; ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <i class="fas fa-image text-gray-400"></i>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 font-medium text-gray-900"><?php echo $prod['code']; ?></td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900"><?php echo $prod['name']; ?></div>
                                    <?php if ($prod['description']): ?>
                                    <div class="text-xs text-gray-500 mt-1"><?php echo substr($prod['description'], 0, 50); ?>...</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-gray-600"><?php echo $prod['name_ar'] ?? '-'; ?></td>
                                <td class="px-6 py-4 text-gray-600"><?php echo $prod['category_name']; ?></td>
                                <td class="px-6 py-4 text-gray-600"><?php echo formatCurrency($prod['cost_price']); ?></td>
                                <td class="px-6 py-4 font-semibold text-primary"><?php echo formatCurrency($prod['sell_price']); ?></td>
                                <td class="px-6 py-4">
                                    <span class="<?php echo $prod['quantity'] <= $prod['reorder_level'] ? 'text-red-600 font-semibold' : 'text-gray-600'; ?>">
                                        <?php echo $prod['quantity']; ?> <?php echo $prod['unit']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-2">
                                        <?php if ($prod['list_in_pos']): ?>
                                        <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">POS</span>
                                        <?php endif; ?>
                                        <?php if ($prod['quantity'] <= $prod['reorder_level']): ?>
                                        <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full">Low</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <a href="?action=edit&id=<?php echo $prod['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-800 text-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="deleteProduct(<?php echo $prod['id']; ?>)" 
                                                class="text-red-600 hover:text-red-800 text-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <a href="stock-control.php?product_id=<?php echo $prod['id']; ?>" 
                                           class="text-green-600 hover:text-green-800 text-sm" title="Stock Control">
                                            <i class="fas fa-warehouse"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination (if needed) -->
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing <span class="font-medium">1</span> to <span class="font-medium"><?php echo count($products); ?></span> of <span class="font-medium"><?php echo count($products); ?></span> results
                        </div>
                        <div class="flex space-x-2">
                            <!-- Pagination buttons can be added here if needed -->
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- Add/Edit Product Form -->
            <div class="max-w-7xl mx-auto">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="flex items-center justify-between p-8 border-b border-gray-200">
                        <h2 class="text-2xl font-semibold text-gray-800">
                            <?php echo $action === 'add' ? 'Add New Product' : 'Edit Product'; ?>
                        </h2>
                        <a href="products.php" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times text-xl"></i>
                        </a>
                    </div>

                    <div class="p-8">
                        <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                            <!-- Left Column - Form Fields -->
                            <div class="lg:col-span-2 space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Product Code -->
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Product Code</label>
                                        <input type="text" name="code" 
                                               value="<?php echo $currentProduct['code'] ?? ''; ?>"
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                               placeholder="Auto-generated if empty" <?php echo $action === 'edit' ? 'readonly' : ''; ?>>
                                    </div>

                                    <!-- Category -->
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Category *</label>
                                        <select name="category_id" required
                                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" 
                                                    <?php echo ($currentProduct['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                                <?php echo $category['name']; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Product Name -->
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Product Name *</label>
                                        <input type="text" name="name" required
                                               value="<?php echo $currentProduct['name'] ?? ''; ?>"
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                               placeholder="Enter product name in English">
                                    </div>

                                    <!-- Arabic Name -->
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Arabic Name</label>
                                        <input type="text" name="name_ar" 
                                               value="<?php echo $currentProduct['name_ar'] ?? ''; ?>"
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                               placeholder="اسم المنتج بالعربية" dir="rtl">
                                    </div>
                                </div>

                                <!-- Description -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                                    <textarea name="description" rows="3"
                                              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                              placeholder="Product description for customers"><?php echo $currentProduct['description'] ?? ''; ?></textarea>
                                </div>

                                <!-- Ingredients -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Ingredients</label>
                                    <textarea name="ingredients" rows="3"
                                              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                              placeholder="List main ingredients (for allergen information)"><?php echo $currentProduct['ingredients'] ?? ''; ?></textarea>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <!-- Cost Price -->
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Cost Price (<?php echo CURRENCY_SYMBOL; ?>)</label>
                                        <input type="number" name="cost_price" step="0.01" min="0"
                                               value="<?php echo $currentProduct['cost_price'] ?? ''; ?>"
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                               placeholder="0.00">
                                    </div>

                                    <!-- Sell Price -->
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Sell Price (<?php echo CURRENCY_SYMBOL; ?>) *</label>
                                        <input type="number" name="sell_price" step="0.01" min="0" required
                                               value="<?php echo $currentProduct['sell_price'] ?? ''; ?>"
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                               placeholder="0.00">
                                    </div>

                                    <!-- Unit -->
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Unit</label>
                                        <select name="unit"
                                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                                            <option value="PCS" <?php echo ($currentProduct['unit'] ?? 'PCS') === 'PCS' ? 'selected' : ''; ?>>PCS</option>
                                            <option value="KG" <?php echo ($currentProduct['unit'] ?? '') === 'KG' ? 'selected' : ''; ?>>KG</option>
                                            <option value="LTR" <?php echo ($currentProduct['unit'] ?? '') === 'LTR' ? 'selected' : ''; ?>>Liters</option>
                                            <option value="BOX" <?php echo ($currentProduct['unit'] ?? '') === 'BOX' ? 'selected' : ''; ?>>BOX</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Initial Quantity -->
                                    <?php if ($action === 'add'): ?>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Initial Quantity</label>
                                        <input type="number" name="quantity" step="0.01" min="0"
                                               value="0"
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                               placeholder="0">
                                        <p class="text-xs text-gray-500 mt-1">Starting stock quantity</p>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Reorder Level -->
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Reorder Level</label>
                                        <input type="number" name="reorder_level" step="0.01" min="0"
                                               value="<?php echo $currentProduct['reorder_level'] ?? '5'; ?>"
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                               placeholder="5">
                                        <p class="text-xs text-gray-500 mt-1">Alert when stock falls below this level</p>
                                    </div>
                                </div>

                                <!-- Photo Upload -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Product Photo</label>
                                    <input type="file" name="photo" accept="image/*" 
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                                    <p class="text-xs text-gray-500 mt-1">Recommended: 300x300px, max 5MB</p>
                                </div>

                                <!-- Options -->
                                <div class="flex items-center space-x-6">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="list_in_pos" 
                                               <?php echo ($currentProduct['list_in_pos'] ?? 1) ? 'checked' : ''; ?>
                                               class="h-4 w-4 text-primary border-gray-300 rounded focus:ring-primary">
                                        <span class="ml-2 text-sm text-gray-700">List in POS</span>
                                    </label>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                                    <a href="products.php" 
                                       class="px-6 py-3 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold rounded-lg transition-colors">
                                        Cancel
                                    </a>
                                    <button type="submit" 
                                            class="px-6 py-3 bg-primary hover:bg-blue-600 text-white font-semibold rounded-lg transition-colors">
                                        <i class="fas fa-save mr-2"></i>
                                        <?php echo $action === 'add' ? 'Add Product' : 'Update Product'; ?>
                                    </button>
                                </div>
                            </div>

                            <!-- Right Column - Product Preview -->
                            <div class="lg:col-span-1">
                                <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4 text-center">Product Preview</h3>
                                    
                                    <!-- Photo Preview -->
                                    <div class="flex justify-center mb-6">
                                        <div class="w-32 h-32 bg-gradient-to-br from-blue-100 to-purple-100 rounded-xl flex items-center justify-center overflow-hidden border-4 border-white shadow-lg">
                                            <?php if (($currentProduct['photo'] ?? '') && $action === 'edit'): ?>
                                                <img src="../../assets/uploads/products/<?php echo $currentProduct['photo']; ?>" 
                                                     alt="Product Photo" class="w-full h-full object-cover" id="product-photo-preview">
                                            <?php else: ?>
                                                <i class="fas fa-image text-gray-400 text-4xl" id="product-icon"></i>
                                                <img src="" alt="Product Photo" class="w-full h-full object-cover hidden" id="product-photo-preview">
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Product Information -->
                                    <div class="space-y-4">
                                        <div class="text-center">
                                            <h4 class="font-bold text-xl text-gray-800" id="preview-name"><?php echo $currentProduct['name'] ?? 'Product Name'; ?></h4>
                                            <p class="text-sm text-gray-600" id="preview-name-ar"><?php echo $currentProduct['name_ar'] ?? 'اسم المنتج'; ?></p>
                                            <p class="text-xs text-gray-500 mt-1" id="preview-code"><?php echo $currentProduct['code'] ?? 'PROD-001'; ?></p>
                                        </div>

                                        <div class="space-y-3">
                                            <div class="flex items-center text-sm">
                                                <i class="fas fa-tag text-gray-400 w-5 mr-3"></i>
                                                <div>
                                                    <p class="text-gray-500">Category</p>
                                                    <p class="font-semibold text-gray-800" id="preview-category"><?php 
                                                        if ($action === 'edit' && $currentProduct['category_id']) {
                                                            foreach ($categories as $cat) {
                                                                if ($cat['id'] == $currentProduct['category_id']) {
                                                                    echo $cat['name'];
                                                                    break;
                                                                }
                                                            }
                                                        } else {
                                                            echo 'Uncategorized';
                                                        }
                                                    ?></p>
                                                </div>
                                            </div>

                                            <div class="flex items-center text-sm">
                                                <i class="fas fa-dollar-sign text-gray-400 w-5 mr-3"></i>
                                                <div>
                                                    <p class="text-gray-500">Cost Price</p>
                                                    <p class="font-semibold text-gray-800" id="preview-cost-price"><?php echo isset($currentProduct['cost_price']) ? formatCurrency($currentProduct['cost_price']) : formatCurrency(0); ?></p>
                                                </div>
                                            </div>

                                            <div class="flex items-center text-sm">
                                                <i class="fas fa-tag text-gray-400 w-5 mr-3"></i>
                                                <div>
                                                    <p class="text-gray-500">Sell Price</p>
                                                    <p class="font-semibold text-green-600" id="preview-sell-price"><?php echo isset($currentProduct['sell_price']) ? formatCurrency($currentProduct['sell_price']) : formatCurrency(0); ?></p>
                                                </div>
                                            </div>

                                            <div class="flex items-center text-sm">
                                                <i class="fas fa-cube text-gray-400 w-5 mr-3"></i>
                                                <div>
                                                    <p class="text-gray-500">Unit</p>
                                                    <p class="font-semibold text-gray-800" id="preview-unit"><?php echo $currentProduct['unit'] ?? 'PCS'; ?></p>
                                                </div>
                                            </div>

                                            <?php if ($action === 'edit'): ?>
                                            <div class="flex items-center text-sm">
                                                <i class="fas fa-boxes text-gray-400 w-5 mr-3"></i>
                                                <div>
                                                    <p class="text-gray-500">Current Stock</p>
                                                    <p class="font-semibold text-blue-600" id="preview-stock"><?php echo $currentProduct['quantity'] ?? '0'; ?> <?php echo $currentProduct['unit'] ?? 'PCS'; ?></p>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Status Badges -->
                                        <div class="flex justify-center space-x-2 mt-4">
                                            <?php if (($currentProduct['list_in_pos'] ?? 1)): ?>
                                            <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">POS Listed</span>
                                            <?php endif; ?>
                                            <?php if (($currentProduct['quantity'] ?? 0) <= ($currentProduct['reorder_level'] ?? 5)): ?>
                                            <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">Low Stock</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Bulk selection functionality
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('select-all-checkbox');
            const productCheckboxes = document.querySelectorAll('.product-checkbox');
            const bulkActionsSection = document.getElementById('bulk-actions');
            const selectedCountSpan = document.getElementById('selected-count');
            const selectedProductsInput = document.getElementById('selected-products-input');
            const selectAllBtn = document.getElementById('select-all');
            const clearSelectionBtn = document.getElementById('clear-selection');
            const bulkActionForm = document.getElementById('bulk-action-form');
            const searchInput = document.getElementById('search');
            const categoryFilter = document.getElementById('category-filter');

            // Update bulk actions visibility
            function updateBulkActions() {
                const selectedCount = document.querySelectorAll('.product-checkbox:checked').length;
                if (selectedCount > 0) {
                    bulkActionsSection.style.display = 'block';
                    selectedCountSpan.textContent = selectedCount + ' products selected';
                    
                    // Update hidden input with selected product IDs
                    const selectedIds = Array.from(document.querySelectorAll('.product-checkbox:checked'))
                        .map(cb => cb.value);
                    selectedProductsInput.value = JSON.stringify(selectedIds);
                } else {
                    bulkActionsSection.style.display = 'none';
                }
            }

            // Select all checkboxes
            selectAllCheckbox.addEventListener('change', function() {
                productCheckboxes.forEach(cb => {
                    cb.checked = selectAllCheckbox.checked;
                });
                updateBulkActions();
            });

            // Individual checkbox change
            productCheckboxes.forEach(cb => {
                cb.addEventListener('change', updateBulkActions);
            });

            // Select all button
            selectAllBtn.addEventListener('click', function() {
                productCheckboxes.forEach(cb => {
                    cb.checked = true;
                });
                selectAllCheckbox.checked = true;
                updateBulkActions();
            });

            // Clear selection button
            clearSelectionBtn.addEventListener('click', function() {
                productCheckboxes.forEach(cb => {
                    cb.checked = false;
                });
                selectAllCheckbox.checked = false;
                updateBulkActions();
            });

            // Confirm bulk delete
            bulkActionForm.addEventListener('submit', function(e) {
                const action = this.querySelector('select[name="bulk_action"]').value;
                if (action === 'delete') {
                    if (!confirm('Are you sure you want to delete the selected products? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                }
            });

            // Live search functionality
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('#products-table tbody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });

            // Category filter
            categoryFilter.addEventListener('change', function() {
                const categoryId = this.value;
                const rows = document.querySelectorAll('#products-table tbody tr');
                
                if (categoryId === '') {
                    rows.forEach(row => row.style.display = '');
                } else {
                    rows.forEach(row => {
                        const rowCategory = row.getAttribute('data-category');
                        if (rowCategory === categoryId) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                }
            });

            // Form preview updates
            const nameInput = document.querySelector('input[name="name"]');
            const nameArInput = document.querySelector('input[name="name_ar"]');
            const categorySelect = document.querySelector('select[name="category_id"]');
            const costPriceInput = document.querySelector('input[name="cost_price"]');
            const sellPriceInput = document.querySelector('input[name="sell_price"]');
            const unitSelect = document.querySelector('select[name="unit"]');
            const photoInput = document.querySelector('input[name="photo"]');

            if (nameInput) {
                nameInput.addEventListener('input', function() {
                    document.getElementById('preview-name').textContent = this.value || 'Product Name';
                });
            }

            if (nameArInput) {
                nameArInput.addEventListener('input', function() {
                    document.getElementById('preview-name-ar').textContent = this.value || 'اسم المنتج';
                });
            }

            if (categorySelect) {
                categorySelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    document.getElementById('preview-category').textContent = selectedOption.text || 'Uncategorized';
                });
            }

            if (costPriceInput) {
                costPriceInput.addEventListener('input', function() {
                    document.getElementById('preview-cost-price').textContent = formatCurrency(this.value || 0);
                });
            }

            if (sellPriceInput) {
                sellPriceInput.addEventListener('input', function() {
                    document.getElementById('preview-sell-price').textContent = formatCurrency(this.value || 0);
                });
            }

            if (unitSelect) {
                unitSelect.addEventListener('change', function() {
                    document.getElementById('preview-unit').textContent = this.value || 'PCS';
                <?php if ($action === 'edit'): ?>
                document.getElementById('preview-stock').textContent = '<?php echo $currentProduct['quantity'] ?? '0'; ?> ' + this.value;
                <?php endif; ?>
                });
            }

            if (photoInput) {
                photoInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const preview = document.getElementById('product-photo-preview');
                            const icon = document.getElementById('product-icon');
                            preview.src = e.target.result;
                            preview.classList.remove('hidden');
                            if (icon) icon.style.display = 'none';
                        }
                        reader.readAsDataURL(file);
                    }
                });
            }
        });

        // Sort table function
        function sortTable(column) {
            const urlParams = new URLSearchParams(window.location.search);
            const currentSort = urlParams.get('sort');
            const currentOrder = urlParams.get('order');
            
            let newOrder = 'asc';
            if (currentSort === column && currentOrder === 'asc') {
                newOrder = 'desc';
            }
            
            urlParams.set('sort', column);
            urlParams.set('order', newOrder);
            window.location.search = urlParams.toString();
        }

        // Delete product function
        function deleteProduct(id) {
            if (confirm('Are you sure you want to delete this product?')) {
                fetch('?ajax=delete&id=' + id)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error deleting product');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error deleting product');
                    });
            }
        }

        // Format currency for preview
        function formatCurrency(amount) {
            return '<?php echo CURRENCY_SYMBOL; ?>' + parseFloat(amount || 0).toFixed(2);
        }
    </script>
</body>
</html>
[file content end]