<?php
/**
 * Database Backup and Restore API
 * Handle database backup and restore operations
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

header('Content-Type: application/json');

// Check authentication and permissions
if (!User::isLoggedIn() || !User::hasPermission('user_manage')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action'];

switch ($action) {
    case 'create':
        $response = createDatabaseBackup();
        break;
        
    case 'restore':
        $response = restoreDatabase();
        break;
        
    case 'list':
        $response = listBackups();
        break;
        
    case 'delete':
        $backupFile = $_GET['file'] ?? '';
        $response = deleteBackup($backupFile);
        break;
}

echo json_encode($response);

function createDatabaseBackup() {
    try {
        $backupDir = '../backup/auto-backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . $filename;
        
        // Generate mysqldump command
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --port=3306 --single-transaction --routines --triggers %s > %s',
            DB_USER,
            DB_PASS,
            DB_HOST,
            DB_NAME,
            escapeshellarg($filepath)
        );
        
        // Execute backup
        $output = [];
        $returnVar = 0;
        exec($command . ' 2>&1', $output, $returnVar);
        
        if ($returnVar === 0 && file_exists($filepath) && filesize($filepath) > 0) {
            // Clean old backups based on retention setting
            cleanOldBackups($backupDir);
            
            // For direct download
            if (isset($_GET['download'])) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($filepath));
                readfile($filepath);
                exit();
            }
            
            return [
                'success' => true, 
                'message' => 'Backup created successfully',
                'filename' => $filename,
                'size' => formatBytes(filesize($filepath))
            ];
        } else {
            return [
                'success' => false, 
                'message' => 'Backup failed: ' . implode('\n', $output)
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false, 
            'message' => 'Backup error: ' . $e->getMessage()
        ];
    }
}

function restoreDatabase() {
    try {
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'No backup file uploaded'];
        }
        
        $uploadedFile = $_FILES['backup_file']['tmp_name'];
        $fileName = $_FILES['backup_file']['name'];
        
        // Validate file extension
        if (pathinfo($fileName, PATHINFO_EXTENSION) !== 'sql') {
            return ['success' => false, 'message' => 'Invalid file type. Only .sql files allowed'];
        }
        
        // Read SQL file content
        $sqlContent = file_get_contents($uploadedFile);
        if (!$sqlContent) {
            return ['success' => false, 'message' => 'Could not read backup file'];
        }
        
        $db = Database::getInstance();
        
        // Disable foreign key checks temporarily
        $db->query("SET FOREIGN_KEY_CHECKS = 0");
        
        // Split and execute SQL statements
        $statements = array_filter(array_map('trim', explode(';', $sqlContent)));
        $executedCount = 0;
        $errors = [];
        
        foreach ($statements as $statement) {
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            
            $result = $db->query($statement);
            if ($result) {
                $executedCount++;
            } else {
                $errors[] = "Failed to execute: " . substr($statement, 0, 50) . "...";
            }
        }
        
        // Re-enable foreign key checks
        $db->query("SET FOREIGN_KEY_CHECKS = 1");
        
        if (empty($errors)) {
            // Log the restore operation
            error_log("Database restored by user ID: " . $_SESSION['user_id'] . " at " . date('Y-m-d H:i:s'));
            
            return [
                'success' => true, 
                'message' => "Database restored successfully. Executed {$executedCount} statements."
            ];
        } else {
            return [
                'success' => false, 
                'message' => 'Restore completed with errors: ' . implode(', ', array_slice($errors, 0, 3))
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false, 
            'message' => 'Restore error: ' . $e->getMessage()
        ];
    }
}

function listBackups() {
    $backupDir = '../backup/auto-backups/';
    $backups = [];
    
    if (is_dir($backupDir)) {
        $files = glob($backupDir . '*.sql');
        
        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'size' => formatBytes(filesize($file)),
                'date' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
        
        // Sort by date (newest first)
        usort($backups, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
    }
    
    return ['success' => true, 'backups' => $backups];
}

function deleteBackup($filename) {
    $backupDir = '../backup/auto-backups/';
    $filepath = $backupDir . $filename;
    
    if (file_exists($filepath) && unlink($filepath)) {
        return ['success' => true, 'message' => 'Backup deleted successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to delete backup'];
    }
}

function cleanOldBackups($backupDir) {
    $db = Database::getInstance();
    $retentionDays = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'backup_retention_days'");
    $retentionDays = intval($retentionDays['setting_value'] ?? 30);
    
    $cutoffTime = time() - ($retentionDays * 24 * 60 * 60);
    $files = glob($backupDir . '*.sql');
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoffTime) {
            unlink($file);
        }
    }
}

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    
    for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}
?>