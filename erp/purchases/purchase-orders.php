<?php
/**
 * ERP System - Purchase Order Management
 * Create and manage purchase orders to suppliers
 */

define('MINANG_SYSTEM', true);
require_once '../../config/config.php';
require_once '../includes/auth-check.php';

$db = Database::getInstance();
$purchase = new Purchase();
$product = new Product();
$action = $_GET['action'] ?? 'list';
$orderId = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $orderData = [
            'supplier_id' => intval($_POST['supplier_id']),
            'order_number' => sanitize($_POST['order_number']),
            'order_date' => $_POST['order_date'],
            'expected_delivery' => $_POST['expected_delivery'],
            'status' => 'pending',
            'notes' => sanitize($_POST['notes'] ?? ''),
            'created_by' => $_SESSION['user_id'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Process order items
        $orderItems = [];
        if (isset($_POST['items'])) {
            foreach ($_POST['items'] as $itemData) {
                if (!empty($itemData['product_id']) && $itemData['quantity'] > 0) {
                    $orderItems[] = [
                        'product_id' => intval($itemData['product_id']),
                        'product_name' => sanitize($itemData['product_name']),
                        'quantity' => floatval($itemData['quantity']),
                        'unit_price' => floatval($itemData['unit_price']),
                        'notes' => sanitize($itemData['notes'] ?? '')
                    ];
                }
            }
        }
        
        if (empty($orderItems)) {
            $error = 'At least one order item is required';
        } else {
            $result = createPurchaseOrder($orderData, $orderItems);
            if ($result['success']) {
                $success = 'Purchase order created successfully';
                header('Location: purchase-orders.php?success=created');
                exit();
            } else {
                $error = $result['message'];
            }
        }
    }
    
    if ($action === 'update_status') {
        $orderId = intval($_POST['order_id']);
        $newStatus = $_POST['status'];
        $statusNotes = sanitize($_POST['status_notes'] ?? '');
        
        $updated = $db->update('purchase_orders', [
            'status' => $newStatus,
            'status_notes' => $statusNotes,
            'status_updated_by' => $_SESSION['user_id'],
            'status_updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$orderId]);
        
        if ($updated) {
            $success = 'Order status updated to: ' . strtoupper($newStatus);
        } else {
            $error = 'Failed to update order status';
        }
    }
}

// Create purchase order function
function createPurchaseOrder($orderData, $orderItems) {
    global $db;
    
    try {
        $db->beginTransaction();
        
        // Calculate totals
        $subtotal = 0;
        foreach ($orderItems as $item) {
            $subtotal += $item['quantity'] * $item['unit_price'];
        }
        $orderData['subtotal'] = $subtotal;
        $orderData['total'] = $subtotal;
        
        // Insert order
        $orderId = $db->insert('purchase_orders', $orderData);
        if (!$orderId) {
            throw new Exception('Failed to create purchase order');
        }
        
        // Insert order items
        foreach ($orderItems as $item) {
            $itemData = array_merge($item, [
                'order_id' => $orderId,
                'total_price' => $item['quantity'] * $item['unit_price']
            ]);
            
            $itemId = $db->insert('purchase_order_items', $itemData);
            if (!$itemId) {
                throw new Exception('Failed to create order item');
            }
        }
        
        $db->commit();
        return ['success' => true, 'order_id' => $orderId];
        
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Get data for display
$suppliers = $purchase->getAllSuppliers();
$products = $product->getAllProducts();

// Get purchase orders with filters
$statusFilter = $_GET['status'] ?? '';
$supplierFilter = $_GET['supplier_id'] ?? '';

$whereConditions = ['1=1'];
$params = [];

if ($statusFilter) {
    $whereConditions[] = 'po.status = ?';
    $params[] = $statusFilter;
}

if ($supplierFilter) {
    $whereConditions[] = 'po.supplier_id = ?';
    $params[] = $supplierFilter;
}

$purchaseOrders = $db->fetchAll("
    SELECT po.*, s.name as supplier_name, u.name as created_by_name,
           COUNT(poi.id) as item_count
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    LEFT JOIN users u ON po.created_by = u.id
    LEFT JOIN purchase_order_items poi ON po.id = poi.order_id
    WHERE " . implode(' AND ', $whereConditions) . "
    GROUP BY po.id
    ORDER BY po.created_at DESC
", $params);

$currentOrder = null;
if ($action === 'view' && $orderId) {
    $currentOrder = $db->fetchOne("
        SELECT po.*, s.name as supplier_name, s.contact_person, s.phone as supplier_phone,
               u.name as created_by_name
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN users u ON po.created_by = u.id
        WHERE po.id = ?
    ", [$orderId]);
    
    if ($currentOrder) {
        $currentOrder['items'] = $db->fetchAll("
            SELECT poi.*, p.code as product_code, p.unit
            FROM purchase_order_items poi
            LEFT JOIN products p ON poi.product_id = p.id
            WHERE poi.order_id = ?
        ", [$orderId]);
    }
}

$pageTitle = 'Purchase Orders';
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
                    <h1 class="text-3xl font-bold text-gray-800">Purchase Orders</h1>
                    <p class="text-gray-600">Create and track purchase orders to suppliers</p>
                </div>
                <div class="flex space-x-3">
                    <button onclick="generateReorderSuggestions()" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-lightbulb mr-2"></i>Reorder Suggestions
                    </button>
                    <a href="?action=add" class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-plus mr-2"></i>New Purchase Order
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="sent" <?php echo $statusFilter === 'sent' ? 'selected' : ''; ?>>Sent</option>
                            <option value="confirmed" <?php echo $statusFilter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="delivered" <?php echo $statusFilter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Supplier</label>
                        <select name="supplier_id" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">All Suppliers</option>
                            <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier['id']; ?>" <?php echo $supplierFilter == $supplier['id'] ? 'selected' : ''; ?>>
                                <?php echo $supplier['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-primary hover:bg-blue-600 text-white font-semibold py-3 rounded-lg">
                            <i class="fas fa-filter mr-2"></i>Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Purchase Orders Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">Purchase Orders</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Order #</th>
                                <th class="px-6 py-4">Supplier</th>
                                <th class="px-6 py-4">Order Date</th>
                                <th class="px-6 py-4">Expected Delivery</th>
                                <th class="px-6 py-4">Items</th>
                                <th class="px-6 py-4">Total</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($purchaseOrders as $order): ?>
                            <?php
                                $statusColors = [
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'sent' => 'bg-blue-100 text-blue-800',
                                    'confirmed' => 'bg-purple-100 text-purple-800',
                                    'delivered' => 'bg-green-100 text-green-800',
                                    'cancelled' => 'bg-red-100 text-red-800'
                                ];
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium text-gray-900"><?php echo $order['order_number']; ?></td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900"><?php echo $order['supplier_name']; ?></div>
                                    <div class="text-sm text-gray-500">By: <?php echo $order['created_by_name']; ?></div>
                                </td>
                                <td class="px-6 py-4 text-gray-600"><?php echo formatDate($order['order_date']); ?></td>
                                <td class="px-6 py-4 text-gray-600">
                                    <?php if ($order['expected_delivery']): ?>
                                        <?php echo formatDate($order['expected_delivery']); ?>
                                        <?php
                                            $daysUntil = (strtotime($order['expected_delivery']) - time()) / (24 * 60 * 60);
                                            if ($daysUntil < 0) {
                                                echo '<div class="text-xs text-red-600 font-semibold">Overdue</div>';
                                            } elseif ($daysUntil <= 1) {
                                                echo '<div class="text-xs text-orange-600 font-semibold">Due Soon</div>';
                                            }
                                        ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-full text-xs font-semibold">
                                        <?php echo $order['item_count']; ?> items
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-semibold text-primary"><?php echo formatCurrency($order['total']); ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo strtoupper($order['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <a href="?action=view&id=<?php echo $order['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-800 text-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                                        <button onclick="showStatusModal(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>')" 
                                                class="text-green-600 hover:text-green-800 text-sm" title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button onclick="printPO(<?php echo $order['id']; ?>)" 
                                                class="text-purple-600 hover:text-purple-800 text-sm" title="Print PO">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($purchaseOrders)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-clipboard-list text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">No Purchase Orders</h3>
                    <p class="text-gray-600">No purchase orders found matching your criteria</p>
                </div>
                <?php endif; ?>
            </div>

            <?php elseif ($action === 'add'): ?>
            <!-- Add Purchase Order Form -->
            <div class="max-w-6xl">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Create Purchase Order</h1>
                        <p class="text-gray-600">Create a new purchase order for supplier</p>
                    </div>
                    <a href="purchase-orders.php" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-2xl"></i>
                    </a>
                </div>

                <form method="POST" id="po-form">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <!-- Order Header -->
                        <div class="lg:col-span-1">
                            <div class="bg-white rounded-xl shadow-lg p-6">
                                <h2 class="text-xl font-semibold text-gray-800 mb-6">Order Details</h2>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Order Number *</label>
                                        <input type="text" name="order_number" required
                                               value="PO-<?php echo date('Ymd-His'); ?>"
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                    </div>

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
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Order Date *</label>
                                        <input type="date" name="order_date" required
                                               value="<?php echo date('Y-m-d'); ?>"
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Expected Delivery</label>
                                        <input type="date" name="expected_delivery"
                                               value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>"
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                                        <textarea name="notes" rows="4"
                                                  class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
                                                  placeholder="Special instructions or requirements"></textarea>
                                    </div>
                                </div>

                                <!-- Order Total -->
                                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                                    <div class="text-center">
                                        <div class="text-sm text-gray-600">Order Total</div>
                                        <div id="order-total" class="text-2xl font-bold text-primary">QR 0.00</div>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <button type="submit" class="w-full mt-6 bg-primary hover:bg-blue-600 text-white font-semibold py-3 rounded-lg transition-colors">
                                    <i class="fas fa-paper-plane mr-2"></i>Create Purchase Order
                                </button>
                            </div>
                        </div>

                        <!-- Order Items -->
                        <div class="lg:col-span-2">
                            <div class="bg-white rounded-xl shadow-lg p-6">
                                <div class="flex items-center justify-between mb-6">
                                    <h2 class="text-xl font-semibold text-gray-800">Order Items</h2>
                                    <button type="button" onclick="addOrderItem()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium">
                                        <i class="fas fa-plus mr-2"></i>Add Item
                                    </button>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="w-full" id="items-table">
                                        <thead class="bg-gray-50">
                                            <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                                                <th class="px-4 py-3">SL</th>
                                                <th class="px-4 py-3">Product</th>
                                                <th class="px-4 py-3">Current Stock</th>
                                                <th class="px-4 py-3">Order Qty</th>
                                                <th class="px-4 py-3">Unit Price</th>
                                                <th class="px-4 py-3">Total</th>
                                                <th class="px-4 py-3">Notes</th>
                                                <th class="px-4 py-3">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="items-tbody">
                                            <!-- Dynamic rows will be added here -->
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                                    <p class="text-sm text-blue-800">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        Add products you want to order from the selected supplier. 
                                        Check current stock levels to determine optimal order quantities.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <?php elseif ($action === 'view' && $currentOrder): ?>
            <!-- View Purchase Order -->
            <div class="max-w-4xl">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Purchase Order Details</h1>
                        <p class="text-gray-600"><?php echo $currentOrder['order_number']; ?></p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="purchase-orders.php" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-lg">
                            <i class="fas fa-arrow-left mr-2"></i>Back
                        </a>
                        <button onclick="printPO(<?php echo $currentOrder['id']; ?>)" 
                                class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-print mr-2"></i>Print PO
                        </button>
                    </div>
                </div>

                <!-- Order Information -->
                <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Order Information</h3>
                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Order Number:</span>
                                    <span class="font-semibold"><?php echo $currentOrder['order_number']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Order Date:</span>
                                    <span class="font-semibold"><?php echo formatDate($currentOrder['order_date']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Expected Delivery:</span>
                                    <span class="font-semibold"><?php echo $currentOrder['expected_delivery'] ? formatDate($currentOrder['expected_delivery']) : 'Not set'; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Created By:</span>
                                    <span class="font-semibold"><?php echo $currentOrder['created_by_name']; ?></span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Supplier Information</h3>
                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Supplier:</span>
                                    <span class="font-semibold"><?php echo $currentOrder['supplier_name']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Contact Person:</span>
                                    <span class="font-semibold"><?php echo $currentOrder['contact_person'] ?? 'Not available'; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Phone:</span>
                                    <span class="font-semibold"><?php echo $currentOrder['supplier_phone'] ?? 'Not available'; ?></span>
                                </div>
                                <div class="flex justify-between text-lg font-bold border-t pt-3">
                                    <span>Total Amount:</span>
                                    <span class="text-primary"><?php echo formatCurrency($currentOrder['total']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Status -->
                    <div class="mt-8 p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <span class="text-sm text-gray-600">Current Status:</span>
                                <span class="px-3 py-1 text-sm rounded-full <?php echo $statusColors[$currentOrder['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo strtoupper($currentOrder['status']); ?>
                                </span>
                            </div>
                            <?php if ($currentOrder['status'] !== 'delivered' && $currentOrder['status'] !== 'cancelled'): ?>
                            <button onclick="showStatusModal(<?php echo $currentOrder['id']; ?>, '<?php echo $currentOrder['status']; ?>')" 
                                    class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                <i class="fas fa-edit mr-2"></i>Update Status
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6">Order Items</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr class="text-left text-xs font-medium text-gray-500 uppercase">
                                    <th class="px-4 py-3">SL</th>
                                    <th class="px-4 py-3">Product</th>
                                    <th class="px-4 py-3">Unit Price</th>
                                    <th class="px-4 py-3">Quantity</th>
                                    <th class="px-4 py-3">Total</th>
                                    <th class="px-4 py-3">Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($currentOrder['items'] as $index => $item): ?>
                                <tr class="border-t border-gray-200">
                                    <td class="px-4 py-3 text-gray-600"><?php echo $index + 1; ?></td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900"><?php echo $item['product_name']; ?></div>
                                        <div class="text-xs text-gray-500">Code: <?php echo $item['product_code'] ?? 'N/A'; ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600"><?php echo formatCurrency($item['unit_price']); ?></td>
                                    <td class="px-4 py-3 text-gray-600"><?php echo $item['quantity']; ?> <?php echo $item['unit'] ?? 'PCS'; ?></td>
                                    <td class="px-4 py-3 font-semibold text-primary"><?php echo formatCurrency($item['total_price']); ?></td>
                                    <td class="px-4 py-3 text-gray-600 text-sm"><?php echo $item['notes'] ?? '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr class="font-bold">
                                    <td colspan="4" class="px-4 py-3 text-right">TOTAL:</td>
                                    <td class="px-4 py-3 text-primary text-lg"><?php echo formatCurrency($currentOrder['total']); ?></td>
                                    <td class="px-4 py-3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Status Update Modal -->
    <div id="status-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Update Order Status</h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" id="status-order-id">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">New Status</label>
                            <select name="status" id="status-select" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="pending">Pending</option>
                                <option value="sent">Sent to Supplier</option>
                                <option value="confirmed">Confirmed by Supplier</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status Notes</label>
                            <textarea name="status_notes" rows="3"
                                      class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
                                      placeholder="Optional notes about status change"></textarea>
                        </div>
                    </div>

                    <div class="flex space-x-3 mt-6">
                        <button type="button" onclick="hideStatusModal()" 
                                class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold py-3 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-primary hover:bg-blue-600 text-white font-semibold py-3 rounded-lg transition-colors">
                            <i class="fas fa-save mr-2"></i>Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let itemCounter = 0;
        
        // Add order item row
        function addOrderItem() {
            itemCounter++;
            const tbody = document.getElementById('items-tbody');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="px-4 py-3 text-gray-600">${itemCounter}</td>
                <td class="px-4 py-3">
                    <select name="items[${itemCounter}][product_id]" required 
                            class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-primary"
                            onchange="loadProductInfo(this, ${itemCounter})">
                        <option value="">Select Product</option>
                        <?php foreach ($products as $prod): ?>
                        <option value="<?php echo $prod['id']; ?>" 
                                data-stock="<?php echo $prod['quantity']; ?>" 
                                data-cost="<?php echo $prod['cost_price']; ?>"
                                data-unit="<?php echo $prod['unit']; ?>">
                            <?php echo $prod['name']; ?> (<?php echo $prod['code']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="items[${itemCounter}][product_name]" id="product_name_${itemCounter}">
                </td>
                <td class="px-4 py-3">
                    <span id="current_stock_${itemCounter}" class="text-sm font-semibold text-gray-600">-</span>
                </td>
                <td class="px-4 py-3">
                    <input type="number" name="items[${itemCounter}][quantity]" step="0.01" min="0.01" required
                           id="quantity_${itemCounter}"
                           class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-primary"
                           onchange="calculateItemTotal(${itemCounter})">
                </td>
                <td class="px-4 py-3">
                    <input type="number" name="items[${itemCounter}][unit_price]" step="0.01" min="0" required
                           id="unit_price_${itemCounter}"
                           class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-primary"
                           onchange="calculateItemTotal(${itemCounter})">
                </td>
                <td class="px-4 py-3">
                    <span id="item_total_${itemCounter}" class="font-semibold text-primary">QR 0.00</span>
                </td>
                <td class="px-4 py-3">
                    <input type="text" name="items[${itemCounter}][notes]" 
                           class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-primary"
                           placeholder="Notes">
                </td>
                <td class="px-4 py-3">
                    <button type="button" onclick="removeItem(this)" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        }

        // Load product information
        function loadProductInfo(select, itemIndex) {
            const selectedOption = select.options[select.selectedIndex];
            const stock = selectedOption.getAttribute('data-stock') || 0;
            const cost = selectedOption.getAttribute('data-cost') || 0;
            const productName = selectedOption.text;
            
            document.getElementById(`current_stock_${itemIndex}`).textContent = stock;
            document.getElementById(`unit_price_${itemIndex}`).value = cost;
            document.getElementById(`product_name_${itemIndex}`).value = productName;
            
            // Suggest order quantity based on stock level
            const currentStock = parseFloat(stock);
            const suggestedQty = currentStock <= 5 ? Math.max(10, currentStock * 2) : Math.max(5, currentStock * 0.5);
            document.getElementById(`quantity_${itemIndex}`).value = suggestedQty;
            
            calculateItemTotal(itemIndex);
        }

        // Calculate item total
        function calculateItemTotal(itemIndex) {
            const quantity = parseFloat(document.getElementById(`quantity_${itemIndex}`).value) || 0;
            const unitPrice = parseFloat(document.getElementById(`unit_price_${itemIndex}`).value) || 0;
            
            const itemTotal = quantity * unitPrice;
            document.getElementById(`item_total_${itemIndex}`).textContent = 'QR ' + itemTotal.toFixed(2);
            
            calculateOrderTotal();
        }

        // Calculate order total
        function calculateOrderTotal() {
            let total = 0;
            const itemTotals = document.querySelectorAll('[id^="item_total_"]');
            
            itemTotals.forEach(totalSpan => {
                const value = parseFloat(totalSpan.textContent.replace('QR ', '')) || 0;
                total += value;
            });
            
            document.getElementById('order-total').textContent = 'QR ' + total.toFixed(2);
        }

        // Remove item
        function removeItem(button) {
            button.closest('tr').remove();
            calculateOrderTotal();
        }

        // Status modal functions
        function showStatusModal(orderId, currentStatus) {
            document.getElementById('status-order-id').value = orderId;
            document.getElementById('status-select').value = currentStatus;
            document.getElementById('status-modal').classList.remove('hidden');
        }

        function hideStatusModal() {
            document.getElementById('status-modal').classList.add('hidden');
        }

        // Print purchase order
        function printPO(orderId) {
            window.open(`../prints/purchase-order.php?id=${orderId}`, '_blank');
        }

        // Generate reorder suggestions
        function generateReorderSuggestions() {
            alert('This feature will analyze low stock products and suggest optimal reorder quantities.');
        }

        // Add initial item row
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('items-tbody')) {
                addOrderItem();
            }
        });
    </script>
</body>
</html>