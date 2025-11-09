<?php
/**
 * POS System - Expense Management
 * Handle supplier payments and cash expenses
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';
require_once 'includes/auth-check.php';

$purchase = new Purchase();
$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'pay') {
    $purchaseId = intval($_POST['purchase_id']);
    $paymentAmount = floatval($_POST['payment_amount']);
    
    $result = $purchase->makePayment($purchaseId, $paymentAmount, 1); // Cash payment
    
    if ($result['success']) {
        $success = 'Payment recorded successfully. Amount: ' . formatCurrency($paymentAmount);
        
        // Generate payment receipt
        $receiptData = [
            'purchase_id' => $purchaseId,
            'payment_amount' => $paymentAmount,
            'payment_date' => date('Y-m-d H:i:s')
        ];
        
        $_SESSION['payment_receipt'] = $receiptData;
        header('Location: expenses.php?action=receipt&purchase_id=' . $purchaseId);
        exit();
    } else {
        $error = $result['message'];
    }
}

// Get unpaid purchases
$unpaidPurchases = $purchase->getUnpaidPurchases();

// Get today's cash payments
$todayCashPayments = Database::getInstance()->fetchAll("
    SELECT p.invoice_number, s.name as supplier_name, e.amount, e.created_at
    FROM expenses e
    LEFT JOIN purchases p ON CAST(SUBSTRING(e.description, LOCATE('#', e.description) + 1) AS UNSIGNED) = p.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE DATE(e.created_at) = CURDATE() AND e.payment_method = 1
    ORDER BY e.created_at DESC
");

// Get purchase for receipt
$receiptPurchase = null;
if ($action === 'receipt' && isset($_GET['purchase_id'])) {
    $receiptPurchase = $purchase->getPurchaseById($_GET['purchase_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Management - <?php echo BUSINESS_NAME; ?></title>
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
    <header class="bg-gradient-to-r from-red-500 to-red-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-white hover:text-red-200">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <div>
                        <h1 class="text-xl font-semibold text-white">Expense Management</h1>
                        <p class="text-red-100 text-sm">Handle supplier payments and cash expenses</p>
                    </div>
                </div>

                <div class="flex items-center space-x-4">
                    <div class="text-white text-center">
                        <div id="current-time" class="text-lg font-semibold"></div>
                        <div id="current-date" class="text-xs text-red-100"></div>
                    </div>
                    <a href="../erp/purchases/invoices.php?action=add" 
                       class="bg-white text-red-600 px-4 py-2 rounded-lg font-medium hover:bg-red-50 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add New Purchase
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Alerts -->
        <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
            <p class="text-red-700"><?php echo $error; ?></p>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
            <p class="text-green-700"><?php echo $success; ?></p>
        </div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Unpaid Purchases -->
            <div class="bg-white rounded-xl shadow-lg">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-800">Unpaid Purchases</h2>
                        <span class="bg-red-100 text-red-800 text-xs font-semibold px-2 py-1 rounded-full">
                            <?php echo count($unpaidPurchases); ?> invoices
                        </span>
                    </div>
                </div>

                <div class="p-6">
                    <?php if (empty($unpaidPurchases)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-check-circle text-4xl text-green-400 mb-4"></i>
                        <p class="text-gray-500">All invoices are paid!</p>
                    </div>
                    <?php else: ?>
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        <?php foreach ($unpaidPurchases as $purchase): ?>
                        <?php $outstanding = $purchase['total'] - $purchase['paid_amount']; ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <h4 class="font-semibold text-gray-900"><?php echo $purchase['invoice_number']; ?></h4>
                                    <p class="text-sm text-gray-600"><?php echo $purchase['supplier_name']; ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-lg font-bold text-red-600"><?php echo formatCurrency($outstanding); ?></p>
                                    <p class="text-xs text-gray-500">Outstanding</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between text-sm text-gray-600 mb-3">
                                <span>Date: <?php echo formatDate($purchase['purchase_date']); ?></span>
                                <span>Total: <?php echo formatCurrency($purchase['total']); ?></span>
                            </div>
                            
                            <div class="flex space-x-2">
                                <button onclick="showPaymentModal(<?php echo $purchase['id']; ?>, '<?php echo $purchase['invoice_number']; ?>', '<?php echo $purchase['supplier_name']; ?>', <?php echo $outstanding; ?>)"
                                        class="flex-1 bg-green-500 hover:bg-green-600 text-white text-sm font-semibold py-2 rounded-lg transition-colors">
                                    <i class="fas fa-money-bill-wave mr-2"></i>Make Payment
                                </button>
                                <a href="../erp/purchases/invoices.php?action=view&id=<?php echo $purchase['id']; ?>" 
                                   class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-lg transition-colors">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Today's Cash Payments -->
            <div class="bg-white rounded-xl shadow-lg">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">Today's Cash Payments</h2>
                    <p class="text-sm text-gray-600">These amounts count as H_cash in shift closing</p>
                </div>

                <div class="p-6">
                    <?php if (empty($todayCashPayments)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-money-bill-wave text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No cash payments today</p>
                    </div>
                    <?php else: ?>
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        <?php 
                        $totalCashToday = 0;
                        foreach ($todayCashPayments as $payment): 
                            $totalCashToday += $payment['amount'];
                        ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div>
                                <p class="font-semibold text-gray-900"><?php echo $payment['invoice_number']; ?></p>
                                <p class="text-sm text-gray-600"><?php echo $payment['supplier_name']; ?></p>
                                <p class="text-xs text-gray-500"><?php echo date('H:i', strtotime($payment['created_at'])); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-bold text-red-600">-<?php echo formatCurrency($payment['amount']); ?></p>
                                <p class="text-xs text-gray-500">Cash Out</p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="border-t border-gray-200 pt-3 mt-4">
                            <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                                <span class="font-semibold text-gray-800">Total Cash Out Today:</span>
                                <span class="text-xl font-bold text-red-600">-<?php echo formatCurrency($totalCashToday); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php elseif ($action === 'receipt' && $receiptPurchase): ?>
        <!-- Payment Receipt -->
        <div class="max-w-md mx-auto">
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <div class="text-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Payment Receipt</h2>
                    <p class="text-sm text-gray-600">Supplier Payment Confirmation</p>
                </div>

                <div id="payment-receipt" class="border-2 border-gray-300 rounded-lg p-4 font-mono text-sm">
                    <div class="text-center mb-4">
                        <div class="font-bold"><?php echo BUSINESS_NAME; ?></div>
                        <div class="text-xs"><?php echo BUSINESS_ADDRESS; ?></div>
                        <div class="text-xs">Tel: <?php echo BUSINESS_PHONE; ?></div>
                        <div class="text-xs">===============================</div>
                        <div class="font-bold mt-2">SUPPLIER PAYMENT RECEIPT</div>
                        <div class="text-xs">===============================</div>
                    </div>

                    <div class="text-xs mb-4">
                        <div>Receipt No: PAY<?php echo date('Ymd') . sprintf('%04d', $receiptPurchase['id']); ?></div>
                        <div>Date & Time: <?php echo date('d/m/Y H:i:s'); ?></div>
                        <div>Paid by: <?php echo User::getCurrentUser()['name']; ?></div>
                        <div>-------------------------------</div>
                    </div>

                    <div class="mb-4 text-xs">
                        <div>Invoice No: <?php echo $receiptPurchase['invoice_number']; ?></div>
                        <div>Supplier: <?php echo $receiptPurchase['supplier_name']; ?></div>
                        <div>Invoice Date: <?php echo formatDate($receiptPurchase['purchase_date']); ?></div>
                        <div>-------------------------------</div>
                    </div>

                    <div class="mb-4 text-xs">
                        <div class="flex justify-between">
                            <span>Invoice Total:</span>
                            <span><?php echo formatCurrency($receiptPurchase['total']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Previous Payments:</span>
                            <span><?php echo formatCurrency($receiptPurchase['paid_amount'] - ($_SESSION['payment_receipt']['payment_amount'] ?? 0)); ?></span>
                        </div>
                        <div class="flex justify-between font-bold">
                            <span>This Payment:</span>
                            <span><?php echo formatCurrency($_SESSION['payment_receipt']['payment_amount'] ?? 0); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Remaining Balance:</span>
                            <span><?php echo formatCurrency($receiptPurchase['total'] - $receiptPurchase['paid_amount']); ?></span>
                        </div>
                        <div>-------------------------------</div>
                    </div>

                    <div class="text-center text-xs">
                        <div>Payment Method: CASH</div>
                        <div class="mt-2 font-bold">PAYMENT SUCCESSFUL</div>
                        <div>Thank you!</div>
                    </div>
                </div>

                <div class="flex space-x-3 mt-6">
                    <button onclick="printPaymentReceipt()" 
                            class="flex-1 bg-primary hover:bg-blue-600 text-white font-semibold py-3 rounded-lg transition-colors">
                        <i class="fas fa-print mr-2"></i>Print Receipt
                    </button>
                    <a href="expenses.php" 
                       class="flex-1 bg-green-500 hover:bg-green-600 text-white font-semibold py-3 rounded-lg text-center transition-colors">
                        <i class="fas fa-check mr-2"></i>Done
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Payment Modal -->
    <div id="payment-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-gray-800">Make Cash Payment</h3>
                    <button onclick="hidePaymentModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <form method="POST" action="?action=pay" id="payment-form">
                    <input type="hidden" name="purchase_id" id="payment-purchase-id">
                    
                    <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                        <div class="text-sm text-gray-600 mb-2">Invoice Details:</div>
                        <div class="font-semibold text-gray-900" id="payment-invoice-number"></div>
                        <div class="text-sm text-gray-600" id="payment-supplier-name"></div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Outstanding Amount</label>
                        <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                            <span id="outstanding-amount" class="text-xl font-bold text-red-600">QR 0.00</span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Amount (QR)</label>
                        <div class="relative">
                            <input type="number" name="payment_amount" step="0.01" min="0" required
                                   id="payment-amount-input"
                                   class="w-full p-3 text-lg font-bold text-center border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                   placeholder="0.00">
                            <button type="button" onclick="setFullAmount()" 
                                    class="absolute right-2 top-2 bg-blue-500 text-white text-xs px-2 py-1 rounded">
                                Full Amount
                            </button>
                        </div>
                    </div>

                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                            <p class="text-yellow-800 text-sm">
                                This cash payment will be deducted from your shift closing as <strong>H_cash</strong>
                            </p>
                        </div>
                    </div>

                    <div class="flex space-x-3">
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

        // Update clock
        function updateClock() {
            const now = new Date();
            document.getElementById('current-time').textContent = now.toLocaleTimeString('en-US', {
                hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
            document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', {
                weekday: 'short', year: 'numeric', month: 'short', day: 'numeric'
            });
        }

        // Show payment modal
        function showPaymentModal(purchaseId, invoiceNumber, supplierName, outstandingAmount) {
            document.getElementById('payment-purchase-id').value = purchaseId;
            document.getElementById('payment-invoice-number').textContent = invoiceNumber;
            document.getElementById('payment-supplier-name').textContent = supplierName;
            document.getElementById('outstanding-amount').textContent = 'QR ' + outstandingAmount.toFixed(2);
            document.getElementById('payment-amount-input').value = outstandingAmount.toFixed(2);
            
            currentOutstanding = outstandingAmount;
            document.getElementById('payment-modal').classList.remove('hidden');
            
            setTimeout(() => {
                document.getElementById('payment-amount-input').focus();
                document.getElementById('payment-amount-input').select();
            }, 100);
        }

        // Hide payment modal
        function hidePaymentModal() {
            document.getElementById('payment-modal').classList.add('hidden');
        }

        // Set full amount
        function setFullAmount() {
            document.getElementById('payment-amount-input').value = currentOutstanding.toFixed(2);
        }

        // Print payment receipt
        function printPaymentReceipt() {
            const receiptContent = document.getElementById('payment-receipt').outerHTML;
            const printWindow = window.open('', '_blank');
            
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Payment Receipt</title>
                        <style>
                            body { font-family: 'Courier New', monospace; font-size: 12px; }
                            @media print {
                                @page { size: 80mm auto; margin: 0mm; }
                                body { margin: 0; padding: 2mm; }
                            }
                        </style>
                    </head>
                    <body>${receiptContent}</body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.print();
        }

        // Initialize
        updateClock();
        setInterval(updateClock, 1000);

        // Clear payment receipt session after use
        <?php if (isset($_SESSION['payment_receipt'])) unset($_SESSION['payment_receipt']); ?>
    </script>
</body>
</html>