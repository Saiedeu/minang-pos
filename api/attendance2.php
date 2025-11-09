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

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

$attendance = new Attendance();
$response = ['success' => false, 'message' => 'Invalid action'];

switch ($action) {
    case 'qid_scan':
        $qidNumber = sanitize($input['qid_number'] ?? '');
        if ($qidNumber) {
            $response = $attendance->toggleAttendanceByQID($qidNumber);
        } else {
            $response = ['success' => false, 'message' => 'QID number required'];
        }
        break;
        
    case 'manual_attendance':
        $userId = intval($input['user_id'] ?? 0);
        $attendanceAction = $input['attendance_action'] ?? '';
        $notes = sanitize($input['notes'] ?? '');
        
        if ($userId && $attendanceAction) {
            $response = $attendance->manualAttendance($userId, $attendanceAction, $notes);
        } else {
            $response = ['success' => false, 'message' => 'User ID and action required'];
        }
        break;
        
    case 'get_today_attendance':
        $todayAttendance = $attendance->getTodayAttendance();
        $response = ['success' => true, 'data' => $todayAttendance];
        break;
        
    case 'get_absent_today':
        $absentStaff = $attendance->getAbsentToday();
        $response = ['success' => true, 'data' => $absentStaff];
        break;
        
    case 'get_attendance_range':
        $startDate = $input['start_date'] ?? date('Y-m-01');
        $endDate = $input['end_date'] ?? date('Y-m-d');
        $userId = $input['user_id'] ?? null;
        
        $attendanceData = $attendance->getAttendanceByDateRange($startDate, $endDate, $userId);
        $response = ['success' => true, 'data' => $attendanceData];
        break;
        
    case 'export_attendance':
        $startDate = $input['start_date'] ?? date('Y-m-01');
        $endDate = $input['end_date'] ?? date('Y-m-d');
        $userId = $input['user_id'] ?? null;
        
        $csv = $attendance->exportAttendanceCSV($startDate, $endDate, $userId);
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="attendance_' . $startDate . '_to_' . $endDate . '.csv"');
        header('Content-Length: ' . strlen($csv));
        echo $csv;
        exit();
        break;
        
    case 'get_user_summary':
        $userId = intval($input['user_id'] ?? 0);
        $month = intval($input['month'] ?? date('m'));
        $year = intval($input['year'] ?? date('Y'));
        
        if ($userId) {
            $summary = $attendance->getUserAttendanceSummary($userId, $month, $year);
            $response = ['success' => true, 'data' => $summary];
        } else {
            $response = ['success' => false, 'message' => 'User ID required'];
        }
        break;
        
    case 'get_late_arrivals':
        $date = $input['date'] ?? date('Y-m-d');
        $lateArrivals = $attendance->getLateArrivals($date);
        $response = ['success' => true, 'data' => $lateArrivals];
        break;
        
    case 'get_early_departures':
        $date = $input['date'] ?? date('Y-m-d');
        $earlyDepartures = $attendance->getEarlyDepartures($date);
        $response = ['success' => true, 'data' => $earlyDepartures];
        break;
}

echo json_encode($response);
?>