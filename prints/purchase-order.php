<?php
/**
 * Purchase Order Print Template
 * Generate printable purchase order for suppliers
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

// Check authentication
if (!User::isLoggedIn()) {
    exit('Unauthorized');
}

$orderId = $_GET['id'] ?? 0;
if (!$orderId) {
    exit('Invalid order ID');
}

$db = Database::getInstance();

// Get purchase order data
$order = $db->fetchOne("
    SELECT po.*, s.name as supplier_name, s.contact_person, s.phone as supplier_phone,
           s.email as supplier_email, s.address as supplier_address,
           u.name as created_by_name
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    LEFT JOIN users u ON po.created_by = u.id
    WHERE po.id = ?
", [$orderId]);

if (!$order) {
    exit('Purchase order not found');
}

// Get order items
$items = $db->fetchAll("
    SELECT poi.*, p.code as product_code, p.unit
    FROM purchase_order_items poi
    LEFT JOIN products p ON poi.product_id = p.id
    WHERE poi.order_id = ?
    ORDER BY poi.id
", [$orderId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order - <?php echo $order['order_number']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            @page { 
                size: A4; 
                margin: 20mm;
            }
            body { 
                font-family: 'Arial', sans-serif; 
                font-size: 12px;
                line-height: 1.4;
                color: black;
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-white">
    <!-- Print Controls -->
    <div class="no-print bg-gray-100 p-4 flex justify-between items-center">
        <a href="../erp/purchases/purchase-orders.php" class="flex items-center text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back to Purchase Orders
        </a>
        <button onclick="window.print()" class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
            <i class="fas fa-print mr-2"></i>Print Purchase Order
        </button>
    </div>

    <!-- Purchase Order Content -->
    <div class="max-w-4xl mx-auto bg-white p-8">
        <!-- Header -->
        <div class="flex justify-between items-start mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800"><?php echo BUSINESS_NAME; ?></h1>
                <p class="text-gray-600"><?php echo BUSINESS_ADDRESS; ?></p>
                <p class="text-gray-600">Tel: <?php echo BUSINESS_PHONE; ?></p>
                <p class="text-gray-600">Email: <?php echo BUSINESS_EMAIL; ?></p>
            </div>
            <div class="text-right">
                <h2 class="text-2xl font-bold text-primary">PURCHASE ORDER</h2>
                <div class="mt-2 text-sm">
                    <div><strong>PO Number:</strong> <?php echo $order['order_number']; ?></div>
                    <div><strong>Date:</strong> <?php echo formatDate($order['order_date']); ?></div>
                    <div><strong>Page:</strong> 1 of 1</div>
                </div>
            </div>
        </div>

        <!-- Supplier Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-3 bg-gray-100 p-3 rounded">SUPPLIER INFORMATION</h3>
                <div class="space-y-2 text-sm pl-3">
                    <div><strong>Company:</strong> <?php echo $order['supplier_name']; ?></div>
                    <?php if ($order['contact_person']): ?>
                    <div><strong>Contact Person:</strong> <?php echo $order['contact_person']; ?></div>
                    <?php endif; ?>
                    <?php if ($order['supplier_phone']): ?>
                    <div><strong>Phone:</strong> <?php echo $order['supplier_phone']; ?></div>
                    <?php endif; ?>
                    <?php if ($order['supplier_email']): ?>
                    <div><strong>Email:</strong> <?php echo $order['supplier_email']; ?></div>
                    <?php endif; ?>
                    <?php if ($order['supplier_address']): ?>
                    <div><strong>Address:</strong> <?php echo $order['supplier_address']; ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-3 bg-gray-100 p-3 rounded">DELIVERY INFORMATION</h3>
                <div class="space-y-2 text-sm pl-3">
                    <div><strong>Delivery Address:</strong></div>
                    <div class="ml-4">
                        <?php echo BUSINESS_NAME; ?><br>
                        <?php echo BUSINESS_ADDRESS; ?><br>
                        Tel: <?php echo BUSINESS_PHONE; ?>
                    </div>
                    <?php if ($order['expected_delivery']): ?>
                    <div class="mt-3"><strong>Expected Delivery:</strong> <?php echo formatDate($order['expected_delivery']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="mb-8">
            <table class="w-full border border-gray-300">
                <thead class="bg-gray-100">
                    <tr class="text-left text-sm font-semibold text-gray-800">
                        <th class="border border-gray-300 px-4 py-3">SL</th>
                        <th class="border border-gray-300 px-4 py-3">Product Description</th>
                        <th class="border border-gray-300 px-4 py-3">Product Code</th>
                        <th class="border border-gray-300 px-4 py-3 text-center">Quantity</th>
                        <th class="border border-gray-300 px-4 py-3 text-right">Unit Price</th>
                        <th class="border border-gray-300 px-4 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $index => $item): ?>
                    <tr>
                        <td class="border border-gray-300 px-4 py-3 text-center"><?php echo $index + 1; ?></td>
                        <td class="border border-gray-300 px-4 py-3">
                            <div class="font-semibold"><?php echo $item['product_name']; ?></div>
                            <?php if ($item['notes']): ?>
                            <div class="text-xs text-gray-600 mt-1"><?php echo $item['notes']; ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="border border-gray-300 px-4 py-3 text-center"><?php echo $item['product_code'] ?? 'N/A'; ?></td>
                        <td class="border border-gray-300 px-4 py-3 text-center"><?php echo $item['quantity']; ?> <?php echo $item['unit'] ?? 'PCS'; ?></td>
                        <td class="border border-gray-300 px-4 py-3 text-right"><?php echo formatCurrency($item['unit_price']); ?></td>
                        <td class="border border-gray-300 px-4 py-3 text-right font-semibold"><?php echo formatCurrency($item['total_price']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr class="font-bold">
                        <td colspan="5" class="border border-gray-300 px-4 py-3 text-right">TOTAL AMOUNT:</td>
                        <td class="border border-gray-300 px-4 py-3 text-right text-primary text-lg"><?php echo formatCurrency($order['total']); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Terms and Conditions -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">TERMS AND CONDITIONS</h3>
            <div class="text-sm text-gray-700 space-y-1">
                <div>1. Please confirm receipt of this purchase order within 24 hours.</div>
                <div>2. All items should be delivered as per specifications and quality standards.</div>
                <div>3. Invoice should reference this PO number: <strong><?php echo $order['order_number']; ?></strong></div>
                <div>4. Payment terms: Net 30 days from delivery date.</div>
                <div>5. Please notify immediately if any items are unavailable or delayed.</div>
                <?php if ($order['expected_delivery']): ?>
                <div>6. Expected delivery date: <strong><?php echo formatDate($order['expected_delivery']); ?></strong></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($order['notes']): ?>
        <!-- Special Instructions -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">SPECIAL INSTRUCTIONS</h3>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Signatures -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-16">
            <div>
                <div class="border-b border-gray-400 mb-2" style="height: 40px;"></div>
                <p class="text-sm text-center">
                    <strong>Prepared By</strong><br>
                    <?php echo $order['created_by_name']; ?><br>
                    <?php echo formatDate($order['created_at']); ?>
                </p>
            </div>
            <div>
                <div class="border-b border-gray-400