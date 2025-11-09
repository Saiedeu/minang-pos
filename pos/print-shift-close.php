<?php
/**
 * POS System - Print Shift Closing Summary
 * Generates and prints shift closing receipt
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

// Check authentication
if (!User::isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$shiftId = $_GET['shift_id'] ?? 0;
$user = User::getCurrentUser();
$db = Database::getInstance();

// Get shift data
$shift = $db->fetchOne("
    SELECT s.*, u.name as cashier_name 
    FROM shifts s 
    LEFT JOIN users u ON s.user_id = u.id 
    WHERE s.id = ? AND s.user_id = ?
", [$shiftId, $user['id']]);

if (!$shift) {
    header('Location: dashboard.php?error=shift_not_found');
    exit();
}

$currencyBreakdown = json_decode($shift['currency_breakdown'] ?? '[]', true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Closing Summary</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            @page { 
                size: 80mm auto; 
                margin: 0mm;
            }
            body { 
                margin: 0; 
                padding: 2mm;
                font-family: 'Courier New', monospace; 
                font-size: 11px;
                line-height: 1.2;
            }
            .no-print { display: none; }
        }
        
        body { 
            font-family: 'Courier New', monospace; 
            font-size: 11px; 
            line-height: 1.2;
        }
    </style>
</head>
<body class="bg-white">
    <!-- Print Controls -->
    <div class="no-print bg-gray-100 p-4 flex justify-between items-center">
        <a href="dashboard.php" class="flex items-center text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
        </a>
        <button onclick="window.print()" class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
            <i class="fas fa-print mr-2"></i>Print Summary
        </button>
    </div>

    <!-- Thermal Receipt Content -->
    <div class="max-w-sm mx-auto bg-white p-4" style="width: 80mm;">
        <div class="text-center mb-4">
            <div class="text-sm font-bold"><?php echo BUSINESS_NAME; ?></div>
            <div class="text-xs"><?php echo BUSINESS_ADDRESS; ?></div>
            <div class="text-xs">Tel: <?php echo BUSINESS_PHONE; ?></div>
            <div class="text-xs">================================</div>
            <div class="text-sm font-bold mt-2">SHIFT CLOSING SUMMARY</div>
            <div class="text-xs">================================</div>
        </div>

        <div class="text-xs mb-4">
            <div class="flex justify-between">
                <span>Date:</span>
                <span><?php echo formatDate($shift['end_time']); ?></span>
            </div>
            <div class="flex justify-between">
                <span>Shift Time:</span>
                <span><?php echo date('H:i', strtotime($shift['start_time'])) . ' - ' . date('H:i', strtotime($shift['end_time'])); ?></span>
            </div>
            <div class="flex justify-between">
                <span>Employee:</span>
                <span><?php echo $shift['cashier_name']; ?></span>
            </div>
            <div class="text-xs">--------------------------------</div>
        </div>

        <div class="mb-4 text-xs">
            <div class="font-semibold mb-2">**OPENING & SALES**</div>
            <div class="flex justify-between">
                <span>Opening Balance (A):</span>
                <span><?php echo formatCurrency($shift['opening_balance']); ?></span>
            </div>
            <div class="flex justify-between">
                <span>Cash Sales (C):</span>
                <span><?php echo formatCurrency($shift['cash_sales']); ?></span>
            </div>
            <div class="flex justify-between">
                <span>Credit Sales (D):</span>
                <span><?php echo formatCurrency($shift['credit_sales']); ?></span>
            </div>
            <div class="flex justify-between">
                <span>Card Sales (E):</span>
                <span><?php echo formatCurrency($shift['card_sales']); ?></span>
            </div>
            <div class="flex justify-between">
                <span>FOC Sales (F):</span>
                <span><?php echo formatCurrency($shift['foc_sales']); ?></span>
            </div>
            <div class="flex justify-between">
                <span>Discount Amount (G):</span>
                <span><?php echo formatCurrency($shift['discount_amount']); ?></span>
            </div>
            <div class="text-xs">--------------------------------</div>
            <div class="flex justify-between font-semibold">
                <span>TOTAL NET SALES (B):</span>
                <span><?php echo formatCurrency($shift['total_sales']); ?></span>
            </div>
        </div>

        <div class="mb-4 text-xs">
            <div class="font-semibold mb-2">**PURCHASES & PAYMENTS**</div>
            <div class="flex justify-between">
                <span>Cash Purchases (H_cash):</span>
                <span><?php echo formatCurrency($shift['cash_purchases']); ?></span>
            </div>
            <div class="text-xs">--------------------------------</div>
        </div>

        <div class="mb-4 text-xs">
            <div class="font-semibold mb-2">**EXPECTED CASH CALCULATION**</div>
            <div class="flex justify-between">
                <span>Expected Cash (I):</span>
                <span><?php echo formatCurrency($shift['expected_cash']); ?></span>
            </div>
            <div class="text-xs text-gray-600">[A+C-H_cash]</div>
        </div>

        <div class="mb-4 text-xs">
            <div class="font-semibold mb-2">**ACTUAL CASH COUNT**</div>
            <div class="flex justify-between">
                <span>Physical Cash (J):</span>
                <span><?php echo formatCurrency($shift['physical_cash']); ?></span>
            </div>
            <div class="text-xs">--------------------------------</div>
            <div class="flex justify-between font-semibold">
                <span>SHORTAGE/EXTRA:</span>
                <span class="<?php echo $shift['shortage_extra'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                    <?php echo formatCurrency($shift['shortage_extra']); ?> 
                    <?php echo $shift['shortage_extra'] >= 0 ? '(Extra)' : '(Short)'; ?>
                </span>
            </div>
        </div>

        <!-- Currency Breakdown -->
        <?php if (!empty($currencyBreakdown)): ?>
        <div class="mb-4 text-xs">
            <div class="font-semibold mb-2">**CURRENCY BREAKDOWN**</div>
            <?php foreach ($currencyBreakdown as $breakdown): ?>
            <div class="flex justify-between">
                <span><?php echo formatCurrency($breakdown['denomination']); ?> × <?php echo $breakdown['count']; ?></span>
                <span><?php echo formatCurrency($breakdown['subtotal']); ?></span>
            </div>
            <?php endforeach; ?>
            <div class="text-xs">--------------------------------</div>
            <div class="flex justify-between font-semibold">
                <span>TOTAL COUNTED:</span>
                <span><?php echo formatCurrency($shift['physical_cash']); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="text-center text-xs mt-4">
            <div>================================</div>
            <div class="font-bold">**SHIFT CLOSED SUCCESSFULLY**</div>
            <div>================================</div>
            <div class="mt-2">Thank you <?php echo $shift['cashier_name']; ?>!</div>
            <div><?php echo formatDateTime($shift['end_time']); ?></div>
        </div>
    </div>

    <script>
        // Auto print on page load
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 1000);
        });

        // Redirect after printing
        window.addEventListener('afterprint', function() {
            setTimeout(function() {
                window.location.href = 'index.php';
            }, 2000);
        });
    </script>
</body>
</html>