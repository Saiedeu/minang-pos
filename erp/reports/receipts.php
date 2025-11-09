<?php
/**
 * ERP System - Receipt Archive
 * Browse and reprint all receipts
 */

define('MINANG_SYSTEM', true);
require_once '../../config/config.php';
require_once '../includes/auth-check.php';

$receipt = new Receipt();
$sale = new Sale();
$db = Database::getInstance();

// Get filter parameters
$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$searchTerm = $_GET['search'] ?? '';

// Get receipt archive
$receipts = $receipt->getReceiptArchive($startDate, $endDate);

// Filter by search term if provided
if ($searchTerm) {
    $receipts = array_filter($receipts, function($receipt) use ($searchTerm) {
        return stripos($receipt['receipt_number'], $searchTerm) !== false ||
               stripos($receipt['order_number'], $searchTerm) !== false ||
               stripos($receipt['customer_name'], $searchTerm) !== false;
    });
}

// Handle reprint request
if (isset($_GET['reprint']) && !empty($_GET['receipt_id'])) {
    $receiptId = intval($_GET['receipt_id']);
    $receiptHtml = $receipt->printReceipt($receiptId);
    
    if ($receiptHtml) {
        // Return receipt for printing
        echo $receiptHtml;
        exit();
    } else {
        $error = 'Receipt not found or cannot be reprinted';
    }
}

