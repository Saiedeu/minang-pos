<?php
/**
 * Print Shift Summary
 * Generate printable shift summary for current shift
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';
require_once 'includes/auth-check.php';

$user = User::getCurrentUser();
$db = Database::getInstance();

// Get current shift
$currentShift = $db->fetchOne("
    SELECT * FROM shifts 
    WHERE user_id = ? AND is_closed = 0 
    ORDER BY start_time DESC LIMIT 1
", [$user['id']]);

if (!$currentShift) {
    header('Location: dashboard.php?error=no_active_shift');
    exit();
}

// Get shift sales
$shiftSales = $db->fetchAll("
    SELECT s.*, 
           CASE s.payment_method
               WHEN 1 THEN 'Cash'
               WHEN 2 THEN 'Card' 
               WHEN 3 THEN 'Credit'
               WHEN 4 THEN 'FOC'
               WHEN 5 THEN 'COD'
           END as payment_method_name
    FROM sales s
    WHERE s.shift_id = ?
    ORDER BY s.created_at ASC
", [$currentShift['id']]);

// Calculate running totals
$runningTotal = $currentShift['opening_balance'];
$cashTransactions = array_filter($shiftSales, fn($s) => $s['payment_method'] == 1);
$totalCashSales = array_sum(array_column($cashTransactions, 'total'));
$expectedCash = $currentShift['opening_balance'] + $totalCashSales;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Summary - <?php echo $currentShift['id']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            @page { 
                size: A4; 
                margin: 15mm;
            }
            body { 
                font-family: 'Courier New', monospace; 
                font-size: 12px;
                line-height: 1.3;
                color: black;
            }
            .no-print { display: none !important; }
            .print-page-break { page-break-before: always; }
        }
    </style>
</head>
<body class="bg-white">
    <!-- Print Controls -->
    <div class="no-print bg-gray-100 p-4 flex justify-between items-center">
        <a href="reports.php" class="flex items-center text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back to Reports
        </a>
        <button onclick="window.print()" class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
            <i class="fas fa-print mr-2"></i>Print Summary
        </button>
    </div>

    <!-- Summary Content -->
    <div class="max-w-4xl mx-auto p-6">
        <!-- Header -->
        <div class="text-center mb-6 border-b border-gray-300 pb-4">
            <h1 class="text-2xl font-bold"><?php echo BUSINESS_NAME; ?></h1>
            <p class="text-sm"><?php echo BUSINESS_ADDRESS; ?></p>
            <p class="text-sm">Tel: <?php echo BUSINESS_PHONE; ?></p>
            <div class="mt-4">
                <h2 class="text-xl font-semibold">SHIFT SUMMARY REPORT</h2>
                <p class="text-sm">Shift in Progress</p>
            </div>
        </div>

        <!-- Shift Info -->
        <div class="mb-6">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <strong>Cashier:</strong> <?php echo $user['name']; ?>
                </div>
                <div>
                    <strong>Shift Started:</strong> <?php echo formatDateTime($currentShift['start_time']); ?>
                </div>
                <div>
                    <strong>Print Time:</strong> <?php echo formatDateTime(date('Y-m-d H:i:s')); ?>
                </div>
                <div>
                    <strong>Shift Duration:</strong> 
                    <?php 
                        $duration = (time() - strtotime($currentShift['start_time'])) / 3600;
                        echo number_format($duration, 1) . ' hours';
                    ?>
                </div>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-3 border-b border-gray-200 pb-1">FINANCIAL SUMMARY</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span>Opening Balance (A):</span>
                    <span class="font-semibold"><?php echo formatCurrency($currentShift['opening_balance']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span>Cash Sales (B):</span>
                    <span class="font-semibold"><?php echo formatCurrency($totalCashSales); ?></span>
                </div>
                <div class="flex justify-between">
                    <span>Card Sales:</span>
                    <span class="font-semibold"><?php echo formatCurrency($currentShift['card_sales'] ?? 0); ?></span>
                </div>
                <div class="flex justify-between">
                    <span>Credit Sales:</span>
                    <span class="font-semibold"><?php echo formatCurrency($currentShift['credit_sales'] ?? 0); ?></span>
                </div>
                <div class="flex justify-between border-t border-gray-300 pt-2">
                    <span class="font-bold">Expected Cash (A+B):</span>
                    <span class="font-bold"><?php echo formatCurrency($expectedCash); ?></span>
                </div>
            </div>
        </div>

        <!-- Transaction List -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-3 border-b border-gray-200 pb-1">TRANSACTION DETAILS</h3>
            
            <?php if (empty($shiftSales)): ?>
            <p class="text-gray-600 text-center py-4">No transactions recorded yet</p>
            <?php else: ?>
            <table class="w-full text-xs">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="text-left py-2 px-2">Time</th>
                        <th class="text-left py-2 px-2">Receipt #</th>
                        <th class="text-left py-2 px-2">Type</th>
                        <th class="text-right py-2 px-2">Amount</th>
                        <th class="text-left py-2 px-2">Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shiftSales as $sale): ?>
                    <tr class="border-t border-gray-200">
                        <td class="py-1 px-2"><?php echo date('H:i', strtotime($sale['created_at'])); ?></td>
                        <td class="py-1 px-2"><?php echo $sale['receipt_number']; ?></td>
                        <td class="py-1 px-2">
                            <?php 
                                $types = [1 => 'Dine', 2 => 'Take', 3 => 'Delivery'];
                                echo $types[$sale['order_type']] ?? 'Unknown';
                                if ($sale['order_type'] == 1 && $sale['table_number']) {
                                    echo ' T' . $sale['table_number'];
                                }
                            ?>
                        </td>
                        <td class="py-1 px-2 text-right font-semibold"><?php echo formatCurrency($sale['total']); ?></td>
                        <td class="py-1 px-2"><?php echo $sale['payment_method_name']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-100 font-semibold">
                    <tr>
                        <td colspan="3" class="py-2 px-2">TOTAL TRANSACTIONS: <?php echo count($shiftSales); ?></td>
                        <td class="py-2 px-2 text-right"><?php echo formatCurrency(array_sum(array_column($shiftSales, 'total'))); ?></td>
                        <td class="py-2 px-2"></td>
                    </tr>
                </tfoot>
            </table>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="text-center text-xs text-gray-500 border-t border-gray-300 pt-4">
            <p>This is a shift summary report generated at <?php echo formatDateTime(date('Y-m-d H:i:s')); ?></p>
            <p>Shift is currently ACTIVE - Final numbers will be available after shift closure</p>
            <p><?php echo SYSTEM_NAME; ?> v<?php echo SYSTEM_VERSION; ?></p>
        </div>
    </div>

    <script>
        // Auto print on page load
        window.addEventListener('load', function() {
            if (window.location.search.includes('auto_print=1')) {
                setTimeout(() => window.print(), 1000);
            }
        });
    </script>
</body>
</html>