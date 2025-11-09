<?php
/**
 * Receipt Print Template
 * Standardized receipt template for thermal printer
 */

if (!defined('MINANG_SYSTEM')) {
    exit('Direct access not allowed');
}

function generateReceiptHTML($saleData, $settings = []) {
    // Get settings
    $shopName = $settings['shop_name'] ?? BUSINESS_NAME;
    $shopNameAr = $settings['shop_name_ar'] ?? BUSINESS_NAME_AR;
    $shopAddress = $settings['shop_address'] ?? BUSINESS_ADDRESS;
    $shopPhone = $settings['shop_phone'] ?? BUSINESS_PHONE;
    $receiptFooter = $settings['receipt_footer'] ?? 'THANK YOU FOR CHOOSING US!';
    $socialHandle = $settings['social_media_handle'] ?? '@langitminang';
    
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - ' . $saleData['receipt_number'] . '</title>
    <style>
        @media print {
            @page { 
                size: 80mm auto; 
                margin: 0;
            }
            body { 
                margin: 0; 
                padding: 2mm;
                -webkit-print-color-adjust: exact;
            }
            .no-print { display: none; }
        }
        
        body { 
            font-family: "Courier New", monospace; 
            font-size: 11px; 
            line-height: 1.2;
            width: 76mm;
            margin: 0;
            padding: 2mm;
            color: black;
        }
        
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .separator { 
            border-top: 1px dashed black; 
            margin: 3px 0; 
            width: 100%;
        }
        .item-row {
            display: flex;
            justify-content: space-between;
            margin: 1px 0;
        }
        .item-details {
            margin-left: 10px;
            color: #555;
            font-size: 10px;
        }
        .total-section {
            margin-top: 5px;
            border-top: 1px solid black;
            padding-top: 3px;
        }
    </style>
</head>
<body>';

    // Header
    $html .= '<div class="center">
        <div class="bold">' . htmlspecialchars($shopName) . '</div>';
    
    if (!empty($shopNameAr)) {
        $html .= '<div>' . htmlspecialchars($shopNameAr) . '</div>';
    }
    
    $html .= '<div>' . htmlspecialchars($shopAddress) . '</div>
        <div>Tel: ' . htmlspecialchars($shopPhone) . '</div>';
    
    if (!empty($settings['shop_email'])) {
        $html .= '<div>Email: ' . htmlspecialchars($settings['shop_email']) . '</div>';
    }
    
    if (!empty($settings['shop_cr'])) {
        $html .= '<div>CR: ' . htmlspecialchars($settings['shop_cr']) . '</div>';
    }
    
    $html .= '<div class="separator"></div>
    </div>';

    // Receipt Information
    $html .= '<div>
        <div class="item-row">
            <span>Receipt No:</span>
            <span>' . htmlspecialchars($saleData['receipt_number']) . '</span>
        </div>
        <div class="item-row">
            <span>Order No:</span>
            <span>' . htmlspecialchars($saleData['order_number']) . '</span>
        </div>
        <div class="item-row">
            <span>Date & Time:</span>
            <span>' . formatDateTime($saleData['created_at']) . '</span>
        </div>';
    
    // Order type specific information
    $orderTypes = [ORDER_DINE_IN => 'DINE-IN', ORDER_TAKEAWAY => 'TAKE AWAY', ORDER_DELIVERY => 'DELIVERY'];
    $orderTypeName = $orderTypes[$saleData['order_type']] ?? 'UNKNOWN';
    
    $html .= '<div class="item-row">
        <span>Order Type:</span>
        <span class="bold">' . $orderTypeName . '</span>
    </div>';
    
    if ($saleData['order_type'] == ORDER_DINE_IN && !empty($saleData['table_number'])) {
        $html .= '<div class="item-row">
            <span>Table No:</span>
            <span class="bold">' . htmlspecialchars($saleData['table_number']) . '</span>
        </div>';
    }
    
    $html .= '<div class="item-row">
        <span>Cashier:</span>
        <span>' . htmlspecialchars($saleData['cashier_name'] ?? 'System') . '</span>
    </div>';
    
    // Customer information for delivery
    if ($saleData['order_type'] == ORDER_DELIVERY && !empty($saleData['customer_name'])) {
        $html .= '<div class="separator"></div>
        <div class="center bold">--- CUSTOMER DETAILS ---</div>
        <div class="item-row">
            <span>Customer:</span>
            <span>' . htmlspecialchars($saleData['customer_name']) . '</span>
        </div>
        <div class="item-row">
            <span>Phone:</span>
            <span>' . htmlspecialchars($saleData['customer_phone']) . '</span>
        </div>';
        
        if (!empty($saleData['customer_address'])) {
            $html .= '<div>
                <span>Address:</span><br>
                <div class="item-details">' . htmlspecialchars($saleData['customer_address']) . '</div>
            </div>';
        }
    }
    
    $html .= '<div class="separator"></div>
    </div>';

    // Items
    $html .= '<div>';
    foreach ($saleData['items'] as $item) {
        $html .= '<div class="item-row">
            <span>' . htmlspecialchars($item['product_name']) . '</span>
            <span>' . formatCurrency($item['total_price']) . '</span>
        </div>';
        
        // Item details (quantity × unit price)
        $html .= '<div class="item-details">
            ' . $item['quantity'] . ' × ' . formatCurrency($item['unit_price']) . '
        </div>';
        
        // Arabic name
        if (!empty($item['product_name_ar'])) {
            $html .= '<div class="item-details" dir="rtl">
                ' . htmlspecialchars($item['product_name_ar']) . '
            </div>';
        }
        
        // Notes
        if (!empty($item['notes'])) {
            $html .= '<div class="item-details">
                Note: ' . htmlspecialchars($item['notes']) . '
            </div>';
        }
    }
    $html .= '</div>';

    // Totals
    $html .= '<div class="total-section">
        <div class="item-row">
            <span>Subtotal:</span>
            <span>' . formatCurrency($saleData['subtotal']) . '</span>
        </div>';
    
    if ($saleData['discount'] > 0) {
        $html .= '<div class="item-row">
            <span>Discount:</span>
            <span>-' . formatCurrency($saleData['discount']) . '</span>
        </div>';
    }
    
    if ($saleData['order_type'] == ORDER_DELIVERY && ($saleData['delivery_fee'] ?? 0) > 0) {
        $html .= '<div class="item-row">
            <span>Delivery Fee:</span>
            <span>' . formatCurrency($saleData['delivery_fee']) . '</span>
        </div>';
    }
    
    $html .= '<div class="item-row bold">
        <span>TOTAL:</span>
        <span>' . formatCurrency($saleData['total']) . '</span>
    </div>';
    
    // Payment information
    $paymentMethods = [
        PAYMENT_CASH => 'CASH', 
        PAYMENT_CARD => 'CARD', 
        PAYMENT_CREDIT => 'CREDIT', 
        PAYMENT_FOC => 'FOC',
        PAYMENT_COD => 'COD'
    ];
    
    $paymentMethodName = $paymentMethods[$saleData['payment_method']] ?? 'UNKNOWN';
    
    $html .= '<div class="item-row">
        <span>Payment Method:</span>
        <span class="bold">' . $paymentMethodName . '</span>
    </div>';

    // Payment specific details
    if ($saleData['payment_method'] == PAYMENT_CASH && $saleData['amount_received'] > 0) {
        $html .= '<div class="item-row">
            <span>Cash Received:</span>
            <span>' . formatCurrency($saleData['amount_received']) . '</span>
        </div>
        <div class="item-row">
            <span>Change:</span>
            <span>' . formatCurrency($saleData['change_amount']) . '</span>
        </div>';
    } elseif ($saleData['payment_method'] == PAYMENT_COD) {
        $html .= '<div class="center bold">--- CASH ON DELIVERY ---</div>';
    }
    
    $html .= '</div>';

    // Footer
    $html .= '<div class="center" style="margin-top: 8px;">
        <div class="separator"></div>';
    
    // Custom footer message
    $footerLines = explode("\n", $receiptFooter);
    foreach ($footerLines as $line) {
        $html .= '<div class="bold">' . htmlspecialchars(trim($line)) . '</div>';
    }
    
    // Delivery specific footer
    if ($saleData['order_type'] == ORDER_DELIVERY) {
        $html .= '<div style="margin-top: 5px;">
            <div>Your order will be delivered</div>
            <div>within 30-45 minutes</div>';
        
        if ($saleData['payment_method'] == PAYMENT_COD) {
            $html .= '<div class="bold">Payment: Cash on Delivery</div>';
        }
        
        $html .= '<div>For inquiries call: ' . htmlspecialchars($shopPhone) . '</div>
        </div>';
    } else {
        $html .= '<div style="margin-top: 5px;">
            <div>Enjoy your meal!</div>
            <div>Visit us again soon</div>
        </div>';
    }
    
    // Social media
    if (!empty($socialHandle)) {
        $html .= '<div style="margin-top: 5px;">
            <div>Follow us ' . htmlspecialchars($socialHandle) . '</div>
        </div>';
    }
    
    $html .= '<div style="margin-top: 8px; font-size: 10px;">
        <div>Printed: ' . date('d/m/Y H:i:s') . '</div>
    </div>
    </div>';

    $html .= '</body></html>';
    
    return $html;
}

// Quick print function for testing
if (isset($_GET['test_print'])) {
    $testSale = [
        'receipt_number' => 'R' . date('Ymd') . '-TEST',
        'order_number' => 'LMR-' . date('md') . 'TEST',
        'created_at' => date('Y-m-d H:i:s'),
        'order_type' => ORDER_DINE_IN,
        'table_number' => '5',
        'cashier_name' => 'Test User',
        'subtotal' => 67.00,
        'discount' => 2.00,
        'total' => 65.00,
        'payment_method' => PAYMENT_CASH,
        'amount_received' => 70.00,
        'change_amount' => 5.00,
        'items' => [
            [
                'product_name' => 'Rendang Daging',
                'product_name_ar' => 'رندانغ اللحم',
                'quantity' => 1,
                'unit_price' => 45.00,
                'total_price' => 45.00,
                'notes' => 'Medium spicy'
            ],
            [
                'product_name' => 'Teh Tarik Special',
                'product_name_ar' => 'شاي تاريك مميز',
                'quantity' => 2,
                'unit_price' => 12.00,
                'total_price' => 24.00,
                'notes' => ''
            ]
        ]
    ];
    
    echo generateReceiptHTML($testSale);
    exit();
}
?>