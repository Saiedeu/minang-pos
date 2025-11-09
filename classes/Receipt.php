<?php
/**
 * Receipt Management Class
 * Handles receipt generation and printing
 */

if (!defined('MINANG_SYSTEM')) {
    exit('Direct access not allowed');
}

class Receipt {
    private $db;
    private $settings;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->loadSettings();
    }

    // Load system settings
    private function loadSettings() {
        $settingsData = $this->db->fetchAll("SELECT setting_key, setting_value FROM settings");
        $this->settings = [];
        foreach ($settingsData as $setting) {
            $this->settings[$setting['setting_key']] = $setting['setting_value'];
        }
    }

    // Generate thermal receipt HTML
    public function generateThermalReceipt($saleData) {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt - ' . $saleData['receipt_number'] . '</title>
    <style>
        @media print {
            @page { 
                size: 80mm auto; 
                margin: 0mm;
            }
            body { 
                margin: 0; 
                padding: 2mm;
                font-family: "Courier New", monospace; 
                font-size: 11px;
                line-height: 1.2;
            }
        }
        body { 
            font-family: "Courier New", monospace; 
            font-size: 11px; 
            width: 76mm;
            line-height: 1.2;
            color: black;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .separator { border-top: 1px dashed black; margin: 5px 0; }
    </style>
</head>
<body>';

        // Header
        $html .= '<div class="center">
            <div class="bold">' . ($this->settings['shop_name'] ?? BUSINESS_NAME) . '</div>';
        
        if (!empty($this->settings['shop_name_ar'])) {
            $html .= '<div>' . $this->settings['shop_name_ar'] . '</div>';
        }
        
        $html .= '<div>' . ($this->settings['shop_address'] ?? BUSINESS_ADDRESS) . '</div>
            <div>Tel: ' . ($this->settings['shop_phone'] ?? BUSINESS_PHONE) . '</div>
            <div class="separator"></div>
        </div>';

        // Receipt Details
        $html .= '<div>
            <div>Receipt No: ' . $saleData['receipt_number'] . '</div>
            <div>Order No: ' . $saleData['order_number'] . '</div>
            <div>Date & Time: ' . formatDateTime($saleData['created_at']) . '</div>
            <div>Order Type: ' . strtoupper($saleData['order_type_name']) . '</div>
            <div>Cashier: ' . $saleData['cashier_name'] . '</div>';
        
        // Order specific details
        if ($saleData['order_type'] == ORDER_DINE_IN && !empty($saleData['table_number'])) {
            $html .= '<div>Table No: ' . $saleData['table_number'] . '</div>';
        }
        
        // Customer details
        if (!empty($saleData['customer_name'])) {
            $html .= '<div>--- CUSTOMER DETAILS ---</div>
                <div>Customer: ' . $saleData['customer_name'] . '</div>
                <div>Phone: ' . $saleData['customer_phone'] . '</div>';
            
            if ($saleData['order_type'] == ORDER_DELIVERY && !empty($saleData['customer_address'])) {
                $html .= '<div>Address: ' . $saleData['customer_address'] . '</div>';
            }
        }
        
        $html .= '<div class="separator"></div>
        </div>';

        // Items
        foreach ($saleData['items'] as $item) {
            $html .= '<div>
                <div style="display: flex; justify-content: space-between;">
                    <span>' . $item['product_name'] . '</span>
                    <span>' . formatCurrency($item['total_price']) . '</span>
                </div>
                <div style="margin-left: 10px; color: #666;">
                    ' . $item['quantity'] . ' x ' . formatCurrency($item['unit_price']) . '
                </div>';
            
            if (!empty($item['product_name_ar'])) {
                $html .= '<div style="margin-left: 10px; color: #666; direction: rtl;">
                    ' . $item['product_name_ar'] . '
                </div>';
            }
            
            if (!empty($item['notes'])) {
                $html .= '<div style="margin-left: 10px; color: #666; font-size: 10px;">
                    Note: ' . $item['notes'] . '
                </div>';
            }
            
            $html .= '</div>';
        }

        // Totals
        $html .= '<div class="separator"></div>
        <div>
            <div style="display: flex; justify-content: space-between;">
                <span>Subtotal:</span>
                <span>' . formatCurrency($saleData['subtotal']) . '</span>
            </div>';
        
        if ($saleData['discount'] > 0) {
            $html .= '<div style="display: flex; justify-content: space-between;">
                <span>Discount:</span>
                <span>-' . formatCurrency($saleData['discount']) . '</span>
            </div>';
        }
        
        if ($saleData['order_type'] == ORDER_DELIVERY && $saleData['delivery_fee'] > 0) {
            $html .= '<div style="display: flex; justify-content: space-between;">
                <span>Delivery Fee:</span>
                <span>' . formatCurrency($saleData['delivery_fee']) . '</span>
            </div>';
        }
        
        $html .= '<div style="display: flex; justify-content: space-between;" class="bold">
                <span>TOTAL:</span>
                <span>' . formatCurrency($saleData['total']) . '</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span>Payment Method:</span>
                <span>' . strtoupper($saleData['payment_method_name']) . '</span>
            </div>';

        // Payment details
        if ($saleData['payment_method'] == PAYMENT_CASH) {
            $html .= '<div style="display: flex; justify-content: space-between;">
                <span>Cash Received:</span>
                <span>' . formatCurrency($saleData['amount_received']) . '</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span>Change:</span>
                <span>' . formatCurrency($saleData['change_amount']) . '</span>
            </div>';
        } elseif ($saleData['payment_method'] == PAYMENT_COD) {
            $html .= '<div style="display: flex; justify-content: space-between;">
                <span>Payment:</span>
                <span>Cash on Delivery</span>
            </div>';
        }

        // Footer
        $html .= '</div>
        <div class="center" style="margin-top: 10px;">
            <div class="separator"></div>
            <div class="bold">THANK YOU FOR CHOOSING US!</div>';
        
        if ($saleData['order_type'] == ORDER_DELIVERY) {
            $html .= '<div>Your order will be delivered</div>
                <div>within 30-45 minutes</div>';
            if ($saleData['payment_method'] == PAYMENT_COD) {
                $html .= '<div>Payment: Cash on Delivery</div>';
            }
            $html .= '<div>Call us: ' . ($this->settings['shop_phone'] ?? BUSINESS_PHONE) . '</div>';
        } else {
            $html .= '<div>Enjoy your meal!</div>
                <div>Visit us again soon</div>';
        }
        
        $html .= '<div>Follow us @langitminang</div>
        </div>';

        $html .= '</body></html>';

        return $html;
    }

    // Print receipt
    public function printReceipt($saleId) {
        $sale = new Sale();
        $saleData = $sale->getSaleById($saleId);
        
        if (!$saleData) {
            return false;
        }
        
        // Add display names for receipt
        $orderTypes = [ORDER_DINE_IN => 'Dine-In', ORDER_TAKEAWAY => 'Take Away', ORDER_DELIVERY => 'Delivery'];
        $paymentMethods = [PAYMENT_CASH => 'Cash', PAYMENT_CARD => 'Card', PAYMENT_CREDIT => 'Credit', PAYMENT_FOC => 'FOC', PAYMENT_COD => 'COD'];
        
        $saleData['order_type_name'] = $orderTypes[$saleData['order_type']] ?? 'Unknown';
        $saleData['payment_method_name'] = $paymentMethods[$saleData['payment_method']] ?? 'Unknown';
        
        $html = $this->generateThermalReceipt($saleData);
        
        // Update print status
        $this->db->update('sales', ['is_printed' => 1], 'id = ?', [$saleId]);
        
        return $html;
    }

    // Get receipt archive
    public function getReceiptArchive($startDate = null, $endDate = null, $limit = 100) {
        $startDate = $startDate ?? date('Y-m-01');
        $endDate = $endDate ?? date('Y-m-d');
        
        $sql = "SELECT s.*, u.name as cashier_name 
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE DATE(s.created_at) BETWEEN ? AND ?
                ORDER BY s.created_at DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$startDate, $endDate, $limit]);
    }

    // Reprint receipt by receipt number
    public function reprintReceipt($receiptNumber) {
        $sale = new Sale();
        return $sale->reprintReceipt($receiptNumber);
    }
}
?>