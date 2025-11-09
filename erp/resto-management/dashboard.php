<?php
/**
 * ERP System - Restaurant Management Dashboard
 * Overview of all restaurant management activities
 */

define('MINANG_SYSTEM', true);
require_once '../../config/config.php';
require_once '../includes/auth-check.php';

$restoMgmt = new RestaurantManagement();
$db = Database::getInstance();

// Get analytics
$analytics = $restoMgmt->getRestaurantAnalytics();

// Get recent activities
$recentTasks = array_slice($restoMgmt->getTasks('pending'), 0, 5);
$recentPlans = array_slice($restoMgmt->getPlans(), 0, 3);
$expirationAlerts = $restoMgmt->getExpirationAlerts(3);

// Get today's temperature compliance
$temperatureCompliance = $db->fetchOne("
    SELECT 
        COUNT(*) as total_records,
        COUNT(CASE WHEN 
            (equipment_type = 'chiller' AND temperature <= 8) OR 
            (equipment_type = 'freezer' AND temperature <= -12)
        THEN 1 END) as compliant_records
    FROM temperature_records 
    WHERE record_date = CURDATE()
");

$compliancePercentage = $temperatureCompliance['total_records'] > 0 ? 
    round(($temperatureCompliance['compliant_records'] / $temperatureCompliance['total_records']) * 100) : 0;

$pageTitle = 'Restaurant Management';
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
                    <h1 class="text-3xl font-bold text-gray-800">Restaurant Management</h1>
                    <p class="text-gray-600">Operational oversight and compliance monitoring</p>
                </div>
            </div>

            <!-- Alert Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm">Pending Tasks</p>
                            <p class="text-3xl font-bold"><?php echo $analytics['pending_tasks']; ?></p>
                        </div>
                        <i class="fas fa-tasks text-4xl text-blue-300"></i>
                    </div>
                    <?php if ($analytics['overdue_tasks'] > 0): ?>
                    <div class="mt-2 text-xs text-blue-100">
                        <i class="fas fa-exclamation-triangle mr-1"></i><?php echo $analytics['overdue_tasks']; ?> overdue
                    </div>
                    <?php endif; ?>
                </div>

                <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-red-100 text-sm">Expiring Items</p>
                            <p class="text-3xl font-bold"><?php echo $analytics['expiring_items']; ?></p>
                        </div>
                        <i class="fas fa-clock text-4xl text-red-300"></i>
                    </div>
                    <?php if ($analytics['expired_items'] > 0): ?>
                    <div class="mt-2 text-xs text-red-100">
                        <i class="fas fa-times-circle mr-1"></i><?php echo $analytics['expired_items']; ?> expired
                    </div>
                    <?php endif; ?>
                </div>

                <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-yellow-100 text-sm">Temperature Compliance</p>
                            <p class="text-3xl font-bold"><?php echo $compliancePercentage; ?>%</p>
                        </div>
                        <i class="fas fa-thermometer-half text-4xl text-yellow-300"></i>
                    </div>
                    <div class="mt-2 text-xs text-yellow-100">
                        <?php echo $temperatureCompliance['total_records']; ?> records today
                    </div>
                </div>

                <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm">Active Plans</p>
                            <p class="text-3xl font-bold"><?php echo $analytics['active_plans']; ?></p>
                        </div>
                        <i class="fas fa-project-diagram text-4xl text-purple-300"></i>
                    </div>
                </div>
            </div>

            <!-- Main Dashboard Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Critical Alerts -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Expiration Alerts -->
                    <?php if (!empty($expirationAlerts)): ?>
                    <div class="bg-white rounded-xl shadow-lg">
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                                Critical Expiration Alerts
                            </h2>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <?php foreach ($expirationAlerts as $alert): ?>
                                <div class="flex items-center justify-between p-4 bg-red-50 border border-red-200 rounded-lg">
                                    <div>
                                        <h4 class="font-semibold text-red-800"><?php echo $alert['item_name']; ?></h4>
                                        <p class="text-sm text-red-600">
                                            <?php 
                                                if ($alert['days_until_expiry'] == 0) {
                                                    echo 'Expires TODAY';
                                                } elseif ($alert['days_until_expiry'] < 0) {
                                                    echo 'Expired ' . abs($alert['days_until_expiry']) . ' day(s) ago';
                                                } else {
                                                    echo 'Expires in ' . $alert['days_until_expiry'] . ' day(s)';
                                                }
                                            ?>
                                        </p>
                                        <p class="text-xs text-gray-600">Location: <?php echo $alert['location'] ?? 'Unknown'; ?></p>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-semibold"><?php echo $alert['quantity']; ?> <?php echo $alert['unit']; ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-4 text-center">
                                <a href="expiration-records.php" class="text-red-600 hover:text-red-800 font-medium">
                                    <i class="fas fa-arrow-right mr-1"></i>View All Expiration Records
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Pending Tasks -->
                    <div class="bg-white rounded-xl shadow-lg">
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-tasks text-blue-500 mr-2"></i>
                                High Priority Tasks
                            </h2>
                        </div>
                        <div class="p-6">
                            <?php if (empty($recentTasks)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-check-circle text-4xl text-green-500 mb-4"></i>
                                <p class="text-gray-600">All tasks completed!</p>
                            </div>
                            <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($recentTasks as $task): ?>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-3 h-3 rounded-full <?php 
                                            echo $task['priority'] == 4 ? 'bg-red-500' : 
                                                ($task['priority'] == 3 ? 'bg-orange-500' : 'bg-blue-500');
                                        ?>"></div>
                                        <div>
                                            <h4 class="font-semibold text-gray-800"><?php echo $task['title']; ?></h4>
                                            <p class="text-sm text-gray-600">
                                                Due: <?php echo $task['due_date'] ? formatDate($task['due_date']) : 'No deadline'; ?>
                                                <?php if ($task['assigned_to_name']): ?>
                                                • Assigned: <?php echo $task['assigned_to_name']; ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <button onclick="quickCompleteTask(<?php echo $task['id']; ?>)" 
                                            class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-4 text-center">
                                <a href="todo-list.php" class="text-blue-600 hover:text-blue-800 font-medium">
                                    <i class="fas fa-arrow-right mr-1"></i>View All Tasks
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Panel -->
                <div class="space-y-6">
                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h2>
                        <div class="space-y-3">
                            <a href="todo-list.php?action=add" 
                               class="flex items-center w-full p-3 text-left bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg transition-colors">
                                <i class="fas fa-plus mr-3"></i>Add New Task
                            </a>
                            <a href="temperature-records.php" 
                               class="flex items-center w-full p-3 text-left bg-yellow-50 hover:bg-yellow-100 text-yellow-700 rounded-lg transition-colors">
                                <i class="fas fa-thermometer-half mr-3"></i>Record Temperature
                            </a>
                            <a href="expiration-records.php?action=add" 
                               class="flex items-center w-full p-3 text-left bg-red-50 hover:bg-red-100 text-red-700 rounded-lg transition-colors">
                                <i class="fas fa-calendar-times mr-3"></i>Add Expiry Item
                            </a>
                            <a href="planner.php?action=add" 
                               class="flex items-center w-full p-3 text-left bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-lg transition-colors">
                                <i class="fas fa-project-diagram mr-3"></i>Create Plan
                            </a>
                        </div>
                    </div>

                    <!-- Recent Plans -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Current Plans</h2>
                        <?php if (empty($recentPlans)): ?>
                        <p class="text-gray-500 text-center py-4">No active plans</p>
                        <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($recentPlans as $plan): ?>
                            <div class="border border-gray-200 rounded-lg p-3">
                                <h4 class="font-semibold text-gray-800 text-sm"><?php echo $plan['title']; ?></h4>
                                <p class="text-xs text-gray-600 mb-2">
                                    <?php echo formatDate($plan['start_date']); ?> - <?php echo formatDate($plan['end_date']); ?>
                                </p>
                                <div class="flex items-center justify-between">
                                    <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded capitalize">
                                        <?php echo $plan['plan_type']; ?>
                                    </span>
                                    <div class="flex items-center space-x-2">
                                        <div class="w-16 bg-gray-200 rounded-full h-2">
                                            <div class="bg-primary h-2 rounded-full" style="width: <?php echo $plan['completion_percentage']; ?>%"></div>
                                        </div>
                                        <span class="text-xs text-gray-600"><?php echo $plan['completion_percentage']; ?>%</span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="mt-4 text-center">
                            <a href="planner.php" class="text-primary hover:text-blue-800 font-medium text-sm">
                                <i class="fas fa-arrow-right mr-1"></i>View All Plans
                            </a>
                        </div>
                    </div>

                    <!-- Temperature Status -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Today's Temperature Status</h2>
                        <div class="text-center mb-4">
                            <div class="text-4xl font-bold <?php echo $compliancePercentage >= 95 ? 'text-green-600' : ($compliancePercentage >= 85 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                <?php echo $compliancePercentage; ?>%
                            </div>
                            <div class="text-sm text-gray-600">Compliance Rate</div>
                        </div>
                        
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span>Total Records:</span>
                                <span class="font-semibold"><?php echo $temperatureCompliance['total_records']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Compliant:</span>
                                <span class="font-semibold text-green-600"><?php echo $temperatureCompliance['compliant_records']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Violations:</span>
                                <span class="font-semibold text-red-600">
                                    <?php echo $temperatureCompliance['total_records'] - $temperatureCompliance['compliant_records']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mt-4 text-center">
                            <a href="temperature-records.php" class="text-primary hover:text-blue-800 font-medium text-sm">
                                <i class="fas fa-arrow-right mr-1"></i>View Temperature Log
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        async function quickCompleteTask(taskId) {
            try {
                const response = await fetch('../../api/restaurant-management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'update_task_status',
                        task_id: taskId,
                        status: 'completed'
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert('Failed to complete task');
                }
            } catch (error) {
                alert('Error completing task');
            }
        }

        // Auto-refresh dashboard every 5 minutes
        setInterval(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>