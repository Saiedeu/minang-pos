<?php
/**
 * POS System - Table Management
 * Manage restaurant tables and their status
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';
require_once 'includes/auth-check.php';

$user = User::getCurrentUser();
$db = Database::getInstance();
$error = '';
$success = '';

// Handle table actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $tableNumber = sanitize($_POST['table_number'] ?? '');
    
    if ($action === 'add_table' && $tableNumber) {
        $tableData = [
            'table_number' => $tableNumber,
            'seating_capacity' => intval($_POST['seating_capacity'] ?? 2),
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Check if table number already exists
        $exists = $db->count('restaurant_tables', 'table_number = ?', [$tableNumber]);
        
        if ($exists > 0) {
            $error = 'Table number already exists';
        } else {
            $inserted = $db->insert('restaurant_tables', $tableData);
            if ($inserted) {
                $success = 'Table added successfully';
            } else {
                $error = 'Failed to add table';
            }
        }
    }
    
    if ($action === 'update_status') {
        $tableId = intval($_POST['table_id']);
        $status = $_POST['status'] ?? 'available';
        $notes = sanitize($_POST['notes'] ?? '');
        
        $updated = $db->update('restaurant_tables', 
            ['status' => $status, 'notes' => $notes, 'updated_at' => date('Y-m-d H:i:s')], 
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

// Create restaurant_tables table if it doesn't exist
$db->query("
    CREATE TABLE IF NOT EXISTS restaurant_tables (
        id INT PRIMARY KEY AUTO_INCREMENT,
        table_number VARCHAR(10) NOT NULL UNIQUE,
        seating_capacity INT DEFAULT 2,
        status ENUM('available', 'occupied', 'reserved', 'cleaning') DEFAULT 'available',
        notes TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

// Get all tables
$tables = $db->fetchAll("SELECT * FROM restaurant_tables WHERE is_active = 1 ORDER BY CAST(table_number AS UNSIGNED)");

// Get current table orders
$tableOrders = $db->fetchAll("
    SELECT s.table_number, COUNT(*) as order_count, MAX(s.created_at) as last_order
    FROM sales s
    WHERE s.order_type = 1 
    AND DATE(s.created_at) = CURDATE()
    AND s.table_number IS NOT NULL
    GROUP BY s.table_number
");

// Create table orders lookup
$tableOrdersMap = [];
foreach ($tableOrders as $order) {
    $tableOrdersMap[$order['table_number']] = $order;
}

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
                        <p class="text-blue-100">Monitor and manage dining tables</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="showAddTableModal()" class="bg-white bg-opacity-20 text-white px-4 py-2 rounded-lg font-medium hover:bg-opacity-30 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add Table
                    </button>
                    <a href="sales.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        <i class="fas fa-cash-register mr-2"></i>New Order
                    </a>
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

        <!-- Table Status Summary -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
            <?php
                $statusCounts = [
                    'available' => 0,
                    'occupied' => 0, 
                    'reserved' => 0,
                    'cleaning' => 0
                ];
                
                foreach ($tables as $table) {
                    $statusCounts[$table['status']]++;
                }
            ?>
            
            <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-green-600"><?php echo $statusCounts['available']; ?></div>
                <div class="text-sm text-gray-600">Available</div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-user-friends text-red-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-red-600"><?php echo $statusCounts['occupied']; ?></div>
                <div class="text-sm text-gray-600">Occupied</div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-bookmark text-yellow-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-yellow-600"><?php echo $statusCounts['reserved']; ?></div>
                <div class="text-sm text-gray-600">Reserved</div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-broom text-blue-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-blue-600"><?php echo $statusCounts['cleaning']; ?></div>
                <div class="text-sm text-gray-600">Cleaning</div>
            </div>
        </div>

        <!-- Tables Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <?php foreach ($tables as $table): ?>
            <?php 
                $statusColors = [
                    'available' => 'bg-green-100 border-green-300 text-green-800',
                    'occupied' => 'bg-red-100 border-red-300 text-red-800',
                    'reserved' => 'bg-yellow-100 border-yellow-300 text-yellow-800',
                    'cleaning' => 'bg-blue-100 border-blue-300 text-blue-800'
                ];
                
                $statusIcons = [
                    'available' => 'fas fa-check-circle',
                    'occupied' => 'fas fa-user-friends',
                    'reserved' => 'fas fa-bookmark',
                    'cleaning' => 'fas fa-broom'
                ];
                
                $tableOrder = $tableOrdersMap[$table['table_number']] ?? null;
            ?>
            <div class="<?php echo $statusColors[$table['status']]; ?> border-2 rounded-xl p-4 cursor-pointer hover:shadow-lg transition-all"
                 onclick="showTableModal('<?php echo $table['id']; ?>', '<?php echo $table['table_number']; ?>', '<?php echo $table['status']; ?>', '<?php echo $table['seating_capacity']; ?>')">
                
                <div class="text-center">
                    <div class="text-3xl mb-2">
                        <i class="<?php echo $statusIcons[$table['status']]; ?>"></i>
                    </div>
                    
                    <div class="text-2xl font-bold">Table <?php echo $table['table_number']; ?></div>
                    
                    <div class="text-sm opacity-80 mb-2">
                        <i class="fas fa-users mr-1"></i><?php echo $table['seating_capacity']; ?> seats
                    </div>
                    
                    <div class="text-sm font-semibold uppercase">
                        <?php echo $table['status']; ?>
                    </div>
                    
                    <?php if ($tableOrder): ?>
                    <div class="text-xs mt-2 bg-white bg-opacity-50 rounded px-2 py-1">
                        <?php echo $tableOrder['order_count']; ?> orders today
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
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
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Table Number</label>
                            <input type="text" name="table_number" required
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
                                   placeholder="e.g., 1, A1, VIP1">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Seating Capacity</label>
                            <select name="seating_capacity" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="2">2 people</option>
                                <option value="4">4 people</option>
                                <option value="6">6 people</option>
                                <option value="8">8 people</option>
                                <option value="10">10+ people</option>
                            </select>
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
    <div id="table-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">
                    Table <span id="modal-table-number"></span>
                </h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="table_id" id="modal-table-id">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex items-center p-3 bg-green-50 border border-green-200 rounded-lg cursor-pointer hover:bg-green-100">
                                    <input type="radio" name="status" value="available" class="mr-3">
                                    <div class="flex items-center">
                                        <i class="fas fa-check-circle text-green-600 mr-2"></i>
                                        <span class="text-green-700 font-medium">Available</span>
                                    </div>
                                </label>
                                <label class="flex items-center p-3 bg-red-50 border border-red-200 rounded-lg cursor-pointer hover:bg-red-100">
                                    <input type="radio" name="status" value="occupied" class="mr-3">
                                    <div class="flex items-center">
                                        <i class="fas fa-user-friends text-red-600 mr-2"></i>
                                        <span class="text-red-700 font-medium">Occupied</span>
                                    </div>
                                </label>
                                <label class="flex items-center p-3 bg-yellow-50 border border-yellow-200 rounded-lg cursor-pointer hover:bg-yellow-100">
                                    <input type="radio" name="status" value="reserved" class="mr-3">
                                    <div class="flex items-center">
                                        <i class="fas fa-bookmark text-yellow-600 mr-2"></i>
                                        <span class="text-yellow-700 font-medium">Reserved</span>
                                    </div>
                                </label>
                                <label class="flex items-center p-3 bg-blue-50 border border-blue-200 rounded-lg cursor-pointer hover:bg-blue-100">
                                    <input type="radio" name="status" value="cleaning" class="mr-3">
                                    <div class="flex items-center">
                                        <i class="fas fa-broom text-blue-600 mr-2"></i>
                                        <span class="text-blue-700 font-medium">Cleaning</span>
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
                        <button type="button" onclick="hideTableModal()" 
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
        function showAddTableModal() {
            document.getElementById('add-table-modal').classList.remove('hidden');
        }

        function hideAddTableModal() {
            document.getElementById('add-table-modal').classList.add('hidden');
        }

        function showTableModal(tableId, tableNumber, currentStatus, capacity) {
            document.getElementById('modal-table-id').value = tableId;
            document.getElementById('modal-table-number').textContent = tableNumber;
            
            // Set current status
            const statusRadio = document.querySelector(`input[name="status"][value="${currentStatus}"]`);
            if (statusRadio) {
                statusRadio.checked = true;
            }
            
            document.getElementById('table-modal').classList.remove('hidden');
        }

        function hideTableModal() {
            document.getElementById('table-modal').classList.add('hidden');
        }

        // Auto-refresh table status every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>