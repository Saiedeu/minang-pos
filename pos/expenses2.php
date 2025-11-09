<?php
/**
 * POS System - Expense Management
 * View and pay purchase invoices, track cash expenses
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';
require_once 'includes/auth-check.php';

$user = User::getCurrentUser();
$purchase = new Purchase();
$error = '';
$success = '';

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_invoice'])) {
    $purchaseId = intval($_POST['purchase_id']);
    $paymentAmount = floatval($_POST['payment_amount']);
    $paymentMethod = 1; // Cash payment
    
    $result = $purchase->makePayment($purchaseId, $paymentAmount, $paymentMethod);
    if ($result['success']) {
        $success = 'Payment recorded successfully';
        // Generate payment receipt
        $paymentReceiptData = [
            'purchase_id' => $purchaseId,
            'amount' => $paymentAmount,
            'date' => date('Y-m-d H:i:s')
        ];
        $_SESSION['payment_receipt'] = $paymentReceiptData;
    } else {
        $error = $result['message'];
    }
}

// Get unpaid purchases
$unpaidPurchases = $purchase->getUnpaidPurchases();

// Search functionality
$searchTerm = $_GET['search'] ?? '';
if ($searchTerm) {
    $unpaidPurchases = $purchase->searchPurchases($searchTerm);
}

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
    <!-- Header -->
    <header class="bg-gradient-to-r from-primary to-blue-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-white hover:text-blue-200">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold text-white">Expense Management</h1>
                        <p class="text-blue-100">Pay supplier invoices and track cash expenses</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="../erp/purchases/invoices.php?action=add" 
                       class="bg-white bg-opacity-20 text-white px-4 py-2 rounded-lg font-medium hover:bg-opacity-30 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add New Purchase
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Alerts -->
        <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
            <p class="text-red-700"><?php echo $error; ?></p>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
            <p class="text-green-700"><?php echo $success; ?></p>
            <?php if (isset($_SESSION['payment_receipt'])): ?>
            <div class="mt-2">
                <button onclick="printPaymentReceipt()" class="text-green-600 hover:text-green-800 font-medium">
                    <i class="fas fa-print mr-2"></i>Print Payment Receipt
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Search -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Search Purchase Invoices</h2>
                <form method="GET" class="flex items-center space-x-2">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>"
                           placeholder="Search by invoice number or supplier..."
                           class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                    <button type="submit" class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if ($searchTerm): ?>
                    <a href="expenses.php" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-lg">
                        <i class="fas fa-times"></i>
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Unpaid Invoices -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-800">
                        <?php echo $searchTerm ? 'Search Results' : 'Outstanding Purchase Invoices'; ?>
                    </h2>
                    <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-medium">
                        <?php echo count($unpaidPurchases); ?> invoices
                    </span>
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
                            <th class="px-6 py-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($unpaidPurchases as $invoice): ?>
                        <?php $outstanding = $invoice['total'] - $invoice['paid_amount']; ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-medium text-gray-900"><?php echo $invoice['invoice_number']; ?></td>
                            <td class="px-6 py-4 text-gray-600"><?php echo $invoice['supplier_name']; ?></td>
                            <td class="px-6 py-4 text-gray-600"><?php echo formatDate($invoice['purchase_date']); ?></td>
                            <td class="px-6 py-4 font-semibold"><?php echo formatCurrency($invoice['total']); ?></td>
                            <td class="px-6 py-4 text-green-600"><?php echo formatCurrency($invoice['paid_amount']); ?></td>
                            <td class="px-6 py-4 font-semibold text-red-600"><?php echo formatCurrency($outstanding); ?></td>
                            <td class="px-6 py-4">
                                <div class="flex space-x-2">
                                    <?php if ($outstanding > 0): ?>
                                    <button onclick="showPaymentModal(<?php echo $invoice['id']; ?>, '<?php echo $invoice['invoice_number']; ?>', '<?php echo $invoice['supplier_name']; ?>', <?php echo $outstanding; ?>)" 
                                            class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm font-medium">
                                        <i class="fas fa-money-bill-wave mr-1"></i>Pay
                                    </button>
                                    <?php endif; ?>
                                    <a href="../erp/purchases/invoices.php?action=view&id=<?php echo $invoice['id']; ?>" 
                                       class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm font-medium">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (empty($unpaidPurchases)): ?>
            <div class="text-center py-12">
                <i class="fas fa-check-circle text-4xl text-green-500 mb-4"></i>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">All Caught Up!</h3>
                <p class="text-gray-600">No outstanding purchase invoices found</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="payment-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Make Cash Payment</h3>
                
                <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                    <div class="text-sm space-y-1">
                        <div><strong>Invoice:</strong> <span id="modal-invoice-number"></span></div>
                        <div><strong>Supplier:</strong> <span id="modal-supplier-name"></span></div>
                        <div><strong>Outstanding:</strong> <span id="modal-outstanding" class="text-red-600 font-semibold"></span></div>
                    </div>
                </div>

                <form method="POST" id="payment-form">
                    <input type="hidden" name="pay_invoice" value="1">
                    <input type="hidden" name="purchase_id" id="modal-purchase-id">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Amount (Cash)</label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-gray-500"><?php echo CURRENCY_SYMBOL; ?></span>
                                <input type="number" name="payment_amount" step="0.01" min="0.01" required
                                       id="modal-payment-amount"
                                       class="w-full pl-12 pr-4 py-3 text-xl font-bold text-center border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                       placeholder="0.00">
                            </div>
                            <div class="flex justify-between mt-2">
                                <button type="button" onclick="setPartialPayment()" class="text-sm text-blue-600 hover:text-blue-800">
                                    Partial Payment
                                </button>
                                <button type="button" onclick="setFullPayment()" class="text-sm text-green-600 hover:text-green-800">
                                    Full Payment
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex space-x-3 mt-6">
                        <button type="button" onclick="hidePaymentModal()" 
                                class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold py-3 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-green-500 hover:bg-green-600 text-white font-semibold py-3 rounded-lg transition-colors">
                            <i class="fas fa-money-bill-wave mr-2"></i>Pay Cash
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentOutstanding = 0;

        function showPaymentModal(purchaseId, invoiceNumber, supplierName, outstanding) {
            document.getElementById('modal-purchase-id').value = purchaseId;
            document.getElementById('modal-invoice-number').textContent = invoiceNumber;
            document.getElementById('modal-supplier-name').textContent = supplierName;
            document.getElementById('modal-outstanding').textContent = 'QR ' + outstanding.toFixed(2);
            document.getElementById('modal-payment-amount').value = outstanding.toFixed(2);
            
            currentOutstanding = outstanding;
            
            document.getElementById('payment-modal').classList.remove('hidden');
            document.getElementById('modal-payment-amount').focus();
        }

        function hidePaymentModal() {
            document.getElementById('payment-modal').classList.add('hidden');
        }

        function setPartialPayment() {
            const partialAmount = currentOutstanding * 0.5;
            document.getElementById('modal-payment-amount').value = partialAmount.toFixed(2);
        }

        function setFullPayment() {
            document.getElementById('modal-payment-amount').value = currentOutstanding.toFixed(2);
        }

        // Print payment receipt
        function printPaymentReceipt() {
            <?php if (isset($_SESSION['payment_receipt'])): ?>
            const receiptData = <?php echo json_encode($_SESSION['payment_receipt']); ?>;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Payment Receipt</title>
                        <style>
                            body { font-family: 'Courier New', monospace; font-size: 12px; width: 80mm; }
                            .center { text-align: center; }
                            .separator { border-top: 1px dashed black; margin: 5px 0; }
                        </style>
                    </head>
                    <body>
                        <div class="center">
                            <div><strong><?php echo BUSINESS_NAME; ?></strong></div>
                            <div><?php echo BUSINESS_ADDRESS; ?></div>
                            <div class="separator"></div>
                            <div><strong>PAYMENT RECEIPT</strong></div>
                            <div class="separator"></div>
                        </div>
                        
                        <div>
                            <div>Date: ${receiptData.date}</div>
                            <div>Purchase ID: ${receiptData.purchase_id}</div>
                            <div>Payment Amount: QR ${receiptData.amount}</div>
                            <div>Payment Method: Cash</div>
                            <div>Processed By: <?php echo $user['name']; ?></div>
                        </div>
                        
                        <div class="center" style="margin-top: 10px;">
                            <div class="separator"></div>
                            <div><strong>THANK YOU!</strong></div>
                        </div>
                    </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.print();
            <?php unset($_SESSION['payment_receipt']); ?>
            <?php endif; ?>
        }

        // Auto-print receipt if payment was successful
        <?php if (isset($_SESSION['payment_receipt'])): ?>
        setTimeout(() => {
            if (confirm('Print payment receipt?')) {
                printPaymentReceipt();
            }
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>