$pageTitle = 'Receipt Archive';
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
                    <h1 class="text-3xl font-bold text-gray-800">Receipt Archive</h1>
                    <p class="text-gray-600">Browse and reprint all transaction receipts</p>
                </div>
                <div class="flex space-x-3">
                    <button onclick="exportReceiptList()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-download mr-2"></i>Export List
                    </button>
                </div>
            </div>

            <!-- Search and Filters -->
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
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>"
                               placeholder="Receipt #, Order #, Customer..."
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-primary hover:bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                            <i class="fas fa-search mr-2"></i>Search
                        </button>
                    </div>
                </form>
            </div>

            <!-- Receipt Archive -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-800">Receipt Archive</h2>
                        <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                            <?php echo count($receipts); ?> receipts
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Receipt #</th>
                                <th class="px-6 py-4">Order #</th>
                                <th class="px-6 py-4">Date & Time</th>
                                <th class="px-6 py-4">Customer</th>
                                <th class="px-6 py-4">Order Type</th>
                                <th class="px-6 py-4">Total</th>
                                <th class="px-6 py-4">Payment</th>
                                <th class="px-6 py-4">Cashier</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($receipts as $receiptItem): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium text-primary">
                                    <a href="?reprint=1&receipt_id=<?php echo $receiptItem['id']; ?>" target="_blank" class="hover:underline">
                                        <?php echo $receiptItem['receipt_number']; ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-gray-900"><?php echo $receiptItem['order_number']; ?></td>
                                <td class="px-6 py-4 text-gray-600"><?php echo formatDateTime($receiptItem['created_at']); ?></td>
                                <td class="px-6 py-4 text-gray-600">
                                    <?php if ($receiptItem['customer_name']): ?>
                                        <div><?php echo $receiptItem['customer_name']; ?></div>
                                        <div class="text-xs text-gray-500"><?php echo $receiptItem['customer_phone']; ?></div>
                                    <?php else: ?>
                                        <span class="text-gray-400">Walk-in Customer</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full <?php
                                        $orderTypeColors = [1 => 'bg-blue-100 text-blue-800', 2 => 'bg-green-100 text-green-800', 3 => 'bg-purple-100 text-purple-800'];
                                        echo $orderTypeColors[$receiptItem['order_type']] ?? 'bg-gray-100 text-gray-800';
                                    ?>">
                                        <?php
                                            $orderTypes = [1 => 'Dine-In', 2 => 'Take Away', 3 => 'Delivery'];
                                            echo $orderTypes[$receiptItem['order_type']] ?? 'Unknown';
                                        ?>
                                    </span>
                                    <?php if ($receiptItem['order_type'] == 1 && $receiptItem['table_number']): ?>
                                    <div class="text-xs text-gray-500 mt-1">Table <?php echo $receiptItem['table_number']; ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 font-semibold text-gray-900"><?php echo formatCurrency($receiptItem['total']); ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full <?php
                                        $paymentColors = [1 => 'bg-green-100 text-green-800', 2 => 'bg-blue-100 text-blue-800', 3 => 'bg-yellow-100 text-yellow-800', 4 => 'bg-red-100 text-red-800', 5 => 'bg-orange-100 text-orange-800'];
                                        echo $paymentColors[$receiptItem['payment_method']] ?? 'bg-gray-100 text-gray-800';
                                    ?>">
                                        <?php
                                            $paymentMethods = [1 => 'Cash', 2 => 'Card', 3 => 'Credit', 4 => 'FOC', 5 => 'COD'];
                                            echo $paymentMethods[$receiptItem['payment_method']] ?? 'Unknown';
                                        ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-600"><?php echo $receiptItem['cashier_name']; ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-2">
                                        <?php if ($receiptItem['is_printed']): ?>
                                        <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">
                                            <i class="fas fa-check mr-1"></i>Printed
                                        </span>
                                        <?php else: ?>
                                        <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">Not Printed</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <button onclick="reprintReceipt(<?php echo $receiptItem['id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-800 text-sm" title="Reprint Receipt">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <button onclick="viewReceiptDetails(<?php echo $receiptItem['id']; ?>)" 
                                                class="text-green-600 hover:text-green-800 text-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="emailReceipt(<?php echo $receiptItem['id']; ?>)" 
                                                class="text-purple-600 hover:text-purple-800 text-sm" title="Email Receipt">
                                            <i class="fas fa-envelope"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($receipts)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-receipt text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">No Receipts Found</h3>
                    <p class="text-gray-600">No receipts match your search criteria</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Receipt Details Modal -->
    <div id="receipt-details-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-gray-800">Receipt Details</h3>
                    <button onclick="hideReceiptDetails()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="receipt-details-content">
                    <!-- Content will be loaded via JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Reprint receipt
        function reprintReceipt(receiptId) {
            const printWindow = window.open(`?reprint=1&receipt_id=${receiptId}`, '_blank', 'width=400,height=600');
            if (printWindow) {
                printWindow.addEventListener('load', function() {
                    printWindow.print();
                });
            }
        }

        // View receipt details
        async function viewReceiptDetails(receiptId) {
            try {
                const response = await fetch(`../api/sales.php?action=get_sale&sale_id=${receiptId}`);
                const result = await response.json();
                
                if (result.success) {
                    displayReceiptDetails(result.data);
                } else {
                    alert('Failed to load receipt details');
                }
            } catch (error) {
                alert('Error loading receipt details');
            }
        }

        function displayReceiptDetails(sale) {
            const content = document.getElementById('receipt-details-content');
            const orderTypes = {1: 'Dine-In', 2: 'Take Away', 3: 'Delivery'};
            const paymentMethods = {1: 'Cash', 2: 'Card', 3: 'Credit', 4: 'FOC', 5: 'COD'};
            
            let itemsHtml = '';
            sale.items.forEach(item => {
                itemsHtml += `
                    <tr class="border-t">
                        <td class="py-2">${item.product_name}</td>
                        <td class="py-2 text-center">${item.quantity}</td>
                        <td class="py-2 text-right">QR ${parseFloat(item.unit_price).toFixed(2)}</td>
                        <td class="py-2 text-right font-semibold">QR ${parseFloat(item.total_price).toFixed(2)}</td>
                    </tr>
                `;
            });
            
            content.innerHTML = `
                <div class="space-y-6">
                    <!-- Receipt Header -->
                    <div class="grid grid-cols-2 gap-6 text-sm">
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-2">Order Information</h4>
                            <div class="space-y-1">
                                <div><strong>Receipt:</strong> ${sale.receipt_number}</div>
                                <div><strong>Order:</strong> ${sale.order_number}</div>
                                <div><strong>Date:</strong> ${new Date(sale.created_at).toLocaleString()}</div>
                                <div><strong>Type:</strong> ${orderTypes[sale.order_type] || 'Unknown'}</div>
                                ${sale.table_number ? `<div><strong>Table:</strong> ${sale.table_number}</div>` : ''}
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-2">Customer & Payment</h4>
                            <div class="space-y-1">
                                <div><strong>Customer:</strong> ${sale.customer_name || 'Walk-in Customer'}</div>
                                ${sale.customer_phone ? `<div><strong>Phone:</strong> ${sale.customer_phone}</div>` : ''}
                                <div><strong>Payment:</strong> ${paymentMethods[sale.payment_method] || 'Unknown'}</div>
                                <div><strong>Cashier:</strong> ${sale.cashier_name}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div>
                        <h4 class="font-semibold text-gray-800 mb-3">Order Items</h4>
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-left py-2 px-2">Item</th>
                                    <th class="text-center py-2 px-2">Qty</th>
                                    <th class="text-right py-2 px-2">Price</th>
                                    <th class="text-right py-2 px-2">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${itemsHtml}
                            </tbody>
                        </table>
                    </div>

                    <!-- Totals -->
                    <div class="border-t pt-4">
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span>Subtotal:</span>
                                <span>QR ${parseFloat(sale.subtotal).toFixed(2)}</span>
                            </div>
                            ${sale.discount > 0 ? `
                            <div class="flex justify-between text-red-600">
                                <span>Discount:</span>
                                <span>-QR ${parseFloat(sale.discount).toFixed(2)}</span>
                            </div>
                            ` : ''}
                            ${sale.delivery_fee > 0 ? `
                            <div class="flex justify-between">
                                <span>Delivery Fee:</span>
                                <span>QR ${parseFloat(sale.delivery_fee).toFixed(2)}</span>
                            </div>
                            ` : ''}
                            <div class="flex justify-between text-lg font-bold border-t pt-2">
                                <span>TOTAL:</span>
                                <span class="text-primary">QR ${parseFloat(sale.total).toFixed(2)}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-center space-x-4">
                        <button onclick="reprintReceipt(${sale.id})" class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-print mr-2"></i>Reprint Receipt
                        </button>
                        <button onclick="emailReceipt(${sale.id})" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-envelope mr-2"></i>Email Receipt
                        </button>
                    </div>
                </div>
            `;
            
            document.getElementById('receipt-details-modal').classList.remove('hidden');
        }

        function hideReceiptDetails() {
            document.getElementById('receipt-details-modal').classList.add('hidden');
        }

        function emailReceipt(receiptId) {
            // Implementation for email receipt
            alert('Email receipt functionality coming soon');
        }

        function exportReceiptList() {
            const startDate = '<?php echo $startDate; ?>';
            const endDate = '<?php echo $endDate; ?>';
            const search = '<?php echo $searchTerm; ?>';
            
            const params = new URLSearchParams({
                action: 'sales',
                start_date: startDate,
                end_date: endDate
            });
            
            window.open(`../../utilities/data-export.php?${params}`, '_blank');
        }
    </script>
</body>
</html>