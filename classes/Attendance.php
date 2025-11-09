<?php
/**
 * Attendance Management Class
 * Handles staff attendance operations
 */

if (!defined('MINANG_SYSTEM')) {
    exit('Direct access not allowed');
}

class Attendance {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Sign in/out by QID barcode
    public function toggleAttendanceByQID($qidNumber) {
        // Get user by QID
        $user = $this->db->fetchOne("SELECT * FROM users WHERE qid_number = ? AND is_active = 1", [$qidNumber]);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Staff member not found'];
        }

        $today = date('Y-m-d');
        
        // Check if user has attendance record for today
        $attendance = $this->db->fetchOne(
            "SELECT * FROM attendance WHERE user_id = ? AND attendance_date = ?",
            [$user['id'], $today]
        );

        if (!$attendance) {
            // Sign In
            $attendanceData = [
                'user_id' => $user['id'],
                'attendance_date' => $today,
                'sign_in_time' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $inserted = $this->db->insert('attendance', $attendanceData);
            
            if ($inserted) {
                return [
                    'success' => true, 
                    'action' => 'sign_in', 
                    'user' => $user,
                    'time' => date('H:i:s')
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to record sign in'];
            }
        } elseif (!$attendance['sign_out_time']) {
            // Sign Out
            $signOutTime = date('Y-m-d H:i:s');
            $signInTime = new DateTime($attendance['sign_in_time']);
            $signOutTimeObj = new DateTime($signOutTime);
            $totalHours = $signInTime->diff($signOutTimeObj)->h + ($signInTime->diff($signOutTimeObj)->i / 60);
            
            $updateData = [
                'sign_out_time' => $signOutTime,
                'total_hours' => $totalHours
            ];
            
            $updated = $this->db->update('attendance', $updateData, 'id = ?', [$attendance['id']]);
            
            if ($updated) {
                return [
                    'success' => true, 
                    'action' => 'sign_out', 
                    'user' => $user,
                    'time' => date('H:i:s'),
                    'total_hours' => $totalHours
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to record sign out'];
            }
        } else {
            return [
                'success' => false, 
                'message' => 'Staff member already completed attendance for today'
            ];
        }
    }

    // Manual attendance by user search
    public function manualAttendance($userId, $action, $notes = '') {
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ? AND is_active = 1", [$userId]);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Staff member not found'];
        }

        $today = date('Y-m-d');
        $attendance = $this->db->fetchOne(
            "SELECT * FROM attendance WHERE user_id = ? AND attendance_date = ?",
            [$userId, $today]
        );

        if ($action === 'sign_in') {
            if ($attendance && $attendance['sign_in_time']) {
                return ['success' => false, 'message' => 'Staff member already signed in today'];
            }
            
            if (!$attendance) {
                $attendanceData = [
                    'user_id' => $userId,
                    'attendance_date' => $today,
                    'sign_in_time' => date('Y-m-d H:i:s'),
                    'notes' => $notes,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $inserted = $this->db->insert('attendance', $attendanceData);
            } else {
                $updated = $this->db->update('attendance', 
                    ['sign_in_time' => date('Y-m-d H:i:s'), 'notes' => $notes], 
                    'id = ?', 
                    [$attendance['id']]
                );
                $inserted = $updated;
            }
            
            return $inserted ? 
                ['success' => true, 'action' => 'sign_in', 'user' => $user] : 
                ['success' => false, 'message' => 'Failed to record sign in'];
        }

        if ($action === 'sign_out') {
            if (!$attendance || !$attendance['sign_in_time']) {
                return ['success' => false, 'message' => 'Staff member has not signed in today'];
            }
            
            if ($attendance['sign_out_time']) {
                return ['success' => false, 'message' => 'Staff member already signed out today'];
            }

            $signOutTime = date('Y-m-d H:i:s');
            $signInTime = new DateTime($attendance['sign_in_time']);
            $signOutTimeObj = new DateTime($signOutTime);
            $totalHours = $signInTime->diff($signOutTimeObj)->h + ($signInTime->diff($signOutTimeObj)->i / 60);
            
            $updateData = [
                'sign_out_time' => $signOutTime,
                'total_hours' => $totalHours,
                'notes' => $notes
            ];
            
            $updated = $this->db->update('attendance', $updateData, 'id = ?', [$attendance['id']]);
            
            return $updated ? 
                ['success' => true, 'action' => 'sign_out', 'user' => $user, 'total_hours' => $totalHours] : 
                ['success' => false, 'message' => 'Failed to record sign out'];
        }

        return ['success' => false, 'message' => 'Invalid action'];
    }

    // Get today's attendance
    public function getTodayAttendance() {
        $today = date('Y-m-d');
        
        $sql = "SELECT a.*, u.name, u.role 
                FROM attendance a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE a.attendance_date = ?
                ORDER BY a.sign_in_time DESC";
        
        return $this->db->fetchAll($sql, [$today]);
    }

    // Get attendance by date range
    public function getAttendanceByDateRange($startDate, $endDate, $userId = null) {
        $where = "a.attendance_date BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        
        if ($userId) {
            $where .= " AND a.user_id = ?";
            $params[] = $userId;
        }
        
        $sql = "SELECT a.*, u.name, u.role 
                FROM attendance a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE {$where}
                ORDER BY a.attendance_date DESC, a.sign_in_time DESC";
        
        return $this->db->fetchAll($sql, $params);
    }

    // Get staff who haven't signed in today
    public function getAbsentToday() {
        $today = date('Y-m-d');
        
        $sql = "SELECT u.* 
                FROM users u
                LEFT JOIN attendance a ON u.id = a.user_id AND a.attendance_date = ?
                WHERE u.is_active = 1 
                AND u.role IN (4, 5, 6, 7)
                AND a.user_id IS NULL
                ORDER BY u.name";
        
        return $this->db->fetchAll($sql, [$today]);
    }

    // Get attendance summary for a user
    public function getUserAttendanceSummary($userId, $month = null, $year = null) {
        $month = $month ?? date('m');
        $year = $year ?? date('Y');
        
        $sql = "SELECT 
                    COUNT(*) as total_days,
                    COUNT(CASE WHEN sign_in_time IS NOT NULL THEN 1 END) as present_days,
                    COUNT(CASE WHEN sign_in_time IS NULL THEN 1 END) as absent_days,
                    AVG(total_hours) as avg_hours,
                    SUM(total_hours) as total_hours
                FROM attendance 
                WHERE user_id = ? 
                AND MONTH(attendance_date) = ? 
                AND YEAR(attendance_date) = ?";
        
        return $this->db->fetchOne($sql, [$userId, $month, $year]);
    }

    // Get late arrivals (after 9:00 AM)
    public function getLateArrivals($date = null) {
        $date = $date ?? date('Y-m-d');
        
        $sql = "SELECT a.*, u.name 
                FROM attendance a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE a.attendance_date = ? 
                AND TIME(a.sign_in_time) > '09:00:00'
                ORDER BY a.sign_in_time";
        
        return $this->db->fetchAll($sql, [$date]);
    }

    // Get early departures (before 17:00)
    public function getEarlyDepartures($date = null) {
        $date = $date ?? date('Y-m-d');
        
        $sql = "SELECT a.*, u.name 
                FROM attendance a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE a.attendance_date = ? 
                AND a.sign_out_time IS NOT NULL
                AND TIME(a.sign_out_time) < '17:00:00'
                ORDER BY a.sign_out_time";
        
        return $this->db->fetchAll($sql, [$date]);
    }

    // Export attendance to CSV
    public function exportAttendanceCSV($startDate, $endDate, $userId = null) {
        $attendance = $this->getAttendanceByDateRange($startDate, $endDate, $userId);
        
        $csv = "Date,Staff Name,Role,Sign In,Sign Out,Total Hours,Notes\n";
        
        foreach ($attendance as $record) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s\n",
                $record['attendance_date'],
                $record['name'],
                User::getRoleName($record['role']),
                $record['sign_in_time'] ? date('H:i:s', strtotime($record['sign_in_time'])) : 'Not signed in',
                $record['sign_out_time'] ? date('H:i:s', strtotime($record['sign_out_time'])) : 'Not signed out',
                $record['total_hours'] ?? '0',
                $record['notes'] ?? ''
            );
        }
        
        return $csv;
    }
}
?>