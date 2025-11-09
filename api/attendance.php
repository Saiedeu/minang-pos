<?php
/**
 * Attendance API Handler
 * Handles AJAX requests for attendance operations
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

header('Content-Type: application/json');

// Check authentication
if (!User::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

$db = Database::getInstance();
$response = ['success' => false, 'message' => 'Invalid action'];

switch ($action) {
    case 'clock_in':
    case 'clock_out':
        $staffId = $input['staff_id'] ?? 0;
        $notes = sanitize($input['notes'] ?? '');
        
        if (!$staffId) {
            $response = ['success' => false, 'message' => 'Staff ID required'];
            break;
        }
        
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        
        // Check existing attendance
        $attendance = $db->fetchOne(
            "SELECT * FROM attendance WHERE user_id = ? AND attendance_date = ?",
            [$staffId, $today]
        );
        
        if ($action === 'clock_in') {
            if ($attendance && $attendance['sign_in_time']) {
                $response = ['success' => false, 'message' => 'Already signed in today'];
            } else {
                if ($attendance) {
                    $updated = $db->update('attendance', 
                        ['sign_in_time' => $now, 'notes' => $notes], 
                        'id = ?', 
                        [$attendance['id']]
                    );
                } else {
                    $updated = $db->insert('attendance', [
                        'user_id' => $staffId,
                        'attendance_date' => $today,
                        'sign_in_time' => $now,
                        'notes' => $notes
                    ]);
                }
                
                $response = $updated ? 
                    ['success' => true, 'message' => 'Sign-in recorded successfully'] :
                    ['success' => false, 'message' => 'Failed to record sign-in'];
            }
        } else { // clock_out
            if (!$attendance || !$attendance['sign_in_time']) {
                $response = ['success' => false, 'message' => 'Must sign in first'];
            } elseif ($attendance['sign_out_time']) {
                $response = ['success' => false, 'message' => 'Already signed out today'];
            } else {
                // Calculate hours
                $signInTime = new DateTime($attendance['sign_in_time']);
                $signOutTime = new DateTime($now);
                $interval = $signInTime->diff($signOutTime);
                $totalHours = $interval->h + ($interval->i / 60);
                
                $updated = $db->update('attendance', 
                    [
                        'sign_out_time' => $now,
                        'total_hours' => round($totalHours, 2),
                        'notes' => $attendance['notes'] . ($notes ? ' | ' . $notes : '')
                    ], 
                    'id = ?', 
                    [$attendance['id']]
                );
                
                $response = $updated ?
                    ['success' => true, 'message' => 'Sign-out recorded successfully', 'hours' => round($totalHours, 2)] :
                    ['success' => false, 'message' => 'Failed to record sign-out'];
            }
        }
        break;
        
    case 'get_staff_status':
        $staffId = $_GET['staff_id'] ?? 0;
        $today = date('Y-m-d');
        
        $attendance = $db->fetchOne(
            "SELECT * FROM attendance WHERE user_id = ? AND attendance_date = ?",
            [$staffId, $today]
        );
        
        $status = 'not_started';
        if ($attendance) {
            if ($attendance['sign_in_time'] && $attendance['sign_out_time']) {
                $status = 'completed';
            } elseif ($attendance['sign_in_time']) {
                $status = 'working';
            }
        }
        
        $response = [
            'success' => true,
            'status' => $status,
            'sign_in_time' => $attendance['sign_in_time'] ?? null,
            'sign_out_time' => $attendance['sign_out_time'] ?? null,
            'total_hours' => $attendance['total_hours'] ?? null
        ];
        break;
        
    case 'get_today_attendance':
        $todayAttendance = $db->fetchAll("
            SELECT a.*, u.name, u.role
            FROM attendance a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.attendance_date = ?
            ORDER BY a.sign_in_time DESC
        ", [date('Y-m-d')]);
        
        $response = ['success' => true, 'data' => $todayAttendance];
        break;
        
    case 'barcode_scan':
        $qidNumber = sanitize($input['qid_number'] ?? '');
        
        // Find staff by QID
        $staff = $db->fetchOne("SELECT * FROM users WHERE qid_number = ? AND is_active = 1", [$qidNumber]);
        
        if (!$staff) {
            $response = ['success' => false, 'message' => 'QID number not found'];
            break;
        }
        
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        
        // Check attendance status
        $attendance = $db->fetchOne(
            "SELECT * FROM attendance WHERE user_id = ? AND attendance_date = ?",
            [$staff['id'], $today]
        );
        
        if (!$attendance || !$attendance['sign_in_time']) {
            // Sign in
            if ($attendance) {
                $updated = $db->update('attendance', ['sign_in_time' => $now], 'id = ?', [$attendance['id']]);
            } else {
                $updated = $db->insert('attendance', [
                    'user_id' => $staff['id'],
                    'attendance_date' => $today,
                    'sign_in_time' => $now
                ]);
            }
            
            $response = $updated ?
                ['success' => true, 'message' => $staff['name'] . ' signed in successfully', 'action' => 'sign_in'] :
                ['success' => false, 'message' => 'Failed to sign in'];
                
        } elseif (!$attendance['sign_out_time']) {
            // Sign out
            $signInTime = new DateTime($attendance['sign_in_time']);
            $signOutTime = new DateTime($now);
            $interval = $signInTime->diff($signOutTime);
            $totalHours = $interval->h + ($interval->i / 60);
            
            $updated = $db->update('attendance', 
                [
                    'sign_out_time' => $now,
                    'total_hours' => round($totalHours, 2)
                ], 
                'id = ?', 
                [$attendance['id']]
            );
            
            $response = $updated ?
                ['success' => true, 'message' => $staff['name'] . ' signed out successfully', 'action' => 'sign_out', 'hours' => round($totalHours, 2)] :
                ['success' => false, 'message' => 'Failed to sign out'];
                
        } else {
            $response = ['success' => false, 'message' => $staff['name'] . ' has already completed attendance for today'];
        }
        break;
}

echo json_encode($response);
?>