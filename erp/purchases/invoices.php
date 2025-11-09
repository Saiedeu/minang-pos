<?php
/**
 * ERP System - Purchase Invoice Management
 * Handle purchase invoices and supplier payments
 */

define('MINANG_SYSTEM', true);
require_once '../../config/config.php';

// Check authentication
if (!User::isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

$user = User::getCurrentUser();
$purchase = new Purchase();
$product = new Product();

$action = $_GET['action'] ?? 'list';
$purchaseId = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $purchaseData = [
            'supplier_id' => intval($_POST['supplier_id']),
            'invoice_number' => sanitize($_POST['invoice_number']),
            'purchase_date' => $_POST['purchase_date'],
            'payment_method' => intval($_POST['payment_method']),
            'discount' => floatval($_POST['discount'] ?? 0),
            'paid_amount' => floatval($_POST['paid_amount'] ?? 0),
            'notes' => sanitize($_POST['notes'] ?? '')
        ];
        
        // Handle invoice photo upload
        if (isset($_FILES['invoice_photo']) && $_FILES['invoice_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../assets/uploads/purchase-invoices/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = 'INV_' . date('Ymd_His') . '_' . $_FILES['invoice_photo']['name'];
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['invoice_photo']['tmp_name'], $uploadPath)) {
                $purchaseData['invoice_photo'] = $fileName;
            }
        }
        
        // Process purchase items
        $purchaseItems = [];
        if (isset($_POST['items'])) {
            foreach ($_POST['items'] as $itemData) {
                if (!empty($itemData['product_id']) && $itemData['quantity'] > 0) {
                    $purchaseItems[] = [
                        'product_id' => intval($itemData['product_id']),
                        'product_name' => sanitize($itemData['product_name']),
                        'quantity' => floatval($itemData['quantity']),
                        'unit_price' => floatval($itemData['unit_price']),
                        'discount' => floatval($itemData['discount'] ?? 0)
                    ];
                }
            }
        }
        
        if (empty($purchaseItems)) {
            $error = 'At least one purchase item is required';
        } else {
            $result = $purchase->createPurchase($purchaseData, $purchaseItems);
            if ($result['success']) {
                $success = 'Purchase invoice created successfully';
                header('Location: invoices.php?success=created');
                exit();
            } else {
                $error = $result['message'];
            }
        }
    }
    
    // Handle payment
    if ($action === 'pay' && $purchaseId) {
        $paymentAmount = floatval($_POST['payment_amount'] ?? 0);
        $paymentMethod = intval($_POST['payment_method'] ?? 1);
        
        $result = $purchase->makePayment($purchaseId, $paymentAmount, $paymentMethod);
        if ($result['success']) {
            $success = 'Payment recorded successfully';
        } else {
            $error = $result['message'];
        }
    }
}

// Get data for display
$suppliers = $purchase->getAllSuppliers();
$purchases = $purchase->getAllPurchases();
$products = $product->getAllProducts();
$currentPurchase = null;

if ($action === 'view' && $purchaseId) {
    $currentPurchase = $purchase->getPurchaseById($purchaseId);
}

