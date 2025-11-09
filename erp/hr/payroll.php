<?php
/**
 * ERP System - Payroll Management
 * Calculate and manage staff salaries and wages
 */

define('MINANG_SYSTEM', true);
require_once '../../config/config.php';
require_once '../includes/auth-check.php';

$db = Database::getInstance();
$user = new User();
$attendance = new Attendance();
$action = $_GET['action'] ?? 'list';
$payrollId = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'generate') {
        $month = intval($_POST['payroll_month']);
        $year = intval($_POST['payroll_year']);
        $selectedUsers = $_POST['selected_users'] ?? [];
        
        if (empty($selectedUsers)) {
            $error = 'Please select at least one staff member';
        } else {
            $processedCount = 0;
            $errors = [];
            
            foreach ($selectedUsers as $userId) {
                $result = generatePayrollForUser($userId, $month, $year);
                if ($result['success']) {
                    $processedCount++;
                } else {
                    $errors[] = $result['message'];
                }
            }
            
            if ($processedCount > 0) {
                $success = "Generated payroll for {$processedCount} staff member(s)";
                if (!empty($errors)) {
                    $success .= " with " . count($errors) . " errors";
                }
            } else {
                $error = 'Failed to generate payroll: ' . implode(', ', $errors);
            }
        }
    }
    
    if ($action === 'pay') {
        $payrollId = intval($_POST['payroll_id']);
        $paymentMethod = intval($_POST['payment_method']);
        $notes = sanitize($_POST['notes'] ?? '');
        
        $updated = $db->update('payroll', [
            'payment_status' => 'paid',
            'payment_method' => $paymentMethod,
            'payment_date' => date('Y-m-d'),
            'payment_notes' => $notes,
            'paid_by' => $_SESSION['user_id']
        ], 'id = ?', [$payrollId]);
        
        if ($updated) {
            $success = 'Salary payment recorded successfully';
        } else {
            $error = 'Failed to record payment';
        }
    }
}

