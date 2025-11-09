<?php
/**
 * ERP System - Expense Management
 * Track and manage all business expenses
 */

define('MINANG_SYSTEM', true);
require_once '../../config/config.php';
require_once '../includes/auth-check.php';

$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';
$expenseId = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $expenseData = [
            'category_id' => intval($_POST['category_id'] ?? 0),
            'description' => sanitize($_POST['description'] ?? ''),
            'amount' => floatval($_POST['amount'] ?? 0),
            'expense_date' => $_POST['expense_date'] ?? date('Y-m-d'),
            'payment_method' => intval($_POST['payment_method'] ?? 1),
            'notes' => sanitize($_POST['notes'] ?? ''),
            'created_by' => $_SESSION['user_id'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Handle receipt photo upload
        if (isset($_FILES['receipt_photo']) && $_FILES['receipt_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../assets/uploads/expense-receipts/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = 'EXP_' . date('Ymd_His') . '_' . $_FILES['receipt_photo']['name'];
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['receipt_photo']['tmp_name'], $uploadPath)) {
                $expenseData['receipt_photo'] = $fileName;
            }
        }
        
        if (empty($expenseData['description']) || $expenseData['amount'] <= 0) {
            $error = 'Description and amount are required';
        } else {
            $inserted = $db->insert('expenses', $expenseData);
            if ($inserted) {
                $success = 'Expense recorded successfully';
                header('Location: expenses.php?success=created');
                exit();
            } else {
                $error = 'Failed to record expense';
            }
        }
    }
}

// Get expense categories
$categories = $db->fetchAll("SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY name");

// Get expenses with filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$categoryFilter = $_GET['category_id'] ?? '';

$whereConditions = ['DATE(e.expense_date) BETWEEN ? AND ?'];
$params = [$startDate, $endDate];

if ($categoryFilter) {
    $whereConditions[] = 'e.category_id = ?';
    $params[] = $categoryFilter;
}

$expenses = $db->fetchAll("
    SELECT e.*, ec.name as category_name, u.name as created_by_name
    FROM expenses e
    LEFT JOIN expense_categories ec ON e.category_id = ec.id
    LEFT JOIN users u ON e.created_by = u.id
    WHERE " . implode(' AND ', $whereConditions) . "
    ORDER BY e.expense_date DESC, e.created_at DESC
", $params);

// Get expense statistics
$expenseStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_expenses,
        SUM(amount) as total_amount,
        SUM(CASE WHEN payment_method = 1 THEN amount ELSE 0 END) as cash_expenses,
        AVG(amount) as avg_expense
    FROM expenses e
    WHERE DATE(e.expense_date) BETWEEN ? AND ?
    " . ($categoryFilter ? "AND e.category_id = ?" : ""),
    array_filter([$startDate, $endDate, $categoryFilter ?: null])
);

