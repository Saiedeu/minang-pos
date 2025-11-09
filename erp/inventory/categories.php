<?php
/**
 * ERP System - Category Management
 * Manage product categories for menu organization
 */

define('MINANG_SYSTEM', true);
require_once '../../config/config.php';
require_once '../includes/auth-check.php';

$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';
$categoryId = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $categoryData = [
            'name' => sanitize($_POST['name'] ?? ''),
            'name_ar' => sanitize($_POST['name_ar'] ?? ''),
            'description' => sanitize($_POST['description'] ?? ''),
            'icon' => sanitize($_POST['icon'] ?? 'fas fa-utensils'),
            'sort_order' => intval($_POST['sort_order'] ?? 0),
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        if (empty($categoryData['name'])) {
            $error = 'Category name is required';
        } else {
            $inserted = $db->insert('categories', $categoryData);
            if ($inserted) {
                $success = 'Category created successfully';
                header('Location: categories.php?success=created');
                exit();
            } else {
                $error = 'Failed to create category';
            }
        }
    }
    
    if ($action === 'edit' && $categoryId) {
        $categoryData = [
            'name' => sanitize($_POST['name'] ?? ''),
            'name_ar' => sanitize($_POST['name_ar'] ?? ''),
            'description' => sanitize($_POST['description'] ?? ''),
            'icon' => sanitize($_POST['icon'] ?? 'fas fa-utensils'),
            'sort_order' => intval($_POST['sort_order'] ?? 0)
        ];
        
        if (empty($categoryData['name'])) {
            $error = 'Category name is required';
        } else {
            $updated = $db->update('categories', $categoryData, 'id = ?', [$categoryId]);
            if ($updated) {
                $success = 'Category updated successfully';
            } else {
                $error = 'Failed to update category';
            }
        }
    }
}

