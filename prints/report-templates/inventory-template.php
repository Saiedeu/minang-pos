<?php
/**
 * Inventory Report Template
 * Professional template for inventory reports
 */

if (!defined('MINANG_SYSTEM')) {
    exit('Direct access not allowed');
}

function generateInventoryReport($inventoryData, $reportType = 'full') {
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Inventory Report - ' . date('Y-m-d') . '</title>
    <style>
        @media print {
            @page { size: A4; margin: 15mm; }
            body { font-family: Arial, sans-serif; font-size: 10px; color: black; }
            .no-print { display: none !important; }
        }
        body { 
            font-family: Arial, sans-serif; 
            font-size: 10px; 
            line-height: 1.3;
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
        .section { margin-bottom: 20px; }
        .section-title { 
            font-weight: bold; 
            font-size: 12px; 
            background: #f5f5f5; 
            padding: 8px; 
            border: 1px solid #ddd; 
            margin-bottom: 10px;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 4px 6px; }
        th { background-color: #f9f9f9; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .low-stock { background-color: #fef2f2; color: #dc2626; }
        .out-of-stock { background-color: #fee2e2; color: #b91c1c; font-weight: bold; }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .summary-card {
            border: 1px solid #ddd;
            padding: 12px;
            background: #f8f9fa;
            text-align: center;
        }
    </style>
</head>
<body>';

    // Header
    $html .= '<div class="header">
        <h1 style="margin: 0; font-size: 18px;">' . BUSINESS_NAME . '</h1>
        <p style="margin: 5px 0;">' . BUSINESS_ADDRESS . '</p>
        <h2 style="margin: 15px 0 5px 0; font-size: 16px; color: #2563eb;">INVENTORY REPORT</h2>
        <p style="margin: 5px 0; font-size: 12px;"><strong>' . formatDate(date('Y-m-d')) . '</strong></p>
    </div>';

    // Summary Cards
    $html .= '<div class="summary-grid">
        <div class="summary-card">
            <h3 style="margin: 0 0 5px 0; color: #1f2937;">Total Products</h3>
            <div style="font-size: 20px; font-weight: bold; color: #2563eb;">' . count($inventoryData) . '</div>
        </div>
        
        <div class="summary-card">
            <h3 style="margin: 0 0 5px 0; color: #1f2937;">In Stock</h3>
            <div style="font-size: 20px; font-weight: bold; color: #059669;">' . 
                count(array_filter($inventoryData, fn($p) => $p['quantity'] > $p['reorder_level'])) . '</div>
        </div>
        
        <div class="summary-card">
            <h3 style="margin: 0 0 5px 0; color: #1f2937;">Low Stock</h3>
            <div style="font-size: 20px; font-weight: bold; color: #d97706;">' . 
                count(array_filter($inventoryData, fn($p) => $p['quantity'] <= $p['reorder_level'] && $p['quantity'] > 0)) . '</div>
        </div>
        
        <div class="summary-card">
            <h3 style="margin: 0 0 5px 0; color: #1f2937;">Out of Stock</h3>
            <div style="font-size: 20px; font-weight: bold; color: #dc2626;">' . 
                count(array_filter($inventoryData, fn($p) => $p['quantity'] == 0)) . '</div>
        </div>
        
        <div class="summary-card">
            <h3 style="margin: 0 0 5px 0; color: #1f2937;">Total Value</h3>
            <div style="font-size: 16px; font-weight: bold; color: #7c3aed;">QR ' . 
                number_format(array_sum(array_map(fn($p) => $p['quantity'] * $p['cost_price'], $inventoryData)), 2) . '</div>
        </div>
    </div>';

    // Critical Stock Alerts
    $criticalStock = array_filter($inventoryData, fn($p) => $p['quantity'] == 0);
    $lowStock = array_filter($inventoryData, fn($p) => $p['quantity'] <= $p['reorder_level'] && $p['quantity'] > 0);
    
    if (!empty($criticalStock) || !empty($lowStock)) {
        $html .= '<div class="section">
            <div class="section-title" style="background: #fee2e2; color: #b91c1c;">⚠️ STOCK ALERTS</div>';
        
        if (!empty($criticalStock)) {
            $html .= '<h4 style="color: #dc2626; margin: 10px 0 5px 0;">OUT OF STOCK (' . count($criticalStock) . ' items)</h4>
            <table>
                <thead>
                    <tr><th>Product</th><th>Category</th><th>Reorder Level</th><th>Status</th></tr>
                </thead>
                <tbody>';
            
            foreach ($criticalStock as $product) {
                $html .= '<tr class="out-of-stock">
                    <td>' . $product['name'] . '</td>
                    <td>' . $product['category_name'] . '</td>
                    <td class="text-center">' . $product['reorder_level'] . '</td>
                    <td class="text-center"><strong>OUT OF STOCK</strong></td>
                </tr>';
            }
            
            $html .= '</tbody></table>';
        }
        
        if (!empty($lowStock)) {
            $html .= '<h4 style="color: #d97706; margin: 15px 0 5px 0;">LOW STOCK (' . count($lowStock) . ' items)</h4>
            <table>
                <thead>
                    <tr><th>Product</th><th>Category</th><th>Current</th><th>Reorder Level</th></tr>
                </thead>
                <tbody>';
            
            foreach ($lowStock as $product) {
                $html .= '<tr class="low-stock">
                    <td>' . $product['name'] . '</td>
                    <td>' . $product['category_name'] . '</td>
                    <td class="text-center">' . $product['quantity'] . ' ' . $product['unit'] . '</td>
                    <td class="text-center">' . $product['reorder_level'] . ' ' . $product['unit'] . '</td>
                </tr>';
            }
            
            $html .= '</tbody></table>';
        }
        
        $html .= '</div>';
    }

    // Full Inventory List
    if ($reportType === 'full') {
        $html .= '<div class="section">
            <div class="section-title">COMPLETE INVENTORY LIST</div>
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th class="text-center">Current Stock</th>
                        <th class="text-center">Reorder Level</th>
                        <th class="text-right">Cost Price</th>
                        <th class="text-right">Sell Price</th>
                        <th class="text-right">Stock Value</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($inventoryData as $product) {
            $stockValue = $product['quantity'] * $product['cost_price'];
            $isLowStock = $product['quantity'] <= $product['reorder_level'];
            $isOutOfStock = $product['quantity'] == 0;
            
            $rowClass = '';
            $status = 'Good';
            if ($isOutOfStock) {
                $rowClass = 'out-of-stock';
                $status = 'OUT';
            } elseif ($isLowStock) {
                $rowClass = 'low-stock';
                $status = 'LOW';
            }
            
            $html .= '<tr class="' . $rowClass . '">
                <td>' . $product['code'] . '</td>
                <td>' . $product['name'] . '</td>
                <td>' . $product['category_name'] . '</td>
                <td class="text-center">' . $product['quantity'] . ' ' . $product['unit'] . '</td>
                <td class="text-center">' . $product['reorder_level'] . ' ' . $product['unit'] . '</td>
                <td class="text-right">QR ' . number_format($product['cost_price'], 2) . '</td>
                <td class="text-right">QR ' . number_format($product['sell_price'], 2) . '</td>
                <td class="text-right">QR ' . number_format($stockValue, 2) . '</td>
                <td class="text-center"><strong>' . $status . '</strong></td>
            </tr>';
        }
        
        $html .= '</tbody>
            </table>
        </div>';
    }

    // Category Summary
    $categoryTotals = [];
    foreach ($inventoryData as $product) {
        $cat = $product['category_name'];
        if (!isset($categoryTotals[$cat])) {
            $categoryTotals[$cat] = ['count' => 0, 'value' => 0];
        }
        $categoryTotals[$cat]['count']++;
        $categoryTotals[$cat]['value'] += ($product['quantity'] * $product['cost_price']);
    }
    
    $html .= '<div class="section">
        <div class="section-title">INVENTORY BY CATEGORY</div>
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="text-center">Product Count</th>
                    <th class="text-right">Total Value</th>
                    <th class="text-right">Percentage</th>
                </tr>
            </thead>
            <tbody>';
    
    $totalInventoryValue = array_sum(array_column($categoryTotals, 'value'));
    
    foreach ($categoryTotals as $category => $totals) {
        $percentage = $totalInventoryValue > 0 ? ($totals['value'] / $totalInventoryValue) * 100 : 0;
        
        $html .= '<tr>
            <td>' . $category . '</td>
            <td class="text-center">' . $totals['count'] . '</td>
            <td class="text-right">QR ' . number_format($totals['value'], 2) . '</td>
            <td class="text-right">' . number_format($percentage, 1) . '%</td>
        </tr>';
    }
    
    $html .= '<tr class="total-row">
            <td><strong>TOTAL</strong></td>
            <td class="text-center"><strong>' . count($inventoryData) . '</strong></td>
            <td class="text-right"><strong>QR ' . number_format($totalInventoryValue, 2) . '</strong></td>
            <td class="text-right"><strong>100.0%</strong></td>
        </tr>
        </tbody>
        </table>
    </div>';

    // Footer
    $html .= '<div style="margin-top: 30px; text-align: center; padding-top: 15px; border-top: 1px solid #ddd; font-size: 9px; color: #666;">
        <p><strong>CONFIDENTIAL INVENTORY REPORT</strong></p>
        <p>' . SYSTEM_NAME . ' v' . SYSTEM_VERSION . ' | Generated: ' . formatDateTime(date('Y-m-d H:i:s')) . '</p>
    </div>';

    $html .= '</body></html>';

    return $html;
}
?>