<?php
/**
 * ERP System - Working Schedule Management
 * Manage staff working schedules and shift assignments
 */

define('MINANG_SYSTEM', true);
require_once '../../config/config.php';
require_once '../includes/auth-check.php';

$db = Database::getInstance();
$user = new User();
$action = $_GET['action'] ?? 'list';
$scheduleId = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $scheduleData = [
            'user_id' => intval($_POST['user_id'] ?? 0),
            'schedule_date' => $_POST['schedule_date'] ?? date('Y-m-d'),
            'shift_start' => $_POST['shift_start'] ?? '08:00',
            'shift_end' => $_POST['shift_end'] ?? '16:00',
            'break_start' => $_POST['break_start'] ?? '12:00',
            'break_end' => $_POST['break_end'] ?? '13:00',
            'schedule_type' => sanitize($_POST['schedule_type'] ?? 'regular'),
            'notes' => sanitize($_POST['notes'] ?? ''),
            'created_by' => $_SESSION['user_id'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Calculate working hours
        $startTime = new DateTime($scheduleData['shift_start']);
        $endTime = new DateTime($scheduleData['shift_end']);
        $breakStartTime = new DateTime($scheduleData['break_start']);
        $breakEndTime = new DateTime($scheduleData['break_end']);
        
        $workingHours = $endTime->diff($startTime)->h + ($endTime->diff($startTime)->i / 60);
        $breakHours = $breakEndTime->diff($breakStartTime)->h + ($breakEndTime->diff($breakStartTime)->i / 60);
        $scheduleData['working_hours'] = $workingHours - $breakHours;
        
        if ($scheduleData['user_id'] == 0) {
            $error = 'Please select a staff member';
        } else {
            // Check for conflicting schedules
            $conflict = $db->fetchOne("
                SELECT id FROM working_schedules 
                WHERE user_id = ? AND schedule_date = ? AND id != ?
            ", [$scheduleData['user_id'], $scheduleData['schedule_date'], $scheduleId]);
            
            if ($conflict) {
                $error = 'Staff member already has a schedule for this date';
            } else {
                $inserted = $db->insert('working_schedules', $scheduleData);
                if ($inserted) {
                    $success = 'Working schedule created successfully';
                    header('Location: shifts.php?success=created');
                    exit();
                } else {
                    $error = 'Failed to create working schedule';
                }
            }
        }
    }
    
    if ($action === 'edit' && $scheduleId) {
        $scheduleData = [
            'shift_start' => $_POST['shift_start'] ?? '08:00',
            'shift_end' => $_POST['shift_end'] ?? '16:00',
            'break_start' => $_POST['break_start'] ?? '12:00',
            'break_end' => $_POST['break_end'] ?? '13:00',
            'schedule_type' => sanitize($_POST['schedule_type'] ?? 'regular'),
            'notes' => sanitize($_POST['notes'] ?? '')
        ];
        
        // Calculate working hours
        $startTime = new DateTime($scheduleData['shift_start']);
        $endTime = new DateTime($scheduleData['shift_end']);
        $breakStartTime = new DateTime($scheduleData['break_start']);
        $breakEndTime = new DateTime($scheduleData['break_end']);
        
        $workingHours = $endTime->diff($startTime)->h + ($endTime->diff($startTime)->i / 60);
        $breakHours = $breakEndTime->diff($breakStartTime)->h + ($breakEndTime->diff($breakStartTime)->i / 60);
        $scheduleData['working_hours'] = $workingHours - $breakHours;
        
        $updated = $db->update('working_schedules', $scheduleData, 'id = ?', [$scheduleId]);
        if ($updated) {
            $success = 'Working schedule updated successfully';
        } else {
            $error = 'Failed to update working schedule';
        }
    }
    
    if ($action === 'bulk_schedule') {
        $startDate = $_POST['bulk_start_date'] ?? date('Y-m-d');
        $endDate = $_POST['bulk_end_date'] ?? date('Y-m-d', strtotime('+6 days'));
        $selectedUsers = $_POST['selected_users'] ?? [];
        $shiftTemplate = [
            'shift_start' => $_POST['template_start'] ?? '08:00',
            'shift_end' => $_POST['template_end'] ?? '16:00',
            'break_start' => $_POST['template_break_start'] ?? '12:00',
            'break_end' => $_POST['template_break_end'] ?? '13:00',
            'schedule_type' => 'regular'
        ];
        
        $createdCount = 0;
        $current = new DateTime($startDate);
        $end = new DateTime($endDate);
        
        while ($current <= $end) {
            foreach ($selectedUsers as $userId) {
                // Check if schedule already exists
                $existing = $db->fetchOne("
                    SELECT id FROM working_schedules 
                    WHERE user_id = ? AND schedule_date = ?
                ", [$userId, $current->format('Y-m-d')]);
                
                if (!$existing) {
                    $scheduleData = array_merge($shiftTemplate, [
                        'user_id' => intval($userId),
                        'schedule_date' => $current->format('Y-m-d'),
                        'working_hours' => 7.0, // Will be calculated properly
                        'created_by' => $_SESSION['user_id'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    if ($db->insert('working_schedules', $scheduleData)) {
                        $createdCount++;
                    }
                }
            }
            $current->modify('+1 day');
        }
        
        if ($createdCount > 0) {
            $success = "Created {$createdCount} schedule entries successfully";
        } else {
            $error = 'No schedules were created (may already exist)';
        }
    }
}

// Get data for display
$currentWeekStart = $_GET['week'] ?? date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime($currentWeekStart . ' +6 days'));

$allStaff = $user->getAllUsers();
$schedules = $db->fetchAll("
    SELECT ws.*, u.name as staff_name, u.role
    FROM working_schedules ws
    LEFT JOIN users u ON ws.user_id = u.id
    WHERE ws.schedule_date BETWEEN ? AND ?
    ORDER BY ws.schedule_date, ws.shift_start
", [$currentWeekStart, $weekEnd]);

$currentSchedule = null;
if ($action === 'edit' && $scheduleId) {
    $currentSchedule = $db->fetchOne("
        SELECT ws.*, u.name as staff_name
        FROM working_schedules ws
        LEFT JOIN users u ON ws.user_id = u.id
        WHERE ws.id = ?
    ", [$scheduleId]);
}

// Handle AJAX delete
if (isset($_GET['ajax']) && $_GET['ajax'] === 'delete' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $deleted = $db->delete('working_schedules', 'id = ?', [$_GET['id']]);
    echo json_encode(['success' => $deleted]);
    exit();
}

// Group schedules by date for calendar view
$scheduleByDate = [];
foreach ($schedules as $schedule) {
    $scheduleByDate[$schedule['schedule_date']][] = $schedule;
}

$pageTitle = 'Working Schedules';
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
            <?php if ($action === 'list'): ?>
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Working Schedules</h1>
                    <p class="text-gray-600">Manage staff working schedules and shifts</p>
                </div>
                <div class="flex space-x-3">
                    <button onclick="showBulkScheduleModal()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-calendar-plus mr-2"></i>Bulk Schedule
                    </button>
                    <a href="?action=add" class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-plus mr-2"></i>Add Schedule
                    </a>
                </div>
            </div>

            <!-- Week Navigation -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex items-center justify-between">
                    <a href="?week=<?php echo date('Y-m-d', strtotime($currentWeekStart . ' -7 days')); ?>" 
                       class="flex items-center text-primary hover:text-blue-600">
                        <i class="fas fa-chevron-left mr-2"></i>Previous Week
                    </a>
                    
                    <div class="text-center">
                        <h2 class="text-xl font-semibold text-gray-800">
                            Week of <?php echo formatDate($currentWeekStart); ?>
                        </h2>
                        <p class="text-gray-600"><?php echo formatDate($currentWeekStart); ?> - <?php echo formatDate($weekEnd); ?></p>
                    </div>
                    
                    <a href="?week=<?php echo date('Y-m-d', strtotime($currentWeekStart . ' +7 days')); ?>" 
                       class="flex items-center text-primary hover:text-blue-600">
                        Next Week<i class="fas fa-chevron-right ml-2"></i>
                    </a>
                </div>
            </div>

            <!-- Weekly Schedule Calendar -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-800">Staff</th>
                                <?php
                                for ($i = 0; $i < 7; $i++) {
                                    $date = date('Y-m-d', strtotime($currentWeekStart . " +{$i} days"));
                                    $dayName = date('D', strtotime($date));
                                    $dayNumber = date('j', strtotime($date));
                                    echo "<th class='px-3 py-4 text-center text-sm font-semibold text-gray-800'>";
                                    echo "<div>{$dayName}</div>";
                                    echo "<div class='text-xs text-gray-500'>{$dayNumber}</div>";
                                    echo "</th>";
                                }
                                ?>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($allStaff as $staff): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center">
                                            <i class="fas fa-user text-white text-sm"></i>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-900"><?php echo $staff['name']; ?></div>
                                            <div class="text-xs text-gray-500"><?php echo User::getRoleName($staff['role']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <?php
                                for ($i = 0; $i < 7; $i++) {
                                    $date = date('Y-m-d', strtotime($currentWeekStart . " +{$i} days"));
                                    $daySchedules = array_filter($schedules, fn($s) => $s['schedule_date'] === $date && $s['user_id'] == $staff['id']);
                                    
                                    echo "<td class='px-3 py-4 text-center'>";
                                    if (!empty($daySchedules)) {
                                        foreach ($daySchedules as $schedule) {
                                            $scheduleClass = $schedule['schedule_type'] === 'overtime' ? 'bg-orange-100 text-orange-800 border-orange-300' : 'bg-blue-100 text-blue-800 border-blue-300';
                                            echo "<div class='text-xs p-2 rounded border {$scheduleClass} mb-1'>";
                                            echo "<div class='font-semibold'>" . date('H:i', strtotime($schedule['shift_start'])) . "-" . date('H:i', strtotime($schedule['shift_end'])) . "</div>";
                                            echo "<div class='opacity-75'>" . number_format($schedule['working_hours'], 1) . "h</div>";
                                            echo "<div class='flex justify-center space-x-1 mt-1'>";
                                            echo "<button onclick='editSchedule({$schedule['id']})' class='text-blue-600 hover:text-blue-800'><i class='fas fa-edit text-xs'></i></button>";
                                            echo "<button onclick='deleteSchedule({$schedule['id']})' class='text-red-600 hover:text-red-800'><i class='fas fa-trash text-xs'></i></button>";
                                            echo "</div>";
                                            echo "</div>";
                                        }
                                    } else {
                                        echo "<button onclick='addQuickSchedule({$staff['id']}, \"{$date}\")' class='w-full h-12 bg-gray-100 hover:bg-gray-200 border-2 border-dashed border-gray-300 rounded-lg text-gray-500 hover:text-gray-700 transition-colors'>";
                                        echo "<i class='fas fa-plus text-xs'></i>";
                                        echo "</button>";
                                    }
                                    echo "</td>";
                                }
                                ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- Add/Edit Schedule Form -->
            <div class="max-w-4xl">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">
                            <?php echo $action === 'add' ? 'Add Working Schedule' : 'Edit Working Schedule'; ?>
                        </h1>
                        <p class="text-gray-600">
                            <?php echo $action === 'add' ? 'Create a new staff working schedule' : 'Update schedule information'; ?>
                        </p>
                    </div>
                    <a href="shifts.php" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-2xl"></i>
                    </a>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-8">
                    <form method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php if ($action === 'add'): ?>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Staff Member *</label>
                                <select name="user_id" required
                                        class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                                    <option value="">Select Staff Member</option>
                                    <?php foreach ($allStaff as $staff): ?>
                                    <option value="<?php echo $staff['id']; ?>">
                                        <?php echo $staff['name']; ?> - <?php echo User::getRoleName($staff['role']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Schedule Date *</label>
                                <input type="date" name="schedule_date" required
                                       value="<?php echo date('Y-m-d'); ?>"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                            </div>
                            <?php else: ?>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Staff Member</label>
                                <input type="text" value="<?php echo $currentSchedule['staff_name'] ?? ''; ?>" readonly
                                       class="w-full p-3 border border-gray-300 bg-gray-100 rounded-lg">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Schedule Date</label>
                                <input type="text" value="<?php echo formatDate($currentSchedule['schedule_date'] ?? ''); ?>" readonly
                                       class="w-full p-3 border border-gray-300 bg-gray-100 rounded-lg">
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Shift Start Time *</label>
                                <input type="time" name="shift_start" required
                                       value="<?php echo $currentSchedule['shift_start'] ?? '08:00'; ?>"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Shift End Time *</label>
                                <input type="time" name="shift_end" required
                                       value="<?php echo $currentSchedule['shift_end'] ?? '16:00'; ?>"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Break Start</label>
                                <input type="time" name="break_start"
                                       value="<?php echo $currentSchedule['break_start'] ?? '12:00'; ?>"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Break End</label>
                                <input type="time" name="break_end"
                                       value="<?php echo $currentSchedule['break_end'] ?? '13:00'; ?>"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Schedule Type</label>
                            <select name="schedule_type" 
                                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                                <option value="regular" <?php echo ($currentSchedule['schedule_type'] ?? 'regular') === 'regular' ? 'selected' : ''; ?>>Regular Shift</option>
                                <option value="overtime" <?php echo ($currentSchedule['schedule_type'] ?? '') === 'overtime' ? 'selected' : ''; ?>>Overtime</option>
                                <option value="training" <?php echo ($currentSchedule['schedule_type'] ?? '') === 'training' ? 'selected' : ''; ?>>Training</option>
                                <option value="meeting" <?php echo ($currentSchedule['schedule_type'] ?? '') === 'meeting' ? 'selected' : ''; ?>>Meeting</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="3"
                                      class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                      placeholder="Optional notes about this schedule"><?php echo $currentSchedule['notes'] ?? ''; ?></textarea>
                        </div>

                        <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                            <a href="shifts.php" 
                               class="px-6 py-3 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold rounded-lg transition-colors">
                                Cancel
                            </a>
                            <button type="submit" 
                                    class="px-6 py-3 bg-primary hover:bg-blue-600 text-white font-semibold rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>
                                <?php echo $action === 'add' ? 'Create Schedule' : 'Update Schedule'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Bulk Schedule Modal -->
    <div id="bulk-schedule-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Bulk Schedule Creation</h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="bulk_schedule">
                    
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
                                <input type="date" name="bulk_start_date" required
                                       value="<?php echo date('Y-m-d'); ?>"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
                                <input type="date" name="bulk_end_date" required
                                       value="<?php echo date('Y-m-d', strtotime('+6 days')); ?>"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Select Staff Members</label>
                            <div class="max-h-48 overflow-y-auto border border-gray-300 rounded-lg p-3 space-y-2">
                                <?php foreach ($allStaff as $staff): ?>
                                <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded">
                                    <input type="checkbox" name="selected_users[]" value="<?php echo $staff['id']; ?>"
                                           class="h-4 w-4 text-primary border-gray-300 rounded focus:ring-primary">
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-900"><?php echo $staff['name']; ?></div>
                                        <div class="text-sm text-gray-500"><?php echo User::getRoleName($staff['role']); ?></div>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-gray-800 mb-3">Shift Template</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Shift Start</label>
                                    <input type="time" name="template_start" value="08:00"
                                           class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-primary">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Shift End</label>
                                    <input type="time" name="template_end" value="16:00"
                                           class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-primary">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Break Start</label>
                                    <input type="time" name="template_break_start" value="12:00"
                                           class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-primary">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Break End</label>
                                    <input type="time" name="template_break_end" value="13:00"
                                           class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-primary">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex space-x-3 mt-6">
                        <button type="button" onclick="hideBulkScheduleModal()" 
                                class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold py-3 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-green-500 hover:bg-green-600 text-white font-semibold py-3 rounded-lg transition-colors">
                            <i class="fas fa-calendar-plus mr-2"></i>Create Schedules
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showBulkScheduleModal() {
            document.getElementById('bulk-schedule-modal').classList.remove('hidden');
        }

        function hideBulkScheduleModal() {
            document.getElementById('bulk-schedule-modal').classList.add('hidden');
        }

        function addQuickSchedule(userId, date) {
            // Quick schedule creation with default times
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const fields = {
                'action': 'add',
                'user_id': userId,
                'schedule_date': date,
                'shift_start': '08:00',
                'shift_end': '16:00',
                'break_start': '12:00',
                'break_end': '13:00',
                'schedule_type': 'regular'
            };
            
            for (const [name, value] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.name = name;
                input.value = value;
                form.appendChild(input);
            }
            
            document.body.appendChild(form);
            form.submit();
        }

        function editSchedule(scheduleId) {
            window.location.href = `?action=edit&id=${scheduleId}`;
        }

        async function deleteSchedule(scheduleId) {
            if (confirm('Are you sure you want to delete this schedule?')) {
                try {
                    const response = await fetch(`?ajax=delete&id=${scheduleId}`);
                    const result = await response.json();
                    
                    if (result.success) {
                        location.reload();
                    } else {
                        alert('Failed to delete schedule');
                    }
                } catch (error) {
                    alert('An error occurred');
                }
            }
        }

        // Auto-calculate working hours
        function calculateHours() {
            const start = document.querySelector('input[name="shift_start"]').value;
            const end = document.querySelector('input[name="shift_end"]').value;
            const breakStart = document.querySelector('input[name="break_start"]').value;
            const breakEnd = document.querySelector('input[name="break_end"]').value;
            
            if (start && end) {
                const startTime = new Date('2000-01-01 ' + start);
                const endTime = new Date('2000-01-01 ' + end);
                const breakStartTime = new Date('2000-01-01 ' + breakStart);
                const breakEndTime = new Date('2000-01-01 ' + breakEnd);
                
                let workingHours = (endTime - startTime) / (1000 * 60 * 60);
                
                if (breakStart && breakEnd) {
                    const breakHours = (breakEndTime - breakStartTime) / (1000 * 60 * 60);
                    workingHours -= breakHours;
                }
                
                // Display calculated hours somewhere
                console.log('Working hours:', workingHours.toFixed(1));
            }
        }

        // Add event listeners for time inputs
        document.addEventListener('DOMContentLoaded', function() {
            const timeInputs = document.querySelectorAll('input[type="time"]');
            timeInputs.forEach(input => {
                input.addEventListener('change', calculateHours);
            });
        });
    </script>
</body>
</html>