$pageTitle = 'Expense Management';
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
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Expense Management</h1>
                    <p class="text-gray-600">Track and analyze business expenses</p>
                </div>
                <div class="flex space-x-3">
                    <a href="categories.php" class="bg-secondary hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-tags mr-2"></i>Manage Categories
                    </a>
                    <a href="?action=add" class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-plus mr-2"></i>Add New Expense
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
                        <input type="date" name="start_date" value="<?php echo $startDate; ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
                        <input type="date" name="end_date" value="<?php echo $endDate; ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                        <select name="category_id" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $categoryFilter == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo $category['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-primary hover:bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-red-100 text-sm">Total Expenses</p>
                            <p class="text-3xl font-bold"><?php echo $expenseStats['total_expenses'] ?? 0; ?></p>
                        </div>
                        <i class="fas fa-file-invoice-dollar text-4xl text-red-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm">Total Amount</p>
                            <p class="text-3xl font-bold"><?php echo formatCurrency($expenseStats['total_amount'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-money-bill-wave text-4xl text-orange-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-yellow-100 text-sm">Cash Expenses</p>
                            <p class="text-3xl font-bold"><?php echo formatCurrency($expenseStats['cash_expenses'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-coins text-4xl text-yellow-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm">Average</p>
                            <p class="text-3xl font-bold"><?php echo formatCurrency($expenseStats['avg_expense'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-calculator text-4xl text-purple-300"></i>
                    </div>
                </div>
            </div>

            <!-- Expenses Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">Expense Records</h2>
                    <p class="text-gray-600">Period: <?php echo formatDate($startDate); ?> - <?php echo formatDate($endDate); ?></p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Date</th>
                                <th class="px-6 py-4">Description</th>
                                <th class="px-6 py-4">Category</th>
                                <th class="px-6 py-4">Amount</th>
                                <th class="px-6 py-4">Payment</th>
                                <th class="px-6 py-4">Receipt</th>
                                <th class="px-6 py-4">Created By</th>
                                <th class="px-6 py-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($expenses as $expense): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium text-gray-900"><?php echo formatDate($expense['expense_date']); ?></td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900"><?php echo $expense['description']; ?></div>
                                    <?php if ($expense['notes']): ?>
                                    <div class="text-sm text-gray-500 mt-1"><?php echo $expense['notes']; ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">
                                        <?php echo $expense['category_name']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-semibold text-red-600"><?php echo formatCurrency($expense['amount']); ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full <?php 
                                        $paymentColors = [1 => 'bg-green-100 text-green-800', 2 => 'bg-blue-100 text-blue-800', 3 => 'bg-purple-100 text-purple-800'];
                                        echo $paymentColors[$expense['payment_method']] ?? 'bg-gray-100 text-gray-800';
                                    ?>">
                                        <?php
                                            $paymentMethods = [1 => 'Cash', 2 => 'Card', 3 => 'Credit'];
                                            echo $paymentMethods[$expense['payment_method']] ?? 'Unknown';
                                        ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if ($expense['receipt_photo']): ?>
                                    <button onclick="viewReceipt('<?php echo $expense['receipt_photo']; ?>')" 
                                            class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-image"></i>
                                    </button>
                                    <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-gray-600 text-sm"><?php echo $expense['created_by_name']; ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <a href="?action=edit&id=<?php echo $expense['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-800 text-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($expenses)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-file-invoice text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">No Expenses Found</h3>
                    <p class="text-gray-600">No expense records for the selected period</p>
                </div>
                <?php endif; ?>
            </div>

            <?php elseif ($action === 'add'): ?>
            <!-- Add Expense Form -->
            <div class="max-w-3xl">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Add New Expense</h1>
                        <p class="text-gray-600">Record a new business expense</p>
                    </div>
                    <a href="expenses.php" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-2xl"></i>
                    </a>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-8">
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Expense Date *</label>
                                <input type="date" name="expense_date" required
                                       value="<?php echo date('Y-m-d'); ?>"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Category *</label>
                                <select name="category_id" required
                                        class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Description *</label>
                            <input type="text" name="description" required
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
                                   placeholder="Brief description of the expense">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Amount (<?php echo CURRENCY_SYMBOL; ?>) *</label>
                                <input type="number" name="amount" step="0.01" min="0.01" required
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
                                       placeholder="0.00">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Method *</label>
                                <select name="payment_method" required
                                        class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                    <option value="1">Cash</option>
                                    <option value="2">Card</option>
                                    <option value="3">Bank Transfer</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Receipt Photo</label>
                            <input type="file" name="receipt_photo" accept="image/*"
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                            <p class="text-xs text-gray-500 mt-1">Upload a photo of the receipt or invoice</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="3"
                                      class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
                                      placeholder="Additional notes or comments"></textarea>
                        </div>

                        <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                            <a href="expenses.php" 
                               class="px-6 py-3 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold rounded-lg transition-colors">
                                Cancel
                            </a>
                            <button type="submit" 
                                    class="px-6 py-3 bg-primary hover:bg-blue-600 text-white font-semibold rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>Record Expense
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Receipt Modal -->
    <div id="receipt-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-gray-800">Receipt Photo</h3>
                    <button onclick="hideReceiptModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="text-center">
                    <img id="receipt-image" src="" alt="Receipt" class="max-w-full max-h-96 mx-auto rounded-lg shadow-lg">
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewReceipt(filename) {
            document.getElementById('receipt-image').src = '../../assets/uploads/expense-receipts/' + filename;
            document.getElementById('receipt-modal').classList.remove('hidden');
        }

        function hideReceiptModal() {
            document.getElementById('receipt-modal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('receipt-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideReceiptModal();
            }
        });
    </script>
</body>
</html>