// Get categories and current category for editing
$categories = $db->fetchAll("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY c.sort_order, c.name
");

$currentCategory = null;
if ($action === 'edit' && $categoryId) {
    $currentCategory = $db->fetchOne("SELECT * FROM categories WHERE id = ?", [$categoryId]);
}

// Handle AJAX delete
if (isset($_GET['ajax']) && $_GET['ajax'] === 'delete' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    
    // Check if category has products
    $productCount = $db->count('products', 'category_id = ? AND is_active = 1', [$_GET['id']]);
    
    if ($productCount > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete category with products']);
    } else {
        $deleted = $db->update('categories', ['is_active' => 0], 'id = ?', [$_GET['id']]);
        echo json_encode(['success' => $deleted]);
    }
    exit();
}

$pageTitle = 'Category Management';
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
            <?php if ($action === 'list'): ?>
            <!-- Categories List -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Categories</h1>
                    <p class="text-gray-600">Organize your menu items into categories</p>
                </div>
                <a href="?action=add" class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-plus mr-2"></i>Add New Category
                </a>
            </div>

            <!-- Categories Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($categories as $category): ?>
                <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 bg-primary bg-opacity-10 rounded-lg flex items-center justify-center">
                                    <i class="<?php echo $category['icon']; ?> text-primary text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800"><?php echo $category['name']; ?></h3>
                                    <?php if ($category['name_ar']): ?>
                                    <p class="text-sm text-gray-500" dir="rtl"><?php echo $category['name_ar']; ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="flex space-x-2">
                                <a href="?action=edit&id=<?php echo $category['id']; ?>" 
                                   class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="deleteCategory(<?php echo $category['id']; ?>)" 
                                        class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>

                        <?php if ($category['description']): ?>
                        <p class="text-gray-600 text-sm mb-4"><?php echo $category['description']; ?></p>
                        <?php endif; ?>

                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500"><?php echo $category['product_count']; ?> products</span>
                            <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-xs">
                                Order: <?php echo $category['sort_order']; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- Add/Edit Category Form -->
            <div class="max-w-2xl">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">
                            <?php echo $action === 'add' ? 'Add New Category' : 'Edit Category'; ?>
                        </h1>
                        <p class="text-gray-600">
                            <?php echo $action === 'add' ? 'Create a new menu category' : 'Update category information'; ?>
                        </p>
                    </div>
                    <a href="categories.php" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-2xl"></i>
                    </a>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-8">
                    <form method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Category Name *</label>
                                <input type="text" name="name" required
                                       value="<?php echo $currentCategory['name'] ?? ''; ?>"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                       placeholder="e.g., Main Dishes">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Arabic Name</label>
                                <input type="text" name="name_ar" 
                                       value="<?php echo $currentCategory['name_ar'] ?? ''; ?>"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                       placeholder="الأطباق الرئيسية" dir="rtl">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                            <textarea name="description" rows="3"
                                      class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                      placeholder="Brief description of this category"><?php echo $currentCategory['description'] ?? ''; ?></textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Icon Class</label>
                                <div class="relative">
                                    <input type="text" name="icon" 
                                           value="<?php echo $currentCategory['icon'] ?? 'fas fa-utensils'; ?>"
                                           class="w-full p-3 pl-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                           placeholder="fas fa-utensils" id="icon-input">
                                    <div class="absolute left-3 top-3 text-gray-400" id="icon-preview">
                                        <i class="<?php echo $currentCategory['icon'] ?? 'fas fa-utensils'; ?>"></i>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">FontAwesome icon class for visual representation</p>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Sort Order</label>
                                <input type="number" name="sort_order" min="0"
                                       value="<?php echo $currentCategory['sort_order'] ?? '0'; ?>"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                       placeholder="0">
                                <p class="text-xs text-gray-500 mt-1">Display order in POS menu (0 = first)</p>
                            </div>
                        </div>

                        <!-- Common Icons Selection -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Quick Icon Selection</label>
                            <div class="grid grid-cols-8 gap-2">
                                <?php
                                $commonIcons = [
                                    'fas fa-utensils', 'fas fa-leaf', 'fas fa-coffee', 'fas fa-ice-cream',
                                    'fas fa-star', 'fas fa-fire', 'fas fa-fish', 'fas fa-drumstick-bite',
                                    'fas fa-wine-glass', 'fas fa-birthday-cake', 'fas fa-apple-alt', 'fas fa-pepper-hot',
                                    'fas fa-cheese', 'fas fa-egg', 'fas fa-carrot', 'fas fa-bread-slice'
                                ];
                                
                                foreach ($commonIcons as $icon):
                                ?>
                                <button type="button" onclick="selectIcon('<?php echo $icon; ?>')" 
                                        class="p-3 border border-gray-300 rounded-lg hover:border-primary hover:bg-primary hover:text-white transition-colors">
                                    <i class="<?php echo $icon; ?>"></i>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                            <a href="categories.php" 
                               class="px-6 py-3 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold rounded-lg transition-colors">
                                Cancel
                            </a>
                            <button type="submit" 
                                    class="px-6 py-3 bg-primary hover:bg-blue-600 text-white font-semibold rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>
                                <?php echo $action === 'add' ? 'Add Category' : 'Update Category'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Icon selection and preview
        function selectIcon(iconClass) {
            document.getElementById('icon-input').value = iconClass;
            document.getElementById('icon-preview').innerHTML = '<i class="' + iconClass + '"></i>';
        }

        // Update icon preview on input change
        document.getElementById('icon-input').addEventListener('input', function() {
            document.getElementById('icon-preview').innerHTML = '<i class="' + this.value + '"></i>';
        });

        // Delete category
        async function deleteCategory(id) {
            if (confirm('Are you sure you want to delete this category? This action cannot be undone.')) {
                try {
                    const response = await fetch(`?ajax=delete&id=${id}`);
                    const result = await response.json();
                    
                    if (result.success) {
                        location.reload();
                    } else {
                        alert(result.message || 'Failed to delete category');
                    }
                } catch (error) {
                    alert('An error occurred while deleting category');
                }
            }
        }
    </script>
</body>
</html>