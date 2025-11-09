<?php
/**
 * POS System - Receipt Printer
 * Print individual receipts for sales
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

$saleId = $_GET['sale_id'] ?? 0;
$receiptNumber = $_GET['receipt_number'] ?? '';

if (!$saleId && !$receiptNumber) {
    header('Location: dashboard.php?error=invalid_receipt');
    exit();
}

$receipt = new Receipt();
$sale = new Sale();

// Get sale data
if ($saleId) {
    $saleData = $sale->getSaleById($saleId);
} else {
    $saleData = $sale->reprintReceipt($receiptNumber);
}

if (!$saleData) {
    header('Location: dashboard.php?error=receipt_not_found');
    exit();
}

// Generate receipt HTML
$receiptHtml = $receipt->generateThermalReceipt($saleData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo $saleData['receipt_number']; ?></title>
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
<body>
    <!-- Print Controls -->
    <div class="no-print" style="background: #f3f4f6; padding: 1rem; text-align: center; border-bottom: 1px solid #e5e7eb;">
        <button onclick="window.print()" style="background: #5d5cde; color: white; padding: 0.5rem 1rem; border: none; border-radius: 0.5rem; margin-right: 0.5rem; cursor: pointer;">
            Print Receipt
        </button>
        <button onclick="window.close()" style="background: #6b7280; color: white; padding: 0.5rem 1rem; border: none; border-radius: 0.5rem; cursor: pointer;">
            Close
        </button>
    </div>

    <!-- Receipt Content -->
    <?php echo $receiptHtml; ?>

    <script>
        // Auto-print on load
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 500);
        });

        // Handle keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            } else if (e.key === 'Escape') {
                window.close();
            }
        });
    </script>
</body>
</html>