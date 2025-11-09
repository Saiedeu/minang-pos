<?php
/**
 * POS System - Shift Closing
 * Handle shift closing with cash counting
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

// Check authentication and permission
if (!User::isLoggedIn() || !User::hasPermission('shift_close')) {
    header('Location: index.php');
    exit();
}

$user = User::getCurrentUser();
$db = Database::getInstance();

// Get active shift
$activeShift = $db->fetchOne(
    "SELECT * FROM shifts WHERE user_id = ? AND is_closed = 0 ORDER BY start_time DESC LIMIT 1", 
    [$user['id']]
);

if (!$activeShift) {
    header('Location: dashboard.php?error=no_active_shift');
    exit();
}

// Get shift sales data
$shiftSales = $db->fetchOne("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(total) as total_sales,
        SUM(CASE WHEN payment_method = 1 THEN total ELSE 0 END) as cash_sales,
        SUM(CASE WHEN payment_method = 2 THEN total ELSE 0 END) as card_sales,
        SUM(CASE WHEN payment_method = 3 THEN total ELSE 0 END) as credit_sales,
        SUM(CASE WHEN payment_method = 4 THEN total ELSE 0 END) as foc_sales,
        SUM(discount) as total_discounts
    FROM sales 
    WHERE shift_id = ?
", [$activeShift['id']]);

// Get shift purchases (cash only)
$shiftPurchases = $db->fetchOne("
    SELECT 
        COALESCE(SUM(CASE WHEN payment_method = 1 THEN paid_amount ELSE 0 END), 0) as cash_purchases
    FROM purchases 
    WHERE DATE(created_at) = DATE(?) AND payment_status > 0
", [$activeShift['start_time']]);

// Calculate expected cash
$expectedCash = $activeShift['opening_balance'] + ($shiftSales['cash_sales'] ?? 0) - ($shiftPurchases['cash_purchases'] ?? 0);

// QR Denominations
$denominations = [0.25, 0.50, 1, 5, 10, 50, 100, 500];

$error = '';
$success = '';

// Handle shift closing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $physicalCash = 0;
    $currencyBreakdown = [];
    
    foreach ($denominations as $denom) {
        $count = intval($_POST["denom_" . str_replace('.', '_', $denom)] ?? 0);
        $subtotal = $count * $denom;
        $physicalCash += $subtotal;
        
        if ($count > 0) {
            $currencyBreakdown[] = [
                'denomination' => $denom,
                'count' => $count,
                'subtotal' => $subtotal
            ];
        }
    }
    
    $shortageExtra = $physicalCash - $expectedCash;
    $notes = sanitize($_POST['notes'] ?? '');
    
    // Update shift record
    $updateData = [
        'end_time' => date('Y-m-d H:i:s'),
        'total_sales' => $shiftSales['total_sales'] ?? 0,
        'cash_sales' => $shiftSales['cash_sales'] ?? 0,
        'card_sales' => $shiftSales['card_sales'] ?? 0,
        'credit_sales' => $shiftSales['credit_sales'] ?? 0,
        'foc_sales' => $shiftSales['foc_sales'] ?? 0,
        'discount_amount' => $shiftSales['total_discounts'] ?? 0,
        'cash_purchases' => $shiftPurchases['cash_purchases'] ?? 0,
        'expected_cash' => $expectedCash,
        'physical_cash' => $physicalCash,
        'shortage_extra' => $shortageExtra,
        'currency_breakdown' => json_encode($currencyBreakdown),
        'is_closed' => 1,
        'notes' => $notes
    ];
    
    $updated = $db->update('shifts', $updateData, 'id = ?', [$activeShift['id']]);
    
    if ($updated) {
        // Clear session shift
        unset($_SESSION['active_shift_id']);
        
        // Redirect to print closing summary
        header('Location: print-shift-close.php?shift_id=' . $activeShift['id']);
        exit();
    } else {
        $error = 'Failed to close shift. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Close Shift - <?php echo BUSINESS_NAME; ?></title>
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
                        <h1 class="text-2xl font-bold text-white">Close Shift</h1>
                        <p class="text-blue-100">Count cash and close your shift</p>
                    </div>
                </div>
                <div class="text-white text-right">
                    <p class="font-semibold"><?php echo $user['name']; ?></p>
                    <p class="text-sm text-blue-100">Shift started: <?php echo formatDateTime($activeShift['start_time']); ?></p>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-6xl mx-auto px-4 py-8">
        <!-- Shift Summary -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Sales Summary -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-chart-bar text-primary mr-3"></i>Shift Sales Summary
                </h2>
                
                <div class="space-y-4">
                    <div class="flex justify-between p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600">Opening Balance (A):</span>
                        <span class="font-semibold"><?php echo formatCurrency($activeShift['opening_balance']); ?></span>
                    </div>
                    <div class="flex justify-between p-3 bg-blue-50 rounded-lg">
                        <span class="text-gray-600">Total Transactions:</span>
                        <span class="font-semibold"><?php echo $shiftSales['total_transactions'] ?? 0; ?></span>
                    </div>
                    <div class="flex justify-between p-3 bg-green-50 rounded-lg">
                        <span class="text-gray-600">Cash Sales (C):</span>
                        <span class="font-semibold text-green-600"><?php echo formatCurrency($shiftSales['cash_sales'] ?? 0); ?></span>
                    </div>
                    <div class="flex justify-between p-3 bg-purple-50 rounded-lg">
                        <span class="text-gray-600">Card Sales (E):</span>
                        <span class="font-semibold text-purple-600"><?php echo formatCurrency($shiftSales['card_sales'] ?? 0); ?></span>
                    </div>
                    <div class="flex justify-between p-3 bg-indigo-50 rounded-lg">
                        <span class="text-gray-600">Credit Sales (D):</span>
                        <span class="font-semibold text-indigo-600"><?php echo formatCurrency($shiftSales['credit_sales'] ?? 0); ?></span>
                    </div>
                    <div class="flex justify-between p-3 bg-red-50 rounded-lg">
                        <span class="text-gray-600">Discounts (G):</span>
                        <span class="font-semibold text-red-600"><?php echo formatCurrency($shiftSales['total_discounts'] ?? 0); ?></span>
                    </div>
                    
                    <div class="border-t pt-4">
                        <div class="flex justify-between p-3 bg-yellow-50 rounded-lg">
                            <span class="text-gray-600 font-semibold">Expected Cash (I):</span>
                            <span class="font-bold text-lg"><?php echo formatCurrency($expectedCash); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cash Counting Form -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-calculator text-success mr-3"></i>Cash Count
                </h2>

                <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-4">
                    <p class="text-red-700"><?php echo $error; ?></p>
                </div>
                <?php endif; ?>

                <form method="POST" action="" id="cash-count-form">
                    <div class="space-y-3 mb-6">
                        <?php foreach ($denominations as $denom): ?>
                        <div class="grid grid-cols-3 gap-4 items-center p-3 bg-gray-50 rounded-lg">
                            <div class="text-right font-semibold">
                                <?php echo formatCurrency($denom); ?>
                            </div>
                            <div>
                                <input type="number" 
                                       name="denom_<?php echo str_replace('.', '_', $denom); ?>" 
                                       min="0" 
                                       class="w-full p-2 text-center border border-gray-300 rounded focus:ring-2 focus:ring-primary focus:border-primary" 
                                       onchange="calculateDenomination(this, <?php echo $denom; ?>)"
                                       placeholder="0">
                            </div>
                            <div class="font-semibold text-primary">
                                = <span id="subtotal_<?php echo str_replace('.', '_', $denom); ?>">0.00</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Total Physical Cash -->
                    <div class="border-t pt-4 mb-6">
                        <div class="flex justify-between items-center p-4 bg-yellow-100 rounded-lg">
                            <span class="text-xl font-bold text-gray-800">Total Physical Cash (J):</span>
                            <span id="total-physical-cash" class="text-2xl font-bold text-primary">QR 0.00</span>
                        </div>
                        
                        <div class="mt-4 p-4 bg-gray-100 rounded-lg">
                            <div class="flex justify-between items-center">
                                <span class="font-semibold">Shortage/Extra:</span>
                                <span id="shortage-extra" class="font-bold text-lg">QR 0.00</span>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-sticky-note mr-2"></i>Shift Notes
                        </label>
                        <textarea name="notes" rows="3" 
                                  class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                  placeholder="Any notes about the shift (optional)"></textarea>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex space-x-4">
                        <a href="dashboard.php" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold py-3 px-6 rounded-lg text-center transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                        </a>
                        <button type="submit" class="flex-1 bg-gradient-to-r from-danger to-red-600 hover:from-red-600 hover:to-red-700 text-white font-semibold py-3 px-6 rounded-lg transition-all">
                            <i class="fas fa-power-off mr-2"></i>Close Shift
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const expectedCash = <?php echo $expectedCash; ?>;
        
        function calculateDenomination(input, denomination) {
            const count = parseInt(input.value) || 0;
            const subtotal = count * denomination;
            
            document.getElementById('subtotal_' + denomination.toString().replace('.', '_')).textContent = subtotal.toFixed(2);
            calculateTotal();
        }
        
        function calculateTotal() {
            let total = 0;
            const denominations = [0.25, 0.50, 1, 5, 10, 50, 100, 500];
            
            denominations.forEach(denom => {
                const input = document.querySelector(`input[name="denom_${denom.toString().replace('.', '_')}"]`);
                const count = parseInt(input.value) || 0;
                total += count * denom;
            });
            
            document.getElementById('total-physical-cash').textContent = 'QR ' + total.toFixed(2);
            
            const shortageExtra = total - expectedCash;
            const shortageExtraElement = document.getElementById('shortage-extra');
            shortageExtraElement.textContent = 'QR ' + (shortageExtra >= 0 ? '+' : '') + shortageExtra.toFixed(2);
            
            if (shortageExtra > 0) {
                shortageExtraElement.className = 'font-bold text-lg text-green-600';
            } else if (shortageExtra < 0) {
                shortageExtraElement.className = 'font-bold text-lg text-red-600';
            } else {
                shortageExtraElement.className = 'font-bold text-lg text-gray-600';
            }
        }
        
        // Auto-calculate on page load
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
        });
        
        // Keyboard shortcut
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'L') {
                e.preventDefault();
                document.getElementById('cash-count-form').submit();
            }
        });
    </script>
</body>
</html>