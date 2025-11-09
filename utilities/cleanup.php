<?php
/**
 * System Cleanup Utility
 * Clean up old data, temporary files, and optimize database
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

// Only allow CLI execution or admin users
if (php_sapi_name() !== 'cli' && (!User::isLoggedIn() || !User::hasPermission('user_manage'))) {
    exit('Unauthorized access');
}

$db = Database::getInstance();
$cleanupReport = [];

echo "Starting system cleanup...\n";

// 1. Clean old session files (older than 30 days)
echo "Cleaning old sessions...\n";
$sessionPath = session_save_path() ?: sys_get_temp_dir();
$sessionFiles = glob($sessionPath . '/sess_*');
$sessionsCleaned = 0;

foreach ($sessionFiles as $file) {
    if (filemtime($file) < time() - (30 * 24 * 60 * 60)) {
        if (unlink($file)) {
            $sessionsCleaned++;
        }
    }
}

$cleanupReport[] = "Cleaned {$sessionsCleaned} old session files";

// 2. Clean old temporary receipt files
echo "Cleaning temporary files...\n";
$tempDir = '../temp/';
if (is_dir($tempDir)) {
    $tempFiles = glob($tempDir . '*');
    $tempCleaned = 0;
    
    foreach ($tempFiles as $file) {
        if (is_file($file) && filemtime($file) < time() - (24 * 60 * 60)) {
            if (unlink($file)) {
                $tempCleaned++;
            }
        }
    }
    
    $cleanupReport[] = "Cleaned {$tempCleaned} temporary files";
}

// 3. Archive old sales data (older than 1 year)
echo "Archiving old sales data...\n";
$oldSalesCount = $db->count('sales', 'created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)');

if ($oldSalesCount > 0) {
    // Create archive tables if they don't exist
    $db->query("
        CREATE TABLE IF NOT EXISTS sales_archive LIKE sales
    ");
    
    $db->query("
        CREATE TABLE IF NOT EXISTS sale_items_archive LIKE sale_items
    ");
    
    // Move old sales to archive
    $db->query("
        INSERT INTO sales_archive 
        SELECT * FROM sales 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)
    ");
    
    $db->query("
        INSERT INTO sale_items_archive 
        SELECT si.* FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.id
        WHERE s.created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)
    ");
    
    // Delete old sales after archiving
    $db->query("
        DELETE si FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.id
        WHERE s.created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)
    ");
    
    $db->query("
        DELETE FROM sales 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)
    ");
    
    $cleanupReport[] = "Archived {$oldSalesCount} old sales records";
}

// 4. Clean old attendance records (older than 2 years)
echo "Cleaning old attendance records...\n";
$oldAttendanceCount = $db->count('attendance', 'attendance_date < DATE_SUB(CURDATE(), INTERVAL 2 YEAR)');

if ($oldAttendanceCount > 0) {
    $db->query("
        DELETE FROM attendance 
        WHERE attendance_date < DATE_SUB(CURDATE(), INTERVAL 2 YEAR)
    ");
    
    $cleanupReport[] = "Cleaned {$oldAttendanceCount} old attendance records";
}

// 5. Clean old stock movements (older than 1 year)
echo "Cleaning old stock movements...\n";
$oldMovementsCount = $db->count('stock_movements', 'created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)');

if ($oldMovementsCount > 0) {
    $db->query("
        DELETE FROM stock_movements 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)
    ");
    
    $cleanupReport[] = "Cleaned {$oldMovementsCount} old stock movements";
}

// 6. Clean old backups (based on retention setting)
echo "Cleaning old backups...\n";
$retentionDays = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'backup_retention_days'");
$retentionDays = intval($retentionDays['setting_value'] ?? 30);

$backupDir = '../backup/auto-backups/';
if (is_dir($backupDir)) {
    $backupFiles = glob($backupDir . '*.sql');
    $backupsCleaned = 0;
    $cutoffTime = time() - ($retentionDays * 24 * 60 * 60);
    
    foreach ($backupFiles as $file) {
        if (filemtime($file) < $cutoffTime) {
            if (unlink($file)) {
                $backupsCleaned++;
            }
        }
    }
    
    $cleanupReport[] = "Cleaned {$backupsCleaned} old backup files";
}

// 7. Optimize database tables
echo "Optimizing database tables...\n";
$tables = ['sales', 'sale_items', 'products', 'purchases', 'attendance', 'users', 'shifts'];
$optimizedTables = 0;

foreach ($tables as $table) {
    try {
        $db->query("OPTIMIZE TABLE {$table}");
        $optimizedTables++;
    } catch (Exception $e) {
        // Continue with other tables
    }
}

$cleanupReport[] = "Optimized {$optimizedTables} database tables";

// 8. Update system statistics
echo "Updating system statistics...\n";
$stats = [
    'last_cleanup' => date('Y-m-d H:i:s'),
    'total_products' => $db->count('products', 'is_active = 1'),
    'total_sales' => $db->count('sales'),
    'total_users' => $db->count('users', 'is_active = 1')
];

foreach ($stats as $key => $value) {
    $db->query(
        "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()",
        [$key, $value, $value]
    );
}

$cleanupReport[] = "Updated system statistics";

// Generate cleanup report
echo "\n=== CLEANUP COMPLETED ===\n";
foreach ($cleanupReport as $report) {
    echo "✓ {$report}\n";
}

echo "\nCleanup completed at: " . date('Y-m-d H:i:s') . "\n";

// Save cleanup log
$logData = [
    'cleanup_date' => date('Y-m-d H:i:s'),
    'report' => implode("\n", $cleanupReport),
    'executed_by' => User::isLoggedIn() ? $_SESSION['user_id'] : 'CLI'
];

$db->insert('system_logs', [
    'log_type' => 'CLEANUP',
    'log_message' => json_encode($logData),
    'created_at' => date('Y-m-d H:i:s')
]);

// If run via web, show results
if (php_sapi_name() !== 'cli') {
    echo '<pre>' . implode("\n", $cleanupReport) . '</pre>';
}
?>