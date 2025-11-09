<?php
/**
 * Payslip Print Template
 * Generate printable payslip for staff salary
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

// Check authentication
if (!User::isLoggedIn()) {
    exit('Unauthorized');
}

$payrollId = $_GET['id'] ?? 0;
if (!$payrollId) {
    exit('Invalid payroll ID');
}

$db = Database::getInstance();

// Get payroll data
$payroll = $db->fetchOne("
    SELECT p.*, u.name as staff_name, u.qid_number, u.phone, u.email, u.joining_date,
           CASE u.role
               WHEN 1 THEN 'Admin'
               WHEN 2 THEN 'Manager'
               WHEN 3 THEN 'Top Management'
               WHEN 4 THEN 'Cashier'
               WHEN 5 THEN 'Waiter'
               WHEN 6 THEN 'Kitchen Staff'
               WHEN 7 THEN 'Chef'
           END as role_name,
           pb.name as paid_by_name
    FROM payroll p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN users pb ON p.paid_by = pb.id
    WHERE p.id = ?
", [$payrollId]);

if (!$payroll) {
    exit('Payroll record not found');
}

$periodName = date('F Y', mktime(0, 0, 0, $payroll['payroll_month'], 1, $payroll['payroll_year']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo $payroll['staff_name']; ?> - <?php echo $periodName; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            @page { 
                size: A4; 
                margin: 20mm;
            }
            body { 
                font-family: 'Arial', sans-serif; 
                font-size: 12px;
                line-height: 1.4;
                color: black;
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-white">
    <!-- Print Controls -->
    <div class="no-print bg-gray-100 p-4 flex justify-between items-center">
        <a href="../erp/hr/payroll.php" class="flex items-center text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back to Payroll
        </a>
        <button onclick="window.print()" class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
            <i class="fas fa-print mr-2"></i>Print Payslip
        </button>
    </div>

    <!-- Payslip Content -->
    <div class="max-w-4xl mx-auto bg-white">
        <!-- Header -->
        <div class="text-center mb-8 border-b-2 border-primary pb-6">
            <h1 class="text-3xl font-bold text-gray-800"><?php echo BUSINESS_NAME; ?></h1>
            <p class="text-gray-600"><?php echo BUSINESS_ADDRESS; ?></p>
            <p class="text-gray-600">Tel: <?php echo BUSINESS_PHONE; ?></p>
            <div class="mt-4">
                <h2 class="text-2xl font-semibold text-primary">SALARY PAYSLIP</h2>
                <p class="text-lg text-gray-700">For the period: <?php echo $periodName; ?></p>
            </div>
        </div>

        <!-- Employee Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-gray-300 pb-2">Employee Information</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Employee Name:</span>
                        <span class="font-semibold"><?php echo $payroll['staff_name']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Position:</span>
                        <span class="font-semibold"><?php echo $payroll['role_name']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">QID Number:</span>
                        <span class="font-semibold"><?php echo $payroll['qid_number'] ?? 'Not provided'; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Phone:</span>
                        <span class="font-semibold"><?php echo $payroll['phone'] ?? 'Not provided'; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Joining Date:</span>
                        <span class="font-semibold"><?php echo $payroll['joining_date'] ? formatDate($payroll['joining_date']) : 'Not provided'; ?></span>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-gray-300 pb-2">Payroll Information</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Payroll Period:</span>
                        <span class="font-semibold"><?php echo $periodName; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Working Days:</span>
                        <span class="font-semibold"><?php echo $payroll['working_days']; ?> days</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Hours:</span>
                        <span class="font-semibold"><?php echo number_format($payroll['total_hours'], 1); ?> hours</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Generated Date:</span>
                        <span class="font-semibold"><?php echo formatDate($payroll['generated_at']); ?></span>
                    </div>
                    <?php if ($payroll['payment_status'] === 'paid'): ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Payment Date:</span>
                        <span class="font-semibold text-green-600"><?php echo formatDate($payroll['payment_date']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Salary Breakdown -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-gray-300 pb-2">Salary Breakdown</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Earnings -->
                <div>
                    <h4 class="font-semibold text-green-700 mb-3">EARNINGS</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span>Base Salary:</span>
                            <span class="font-semibold"><?php echo formatCurrency($payroll['base_salary']); ?></span>
                        </div>
                        <?php if ($payroll['overtime_pay'] > 0): ?>
                        <div class="flex justify-between">
                            <span>Overtime Pay (<?php echo number_format($payroll['overtime_hours'], 1); ?>h):</span>
                            <span class="font-semibold"><?php echo formatCurrency($payroll['overtime_pay']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($payroll['attendance_bonus'] > 0): ?>
                        <div class="flex justify-between">
                            <span>Attendance Bonus:</span>
                            <span class="font-semibold"><?php echo formatCurrency($payroll['attendance_bonus']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($payroll['other_allowances'] > 0): ?>
                        <div class="flex justify-between">
                            <span>Other Allowances:</span>
                            <span class="font-semibold"><?php echo formatCurrency($payroll['other_allowances']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between border-t border-gray-300 pt-2 text-base">
                            <span class="font-bold">Gross Salary:</span>
                            <span class="font-bold text-green-600"><?php echo formatCurrency($payroll['gross_salary']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Deductions -->
                <div>
                    <h4 class="font-semibold text-red-700 mb-3">DEDUCTIONS</h4>
                    <div class="space-y-2 text-sm">
                        <?php if ($payroll['absence_deduction'] > 0): ?>
                        <div class="flex justify-between">
                            <span>Absence Deduction:</span>
                            <span class="font-semibold"><?php echo formatCurrency($payroll['absence_deduction']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($payroll['other_deductions'] > 0): ?>
                        <div class="flex justify-between">
                            <span>Other Deductions:</span>
                            <span class="font-semibold"><?php echo formatCurrency($payroll['other_deductions']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($payroll['total_deductions'] == 0): ?>
                        <div class="flex justify-between text-gray-500">
                            <span>No Deductions</span>
                            <span>-</span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between border-t border-gray-300 pt-2 text-base">
                            <span class="font-bold">Total Deductions:</span>
                            <span class="font-bold text-red-600"><?php echo formatCurrency($payroll['total_deductions']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Net Salary -->
        <div class="bg-primary bg-opacity-10 border-2 border-primary rounded-lg p-6 mb-8">
            <div class="text-center">
                <h3 class="text-2xl font-bold text-primary mb-2">NET SALARY</h3>
                <p class="text-4xl font-bold text-primary"><?php echo formatCurrency($payroll['net_salary']); ?></p>
                <p class="text-sm text-gray-600 mt-2">
                    (<?php echo ucfirst(numberToWords($payroll['net_salary'])); ?> Qatari Riyals Only)
                </p>
            </div>
        </div>

        <!-- Payment Information -->
        <?php if ($payroll['payment_status'] === 'paid'): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-8">
            <h3 class="text-lg font-semibold text-green-800 mb-3">Payment Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="flex justify-between">
                    <span class="text-green-700">Payment Status:</span>
                    <span class="font-semibold text-green-800">PAID</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-green-700">Payment Date:</span>
                    <span class="font-semibold text-green-800"><?php echo formatDate($payroll['payment_date']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-green-700">Payment Method:</span>
                    <span class="font-semibold text-green-800">
                        <?php
                            $methods = [1 => 'Cash', 2 => 'Bank Transfer', 3 => 'Cheque'];
                            echo $methods[$payroll['payment_method']] ?? 'Unknown';
                        ?>
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-green-700">Paid By:</span>
                    <span class="font-semibold text-green-800"><?php echo $payroll['paid_by_name'] ?? 'System'; ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="text-center text-xs text-gray-500 border-t border-gray-300 pt-6">
            <p>This is a computer-generated payslip and does not require a signature.</p>
            <p>For any queries regarding this payslip, please contact HR department.</p>
            <p class="mt-2"><?php echo SYSTEM_NAME; ?> v<?php echo SYSTEM_VERSION; ?> - Generated on <?php echo formatDateTime(date('Y-m-d H:i:s')); ?></p>
        </div>
    </div>

    <script>
        // Auto print on page load
        window.addEventListener('load', function() {
            setTimeout(() => window.print(), 1000);
        });
    </script>
</body>
</html>