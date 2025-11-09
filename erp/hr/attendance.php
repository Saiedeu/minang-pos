<?php
/**
 * ERP System - Attendance Reports
 * View comprehensive attendance analytics and reports
 */

define('MINANG_SYSTEM', true);
require_once '../../config/config.php';
require_once '../includes/auth-check.php';

$attendance = new Attendance();
$user = new User();
$db = Database::getInstance();

// Get filter parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$userId = $_GET['user_id'] ?? '';
$reportType = $_GET['report_type'] ?? 'summary';

// Get attendance data
$attendanceRecords = $attendance->getAttendanceByDateRange($startDate, $endDate, $userId ?: null);

// Get all staff for filter
$allStaff = $user->getAllUsers();

// Get attendance summary statistics
$attendanceStats = $db->fetchOne("
    SELECT 
        COUNT(DISTINCT user_id) as active_staff,
        COUNT(*) as total_records,
        COUNT(CASE WHEN sign_in_time IS NOT NULL THEN 1 END) as present_count,
        COUNT(CASE WHEN sign_in_time IS NULL THEN 1 END) as absent_count,
        AVG(total_hours) as avg_hours_per_day,
        COUNT(CASE WHEN TIME(sign_in_time) > '09:00:00' THEN 1 END) as late_arrivals
    FROM attendance a
    WHERE a.attendance_date BETWEEN ? AND ?
    " . ($userId ? "AND a.user_id = ?" : ""), 
    array_filter([$startDate, $endDate, $userId ?: null])
);

