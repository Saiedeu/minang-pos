<?php
/**
 * POS System - Staff Attendance Management
 * Handle staff check-in/out with barcode and manual methods
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';
require_once 'includes/auth-check.php';

$user = User::getCurrentUser();
$db = Database::getInstance();

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle attendance actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'clock') {
        $staffId = intval($_POST['staff_id']);
        $notes = sanitize($_POST['notes'] ?? '');
        $clockType = $_POST['clock_type']; // 'in' or 'out'
        
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        
        // Check if attendance record exists for today
        $attendance = $db->fetchOne(
            "SELECT * FROM attendance WHERE user_id = ? AND attendance_date = ?",
            [$staffId, $today]
        );
        
        if ($clockType === 'in') {
            if ($attendance) {
                if ($attendance['sign_in_time']) {
                    $error = 'Staff member has already signed in today';
                } else {
                    // Update sign-in time
                    $db->update('attendance', 
                        ['sign_in_time' => $now, 'notes' => $notes], 
                        'id = ?', 
                        [$attendance['id']]
                    );
                    $success = 'Sign-in recorded successfully';
                }
            } else {
                // Create new attendance record
                $db->insert('attendance', [
                    'user_id' => $staffId,
                    'attendance_date' => $today,
                    'sign_in_time' => $now,
                    'notes' => $notes
                ]);
                $success = 'Sign-in recorded successfully';
            }
        } elseif ($clockType === 'out') {
            if ($attendance && $attendance['sign_in_time']) {
                if ($attendance['sign_out_time']) {
                    $error = 'Staff member has already signed out today';
                } else {
                    // Calculate total hours
                    $signInTime = new DateTime($attendance['sign_in_time']);
                    $signOutTime = new DateTime($now);
                    $totalHours = $signInTime->diff($signOutTime)->h + ($signInTime->diff($signOutTime)->i / 60);
                    
                    $db->update('attendance', 
                        [
                            'sign_out_time' => $now, 
                            'total_hours' => round($totalHours, 2),
                            'notes' => $attendance['notes'] . ($notes ? ' | ' . $notes : '')
                        ], 
                        'id = ?', 
                        [$attendance['id']]
                    );
                    $success = 'Sign-out recorded successfully';
                }
            } else {
                $error = 'Staff member has not signed in today';
            }
        }
    }
    
    // Handle barcode scan
    if ($action === 'barcode') {
        $qidNumber = sanitize($_POST['qid_number']);
        
        // Find staff by QID
        $staff = $db->fetchOne("SELECT * FROM users WHERE qid_number = ? AND is_active = 1", [$qidNumber]);
        
        if (!$staff) {
            $error = 'QID number not found in system';
        } else {
            $today = date('Y-m-d');
            $now = date('Y-m-d H:i:s');
            
            // Check current attendance status
            $attendance = $db->fetchOne(
                "SELECT * FROM attendance WHERE user_id = ? AND attendance_date = ?",
                [$staff['id'], $today]
            );
            
            if (!$attendance || !$attendance['sign_in_time']) {
                // Sign in
                if ($attendance) {
                    $db->update('attendance', ['sign_in_time' => $now], 'id = ?', [$attendance['id']]);
                } else {
                    $db->insert('attendance', [
                        'user_id' => $staff['id'],
                        'attendance_date' => $today,
                        'sign_in_time' => $now
                    ]);
                }
                $success = $staff['name'] . ' signed in successfully';
            } elseif (!$attendance['sign_out_time']) {
                // Sign out
                $signInTime = new DateTime($attendance['sign_in_time']);
                $signOutTime = new DateTime($now);
                $totalHours = $signInTime->diff($signOutTime)->h + ($signInTime->diff($signOutTime)->i / 60);
                
                $db->update('attendance', 
                    [
                        'sign_out_time' => $now,
                        'total_hours' => round($totalHours, 2)
                    ], 
                    'id = ?', 
                    [$attendance['id']]
                );
                $success = $staff['name'] . ' signed out successfully';
            } else {
                $error = $staff['name'] . ' has already completed attendance for today';
            }
        }
    }
}

// Get today's attendance
$todayAttendance = $db->fetchAll("
    SELECT a.*, u.name, u.qid_number, u.role
    FROM attendance a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.attendance_date = ?
    ORDER BY a.sign_in_time DESC
", [date('Y-m-d')]);

// Get all active staff
$activeStaff = $db->fetchAll("
    SELECT id, name, qid_number, role, phone
    FROM users 
    WHERE is_active = 1 
    ORDER BY name
");

// Get recent attendance (last 7 days)
$recentAttendance = $db->fetchAll("
    SELECT a.*, u.name, u.role
    FROM attendance a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY a.attendance_date DESC, a.sign_in_time DESC
    LIMIT 50
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Attendance - <?php echo BUSINESS_NAME; ?></title>
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
    <!-- Header -->
    <header class="bg-gradient-to-r from-primary to-blue-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-white hover:text-blue-200">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <div>
                        <h1 class="text-xl font-semibold text-white">Staff Attendance</h1>
                        <p class="text-blue-100 text-sm">Manage staff check-in and check-out</p>
                    </div>
                </div>

                <div class="flex items-center space-x-4">
                    <div class="text-white text-center">
                        <div id="current-time" class="text-lg font-semibold"></div>
                        <div id="current-date" class="text-xs text-blue-100"></div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Alerts -->
        <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
            <div class="flex">
                <i class="fas fa-exclamation-triangle text-red-500 mr-2 mt-0.5"></i>
                <p class="text-red-700"><?php echo $error; ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
            <div class="flex">
                <i class="fas fa-check-circle text-green-500 mr-2 mt-0.5"></i>
                <p class="text-green-700"><?php echo $success; ?></p>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Attendance Methods -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Barcode Scan Method -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-qrcode text-primary text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-lg font-semibold text-gray-800">QID Barcode Scan</h2>
                            <p class="text-sm text-gray-600">Scan QID for instant check-in/out</p>
                        </div>
                    </div>
                    
                    <form method="POST" action="?action=barcode">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">QID Number</label>
                                <input type="text" name="qid_number" id="qid-scanner" 
                                       class="w-full p-3 text-lg font-mono text-center border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                       placeholder="Scan or enter QID number" required autofocus>
                                <p class="text-xs text-gray-500 mt-1">Focus here and scan QID barcode</p>
                            </div>
                            <button type="submit" 
                                    class="w-full bg-primary hover:bg-blue-600 text-white font-semibold py-3 rounded-lg transition-colors">
                                <i class="fas fa-clock mr-2"></i>Clock In/Out
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Manual Search Method -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-search text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-lg font-semibold text-gray-800">Manual Search</h2>
                            <p class="text-sm text-gray-600">Search staff manually</p>
                        </div>
                    </div>
                    
                    <form method="POST" action="?action=clock">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Select Staff Member</label>
                                <select name="staff_id" required 
                                        class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                    <option value="">Choose staff member</option>
                                    <?php foreach ($activeStaff as $staff): ?>
                                    <option value="<?php echo $staff['id']; ?>">
                                        <?php echo $staff['name']; ?> - <?php echo User::getRoleName($staff['role']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <button type="submit" name="clock_type" value="in"
                                        class="bg-green-500 hover:bg-green-600 text-white font-semibold py-3 rounded-lg transition-colors">
                                    <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                                </button>
                                <button type="submit" name="clock_type" value="out"
                                        class="bg-red-500 hover:bg-red-600 text-white font-semibold py-3 rounded-lg transition-colors">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Sign Out
                                </button>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Notes (Optional)</label>
                                <textarea name="notes" rows="2" 
                                          class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary resize-none"
                                          placeholder="Late arrival reason, early leave, etc."></textarea>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Today's Attendance & Recent Records -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Today's Attendance -->
                <div class="bg-white rounded-xl shadow-lg">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-semibold text-gray-800">Today's Attendance</h2>
                            <span class="text-sm text-gray-500"><?php echo date('l, F j, Y'); ?></span>
                        </div>
                    </div>

                    <div class="p-6">
                        <?php if (empty($todayAttendance)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-user-clock text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No attendance records for today</p>
                        </div>
                        <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <th class="pb-3">Staff Member</th>
                                        <th class="pb-3">Role</th>
                                        <th class="pb-3">Sign In</th>
                                        <th class="pb-3">Sign Out</th>
                                        <th class="pb-3">Total Hours</th>
                                        <th class="pb-3">Status</th>
                                        <th class="pb-3">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm">
                                    <?php foreach ($todayAttendance as $attendance): ?>
                                    <tr class="border-t border-gray-200">
                                        <td class="py-3">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center mr-3">
                                                    <i class="fas fa-user text-white text-xs"></i>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-gray-900"><?php echo $attendance['name']; ?></div>
                                                    <div class="text-xs text-gray-500">QID: <?php echo $attendance['qid_number']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 text-gray-600">
                                            <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">
                                                <?php echo User::getRoleName($attendance['role']); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 text-gray-600">
                                            <?php echo $attendance['sign_in_time'] ? date('H:i', strtotime($attendance['sign_in_time'])) : '-'; ?>
                                        </td>
                                        <td class="py-3 text-gray-600">
                                            <?php echo $attendance['sign_out_time'] ? date('H:i', strtotime($attendance['sign_out_time'])) : '-'; ?>
                                        </td>
                                        <td class="py-3 text-gray-600">
                                            <?php echo $attendance['total_hours'] ? $attendance['total_hours'] . 'h' : '-'; ?>
                                        </td>
                                        <td class="py-3">
                                            <?php if ($attendance['sign_in_time'] && $attendance['sign_out_time']): ?>
                                                <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Complete</span>
                                            <?php elseif ($attendance['sign_in_time']): ?>
                                                <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">Working</span>
                                            <?php else: ?>
                                                <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">Absent</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 text-gray-600">
                                            <?php echo $attendance['notes'] ? substr($attendance['notes'], 0, 20) . '...' : '-'; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Attendance Records -->
                <div class="bg-white rounded-xl shadow-lg">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-semibold text-gray-800">Recent Attendance (Last 7 Days)</h2>
                            <a href="../erp/hr/attendance.php" class="text-primary hover:text-blue-700 text-sm font-medium">
                                View Full Reports
                            </a>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <th class="pb-3">Date</th>
                                        <th class="pb-3">Staff</th>
                                        <th class="pb-3">Role</th>
                                        <th class="pb-3">Sign In</th>
                                        <th class="pb-3">Sign Out</th>
                                        <th class="pb-3">Hours</th>
                                        <th class="pb-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm">
                                    <?php foreach ($recentAttendance as $record): ?>
                                    <tr class="border-t border-gray-200">
                                        <td class="py-3 text-gray-600"><?php echo date('M j', strtotime($record['attendance_date'])); ?></td>
                                        <td class="py-3 font-medium text-gray-900"><?php echo $record['name']; ?></td>
                                        <td class="py-3 text-gray-600"><?php echo User::getRoleName($record['role']); ?></td>
                                        <td class="py-3 text-gray-600">
                                            <?php echo $record['sign_in_time'] ? date('H:i', strtotime($record['sign_in_time'])) : '-'; ?>
                                        </td>
                                        <td class="py-3 text-gray-600">
                                            <?php echo $record['sign_out_time'] ? date('H:i', strtotime($record['sign_out_time'])) : '-'; ?>
                                        </td>
                                        <td class="py-3 text-gray-600">
                                            <?php echo $record['total_hours'] ? $record['total_hours'] . 'h' : '-'; ?>
                                        </td>
                                        <td class="py-3">
                                            <?php if ($record['sign_in_time'] && $record['sign_out_time']): ?>
                                                <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">✓</span>
                                            <?php elseif ($record['sign_in_time']): ?>
                                                <span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full">Working</span>
                                            <?php else: ?>
                                                <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full">Absent</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Update clock
        function updateClock() {
            const now = new Date();
            document.getElementById('current-time').textContent = now.toLocaleTimeString('en-US', {
                hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
            document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', {
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
            });
        }

        // Initialize
        updateClock();
        setInterval(updateClock, 1000);

        // Auto-submit on QID scan (assuming barcode scanner adds Enter)
        document.getElementById('qid-scanner').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.closest('form').submit();
            }
        });

        // Clear QID input after submission
        document.addEventListener('DOMContentLoaded', function() {
            const qidInput = document.getElementById('qid-scanner');
            if (qidInput) {
                qidInput.focus();
                qidInput.select();
            }
        });
    </script>
</body>
</html>