<?php
/**
 * Minang Restaurant - Main Configuration File
 * This file contains all system configurations and database settings
 */

// Force UTF-8 encoding to prevent character issues
mb_internal_encoding('UTF-8');

// Prevent direct access
if (!defined('MINANG_SYSTEM')) {
    define('MINANG_SYSTEM', true);
}

// Error Reporting (Set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// System Version
define('SYSTEM_VERSION', '1.0.0');
define('SYSTEM_NAME', 'Minang Restaurant POS & ERP');

define('DB_HOST', 'localhost');
define('DB_NAME', 'minang');
define('DB_USER', 'user');
define('DB_PASS', '123456');
define('DB_CHARSET', 'utf8mb4');

// System Paths
define('ROOT_PATH', dirname(dirname(__FILE__)));
define('POS_PATH', ROOT_PATH . '/pos/');
define('ERP_PATH', ROOT_PATH . '/erp/');
define('ASSETS_PATH', ROOT_PATH . '/assets/');
define('UPLOADS_PATH', ROOT_PATH . '/assets/uploads/');
define('CLASSES_PATH', ROOT_PATH . '/classes/');

// System URLs (Update these according to your domain)
define('BASE_URL', 'http://localhost/minang-restaurant-system/');
define('POS_URL', 'http://pos.minangrestaurant.com/');
define('ERP_URL', 'http://portal.minangrestaurant.com/');
define('ASSETS_URL', BASE_URL . 'assets/');

// Session Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('SESSION_NAME', 'MINANG_SESSION');

// Security Settings
define('ENCRYPTION_KEY', 'MinangRestaurant2024SecureKey!@#');
define('PASSWORD_SALT', 'Minang_Salt_2024');

// Business Settings
define('BUSINESS_NAME', 'Langit Minang Restaurant');
define('BUSINESS_NAME_AR', 'مطعم لانجيت مينانج');
define('BUSINESS_ADDRESS', 'Level M, Doha Souq Mall, Doha, Qatar');
define('BUSINESS_PHONE', '+974-XXXX-XXXX');
define('BUSINESS_EMAIL', 'info@minangrestaurant.com');
define('BUSINESS_CR', 'CR-123456789');
define('BUSINESS_WEBSITE', 'www.minangrestaurant.com');

// Currency Settings - Using MINANG_ prefix to avoid hosting conflicts
define('MINANG_CURRENCY_CODE', 'QAR');
define('MINANG_CURRENCY_SYMBOL', 'QR');
define('MINANG_CURRENCY_POSITION', 'before');

// Backward compatibility - only define if not corrupted
if (!defined('CURRENCY_CODE')) {
    define('CURRENCY_CODE', 'QAR');
}
if (!defined('CURRENCY_POSITION')) {
    define('CURRENCY_POSITION', 'before');
}
// Note: Skip CURRENCY_SYMBOL as it's corrupted by hosting environment (shows 262145)

// Date & Time Settings
define('TIMEZONE', 'Asia/Qatar');
define('DATE_FORMAT', 'd/m/Y');
define('TIME_FORMAT', 'H:i:s');
define('DATETIME_FORMAT', 'd/m/Y H:i:s');

// File Upload Settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_DOC_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

// Receipt Settings
define('RECEIPT_WIDTH', '80mm');
define('RECEIPT_FONT_SIZE', '12px');
define('RECEIPT_LINE_HEIGHT', '1.2');

// POS Settings
define('DEFAULT_TAX_RATE', 0); // Qatar is tax-free
define('CASH_DRAWER_ENABLED', true);
define('AUTO_PRINT_RECEIPT', true);
define('CUSTOMER_DISPLAY_ENABLED', false);

// User Roles
define('ROLE_ADMIN', 1);
define('ROLE_MANAGER', 2);
define('ROLE_TOP_MANAGEMENT', 3);
define('ROLE_CASHIER', 4);
define('ROLE_WAITER', 5);
define('ROLE_KITCHEN_STAFF', 6);
define('ROLE_CHEF', 7);

// User Role Names
$USER_ROLES = [
    ROLE_ADMIN => 'Admin',
    ROLE_MANAGER => 'Manager', 
    ROLE_TOP_MANAGEMENT => 'Top Management',
    ROLE_CASHIER => 'Cashier',
    ROLE_WAITER => 'Waiter',
    ROLE_KITCHEN_STAFF => 'Kitchen Staff',
    ROLE_CHEF => 'Chef'
];