// Get monthly summary by user
$monthlyByUser = $db->fetchAll("
    SELECT 
        u.name,
        u.role,
        COUNT(*) as days_recorded,
        COUNT(CASE WHEN a.sign_in_time IS NOT NULL THEN 1 END) as days_present,
        COUNT(CASE WHEN a.sign_in_time IS NULL THEN 1 END) as days_absent,
        AVG(a.total_hours) as avg_daily_hours,
        SUM(a.total_hours) as total_hours,
        COUNT(CASE WHEN TIME(a.sign_in_time) > '09:00:00' THEN 1 END) as late_count
    FROM users u
    LEFT JOIN attendance a ON u.id = a.user_id AND a.attendance_date BETWEEN ? AND ?
    WHERE u.is_active = 1 AND u.role IN (4,5,6,7)
    " . ($userId ? "AND u.id = ?" : "") . "
    GROUP BY u.id, u.name, u.role
    ORDER BY u.name
", array_filter([$startDate, $endDate, $userId ?: null]));

$pageTitle = 'Attendance Reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo BUSINESS_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '<?php echo PRIMARY_COLOR; ?>',
                        success: '<?php echo SUCCESS_COLOR; ?>',
                        warning: '<?php echo WARNING_COLOR; ?>',
                        danger: '<?php echo DANGER_COLOR; ?>'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="ml-64 min-h-screen">
        <?php include '../includes/header.php'; ?>
        
        <main class="p-8">
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Attendance Reports</h1>
                    <p class="text-gray-600">Monitor staff attendance and working hours</p>
                </div>
                <div class="flex space-x-3">
                    <button onclick="exportAttendance()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-download mr-2"></i>Export CSV
                    </button>
                    <a href="../../pos/attendance.php" class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-clock mr-2"></i>POS Attendance
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
                        <input type="date" name="start_date" value="<?php echo $startDate; ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
                        <input type="date" name="end_date" value="<?php echo $endDate; ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Staff Member</label>
                        <select name="user_id" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">All Staff</option>
                            <?php foreach ($allStaff as $staff): ?>
                            <option value="<?php echo $staff['id']; ?>" <?php echo $userId == $staff['id'] ? 'selected' : ''; ?>>
                                <?php echo $staff['name']; ?> - <?php echo User::getRoleName($staff['role']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-primary hover:bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                            <i class="fas fa-search mr-2"></i>Generate Report
                        </button>
                    </div>
                </form>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm">Active Staff</p>
                            <p class="text-3xl font-bold"><?php echo $attendanceStats['active_staff'] ?? 0; ?></p>
                        </div>
                        <i class="fas fa-users text-4xl text-blue-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm">Total Present</p>
                            <p class="text-3xl font-bold"><?php echo $attendanceStats['present_count'] ?? 0; ?></p>
                        </div>
                        <i class="fas fa-user-check text-4xl text-green-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-red-100 text-sm">Late Arrivals</p>
                            <p class="text-3xl font-bold"><?php echo $attendanceStats['late_arrivals'] ?? 0; ?></p>
                        </div>
                        <i class="fas fa-clock text-4xl text-red-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm">Avg Hours/Day</p>
                            <p class="text-3xl font-bold"><?php echo number_format($attendanceStats['avg_hours_per_day'] ?? 0, 1); ?></p>
                        </div>
                        <i class="fas fa-business-time text-4xl text-purple-300"></i>
                    </div>
                </div>
            </div>

            <!-- Monthly Summary by User -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-8">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">Monthly Summary by Staff</h2>
                    <p class="text-gray-600">Period: <?php echo formatDate($startDate); ?> - <?php echo formatDate($endDate); ?></p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Staff Name</th>
                                <th class="px-6 py-4">Role</th>
                                <th class="px-6 py-4">Present Days</th>
                                <th class="px-6 py-4">Absent Days</th>
                                <th class="px-6 py-4">Late Count</th>
                                <th class="px-6 py-4">Total Hours</th>
                                <th class="px-6 py-4">Avg Hours/Day</th>
                                <th class="px-6 py-4">Attendance %</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($monthlyByUser as $record): ?>
                            <?php
                                $totalDays = $record['days_recorded'] ?: 1;
                                $attendancePercent = ($record['days_present'] / $totalDays) * 100;
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900"><?php echo $record['name']; ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                        <?php echo User::getRoleName($record['role']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-green-600 font-semibold"><?php echo $record['days_present']; ?></td>
                                <td class="px-6 py-4 text-red-600 font-semibold"><?php echo $record['days_absent']; ?></td>
                                <td class="px-6 py-4 <?php echo $record['late_count'] > 0 ? 'text-orange-600' : 'text-gray-600'; ?>">
                                    <?php echo $record['late_count']; ?>
                                </td>
                                <td class="px-6 py-4 font-semibold text-gray-900">
                                    <?php echo number_format($record['total_hours'] ?? 0, 1); ?>h
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <?php echo number_format($record['avg_daily_hours'] ?? 0, 1); ?>h
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-12 h-2 bg-gray-200 rounded-full mr-2">
                                            <div class="h-2 rounded-full <?php echo $attendancePercent >= 90 ? 'bg-green-500' : ($attendancePercent >= 75 ? 'bg-yellow-500' : 'bg-red-500'); ?>" 
                                                 style="width: <?php echo min(100, $attendancePercent); ?>%"></div>
                                        </div>
                                        <span class="text-sm font-medium text-gray-900"><?php echo number_format($attendancePercent, 1); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Daily Attendance Records -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">Daily Attendance Records</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Date</th>
                                <th class="px-6 py-4">Staff Name</th>
                                <th class="px-6 py-4">Role</th>
                                <th class="px-6 py-4">Sign In</th>
                                <th class="px-6 py-4">Sign Out</th>
                                <th class="px-6 py-4">Total Hours</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($attendanceRecords as $record): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium text-gray-900"><?php echo formatDate($record['attendance_date']); ?></td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900"><?php echo $record['name']; ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                        <?php echo User::getRoleName($record['role']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($record['sign_in_time']): ?>
                                        <span class="text-green-600 font-semibold">
                                            <?php echo date('H:i', strtotime($record['sign_in_time'])); ?>
                                        </span>
                                        <?php if (date('H:i', strtotime($record['sign_in_time'])) > '09:00'): ?>
                                            <span class="ml-2 px-1 py-0.5 text-xs bg-orange-100 text-orange-800 rounded">Late</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-red-500">Not signed in</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($record['sign_out_time']): ?>
                                        <span class="text-red-600 font-semibold">
                                            <?php echo date('H:i', strtotime($record['sign_out_time'])); ?>
                                        </span>
                                    <?php elseif ($record['sign_in_time']): ?>
                                        <span class="text-blue-500 animate-pulse">Working...</span>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 font-semibold text-primary">
                                    <?php echo $record['total_hours'] ? number_format($record['total_hours'], 1) . 'h' : '-'; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!$record['sign_in_time']): ?>
                                        <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full">Absent</span>
                                    <?php elseif (!$record['sign_out_time']): ?>
                                        <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full animate-pulse">Present</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">Completed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-gray-600 text-sm">
                                    <?php echo $record['notes'] ?? '-'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($attendanceRecords)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-calendar-times text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">No Records Found</h3>
                    <p class="text-gray-600">No attendance records for the selected period</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function exportAttendance() {
            const startDate = '<?php echo $startDate; ?>';
            const endDate = '<?php echo $endDate; ?>';
            const userId = '<?php echo $userId; ?>';
            
            const params = new URLSearchParams({
                action: 'export_csv',
                start_date: startDate,
                end_date: endDate,
                user_id: userId
            });
            
            window.open(`../api/attendance.php?${params}`, '_blank');
        }

        // Auto-update date range
        document.querySelector('input[name="start_date"]').addEventListener('change', function() {
            const endDateInput = document.querySelector('input[name="end_date"]');
            if (this.value > endDateInput.value) {
                endDateInput.value = this.value;
            }
        });
    </script>
</body>
</html>