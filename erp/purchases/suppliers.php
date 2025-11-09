<?php
/**
 * ERP System - Supplier Management
 * Manage supplier information and contacts
 */

define('MINANG_SYSTEM', true);
require_once '../../config/config.php';
require_once '../includes/auth-check.php';

$purchase = new Purchase();
$action = $_GET['action'] ?? 'list';
$supplierId = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $supplierData = [
            'name' => sanitize($_POST['name'] ?? ''),
            'contact_person' => sanitize($_POST['contact_person'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'email' => sanitize($_POST['email'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'cr_number' => sanitize($_POST['cr_number'] ?? ''),
            'is_active' => 1
        ];
        
        $result = $purchase->createSupplier($supplierData);
        if ($result['success']) {
            $success = 'Supplier created successfully';
            header('Location: suppliers.php?success=created');
            exit();
        } else {
            $error = $result['message'];
        }
    }
    
    if ($action === 'edit' && $supplierId) {
        $supplierData = [
            'name' => sanitize($_POST['name'] ?? ''),
            'contact_person' => sanitize($_POST['contact_person'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'email' => sanitize($_POST['email'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'cr_number' => sanitize($_POST['cr_number'] ?? '')
        ];
        
        $result = $purchase->updateSupplier($supplierId, $supplierData);
        if ($result['success']) {
            $success = 'Supplier updated successfully';
        } else {
            $error = $result['message'];
        }
    }
}

// Get data for display
$suppliers = $purchase->getAllSuppliers();
$currentSupplier = null;

if ($action === 'edit' && $supplierId) {
    $currentSupplier = $purchase->getSupplierById($supplierId);
}

// Get supplier statistics
$db = Database::getInstance();
$supplierStats = $db->fetchAll("
    SELECT 
        s.id,
        s.name,
        COUNT(p.id) as total_purchases,
        COALESCE(SUM(p.total), 0) as total_amount,
        COALESCE(SUM(p.total - p.paid_amount), 0) as outstanding_amount
    FROM suppliers s
    LEFT JOIN purchases p ON s.id = p.supplier_id
    WHERE s.is_active = 1
    GROUP BY s.id, s.name
    ORDER BY total_amount DESC
");

$pageTitle = 'Supplier Management';
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
            <!-- Suppliers List -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Suppliers</h1>
                    <p class="text-gray-600">Manage your supplier database</p>
                </div>
                <a href="?action=add" class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-plus mr-2"></i>Add New Supplier
                </a>
            </div>

            <!-- Supplier Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Suppliers</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo count($suppliers); ?></p>
                        </div>
                        <i class="fas fa-truck text-3xl text-blue-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Active Suppliers</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo count(array_filter($suppliers, fn($s) => $s['is_active'])); ?></p>
                        </div>
                        <i class="fas fa-check-circle text-3xl text-green-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Purchases</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo array_sum(array_column($supplierStats, 'total_purchases')); ?></p>
                        </div>
                        <i class="fas fa-file-invoice text-3xl text-purple-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Outstanding</p>
                            <p class="text-2xl font-bold text-red-600"><?php echo formatCurrency(array_sum(array_column($supplierStats, 'outstanding_amount'))); ?></p>
                        </div>
                        <i class="fas fa-exclamation-triangle text-3xl text-red-500"></i>
                    </div>
                </div>
            </div>

            <!-- Suppliers Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">Supplier Directory</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Supplier Name</th>
                                <th class="px-6 py-4">Contact Person</th>
                                <th class="px-6 py-4">Phone</th>
                                <th class="px-6 py-4">Email</th>
                                <th class="px-6 py-4">Total Purchases</th>
                                <th class="px-6 py-4">Outstanding</th>
                                <th class="px-6 py-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($suppliers as $supplier): ?>
                            <?php
                                $stats = array_filter($supplierStats, fn($s) => $s['id'] == $supplier['id']);
                                $stats = !empty($stats) ? array_values($stats)[0] : ['total_purchases' => 0, 'total_amount' => 0, 'outstanding_amount' => 0];
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900"><?php echo $supplier['name']; ?></div>
                                    <?php if ($supplier['cr_number']): ?>
                                    <div class="text-xs text-gray-500">CR: <?php echo $supplier['cr_number']; ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-gray-600"><?php echo $supplier['contact_person'] ?? '-'; ?></td>
                                <td class="px-6 py-4 text-gray-600">
                                    <?php if ($supplier['phone']): ?>
                                    <a href="tel:<?php echo $supplier['phone']; ?>" class="text-primary hover:underline">
                                        <?php echo $supplier['phone']; ?>
                                    </a>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <?php if ($supplier['email']): ?>
                                    <a href="mailto:<?php echo $supplier['email']; ?>" class="text-primary hover:underline">
                                        <?php echo $supplier['email']; ?>
                                    </a>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-semibold text-gray-900"><?php echo $stats['total_purchases']; ?></div>
                                    <div class="text-xs text-gray-500"><?php echo formatCurrency($stats['total_amount']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="<?php echo $stats['outstanding_amount'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-600'; ?>">
                                        <?php echo formatCurrency($stats['outstanding_amount']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <a href="?action=edit&id=<?php echo $supplier['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-800 text-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="invoices.php?supplier_id=<?php echo $supplier['id']; ?>" 
                                           class="text-green-600 hover:text-green-800 text-sm" title="View Purchases">
                                            <i class="fas fa-file-invoice"></i>
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
            <!-- Add/Edit Supplier Form -->
            <div class="max-w-3xl">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">
                            <?php echo $action === 'add' ? 'Add New Supplier' : 'Edit Supplier'; ?>
                        </h1>
                        <p class="text-gray-600">
                            <?php echo $action === 'add' ? 'Register a new supplier' : 'Update supplier information'; ?>
                        </p>
                    </div>
                    <a href="suppliers.php" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-2xl"></i>
                    </a>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-8">
                    <form method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Supplier Name *</label>
                                <input type="text" name="name" required
                                       value="<?php echo $currentSupplier['name'] ?? ''; ?>"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                       placeholder="e.g., Al-Reef Food Supplies">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Contact Person</label>
                                <input type="text" name="contact_person" 
                                       value="<?php echo $currentSupplier['contact_person'] ?? ''; ?>"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                       placeholder="Contact person name">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                                <input type="tel" name="phone" 
                                       value="<?php echo $currentSupplier['phone'] ?? ''; ?>"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                       placeholder="+974-XXXX-XXXX">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                                <input type="email" name="email" 
                                       value="<?php echo $currentSupplier['email'] ?? ''; ?>"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                       placeholder="supplier@email.com">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Address</label>
                            <textarea name="address" rows="3"
                                      class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                      placeholder="Complete supplier address"><?php echo $currentSupplier['address'] ?? ''; ?></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Commercial Registration (CR)</label>
                            <input type="text" name="cr_number" 
                                   value="<?php echo $currentSupplier['cr_number'] ?? ''; ?>"
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                   placeholder="CR-123456789">
                        </div>

                        <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                            <a href="suppliers.php" 
                               class="px-6 py-3 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold rounded-lg transition-colors">
                                Cancel
                            </a>
                            <button type="submit" 
                                    class="px-6 py-3 bg-primary hover:bg-blue-600 text-white font-semibold rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>
                                <?php echo $action === 'add' ? 'Add Supplier' : 'Update Supplier'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>