// Payment Methods
define('PAYMENT_CASH', 1);
define('PAYMENT_CARD', 2);
define('PAYMENT_CREDIT', 3);
define('PAYMENT_FOC', 4);
define('PAYMENT_COD', 5);

$PAYMENT_METHODS = [
    PAYMENT_CASH => 'Cash',
    PAYMENT_CARD => 'Card',
    PAYMENT_CREDIT => 'Credit',
    PAYMENT_FOC => 'FOC',
    PAYMENT_COD => 'COD'
];

// Order Types
define('ORDER_DINE_IN', 1);
define('ORDER_TAKEAWAY', 2);
define('ORDER_DELIVERY', 3);

$ORDER_TYPES = [
    ORDER_DINE_IN => 'Dine-In',
    ORDER_TAKEAWAY => 'Take Away',
    ORDER_DELIVERY => 'Delivery'
];

// System Colors (Premium Professional Palette)
define('PRIMARY_COLOR', '#2563eb');      // Professional Blue
define('SECONDARY_COLOR', '#64748b');    // Slate Gray
define('SUCCESS_COLOR', '#059669');      // Emerald Green
define('WARNING_COLOR', '#d97706');      // Amber Orange
define('DANGER_COLOR', '#dc2626');       // Red
define('INFO_COLOR', '#0891b2');         // Cyan
define('LIGHT_COLOR', '#f8fafc');        // Light Gray
define('DARK_COLOR', '#1e293b');         // Dark Slate

// QR Denominations (Qatar Riyal)
$QR_DENOMINATIONS = [
    0.25, 0.50, 1, 5, 10, 50, 100, 500
];

// Default Staff User (Yanti)
define('DEFAULT_USER_NAME', 'Yanti');
define('DEFAULT_USERNAME', 'yanti');
define('DEFAULT_PASSWORD', 'cas@yanti'); // Will be hashed in database
define('DEFAULT_USER_ROLE', ROLE_CASHIER);

// Set Timezone
date_default_timezone_set(TIMEZONE);

// Auto-include essential classes
function minang_autoload($class_name) {
    $class_file = CLASSES_PATH . $class_name . '.php';
    if (file_exists($class_file)) {
        require_once $class_file;
    }
}
spl_autoload_register('minang_autoload');

// Session Start
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Helper Functions - FIXED to use proper currency symbol
function formatCurrency($amount) {
    // Use our safe currency symbol instead of the corrupted CURRENCY_SYMBOL
    $symbol = MINANG_CURRENCY_SYMBOL;  // This will always be 'QR'
    $amount = is_numeric($amount) ? floatval($amount) : 0;
    
    if (MINANG_CURRENCY_POSITION === 'before') {
        return $symbol . ' ' . number_format($amount, 2);
    } else {
        return number_format($amount, 2) . ' ' . $symbol;
    }
}

function formatDate($date, $format = DATE_FORMAT) {
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = DATETIME_FORMAT) {
    return date($format, strtotime($datetime));
}

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function hashPassword($password) {
    return password_hash($password . PASSWORD_SALT, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password . PASSWORD_SALT, $hash);
}

function generateOrderNumber() {
    $date = date('md'); // MMDD format
    $time = date('His'); // HHMMSS format
    return 'LMR-' . $date . '-' . $time;
}

function generateReceiptNumber() {
    return 'R' . date('Ymd') . '-' . sprintf('%06d', mt_rand(1, 999999));
}

// System Status Check
function checkSystemStatus() {
    $status = [
        'database' => false,
        'uploads_writable' => false,
        'session_active' => false
    ];
    
    // Check database connection
    try {
        $db = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER, 
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $status['database'] = true;
    } catch(PDOException $e) {
        $status['database'] = false;
    }
    
    // Check uploads directory
    $status['uploads_writable'] = is_writable(UPLOADS_PATH);
    
    // Check session
    $status['session_active'] = (session_status() === PHP_SESSION_ACTIVE);
    
    return $status;
}

// Initialize system
$SYSTEM_STATUS = checkSystemStatus();

?>