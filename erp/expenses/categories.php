<?php
/**
 * ERP System - Expense Categories
 * Manage expense categories for better organization
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
            'description' => sanitize($_POST['description'] ?? ''),
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        if (empty($categoryData['name'])) {
            $error = 'Category name is required';
        } else {
            $inserted = $db->insert('expense_categories', $categoryData);
            if ($inserted) {
                $success = 'Expense category created successfully';
                header('Location: categories.php?success=created');
                exit();
            } else {
                $error = 'Failed to create expense category';
            }
        }
    }
    
    if ($action === 'edit' && $categoryId) {
        $categoryData = [
            'name' => sanitize($_POST['name'] ?? ''),
            'description' => sanitize($_POST['description'] ?? '')
        ];
        
        if (empty($categoryData['name'])) {
            $error = 'Category name is required';
        } else {
            $updated = $db->update('expense_categories', $categoryData, 'id = ?', [$categoryId]);
            if ($updated) {
                $success = 'Expense category updated successfully';
            } else {
                $error = 'Failed to update expense category';
            }
        }
    }
}

// Get expense categories with expense counts
$categories = $db->fetchAll("
    SELECT ec.*, COUNT(e.id) as expense_count, COALESCE(SUM(e.amount), 0) as total_amount
    FROM expense_categories ec
    LEFT JOIN expenses e ON ec.id = e.category_id
    WHERE ec.is_active = 1
    GROUP BY ec.id
    ORDER BY ec.name
");

$currentCategory = null;
if ($action === 'edit' && $categoryId) {
    $currentCategory = $db->fetchOne("SELECT * FROM expense_categories WHERE id = ?", [$categoryId]);
}

// Handle AJAX delete
if (isset($_GET['ajax']) && $_GET['ajax'] === 'delete' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    
    // Check if category has expenses
    $expenseCount = $db->count('expenses', 'category_id = ?', [$_GET['id']]);
    
    if ($expenseCount > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete category with expenses']);
    } else {
        $deleted = $db->update('expense_categories', ['is_active' => 0], 'id = ?', [$_GET['id']]);
        echo json_encode(['success' => $deleted]);
    }
    exit();
}

$pageTitle = 'Expense Categories';
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
                    <h1 class="text-3xl font-bold text-gray-800">Expense Categories</h1>
                    <p class="text-gray-600">Organize business expenses into categories</p>
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
                                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-receipt text-red-600 text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800"><?php echo $category['name']; ?></h3>
                                    <p class="text-sm text-gray-500"><?php echo $category['expense_count']; ?> expenses</p>
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

                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="text-center">
                                <div class="text-sm text-gray-500">Total Amount</div>
                                <div class="text-lg font-bold text-primary"><?php echo formatCurrency($category['total_amount']); ?></div>
                            </div>
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
                            <?php echo $action === 'add' ? 'Add New Expense Category' : 'Edit Expense Category'; ?>
                        </h1>
                        <p class="text-gray-600">
                            <?php echo $action === 'add' ? 'Create a new expense category' : 'Update category information'; ?>
                        </p>
                    </div>
                    <a href="categories.php" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-2xl"></i>
                    </a>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-8">
                    <form method="POST" class="space-y-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Category Name *</label>
                            <input type="text" name="name" required
                                   value="<?php echo $currentCategory['name'] ?? ''; ?>"
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                   placeholder="e.g., Utilities, Marketing, Maintenance">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                            <textarea name="description" rows="4"
                                      class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                      placeholder="Describe what types of expenses belong to this category"><?php echo $currentCategory['description'] ?? ''; ?></textarea>
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
        // Delete category
        async function deleteCategory(id) {
            if (confirm('Are you sure you want to delete this expense category?')) {
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