$pageTitle = 'Purchase Invoices';
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
            <!-- Purchase Invoices List -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Purchase Invoices</h1>
                    <p class="text-gray-600">Manage supplier invoices and payments</p>
                </div>
                <div class="flex space-x-3">
                    <a href="suppliers.php" class="bg-secondary hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-truck mr-2"></i>Manage Suppliers
                    </a>
                    <a href="?action=add" class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-plus mr-2"></i>Add New Purchase
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <?php
                $stats = $purchase->getPurchaseStats();
                ?>
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Purchases</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_purchases'] ?? 0; ?></p>
                        </div>
                        <i class="fas fa-file-invoice text-3xl text-blue-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Amount</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo formatCurrency($stats['total_amount'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-money-bill-wave text-3xl text-green-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Paid Amount</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo formatCurrency($stats['paid_amount'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-check-circle text-3xl text-green-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Outstanding</p>
                            <p class="text-2xl font-bold text-red-600"><?php echo formatCurrency($stats['outstanding_amount'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-exclamation-triangle text-3xl text-red-500"></i>
                    </div>
                </div>
            </div>

            <!-- Purchases Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-800">Recent Purchase Invoices</h2>
                        <input type="text" placeholder="Search invoices..." 
                               class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Invoice #</th>
                                <th class="px-6 py-4">Supplier</th>
                                <th class="px-6 py-4">Date</th>
                                <th class="px-6 py-4">Total</th>
                                <th class="px-6 py-4">Paid</th>
                                <th class="px-6 py-4">Outstanding</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($purchases as $purch): ?>
                            <?php
                                $outstanding = $purch['total'] - $purch['paid_amount'];
                                $statusColors = [
                                    0 => 'bg-red-100 text-red-800',
                                    1 => 'bg-yellow-100 text-yellow-800', 
                                    2 => 'bg-green-100 text-green-800'
                                ];
                                $statusLabels = [0 => 'Unpaid', 1 => 'Partial', 2 => 'Paid'];
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium text-gray-900"><?php echo $purch['invoice_number']; ?></td>
                                <td class="px-6 py-4 text-gray-600"><?php echo $purch['supplier_name']; ?></td>
                                <td class="px-6 py-4 text-gray-600"><?php echo formatDate($purch['purchase_date']); ?></td>
                                <td class="px-6 py-4 font-semibold"><?php echo formatCurrency($purch['total']); ?></td>
                                <td class="px-6 py-4 text-green-600"><?php echo formatCurrency($purch['paid_amount']); ?></td>
                                <td class="px-6 py-4 <?php echo $outstanding > 0 ? 'text-red-600 font-semibold' : 'text-gray-600'; ?>">
                                    <?php echo formatCurrency($outstanding); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $statusColors[$purch['payment_status']]; ?>">
                                        <?php echo $statusLabels[$purch['payment_status']]; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <a href="?action=view&id=<?php echo $purch['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-800 text-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($outstanding > 0): ?>
                                        <button onclick="showPaymentModal(<?php echo $purch['id']; ?>, <?php echo $outstanding; ?>)" 
                                                class="text-green-600 hover:text-green-800 text-sm" title="Make Payment">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($action === 'add'): ?>
            <!-- Add Purchase Form -->
            <div class="max-w-6xl">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Add New Purchase</h1>
                        <p class="text-gray-600">Create a new purchase invoice</p>
                    </div>
                    <a href="invoices.php" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-2xl"></i>
                    </a>
                </div>

                <form method="POST" enctype="multipart/form-data" id="purchase-form">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <!-- Purchase Header -->
                        <div class="lg:col-span-1">
                            <div class="bg-white rounded-xl shadow-lg p-6 sticky top-4">
                                <h2 class="text-xl font-semibold text-gray-800 mb-6">Purchase Details</h2>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Supplier *</label>
                                        <select name="supplier_id" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                            <option value="">Select Supplier</option>
                                            <?php foreach ($suppliers as $supplier): ?>
                                            <option value="<?php echo $supplier['id']; ?>"><?php echo $supplier['name']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Invoice Number *</label>
                                        <input type="text" name="invoice_number" required
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
                                               placeholder="INV-001">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Purchase Date *</label>
                                        <input type="date" name="purchase_date" required
                                               value="<?php echo date('Y-m-d'); ?>"
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Method</label>
                                        <select name="payment_method" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                            <option value="1">Cash</option>
                                            <option value="2">Card</option>
                                            <option value="3">Credit</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Amount Paid</label>
                                        <input type="number" name="paid_amount" step="0.01" min="0"
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
                                               placeholder="0.00" id="paid-amount">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Invoice Photo</label>
                                        <input type="file" name="invoice_photo" accept="image/*"
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                                        <textarea name="notes" rows="3"
                                                  class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
                                                  placeholder="Additional notes"></textarea>
                                    </div>
                                </div>

                                <!-- Totals -->
                                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between">
                                            <span>Subtotal:</span>
                                            <span id="subtotal-display">QR 0.00</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Discount:</span>
                                            <div class="flex items-center space-x-2">
                                                <input type="number" name="discount" step="0.01" min="0" id="discount-input"
                                                       class="w-20 p-1 text-xs text-right border rounded"
                                                       placeholder="0.00" onchange="calculateTotals()">
                                                <span>QR</span>
                                            </div>
                                        </div>
                                        <div class="flex justify-between text-lg font-bold border-t pt-2">
                                            <span>TOTAL:</span>
                                            <span id="total-display" class="text-primary">QR 0.00</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <button type="submit" class="w-full mt-6 bg-primary hover:bg-blue-600 text-white font-semibold py-3 rounded-lg transition-colors">
                                    <i class="fas fa-save mr-2"></i>Save Purchase Invoice
                                </button>
                            </div>
                        </div>

                        <!-- Purchase Items -->
                        <div class="lg:col-span-2">
                            <div class="bg-white rounded-xl shadow-lg p-6">
                                <div class="flex items-center justify-between mb-6">
                                    <h2 class="text-xl font-semibold text-gray-800">Purchase Items</h2>
                                    <button type="button" onclick="addPurchaseItem()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium">
                                        <i class="fas fa-plus mr-2"></i>Add Item
                                    </button>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="w-full" id="items-table">
                                        <thead class="bg-gray-50">
                                            <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                                                <th class="px-4 py-3">SL</th>
                                                <th class="px-4 py-3">Product</th>
                                                <th class="px-4 py-3">Cost Price</th>
                                                <th class="px-4 py-3">Quantity</th>
                                                <th class="px-4 py-3">Discount</th>
                                                <th class="px-4 py-3">Total</th>
                                                <th class="px-4 py-3">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="items-tbody">
                                            <!-- Dynamic rows will be added here -->
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        Click "Add Item" to start adding products to this purchase invoice. 
                                        You can search existing products or add new ones directly.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <?php elseif ($action === 'view' && $currentPurchase): ?>
            <!-- View Purchase Details -->
            <div class="max-w-4xl">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Purchase Invoice</h1>
                        <p class="text-gray-600"><?php echo $currentPurchase['invoice_number']; ?></p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="invoices.php" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-lg">
                            <i class="fas fa-arrow-left mr-2"></i>Back to List
                        </a>
                        <?php if ($currentPurchase['total'] - $currentPurchase['paid_amount'] > 0): ?>
                        <button onclick="showPaymentModal(<?php echo $currentPurchase['id']; ?>, <?php echo $currentPurchase['total'] - $currentPurchase['paid_amount']; ?>)" 
                                class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-money-bill-wave mr-2"></i>Make Payment
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Purchase Details Card -->
                <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Purchase Information</h3>
                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Invoice Number:</span>
                                    <span class="font-semibold"><?php echo $currentPurchase['invoice_number']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Supplier:</span>
                                    <span class="font-semibold"><?php echo $currentPurchase['supplier_name']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Purchase Date:</span>
                                    <span class="font-semibold"><?php echo formatDate($currentPurchase['purchase_date']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Created By:</span>
                                    <span class="font-semibold"><?php echo $currentPurchase['created_by_name']; ?></span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment Summary</h3>
                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Subtotal:</span>
                                    <span class="font-semibold"><?php echo formatCurrency($currentPurchase['subtotal']); ?></span>
                                </div>
                                <?php if ($currentPurchase['discount'] > 0): ?>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Discount:</span>
                                    <span class="font-semibold text-red-600">-<?php echo formatCurrency($currentPurchase['discount']); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="flex justify-between text-lg font-bold border-t pt-3">
                                    <span>Total:</span>
                                    <span class="text-primary"><?php echo formatCurrency($currentPurchase['total']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Paid Amount:</span>
                                    <span class="font-semibold text-green-600"><?php echo formatCurrency($currentPurchase['paid_amount']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Outstanding:</span>
                                    <span class="font-semibold text-red-600"><?php echo formatCurrency($currentPurchase['total'] - $currentPurchase['paid_amount']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Purchase Items -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6">Purchase Items</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                                    <th class="px-4 py-3">SL</th>
                                    <th class="px-4 py-3">Product</th>
                                    <th class="px-4 py-3">Unit Price</th>
                                    <th class="px-4 py-3">Quantity</th>
                                    <th class="px-4 py-3">Discount</th>
                                    <th class="px-4 py-3">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($currentPurchase['items'] as $index => $item): ?>
                                <tr class="border-t border-gray-200">
                                    <td class="px-4 py-3 text-gray-600"><?php echo $index + 1; ?></td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900"><?php echo $item['product_name']; ?></div>
                                        <div class="text-xs text-gray-500">Code: <?php echo $item['product_code'] ?? 'N/A'; ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600"><?php echo formatCurrency($item['unit_price']); ?></td>
                                    <td class="px-4 py-3 text-gray-600"><?php echo $item['quantity']; ?></td>
                                    <td class="px-4 py-3 text-red-600"><?php echo formatCurrency($item['discount']); ?></td>
                                    <td class="px-4 py-3 font-semibold text-primary"><?php echo formatCurrency($item['total_price']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Payment Modal -->
    <div id="payment-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Make Payment</h3>
                
                <form method="POST" id="payment-form">
                    <input type="hidden" name="action" value="pay">
                    <input type="hidden" name="id" id="payment-purchase-id">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Outstanding Amount</label>
                            <div class="p-3 bg-gray-100 rounded-lg">
                                <span id="outstanding-amount" class="text-xl font-bold text-red-600">QR 0.00</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Amount</label>
                            <input type="number" name="payment_amount" step="0.01" min="0" id="payment-amount-input"
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
                                   placeholder="0.00" required>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Method</label>
                            <select name="payment_method" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="1">Cash</option>
                                <option value="2">Card</option>
                                <option value="3">Bank Transfer</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex space-x-3 mt-6">
                        <button type="button" onclick="hidePaymentModal()" 
                                class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold py-3 rounded-lg">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-primary hover:bg-blue-600 text-white font-semibold py-3 rounded-lg">
                            <i class="fas fa-check mr-2"></i>Record Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let itemCounter = 0;
        
        // Add purchase item row
        function addPurchaseItem() {
            itemCounter++;
            const tbody = document.getElementById('items-tbody');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="px-4 py-3 text-gray-600">${itemCounter}</td>
                <td class="px-4 py-3">
                    <select name="items[${itemCounter}][product_id]" required 
                            class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-primary"
                            onchange="loadProductPrice(this, ${itemCounter})">
                        <option value="">Select Product</option>
                        <?php foreach ($products as $prod): ?>
                        <option value="<?php echo $prod['id']; ?>" data-price="<?php echo $prod['cost_price']; ?>">
                            <?php echo $prod['name']; ?> (<?php echo $prod['code']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="items[${itemCounter}][product_name]" id="product_name_${itemCounter}">
                </td>
                <td class="px-4 py-3">
                    <input type="number" name="items[${itemCounter}][unit_price]" step="0.01" min="0" required
                           id="unit_price_${itemCounter}"
                           class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-primary"
                           onchange="calculateItemTotal(${itemCounter})">
                </td>
                <td class="px-4 py-3">
                    <input type="number" name="items[${itemCounter}][quantity]" step="0.01" min="0.01" required
                           id="quantity_${itemCounter}"
                           class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-primary"
                           onchange="calculateItemTotal(${itemCounter})">
                </td>
                <td class="px-4 py-3">
                    <input type="number" name="items[${itemCounter}][discount]" step="0.01" min="0"
                           id="discount_${itemCounter}"
                           class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-primary"
                           onchange="calculateItemTotal(${itemCounter})" placeholder="0.00">
                </td>
                <td class="px-4 py-3">
                    <span id="item_total_${itemCounter}" class="font-semibold text-primary">QR 0.00</span>
                </td>
                <td class="px-4 py-3">
                    <button type="button" onclick="removeItem(this)" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        }

        // Load product price when product selected
        function loadProductPrice(select, itemIndex) {
            const selectedOption = select.options[select.selectedIndex];
            const price = selectedOption.getAttribute('data-price') || 0;
            const productName = selectedOption.text;
            
            document.getElementById(`unit_price_${itemIndex}`).value = price;
            document.getElementById(`product_name_${itemIndex}`).value = productName;
            calculateItemTotal(itemIndex);
        }

        // Calculate item total
        function calculateItemTotal(itemIndex) {
            const unitPrice = parseFloat(document.getElementById(`unit_price_${itemIndex}`).value) || 0;
            const quantity = parseFloat(document.getElementById(`quantity_${itemIndex}`).value) || 0;
            const discount = parseFloat(document.getElementById(`discount_${itemIndex}`).value) || 0;
            
            const itemTotal = (unitPrice * quantity) - discount;
            document.getElementById(`item_total_${itemIndex}`).textContent = 'QR ' + itemTotal.toFixed(2);
            
            calculateTotals();
        }

        // Calculate totals
        function calculateTotals() {
            let subtotal = 0;
            const itemTotals = document.querySelectorAll('[id^="item_total_"]');
            
            itemTotals.forEach(totalSpan => {
                const value = parseFloat(totalSpan.textContent.replace('QR ', '')) || 0;
                subtotal += value;
            });
            
            const discount = parseFloat(document.getElementById('discount-input').value) || 0;
            const total = subtotal - discount;
            
            document.getElementById('subtotal-display').textContent = 'QR ' + subtotal.toFixed(2);
            document.getElementById('total-display').textContent = 'QR ' + total.toFixed(2);
        }

        // Remove item row
        function removeItem(button) {
            button.closest('tr').remove();
            calculateTotals();
        }

        // Payment modal functions
        function showPaymentModal(purchaseId, outstandingAmount) {
            document.getElementById('payment-purchase-id').value = purchaseId;
            document.getElementById('outstanding-amount').textContent = 'QR ' + outstandingAmount.toFixed(2);
            document.getElementById('payment-amount-input').value = outstandingAmount.toFixed(2);
            document.getElementById('payment-modal').classList.remove('hidden');
        }

        function hidePaymentModal() {
            document.getElementById('payment-modal').classList.add('hidden');
        }

        // Add initial item row
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('items-tbody')) {
                addPurchaseItem();
            }
        });
    </script>
</body>
</html>