<?php
/**
 * Daily Maintenance Tasks
 * Run automated daily maintenance and cleanup tasks
 * Add to cron: 0 2 * * * /usr/bin/php /path/to/daily-tasks.php
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

// Ensure script runs only from command line or authorized source
if (php_sapi_name() !== 'cli' && !isset($_GET['auth_key']) || $_GET['auth_key'] !== 'minang_cron_2024') {
    exit('Access denied');
}

$db = Database::getInstance();
$logMessages = [];

function logTask($message) {
    global $logMessages;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}";
    $logMessages[] = $logMessage;
    echo $logMessage . "\n";
    
    // Also log to file
    error_log($logMessage, 3, '../logs/daily-tasks.log');
}

logTask("Starting daily maintenance tasks...");

try {
    // 1. Generate stock alerts
    logTask("Checking inventory levels...");
    $systemAlert = new SystemAlert();
    $systemAlert->generateStockAlerts();
    logTask("Stock alerts generated");
    
    // 2. Check unpaid purchase invoices
    logTask("Checking unpaid purchase invoices...");
    $systemAlert->checkUnpaidPurchaseAlerts();
    logTask("Purchase payment alerts generated");
    
    // 3. Clean up old alerts
    logTask("Cleaning up old alerts...");
    $cleanedAlerts = $systemAlert->cleanupOldAlerts(7);
    logTask("Cleaned {$cleanedAlerts} old alerts");
    
    // 4. Database cleanup
    logTask("Performing database cleanup...");
    
    // Remove expired sessions
    $cleanedSessions = $db->delete('user_sessions', 'expires_at < NOW()');
    logTask("Cleaned {$cleanedSessions} expired sessions");
    
    // Clean up old customer display messages
    $cleanedDisplays = $db->delete('customer_display_messages', 'created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    logTask("Cleaned {$cleanedDisplays} old display messages");
    
    // 5. Auto-backup database (if enabled)
    $autoBackup = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'auto_backup'");
    
    if ($autoBackup && $autoBackup['setting_value'] === '1') {
        logTask("Creating automatic database backup...");
        
        $backupDir = '../backup/auto-backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $backupFile = $backupDir . 'auto_backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --single-transaction --routines --triggers %s > %s',
            DB_USER,
            DB_PASS,
            DB_HOST,
            DB_NAME,
            escapeshellarg($backupFile)
        );
        
        exec($command . ' 2>&1', $output, $returnVar);
        
        if ($returnVar === 0 && file_exists($backupFile) && filesize($backupFile) > 0) {
            logTask("Database backup created successfully: " . basename($backupFile));
            
            // Clean old backups
            $retentionDays = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'backup_retention_days'");
            $retentionDays = intval($retentionDays['setting_value'] ?? 30);
            
            $cutoffTime = time() - ($retentionDays * 24 * 60 * 60);
            $files = glob($backupDir . '*.sql');
            
            $deletedCount = 0;
            foreach ($files as $file) {
                if (filemtime($file) < $cutoffTime) {
                    unlink($file);
                    $deletedCount++;
                }
            }
            
            if ($deletedCount > 0) {
                logTask("Cleaned {$deletedCount} old backup files");
            }
        } else {
            logTask("ERROR: Database backup failed - " . implode('; ', $output));
        }
    }
    
    // 6. Update system statistics
    logTask("Updating system statistics...");
    
    $todayStats = [
        'total_sales' => $db->count('sales', 'DATE(created_at) = CURDATE()'),
        'total_revenue' => $db->fetchOne("SELECT COALESCE(SUM(total), 0) as revenue FROM sales WHERE DATE(created_at) = CURDATE()")['revenue'],
        'active_products' => $db->count('products', 'is_active = 1 AND quantity > 0'),
        'low_stock_count' => $db->count('products', 'is_active = 1 AND quantity <= reorder_level'),
        'staff_present' => $db->count('attendance', 'attendance_date = CURDATE() AND sign_in_time IS NOT NULL')
    ];
    
    foreach ($todayStats as $key => $value) {
        $db->query(
            "INSERT INTO daily_stats (stat_date, stat_key, stat_value) 
             VALUES (CURDATE(), ?, ?) 
             ON DUPLICATE KEY UPDATE stat_value = ?, updated_at = NOW()",
            [$key, $value, $value]
        );
    }
    logTask("System statistics updated");
    
    // 7. Send daily summary email (if configured)
    $emailSettings = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'daily_email_reports'");
    
    if ($emailSettings && $emailSettings['setting_value'] === '1') {
        logTask("Preparing daily summary email...");
        
        $emailData = [
            'date' => date('Y-m-d'),
            'stats' => $todayStats,
            'alerts' => $systemAlert->getUserAlerts(null, 5)
        ];
        
        // Send email functionality would be implemented here
        logTask("Daily summary email prepared (email sending not implemented)");
    }
    
    logTask("All daily maintenance tasks completed successfully");
    
    // Update last run timestamp
    $db->query(
        "INSERT INTO settings (setting_key, setting_value) VALUES ('last_daily_maintenance', ?) ON DUPLICATE KEY UPDATE setting_value = ?",
        [date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
    );
    
} catch (Exception $e) {
    logTask("ERROR: " . $e->getMessage());
    
    // Send error notification to admin
    $systemAlert = new SystemAlert();
    $systemAlert->createAlert(
        'MAINTENANCE_ERROR',
        'Daily Maintenance Failed',
        "Daily maintenance tasks failed: " . $e->getMessage(),
        1, // Send to admin user
        'high'
    );
}

logTask("Daily maintenance script completed");

// If running via web (for testing), show results
if (php_sapi_name() !== 'cli') {
    echo "<html><body><h2>Daily Maintenance Results</h2><pre>";
    foreach ($logMessages as $message) {
        echo htmlspecialchars($message) . "\n";
    }
    echo "</pre></body></html>";
}
?>