// Generate payroll for individual user
function generatePayrollForUser($userId, $month, $year) {
    global $db, $attendance;
    
    // Check if payroll already exists
    $existing = $db->fetchOne("
        SELECT id FROM payroll 
        WHERE user_id = ? AND payroll_month = ? AND payroll_year = ?
    ", [$userId, $month, $year]);
    
    if ($existing) {
        return ['success' => false, 'message' => 'Payroll already exists for this period'];
    }
    
    // Get user data
    $userData = $db->fetchOne("SELECT * FROM users WHERE id = ? AND is_active = 1", [$userId]);
    if (!$userData) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    // Get attendance summary
    $attendanceSummary = $attendance->getUserAttendanceSummary($userId, $month, $year);
    
    // Calculate salary components
    $baseSalary = floatval($userData['salary']) ?: 0;
    $workingDays = intval($attendanceSummary['present_days']) ?: 0;
    $totalHours = floatval($attendanceSummary['total_hours']) ?: 0;
    $overtimeHours = max(0, $totalHours - ($workingDays * 8)); // Overtime if more than 8h/day average
    
    // Calculate allowances and deductions
    $overtimePay = $overtimeHours * ($baseSalary / 160); // Assuming 160 working hours per month
    $attendanceBonus = ($workingDays >= 26) ? 200 : 0; // Bonus for full attendance
    
    // Deductions
    $absenceDeduction = max(0, (30 - $workingDays)) * ($baseSalary / 30); // Deduct for absent days
    
    // Calculate totals
    $grossSalary = $baseSalary + $overtimePay + $attendanceBonus;
    $totalDeductions = $absenceDeduction;
    $netSalary = $grossSalary - $totalDeductions;
    
    $payrollData = [
        'user_id' => $userId,
        'payroll_month' => $month,
        'payroll_year' => $year,
        'base_salary' => $baseSalary,
        'overtime_hours' => $overtimeHours,
        'overtime_pay' => $overtimePay,
        'attendance_bonus' => $attendanceBonus,
        'other_allowances' => 0,
        'absence_deduction' => $absenceDeduction,
        'other_deductions' => 0,
        'gross_salary' => $grossSalary,
        'total_deductions' => $totalDeductions,
        'net_salary' => $netSalary,
        'working_days' => $workingDays,
        'total_hours' => $totalHours,
        'payment_status' => 'pending',
        'generated_by' => $_SESSION['user_id'],
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    $payrollId = $db->insert('payroll', $payrollData);
    
    return $payrollId ? 
        ['success' => true, 'payroll_id' => $payrollId] : 
        ['success' => false, 'message' => 'Failed to generate payroll'];
}

// Get data for display
$currentMonth = intval($_GET['month'] ?? date('n'));
$currentYear = intval($_GET['year'] ?? date('Y'));

$payrollRecords = $db->fetchAll("
    SELECT p.*, u.name as staff_name, u.role
    FROM payroll p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.payroll_month = ? AND p.payroll_year = ?
    ORDER BY u.name
", [$currentMonth, $currentYear]);

$allStaff = $user->getAllUsers();

// Get payroll statistics
$payrollStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_records,
        SUM(net_salary) as total_payroll,
        SUM(CASE WHEN payment_status = 'paid' THEN net_salary ELSE 0 END) as paid_amount,
        COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_count
    FROM payroll
    WHERE payroll_month = ? AND payroll_year = ?
", [$currentMonth, $currentYear]);

$pageTitle = 'Payroll Management';
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
                    <h1 class="text-3xl font-bold text-gray-800">Payroll Management</h1>
                    <p class="text-gray-600">Calculate and manage staff salaries</p>
                </div>
                <div class="flex space-x-3">
                    <button onclick="showGeneratePayrollModal()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-calculator mr-2"></i>Generate Payroll
                    </button>
                    <button onclick="exportPayroll()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-download mr-2"></i>Export
                    </button>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
                <p class="text-red-700"><?php echo $error; ?></p>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
                <p class="text-green-700"><?php echo $success; ?></p>
            </div>
            <?php endif; ?>

            <!-- Period Selection -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <form method="GET" class="flex items-center space-x-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Month</label>
                        <select name="month" class="p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $currentMonth == $m ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Year</label>
                        <select name="year" class="p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                            <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $currentYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="pt-7">
                        <button type="submit" class="bg-primary hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-medium">
                            <i class="fas fa-search mr-2"></i>View Period
                        </button>
                    </div>
                </form>
            </div>

            <!-- Payroll Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm">Total Records</p>
                            <p class="text-3xl font-bold"><?php echo $payrollStats['total_records'] ?? 0; ?></p>
                        </div>
                        <i class="fas fa-file-alt text-4xl text-blue-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm">Total Payroll</p>
                            <p class="text-3xl font-bold"><?php echo formatCurrency($payrollStats['total_payroll'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-money-check-alt text-4xl text-green-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm">Paid Amount</p>
                            <p class="text-3xl font-bold"><?php echo formatCurrency($payrollStats['paid_amount'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-check-circle text-4xl text-purple-300"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm">Pending</p>
                            <p class="text-3xl font-bold"><?php echo $payrollStats['pending_count'] ?? 0; ?></p>
                        </div>
                        <i class="fas fa-hourglass-half text-4xl text-orange-300"></i>
                    </div>
                </div>
            </div>

            <!-- Payroll Records -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-800">
                            Payroll for <?php echo date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)); ?>
                        </h2>
                        <span class="bg-primary text-white px-3 py-1 rounded-full text-sm font-semibold">
                            <?php echo count($payrollRecords); ?> records
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Staff Member</th>
                                <th class="px-6 py-4">Base Salary</th>
                                <th class="px-6 py-4">Working Days</th>
                                <th class="px-6 py-4">Overtime Pay</th>
                                <th class="px-6 py-4">Allowances</th>
                                <th class="px-6 py-4">Deductions</th>
                                <th class="px-6 py-4">Net Salary</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($payrollRecords as $payroll): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center">
                                            <i class="fas fa-user text-white"></i>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-900"><?php echo $payroll['staff_name']; ?></div>
                                            <div class="text-sm text-gray-500"><?php echo User::getRoleName($payroll['role']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 font-medium text-gray-900"><?php echo formatCurrency($payroll['base_salary']); ?></td>
                                <td class="px-6 py-4 text-center">
                                    <div class="text-lg font-bold text-gray-900"><?php echo $payroll['working_days']; ?></div>
                                    <div class="text-xs text-gray-500"><?php echo number_format($payroll['total_hours'], 1); ?>h total</div>
                                </td>
                                <td class="px-6 py-4 text-green-600">
                                    <?php if ($payroll['overtime_hours'] > 0): ?>
                                    <div class="font-semibold"><?php echo formatCurrency($payroll['overtime_pay']); ?></div>
                                    <div class="text-xs"><?php echo number_format($payroll['overtime_hours'], 1); ?>h OT</div>
                                    <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-blue-600">
                                    <?php
                                        $totalAllowances = $payroll['attendance_bonus'] + $payroll['other_allowances'];
                                        echo $totalAllowances > 0 ? formatCurrency($totalAllowances) : '-';
                                    ?>
                                </td>
                                <td class="px-6 py-4 text-red-600">
                                    <?php
                                        $totalDeductions = $payroll['absence_deduction'] + $payroll['other_deductions'];
                                        echo $totalDeductions > 0 ? formatCurrency($totalDeductions) : '-';
                                    ?>
                                </td>
                                <td class="px-6 py-4 font-bold text-primary text-lg"><?php echo formatCurrency($payroll['net_salary']); ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full <?php 
                                        echo $payroll['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; 
                                    ?>">
                                        <?php echo strtoupper($payroll['payment_status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <?php if ($payroll['payment_status'] === 'pending'): ?>
                                        <button onclick="showPaymentModal(<?php echo $payroll['id']; ?>, '<?php echo $payroll['staff_name']; ?>', <?php echo $payroll['net_salary']; ?>)" 
                                                class="text-green-600 hover:text-green-800 text-sm" title="Pay Salary">
                                            <i class="fas fa-money-check-alt"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button onclick="viewPayslip(<?php echo $payroll['id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-800 text-sm" title="View Payslip">
                                            <i class="fas fa-file-alt"></i>
                                        </button>
                                        <button onclick="printPayslip(<?php echo $payroll['id']; ?>)" 
                                                class="text-purple-600 hover:text-purple-800 text-sm" title="Print Payslip">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($payrollRecords)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-money-check-alt text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">No Payroll Records</h3>
                    <p class="text-gray-600">No payroll generated for <?php echo date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)); ?></p>
                    <button onclick="showGeneratePayrollModal()" class="mt-4 bg-primary hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-medium">
                        <i class="fas fa-calculator mr-2"></i>Generate Payroll Now
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Generate Payroll Modal -->
    <div id="generate-payroll-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Generate Payroll</h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="generate">
                    
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Month</label>
                                <select name="payroll_month" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $currentMonth == $m ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Year</label>
                                <select name="payroll_year" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                    <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $currentYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Select Staff Members</label>
                            <div class="max-h-64 overflow-y-auto border border-gray-300 rounded-lg p-3">
                                <div class="mb-3">
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" id="select-all" class="h-4 w-4 text-primary border-gray-300 rounded focus:ring-primary">
                                        <span class="font-semibold text-gray-800">Select All</span>
                                    </label>
                                </div>
                                <div class="space-y-2">
                                    <?php foreach ($allStaff as $staff): ?>
                                    <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded">
                                        <input type="checkbox" name="selected_users[]" value="<?php echo $staff['id']; ?>"
                                               class="staff-checkbox h-4 w-4 text-primary border-gray-300 rounded focus:ring-primary">
                                        <div class="flex-1 flex items-center justify-between">
                                            <div>
                                                <div class="font-medium text-gray-900"><?php echo $staff['name']; ?></div>
                                                <div class="text-sm text-gray-500"><?php echo User::getRoleName($staff['role']); ?></div>
                                            </div>
                                            <div class="text-right">
                                                <div class="font-semibold text-primary"><?php echo formatCurrency($staff['salary']); ?></div>
                                                <div class="text-xs text-gray-500">base salary</div>
                                            </div>
                                        </div>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-yellow-600 mr-2"></i>
                                <div class="text-sm text-yellow-800">
                                    <strong>Payroll Calculation:</strong> Based on attendance records, base salary, 
                                    overtime hours, and configured allowances/deductions.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex space-x-3 mt-6">
                        <button type="button" onclick="hideGeneratePayrollModal()" 
                                class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold py-3 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-green-500 hover:bg-green-600 text-white font-semibold py-3 rounded-lg transition-colors">
                            <i class="fas fa-calculator mr-2"></i>Generate Payroll
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="payment-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Record Salary Payment</h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="pay">
                    <input type="hidden" name="payroll_id" id="payment-payroll-id">
                    
                    <div class="space-y-4">
                        <div class="p-4 bg-gray-50 rounded-lg text-center">
                            <div class="text-sm text-gray-600">Staff Member</div>
                            <div class="font-semibold text-gray-800" id="payment-staff-name"></div>
                            <div class="text-2xl font-bold text-primary mt-2" id="payment-amount"></div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Method</label>
                            <select name="payment_method" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="1">Cash</option>
                                <option value="2">Bank Transfer</option>
                                <option value="3">Cheque</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Notes</label>
                            <textarea name="notes" rows="3"
                                      class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
                                      placeholder="Optional payment notes"></textarea>
                        </div>
                    </div>

                    <div class="flex space-x-3 mt-6">
                        <button type="button" onclick="hidePaymentModal()" 
                                class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold py-3 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-green-500 hover:bg-green-600 text-white font-semibold py-3 rounded-lg transition-colors">
                            <i class="fas fa-check mr-2"></i>Record Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function showGeneratePayrollModal() {
            document.getElementById('generate-payroll-modal').classList.remove('hidden');
        }

        function hideGeneratePayrollModal() {
            document.getElementById('generate-payroll-modal').classList.add('hidden');
        }

        function showPaymentModal(payrollId, staffName, netSalary) {
            document.getElementById('payment-payroll-id').value = payrollId;
            document.getElementById('payment-staff-name').textContent = staffName;
            document.getElementById('payment-amount').textContent = 'QR ' + netSalary.toFixed(2);
            document.getElementById('payment-modal').classList.remove('hidden');
        }

        function hidePaymentModal() {
            document.getElementById('payment-modal').classList.add('hidden');
        }

        // Select all checkbox functionality
        document.getElementById('select-all')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.staff-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        // Print payslip
        function printPayslip(payrollId) {
            window.open(`../prints/payslip.php?id=${payrollId}`, '_blank');
        }

        // View payslip details
        function viewPayslip(payrollId) {
            window.open(`payslip-details.php?id=${payrollId}`, '_blank');
        }

        // Export payroll
        function exportPayroll() {
            const month = <?php echo $currentMonth; ?>;
            const year = <?php echo $currentYear; ?>;
            window.open(`../utilities/data-export.php?action=payroll&month=${month}&year=${year}`, '_blank');
        }
    </script>
</body>
</html>