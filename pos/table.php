<?php
/**
 * POS System - Table Management
 * Manage restaurant tables and their occupancy status
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';
require_once 'includes/auth-check.php';

$user = User::getCurrentUser();
$db = Database::getInstance();
$error = '';
$success = '';

// Handle table operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_table') {
        $tableData = [
            'table_number' => sanitize($_POST['table_number'] ?? ''),
            'table_name' => sanitize($_POST['table_name'] ?? ''),
            'capacity' => intval($_POST['capacity'] ?? 4),
            'location' => sanitize($_POST['location'] ?? ''),
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        if (empty($tableData['table_number'])) {
            $error = 'Table number is required';
        } else {
            $inserted = $db->insert('tables', $tableData);
            if ($inserted) {
                $success = 'Table added successfully';
            } else {
                $error = 'Failed to add table';
            }
        }
    }
    
    if ($action === 'update_status') {
        $tableId = intval($_POST['table_id'] ?? 0);
        $status = $_POST['status'] ?? 'available';
        $notes = sanitize($_POST['notes'] ?? '');
        
        $updated = $db->update('tables', 
            ['status' => $status, 'status_notes' => $notes, 'updated_at' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$tableId]
        );
        
        if ($updated) {
            $success = 'Table status updated';
        } else {
            $error = 'Failed to update table status';
        }
    }
}

// Get all tables with current orders
$tables = $db->fetchAll("
    SELECT t.*, 
           COUNT(s.id) as active_orders,
           SUM(s.total) as table_total,
           MAX(s.created_at) as last_order_time
    FROM tables t
    LEFT JOIN sales s ON t.table_number = s.table_number 
        AND s.kitchen_status < 2 
        AND DATE(s.created_at) = CURDATE()
    WHERE t.is_active = 1
    GROUP BY t.id
    ORDER BY CAST(t.table_number AS UNSIGNED)
");

// Get today's table statistics
$tableStats = $db->fetchOne("
    SELECT 
        COUNT(DISTINCT s.table_number) as active_tables,
        COUNT(s.id) as table_orders,
        SUM(s.total) as table_revenue
    FROM sales s
    WHERE s.order_type = 1 
    AND DATE(s.created_at) = CURDATE()
");

$pageTitle = 'Table Management';
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
    <!-- Header -->
    <header class="bg-gradient-to-r from-primary to-blue-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-white hover:text-blue-200">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold text-white">Table Management</h1>
                        <p class="text-blue-100">Monitor and manage restaurant tables</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="showAddTableModal()" class="bg-white bg-opacity-20 text-white px-4 py-2 rounded-lg font-medium hover:bg-opacity-30">
                        <i class="fas fa-plus mr-2"></i>Add Table
                    </button>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 py-8">
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

        <!-- Table Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Active Tables</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $tableStats['active_tables'] ?? 0; ?></p>
                    </div>
                    <i class="fas fa-chair text-4xl text-blue-500"></i>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Table Orders</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $tableStats['table_orders'] ?? 0; ?></p>
                    </div>
                    <i class="fas fa-utensils text-4xl text-green-500"></i>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Table Revenue</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo formatCurrency($tableStats['table_revenue'] ?? 0); ?></p>
                    </div>
                    <i class="fas fa-money-bill-wave text-4xl text-primary"></i>
                </div>
            </div>
        </div>

        <!-- Tables Grid -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-semibold text-gray-800">Restaurant Tables</h2>
                <div class="flex items-center space-x-4 text-sm">
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-green-500 rounded mr-2"></div>
                        <span>Available</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-yellow-500 rounded mr-2"></div>
                        <span>Occupied</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-red-500 rounded mr-2"></div>
                        <span>Reserved</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-4">
                <?php foreach ($tables as $table): ?>
                <?php
                    $statusColor = 'bg-green-500';
                    $statusText = 'Available';
                    
                    if ($table['active_orders'] > 0) {
                        $statusColor = 'bg-yellow-500';
                        $statusText = 'Occupied';
                    } elseif ($table['status'] === 'reserved') {
                        $statusColor = 'bg-blue-500';
                        $statusText = 'Reserved';
                    } elseif ($table['status'] === 'maintenance') {
                        $statusColor = 'bg-red-500';
                        $statusText = 'Maintenance';
                    }
                ?>
                
                <div class="bg-white border-2 border-gray-200 rounded-xl p-4 hover:shadow-lg transition-all cursor-pointer"
                     onclick="showTableDetails(<?php echo $table['id']; ?>, '<?php echo $table['table_number']; ?>')">
                    <!-- Table Status Indicator -->
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-4 h-4 <?php echo $statusColor; ?> rounded-full"></div>
                        <span class="text-xs text-gray-500"><?php echo $table['capacity']; ?> seats</span>
                    </div>
                    
                    <!-- Table Number -->
                    <div class="text-center mb-3">
                        <div class="text-3xl font-bold text-gray-800"><?php echo $table['table_number']; ?></div>
                        <?php if ($table['table_name']): ?>
                        <div class="text-sm text-gray-600"><?php echo $table['table_name']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Table Info -->
                    <div class="text-center">
                        <div class="text-xs font-semibold mb-1 <?php 
                            echo $statusColor === 'bg-green-500' ? 'text-green-600' : 
                                ($statusColor === 'bg-yellow-500' ? 'text-yellow-600' : 
                                ($statusColor === 'bg-blue-500' ? 'text-blue-600' : 'text-red-600')); 
                        ?>">
                            <?php echo strtoupper($statusText); ?>
                        </div>
                        
                        <?php if ($table['active_orders'] > 0): ?>
                        <div class="text-xs text-gray-600">
                            <?php echo $table['active_orders']; ?> active order<?php echo $table['active_orders'] > 1 ? 's' : ''; ?>
                        </div>
                        <div class="text-xs font-semibold text-primary">
                            <?php echo formatCurrency($table['table_total']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($table['location']): ?>
                        <div class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-map-marker-alt mr-1"></i><?php echo $table['location']; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="mt-3 pt-3 border-t border-gray-200">
                        <div class="flex justify-center space-x-2">
                            <?php if ($table['active_orders'] == 0): ?>
                            <button onclick="event.stopPropagation(); updateTableStatus(<?php echo $table['id']; ?>, 'reserved')" 
                                    class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded hover:bg-blue-200">
                                Reserve
                            </button>
                            <?php endif; ?>
                            
                            <button onclick="event.stopPropagation(); viewTableHistory(<?php echo $table['id']; ?>)" 
                                    class="text-xs bg-gray-100 text-gray-800 px-2 py-1 rounded hover:bg-gray-200">
                                History
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Add Table Modal -->
    <div id="add-table-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Add New Table</h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="add_table">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Table Number *</label>
                            <input type="text" name="table_number" required
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
                                   placeholder="e.g., 01, A1, VIP-1">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Table Name</label>
                            <input type="text" name="table_name"
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
                                   placeholder="Optional table name">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Capacity</label>
                                <input type="number" name="capacity" min="1" max="20" value="4"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Location</label>
                                <select name="location" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                    <option value="">Select Area</option>
                                    <option value="Main Hall">Main Hall</option>
                                    <option value="VIP Section">VIP Section</option>
                                    <option value="Outdoor">Outdoor</option>
                                    <option value="Private Room">Private Room</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="flex space-x-3 mt-6">
                        <button type="button" onclick="hideAddTableModal()" 
                                class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold py-3 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-primary hover:bg-blue-600 text-white font-semibold py-3 rounded-lg transition-colors">
                            <i class="fas fa-plus mr-2"></i>Add Table
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Table Status Modal -->
    <div id="table-status-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Update Table Status</h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="table_id" id="status-table-id">
                    
                    <div class="mb-4">
                        <div class="text-lg font-semibold text-center mb-4">
                            Table <span id="status-table-number"></span>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex items-center p-3 bg-green-50 border border-green-200 rounded-lg cursor-pointer hover:bg-green-100">
                                    <input type="radio" name="status" value="available" class="mr-3">
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                                        <span class="text-green-700">Available</span>
                                    </div>
                                </label>
                                
                                <label class="flex items-center p-3 bg-blue-50 border border-blue-200 rounded-lg cursor-pointer hover:bg-blue-100">
                                    <input type="radio" name="status" value="reserved" class="mr-3">
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                                        <span class="text-blue-700">Reserved</span>
                                    </div>
                                </label>
                                
                                <label class="flex items-center p-3 bg-red-50 border border-red-200 rounded-lg cursor-pointer hover:bg-red-100 col-span-2">
                                    <input type="radio" name="status" value="maintenance" class="mr-3">
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                                        <span class="text-red-700">Maintenance</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="3"
                                      class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
                                      placeholder="Optional notes about table status"></textarea>
                        </div>
                    </div>

                    <div class="flex space-x-3 mt-6">
                        <button type="button" onclick="hideTableStatusModal()" 
                                class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold py-3 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-primary hover:bg-blue-600 text-white font-semibold py-3 rounded-lg transition-colors">
                            <i class="fas fa-save mr-2"></i>Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Table management functions
        function showAddTableModal() {
            document.getElementById('add-table-modal').classList.remove('hidden');
        }

        function hideAddTableModal() {
            document.getElementById('add-table-modal').classList.add('hidden');
        }

        function showTableDetails(tableId, tableNumber) {
            document.getElementById('status-table-id').value = tableId;
            document.getElementById('status-table-number').textContent = tableNumber;
            document.getElementById('table-status-modal').classList.remove('hidden');
        }

        function hideTableStatusModal() {
            document.getElementById('table-status-modal').classList.add('hidden');
        }

        function updateTableStatus(tableId, status) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.name = 'action';
            actionInput.value = 'update_status';
            
            const tableIdInput = document.createElement('input');
            tableIdInput.name = 'table_id';
            tableIdInput.value = tableId;
            
            const statusInput = document.createElement('input');
            statusInput.name = 'status';
            statusInput.value = status;
            
            form.appendChild(actionInput);
            form.appendChild(tableIdInput);
            form.appendChild(statusInput);
            
            document.body.appendChild(form);
            form.submit();
        }

        function viewTableHistory(tableId) {
            // Implementation for viewing table order history
            alert('Table history feature coming soon');
        }

        // Auto refresh every 2 minutes
        setInterval(() => {
            location.reload();
        }, 120000);
    </script>
</body>
</html>