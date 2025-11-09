<?php
/**
 * Shift Close Print Template
 * Professional template for shift closing reports
 */

if (!defined('MINANG_SYSTEM')) {
    exit('Direct access not allowed');
}

function generateShiftCloseReport($shiftData, $salesData, $userData) {
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Shift Close Report - ' . $shiftData['id'] . '</title>
    <style>
        @media print {
            @page { 
                size: A4; 
                margin: 15mm;
            }
            body { 
                margin: 0; 
                padding: 0;
                font-family: "Arial", sans-serif; 
                font-size: 11px;
                line-height: 1.3;
                color: black;
            }
            .no-print { display: none !important; }
        }
        body { 
            font-family: "Arial", sans-serif; 
            font-size: 11px; 
            line-height: 1.3;
            color: black;
            max-width: 210mm;
            margin: 0 auto;
            padding: 10mm;
        }
        .header { 
            text-align: center; 
            border-bottom: 2px solid #333; 
            padding-bottom: 10px; 
            margin-bottom: 15px; 
        }
        .section { 
            margin-bottom: 15px; 
            border: 1px solid #ddd; 
            padding: 8px; 
        }
        .section-title { 
            font-weight: bold; 
            font-size: 12px; 
            background: #f5f5f5; 
            margin: -8px -8px 8px -8px; 
            padding: 5px 8px; 
            border-bottom: 1px solid #ddd; 
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { 
            border: 1px solid #ddd; 
            padding: 4px 6px; 
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
            background-color: #f0f8ff; 
            font-weight: bold; 
        }
        .summary-box { 
            border: 2px solid #333; 
            padding: 10px; 
            margin: 10px 0; 
            background: #f8f9fa; 
        }
        .signature-section {
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        .signature-box {
            border: 1px solid #333;
            height: 50px;
            margin-top: 5px;
            display: flex;
            align-items: flex-end;
            padding: 5px;
        }
    </style>
</head>
<body>';

    // Header
    $html .= '<div class="header">
        <h1 style="margin: 0; font-size: 18px;">' . BUSINESS_NAME . '</h1>
        <p style="margin: 5px 0;">' . BUSINESS_ADDRESS . '</p>
        <p style="margin: 5px 0;">Tel: ' . BUSINESS_PHONE . '</p>
        <h2 style="margin: 10px 0; font-size: 16px;">SHIFT CLOSING REPORT</h2>
        <p style="margin: 5px 0;">Shift ID: ' . $shiftData['id'] . '</p>
    </div>';

    // Shift Information
    $html .= '<div class="section">
        <div class="section-title">SHIFT INFORMATION</div>
        <table>
            <tr><td><strong>Cashier:</strong></td><td>' . $userData['name'] . '</td></tr>
            <tr><td><strong>Start Time:</strong></td><td>' . formatDateTime($shiftData['start_time']) . '</td></tr>
            <tr><td><strong>End Time:</strong></td><td>' . formatDateTime($shiftData['end_time']) . '</td></tr>
            <tr><td><strong>Duration:</strong></td><td>' . 
                number_format((strtotime($shiftData['end_time']) - strtotime($shiftData['start_time'])) / 3600, 1) . 
                ' hours</td></tr>
            <tr><td><strong>Total Transactions:</strong></td><td>' . count($salesData) . '</td></tr>
        </table>
    </div>';

    // Cash Summary
    $html .= '<div class="summary-box">
        <table>
            <tr class="total-row">
                <td colspan="2" class="text-center"><strong>CASH SUMMARY</strong></td>
            </tr>
            <tr>
                <td>Opening Balance (A):</td>
                <td class="text-right font-bold">QR ' . number_format($shiftData['opening_balance'], 2) . '</td>
            </tr>
            <tr>
                <td>Cash Sales (B):</td>
                <td class="text-right font-bold">QR ' . number_format($shiftData['cash_sales'], 2) . '</td>
            </tr>
            <tr>
                <td>Expected Cash (A + B):</td>
                <td class="text-right font-bold">QR ' . number_format($shiftData['expected_cash'], 2) . '</td>
            </tr>
            <tr>
                <td>Physical Cash Count:</td>
                <td class="text-right font-bold">QR ' . number_format($shiftData['physical_cash'], 2) . '</td>
            </tr>
            <tr class="total-row">
                <td>Shortage/Extra (Physical - Expected):</td>
                <td class="text-right font-bold ' . ($shiftData['shortage_extra'] < 0 ? 'color: red;' : 'color: green;') . '">
                    QR ' . number_format($shiftData['shortage_extra'], 2) . 
                '</td>
            </tr>
        </table>
    </div>';

    // Payment Breakdown
    $html .= '<div class="section">
        <div class="section-title">PAYMENT METHOD BREAKDOWN</div>
        <table>
            <tr><td>Cash Sales:</td><td class="text-right">QR ' . number_format($shiftData['cash_sales'], 2) . '</td></tr>
            <tr><td>Card Sales:</td><td class="text-right">QR ' . number_format($shiftData['card_sales'], 2) . '</td></tr>
            <tr><td>Credit Sales:</td><td class="text-right">QR ' . number_format($shiftData['credit_sales'], 2) . '</td></tr>
            <tr><td>FOC Sales:</td><td class="text-right">QR ' . number_format($shiftData['foc_sales'], 2) . '</td></tr>
            <tr class="total-row">
                <td><strong>TOTAL SALES:</strong></td>
                <td class="text-right"><strong>QR ' . number_format($shiftData['total_sales'], 2) . '</strong></td>
            </tr>
        </table>
    </div>';

    // Transaction Details
    $html .= '<div class="section">
        <div class="section-title">TRANSACTION DETAILS</div>';
    
    if (empty($salesData)) {
        $html .= '<p class="text-center">No transactions recorded during this shift</p>';
    } else {
        $html .= '<table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Receipt #</th>
                    <th>Type</th>
                    <th>Customer</th>
                    <th>Payment</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($salesData as $sale) {
            $orderTypes = [1 => 'Dine-In', 2 => 'Take Away', 3 => 'Delivery'];
            $paymentMethods = [1 => 'Cash', 2 => 'Card', 3 => 'Credit', 4 => 'FOC', 5 => 'COD'];
            
            $html .= '<tr>
                <td>' . date('H:i', strtotime($sale['created_at'])) . '</td>
                <td>' . $sale['receipt_number'] . '</td>
                <td>' . ($orderTypes[$sale['order_type']] ?? 'Unknown') . '</td>
                <td>' . ($sale['customer_name'] ?: 'Walk-in') . '</td>
                <td>' . ($paymentMethods[$sale['payment_method']] ?? 'Unknown') . '</td>
                <td class="text-right">QR ' . number_format($sale['total'], 2) . '</td>
            </tr>';
        }
        
        $html .= '</tbody>
        </table>';
    }
    
    $html .= '</div>';

    // Closing Notes
    if (!empty($shiftData['closing_notes'])) {
        $html .= '<div class="section">
            <div class="section-title">CLOSING NOTES</div>
            <p>' . htmlspecialchars($shiftData['closing_notes']) . '</p>
        </div>';
    }

    // Signature Section
    $html .= '<div class="signature-section">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <strong>Cashier Signature:</strong>
                <div class="signature-box">
                    <small style="color: #666;">Verified cash count and report accuracy</small>
                </div>
                <div class="text-center" style="margin-top: 5px;">
                    <strong>' . $userData['name'] . '</strong><br>
                    <small>Date: _______________</small>
                </div>
            </div>
            
            <div>
                <strong>Manager Approval:</strong>
                <div class="signature-box">
                    <small style="color: #666;">Reviewed and approved shift closure</small>
                </div>
                <div class="text-center" style="margin-top: 5px;">
                    <strong>_________________________</strong><br>
                    <small>Date: _______________</small>
                </div>
            </div>
        </div>
    </div>';

    // Footer
    $html .= '<div style="text-align: center; margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 10px; color: #666;">
        <p>This report was generated automatically by ' . SYSTEM_NAME . ' v' . SYSTEM_VERSION . '</p>
        <p>Print Time: ' . formatDateTime(date('Y-m-d H:i:s')) . '</p>
        <p><strong>*** CONFIDENTIAL DOCUMENT ***</strong></p>
    </div>';

    $html .= '</body></html>';

    return $html;
}
?>