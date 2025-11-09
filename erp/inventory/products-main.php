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
}

// Get data for display
$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
$products = $product->getAllProducts();
$currentProduct = null;

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
                    <a href="categories.php" class="bg-secondary hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
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
            <!-- Products List -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-800">All Products</h2>
                        <div class="flex items-center space-x-4">
                            <input type="text" id="search" placeholder="Search products..." 
                                   class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                            <select id="category-filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full" id="products-table">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Photo</th>
                                <th class="px-6 py-4">Code</th>
                                <th class="px-6 py-4">Name</th>
                                <th class="px-6 py-4">Arabic Name</th>
                                <th class="px-6 py-4">Category</th>
                                <th class="px-6 py-4">Cost Price</th>
                                <th class="px-6 py-4">Sell Price</th>
                                <th class="px-6 py-4">Stock</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($products as $prod): ?>
                            <tr class="hover:bg-gray-50">
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
            </div>

            <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- Add/Edit Product Form -->
            <div class="max-w-4xl">
                <div class="bg-white rounded-xl shadow-lg p-8">
                    <div class="mb-6">
                        <div class="flex items-center justify-between">
                            <h2 class="text-2xl font-semibold text-gray-800">
                                <?php echo $action === 'add' ? 'Add New Product' : 'Edit Product'; ?>
                            </h2>
                            <a href="products.php" class="text-gray-500 hover:text-gray-700">
                                <i class="fas fa-times text-xl"></i>
                            </a>
                        </div>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
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
                            <div class="flex items-center space-x-4">
                                <?php if ($action === 'edit' && $currentProduct['photo']): ?>
                                <div class="w-20 h-20 bg-gray-200 rounded-lg overflow-hidden">
                                    <img src="../../assets/uploads/products/<?php echo $currentProduct['photo']; ?>" 
                                         alt="Current photo" class="w-full h-full object-cover">
                                </div>
                                <?php endif; ?>
                                <div class="flex-1">
                                    <input type="file" name="photo" accept="image/*" 
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                                    <p class="text-xs text-gray-500 mt-1">Recommended: 300x300px, max 5MB</p>
                                </div>
                            </div>
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
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Search and filter functionality
        document.getElementById('search').addEventListener('input', filterProducts);
        document.getElementById('category-filter').addEventListener('change', filterProducts);

        function filterProducts() {
            const search = document.getElementById('search').value.toLowerCase();
            const categoryFilter = document.getElementById('category-filter').value;
            const rows = document.querySelectorAll('#products-table tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const categoryCell = row.cells[4].textContent;
                const matchesSearch = text.includes(search);
                const matchesCategory = !categoryFilter || categoryCell.includes(categoryFilter);
                
                row.style.display = (matchesSearch && matchesCategory) ? '' : 'none';
            });
        }

        // Delete product
        async function deleteProduct(id) {
            if (confirm('Are you sure you want to delete this product?')) {
                try {
                    const response = await fetch(`?ajax=delete&id=${id}`);
                    const result = await response.json();
                    
                    if (result.success) {
                        location.reload();
                    } else {
                        alert('Failed to delete product');
                    }
                } catch (error) {
                    alert('An error occurred');
                }
            }
        }

        // Auto-generate code based on category
        document.querySelector('select[name="category_id"]')?.addEventListener('change', function() {
            if (this.value && document.querySelector('input[name="code"]').value === '') {
                // Could implement auto-code generation here
            }
        });
    </script>
</body>
</html>