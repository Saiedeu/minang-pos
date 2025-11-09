<?php
/**
 * POS System - Receipt Reprinting
 * Reprint receipts by receipt number or sale ID
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';
require_once 'includes/auth-check.php';

$user = User::getCurrentUser();
$receipt = new Receipt();
$error = '';
$success = '';

// Handle reprint request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiptNumber = sanitize($_POST['receipt_number'] ?? '');
    
    if ($receiptNumber) {
        $saleData = $receipt->reprintReceipt($receiptNumber);
        if ($saleData) {
            // Redirect to print page
            header('Location: print-receipt.php?receipt_number=' . urlencode($receiptNumber));
            exit();
        } else {
            $error = 'Receipt not found: ' . $receiptNumber;
        }
    } else {
        $error = 'Please enter a receipt number';
    }
}

// Get recent receipts for quick access
$db = Database::getInstance();
$recentReceipts = $db->fetchAll("
    SELECT receipt_number, order_number, total, created_at
    FROM sales 
    WHERE user_id = ? 
    AND DATE(created_at) = CURDATE()
    ORDER BY created_at DESC 
    LIMIT 10
", [$user['id']]);

$pageTitle = 'Reprint Receipt';
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
                        <h1 class="text-2xl font-bold text-white">Reprint Receipt</h1>
                        <p class="text-blue-100">Reprint previous receipts</p>
                    </div>
                </div>
                <div class="text-white text-right">
                    <p class="font-semibold"><?php echo $user['name']; ?></p>
                    <p class="text-sm text-blue-100"><?php echo User::getRoleName($user['role']); ?></p>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Alerts -->
        <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
            <div class="flex">
                <i class="fas fa-exclamation-triangle text-red-500 mr-2 mt-0.5"></i>
                <p class="text-red-700"><?php echo $error; ?></p>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Receipt Number Search -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center mb-6">
                    <i class="fas fa-search text-2xl text-primary mr-3"></i>
                    <h2 class="text-xl font-semibold text-gray-800">Search by Receipt Number</h2>
                </div>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Receipt Number</label>
                        <input type="text" name="receipt_number" required autofocus
                               class="w-full p-4 text-xl text-center border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                               placeholder="R240315-001">
                        <p class="text-xs text-gray-500 mt-1">Enter the complete receipt number</p>
                    </div>

                    <button type="submit" 
                            class="w-full bg-primary hover:bg-blue-600 text-white font-semibold py-3 rounded-lg transition-colors">
                        <i class="fas fa-print mr-2"></i>Find & Reprint Receipt
                    </button>
                </form>

                <!-- Receipt Format Example -->
                <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h4 class="font-semibold text-blue-800 mb-2">Receipt Number Format:</h4>
                    <div class="text-sm text-blue-700 space-y-1">
                        <div><code class="bg-blue-100 px-2 py-1 rounded">R240315-001</code> - Today's first receipt</div>
                        <div><code class="bg-blue-100 px-2 py-1 rounded">R240315-045</code> - Today's 45th receipt</div>
                        <div class="text-xs text-blue-600 mt-2">Format: R[YYMMDD]-[###]</div>
                    </div>
                </div>
            </div>

            <!-- Recent Receipts -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center mb-6">
                    <i class="fas fa-clock text-2xl text-green-500 mr-3"></i>
                    <h2 class="text-xl font-semibold text-gray-800">Today's Recent Receipts</h2>
                </div>

                <?php if (empty($recentReceipts)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-receipt text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">No receipts found for today</p>
                    <p class="text-sm text-gray-400">Complete some sales to see recent receipts</p>
                </div>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($recentReceipts as $receiptItem): ?>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                        <div>
                            <div class="font-semibold text-gray-900"><?php echo $receiptItem['receipt_number']; ?></div>
                            <div class="text-sm text-gray-600">
                                Order: <?php echo $receiptItem['order_number']; ?> | 
                                <?php echo formatCurrency($receiptItem['total']); ?>
                            </div>
                            <div class="text-xs text-gray-500"><?php echo date('H:i', strtotime($receiptItem['created_at'])); ?></div>
                        </div>
                        <button onclick="reprintReceipt('<?php echo $receiptItem['receipt_number']; ?>')"
                                class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            <i class="fas fa-print mr-1"></i>Reprint
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Instructions -->
        <div class="bg-white rounded-xl shadow-lg p-6 mt-8">
            <div class="flex items-center mb-4">
                <i class="fas fa-info-circle text-2xl text-blue-500 mr-3"></i>
                <h2 class="text-xl font-semibold text-gray-800">Instructions</h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                <div>
                    <h3 class="font-semibold text-gray-700 mb-2">How to Reprint:</h3>
                    <ul class="space-y-1 text-gray-600">
                        <li>1. Enter the complete receipt number</li>
                        <li>2. Click "Find & Reprint Receipt"</li>
                        <li>3. Review the receipt details</li>
                        <li>4. Print using your thermal printer</li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="font-semibold text-gray-700 mb-2">Quick Access:</h3>
                    <ul class="space-y-1 text-gray-600">
                        <li>• Use "Today's Recent Receipts" for quick reprinting</li>
                        <li>• Receipt numbers are case-sensitive</li>
                        <li>• Only receipts from your shifts can be reprinted</li>
                        <li>• All reprints are logged for auditing</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        function reprintReceipt(receiptNumber) {
            window.open('print-receipt.php?receipt_number=' + encodeURIComponent(receiptNumber), '_blank', 'width=400,height=600');
        }

        // Auto-focus receipt number input
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input[name="receipt_number"]').focus();
        });

        // Handle enter key in receipt number input
        document.querySelector('input[name="receipt_number"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.closest('form').submit();
            }
        });
    </script>
</body>
</html>