<?php
/**
 * Daily Sales Report Template
 * Professional template for daily sales reports
 */

if (!defined('MINANG_SYSTEM')) {
    exit('Direct access not allowed');
}

function generateDailySalesReport($reportData, $salesData) {
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Daily Sales Report - ' . $reportData['date'] . '</title>
    <style>
        @media print {
            @page { size: A4; margin: 15mm; }
            body { font-family: Arial, sans-serif; font-size: 11px; color: black; }
            .no-print { display: none !important; }
        }
        body { 
            font-family: Arial, sans-serif; 
            font-size: 11px; 
            line-height: 1.4;
            max-width: 210mm;
            margin: 0 auto;
            padding: 10mm;
        }
        .header { 
            text-align: center; 
            border-bottom: 2px solid #333; 
            padding-bottom: 15px; 
            margin-bottom: 20px; 
        }
        .section { 
            margin-bottom: 20px; 
        }
        .section-title { 
            font-weight: bold; 
            font-size: 13px; 
            background: #f5f5f5; 
            padding: 8px; 
            border: 1px solid #ddd; 
            margin-bottom: 10px;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 10px; 
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 6px 8px; 
            text-align: left; 
        }
        th { 
            background-color: #f9f9f9; 
            font-weight: bold; 
            font-size: 10px; 
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .total-row { 
            background-color: #e8f4fd; 
            font-weight: bold; 
        }
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .summary-card {
            border: 1px solid #ddd;
            padding: 15px;
            background: #f8f9fa;
        }
    </style>
</head>
<body>';

    // Header
    $html .= '<div class="header">
        <h1 style="margin: 0; font-size: 20px; color: #333;">' . BUSINESS_NAME . '</h1>
        <p style="margin: 5px 0; color: #666;">' . BUSINESS_ADDRESS . '</p>
        <p style="margin: 5px 0; color: #666;">Tel: ' . BUSINESS_PHONE . '</p>
        <h2 style="margin: 15px 0 5px 0; font-size: 16px; color: #2563eb;">DAILY SALES REPORT</h2>
        <p style="margin: 5px 0; font-size: 12px;"><strong>' . formatDate($reportData['date']) . '</strong></p>
        <p style="margin: 5px 0; font-size: 10px; color: #666;">Generated: ' . formatDateTime(date('Y-m-d H:i:s')) . '</p>
    </div>';

    // Summary Cards
    $html .= '<div class="summary-grid">
        <div class="summary-card">
            <h3 style="margin: 0 0 10px 0; color: #1f2937;">Sales Summary</h3>
            <table style="margin: 0;">
                <tr><td>Total Transactions:</td><td class="text-right font-bold">' . $reportData['total_transactions'] . '</td></tr>
                <tr><td>Gross Sales:</td><td class="text-right font-bold">QR ' . number_format($reportData['gross_sales'], 2) . '</td></tr>
                <tr><td>Total Discounts:</td><td class="text-right font-bold">QR ' . number_format($reportData['total_discounts'], 2) . '</td></tr>
                <tr class="total-row">
                    <td><strong>NET SALES:</strong></td>
                    <td class="text-right"><strong>QR ' . number_format($reportData['net_sales'], 2) . '</strong></td>
                </tr>
            </table>
        </div>
        
        <div class="summary-card">
            <h3 style="margin: 0 0 10px 0; color: #1f2937;">Payment Breakdown</h3>
            <table style="margin: 0;">
                <tr><td>Cash:</td><td class="text-right font-bold">QR ' . number_format($reportData['cash_sales'], 2) . '</td></tr>
                <tr><td>Card:</td><td class="text-right font-bold">QR ' . number_format($reportData['card_sales'], 2) . '</td></tr>
                <tr><td>Credit:</td><td class="text-right font-bold">QR ' . number_format($reportData['credit_sales'], 2) . '</td></tr>
                <tr><td>FOC:</td><td class="text-right font-bold">QR ' . number_format($reportData['foc_sales'], 2) . '</td></tr>
                <tr class="total-row">
                    <td><strong>TOTAL:</strong></td>
                    <td class="text-right"><strong>QR ' . number_format($reportData['total_sales'], 2) . '</strong></td>
                </tr>
            </table>
        </div>
    </div>';

    // Order Type Analysis
    $html .= '<div class="section">
        <div class="section-title">ORDER TYPE ANALYSIS</div>
        <table>
            <thead>
                <tr>
                    <th>Order Type</th>
                    <th class="text-center">Count</th>
                    <th class="text-right">Amount</th>
                    <th class="text-right">Percentage</th>
                    <th class="text-right">Avg. Value</th>
                </tr>
            </thead>
            <tbody>';
    
    $orderTypes = [1 => 'Dine-In', 2 => 'Take Away', 3 => 'Delivery'];
    foreach ($reportData['order_types'] as $typeId => $data) {
        $percentage = ($data['amount'] / $reportData['total_sales']) * 100;
        $avgValue = $data['count'] > 0 ? $data['amount'] / $data['count'] : 0;
        
        $html .= '<tr>
            <td>' . ($orderTypes[$typeId] ?? 'Unknown') . '</td>
            <td class="text-center">' . $data['count'] . '</td>
            <td class="text-right">QR ' . number_format($data['amount'], 2) . '</td>
            <td class="text-right">' . number_format($percentage, 1) . '%</td>
            <td class="text-right">QR ' . number_format($avgValue, 2) . '</td>
        </tr>';
    }
    
    $html .= '</tbody>
        </table>
    </div>';

    // Best Selling Products
    if (!empty($reportData['best_selling'])) {
        $html .= '<div class="section">
            <div class="section-title">TOP SELLING PRODUCTS</div>
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Product Name</th>
                        <th class="text-center">Qty Sold</th>
                        <th class="text-right">Revenue</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($reportData['best_selling'] as $index => $product) {
            $html .= '<tr>
                <td class="text-center">' . ($index + 1) . '</td>
                <td>' . $product['product_name'] . '</td>
                <td class="text-center font-bold">' . $product['total_quantity'] . '</td>
                <td class="text-right">QR ' . number_format($product['total_sales'], 2) . '</td>
            </tr>';
        }
        
        $html .= '</tbody>
            </table>
        </div>';
    }

    // Hourly Sales Trend
    if (!empty($reportData['hourly_sales'])) {
        $html .= '<div class="section">
            <div class="section-title">HOURLY SALES TREND</div>
            <table>
                <thead>
                    <tr>
                        <th>Hour</th>
                        <th class="text-center">Transactions</th>
                        <th class="text-right">Amount</th>
                        <th class="text-right">% of Total</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($reportData['hourly_sales'] as $hour => $data) {
            $percentage = ($data['amount'] / $reportData['total_sales']) * 100;
            
            $html .= '<tr>
                <td>' . sprintf('%02d:00 - %02d:59', $hour, $hour) . '</td>
                <td class="text-center">' . $data['count'] . '</td>
                <td class="text-right">QR ' . number_format($data['amount'], 2) . '</td>
                <td class="text-right">' . number_format($percentage, 1) . '%</td>
            </tr>';
        }
        
        $html .= '</tbody>
            </table>
        </div>';
    }

    // Footer
    $html .= '<div style="margin-top: 30px; text-align: center; padding-top: 15px; border-top: 1px solid #ddd; font-size: 10px; color: #666;">
        <p><strong>' . SYSTEM_NAME . ' v' . SYSTEM_VERSION . '</strong></p>
        <p>This report contains confidential business information</p>
        <p>Report Generated: ' . formatDateTime(date('Y-m-d H:i:s')) . '</p>
    </div>';

    $html .= '</body></html>';

    return $html;
}
?>