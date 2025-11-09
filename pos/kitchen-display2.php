<?php
/**
 * POS System - Kitchen Display
 * Real-time order display for kitchen staff
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

// Kitchen display doesn't require full authentication, just basic validation
if (!isset($_SESSION)) {
    session_start();
}

$db = Database::getInstance();

// Check if kitchen_status column exists, if not use a workaround
$tableSchema = $db->fetchAll("DESCRIBE sales");
$hasKitchenStatus = false;
foreach ($tableSchema as $column) {
    if ($column['Field'] === 'kitchen_status') {
        $hasKitchenStatus = true;
        break;
    }
}

// Get pending orders based on whether kitchen_status column exists
if ($hasKitchenStatus) {
    // Use kitchen_status column if it exists
    $pendingOrders = $db->fetchAll("
        SELECT s.*, 
               CASE 
                   WHEN s.order_type = 1 THEN CONCAT('Table ', COALESCE(s.table_number, 'Unknown'))
                   WHEN s.order_type = 2 THEN 'Take Away'
                   WHEN s.order_type = 3 THEN CONCAT('Delivery - ', COALESCE(s.customer_name, 'Customer'))
                   ELSE 'Order'
               END as order_display,
               TIMESTAMPDIFF(MINUTE, s.created_at, NOW()) as minutes_ago
        FROM sales s
        WHERE s.kitchen_status IN (0, 1)
        AND DATE(s.created_at) = CURDATE()
        ORDER BY s.created_at ASC
    ");
} else {
    // Fallback: use notes field to track kitchen status
    $pendingOrders = $db->fetchAll("
        SELECT s.*, 
               CASE 
                   WHEN s.order_type = 1 THEN CONCAT('Table ', COALESCE(s.table_number, 'Unknown'))
                   WHEN s.order_type = 2 THEN 'Take Away'
                   WHEN s.order_type = 3 THEN CONCAT('Delivery - ', COALESCE(s.customer_name, 'Customer'))
                   ELSE 'Order'
               END as order_display,
               TIMESTAMPDIFF(MINUTE, s.created_at, NOW()) as minutes_ago,
               CASE 
                   WHEN s.notes LIKE '%KITCHEN:PREPARING%' THEN 1
                   WHEN s.notes LIKE '%KITCHEN:READY%' THEN 2
                   WHEN s.notes LIKE '%KITCHEN:COMPLETED%' THEN 3
                   ELSE 0
               END as kitchen_status
        FROM sales s
        WHERE DATE(s.created_at) = CURDATE()
        AND (s.notes NOT LIKE '%KITCHEN:READY%' OR s.notes IS NULL)
        AND (s.notes NOT LIKE '%KITCHEN:COMPLETED%' OR s.notes IS NULL)
        ORDER BY s.created_at ASC
    ");
}

// Ensure we have an array
if (!$pendingOrders) {
    $pendingOrders = [];
}

// Get order items for each pending order
foreach ($pendingOrders as &$order) {
    $orderItems = $db->fetchAll("
        SELECT * FROM sale_items 
        WHERE sale_id = ? 
        ORDER BY id
    ", [$order['id']]);
    
    $order['items'] = $orderItems ? $orderItems : [];
    
    // Ensure kitchen_status is set
    if (!isset($order['kitchen_status'])) {
        $order['kitchen_status'] = 0;
    }
}

// Handle AJAX status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $orderId = intval($_POST['order_id'] ?? 0);
    $status = intval($_POST['kitchen_status'] ?? 0);
    
    if ($hasKitchenStatus) {
        // Update kitchen_status column directly
        $updated = $db->update('sales', 
            [
                'kitchen_status' => $status, 
                'kitchen_updated_at' => date('Y-m-d H:i:s')
            ], 
            'id = ?', 
            [$orderId]
        );
    } else {
        // Update using notes field
        $currentOrder = $db->fetchOne("SELECT notes FROM sales WHERE id = ?", [$orderId]);
        $currentNotes = $currentOrder['notes'] ?? '';
        
        // Remove existing kitchen status from notes
        $cleanNotes = preg_replace('/KITCHEN:(PENDING|PREPARING|READY|COMPLETED)\s*/', '', $currentNotes);
        $cleanNotes = trim($cleanNotes);
        
        // Add new kitchen status
        $statusText = ['PENDING', 'PREPARING', 'READY', 'COMPLETED'][$status] ?? 'PENDING';
        $newNotes = $cleanNotes ? $cleanNotes . ' KITCHEN:' . $statusText : 'KITCHEN:' . $statusText;
        
        $updated = $db->update('sales', 
            ['notes' => $newNotes], 
            'id = ?', 
            [$orderId]
        );
    }
    
    echo json_encode(['success' => $updated > 0]);
    exit();
}

$pageTitle = 'Kitchen Display';
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
                        primary: '#5D5CDE',
                        success: '#10B981',
                        warning: '#F59E0B',
                        danger: '#EF4444'
                    }
                }
            }
        }
    </script>
    <style>
        .order-urgent { animation: pulse 2s infinite; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
<body class="bg-gray-900 text-white overflow-hidden">
    <!-- Header -->
    <header class="bg-gradient-to-r from-orange-600 to-red-600 shadow-lg h-20">
        <div class="flex items-center justify-between px-6 h-full">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-fire text-white text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-white">Kitchen Display System</h1>
                    <p class="text-orange-100">Real-time order management</p>
                </div>
            </div>
            
            <div class="flex items-center space-x-6">
                <div class="text-center">
                    <div id="current-time" class="text-2xl font-bold text-white"></div>
                    <div class="text-sm text-orange-100">Current Time</div>
                </div>
                
                <div class="text-center">
                    <div class="text-2xl font-bold text-white"><?php echo count($pendingOrders); ?></div>
                    <div class="text-sm text-orange-100">Pending Orders</div>
                </div>
                
                <button onclick="location.reload()" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-sync-alt mr-2"></i>Refresh
                </button>
            </div>
        </div>
    </header>

    <!-- Orders Grid -->
    <main class="p-6" style="height: calc(100vh - 80px); overflow-y: auto;">
        <?php if (empty($pendingOrders)): ?>
        <div class="flex items-center justify-center h-full">
            <div class="text-center">
                <i class="fas fa-check-circle text-8xl text-green-500 mb-6"></i>
                <h2 class="text-4xl font-bold text-white mb-4">All Orders Complete!</h2>
                <p class="text-gray-400 text-xl">No pending orders in the kitchen</p>
                <div class="mt-6">
                    <div class="text-gray-500">
                        <i class="fas fa-clock mr-2"></i>
                        <span id="no-orders-time" class="text-2xl font-bold"></span>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-6">
            <?php foreach ($pendingOrders as $order): ?>
            <div class="bg-white text-gray-900 rounded-xl shadow-2xl overflow-hidden border-l-4 <?php 
                echo $order['minutes_ago'] > 20 ? 'border-red-500 order-urgent' : 
                    ($order['minutes_ago'] > 10 ? 'border-yellow-500' : 'border-green-500'); 
            ?>">
                <!-- Order Header -->
                <div class="p-4 bg-gradient-to-r <?php 
                    echo $order['minutes_ago'] > 20 ? 'from-red-500 to-red-600' : 
                        ($order['minutes_ago'] > 10 ? 'from-yellow-500 to-yellow-600' : 'from-green-500 to-green-600'); 
                ?> text-white">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-receipt text-lg"></i>
                            <span class="font-bold text-lg"><?php echo htmlspecialchars($order['order_number']); ?></span>
                        </div>
                        <div class="text-right">
                            <div class="text-sm opacity-90">
                                <?php echo $order['minutes_ago']; ?> min ago
                            </div>
                            <div class="text-xs opacity-75">
                                <?php echo date('H:i', strtotime($order['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div class="font-semibold"><?php echo htmlspecialchars($order['order_display']); ?></div>
                        <div class="flex space-x-2">
                            <?php if ($order['order_type'] == 3): ?>
                            <span class="bg-white/20 px-2 py-1 rounded text-xs">
                                <i class="fas fa-motorcycle mr-1"></i>DELIVERY
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($order['kitchen_status'] == 1): ?>
                            <span class="bg-white/20 px-2 py-1 rounded text-xs animate-pulse">
                                <i class="fas fa-clock mr-1"></i>COOKING
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="p-4">
                    <div class="space-y-3">
                        <?php foreach ($order['items'] as $item): ?>
                        <div class="flex items-center justify-between py-2 border-b border-gray-200">
                            <div class="flex-1">
                                <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <?php if ($item['product_name_ar']): ?>
                                <div class="text-sm text-gray-600" dir="rtl"><?php echo htmlspecialchars($item['product_name_ar']); ?></div>
                                <?php endif; ?>
                                <?php if ($item['notes']): ?>
                                <div class="text-sm text-blue-600 bg-blue-50 px-2 py-1 rounded mt-1">
                                    <i class="fas fa-sticky-note mr-1"></i><?php echo htmlspecialchars($item['notes']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-right ml-4">
                                <div class="text-2xl font-bold text-primary"><?php echo $item['quantity']; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $item['quantity'] > 1 ? 'pieces' : 'piece'; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="p-4 bg-gray-50 border-t">
                    <div class="grid grid-cols-2 gap-3">
                        <?php if ($order['kitchen_status'] == 0): ?>
                        <button onclick="updateKitchenStatus(<?php echo $order['id']; ?>, 1)" 
                                class="bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-fire mr-2"></i>Start Cooking
                        </button>
                        <button onclick="updateKitchenStatus(<?php echo $order['id']; ?>, 2)" 
                                class="bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-check mr-2"></i>Mark Ready
                        </button>
                        <?php elseif ($order['kitchen_status'] == 1): ?>
                        <button onclick="updateKitchenStatus(<?php echo $order['id']; ?>, 2)" 
                                class="bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-lg transition-colors col-span-2">
                            <i class="fas fa-check-circle mr-2"></i>Order Ready
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <div class="text-xs text-gray-500">
                            Status: 
                            <?php 
                                $statusText = ['Pending', 'Cooking', 'Ready', 'Cancelled'];
                                echo $statusText[$order['kitchen_status']] ?? 'Unknown';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>

    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            document.getElementById('current-time').textContent = now.toLocaleTimeString('en-US', {
                hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
            
            // Update no-orders time if visible
            const noOrdersTime = document.getElementById('no-orders-time');
            if (noOrdersTime) {
                noOrdersTime.textContent = now.toLocaleTimeString('en-US', {
                    hour12: true, hour: '2-digit', minute: '2-digit'
                });
            }
        }

        // Update kitchen status
        async function updateKitchenStatus(orderId, status) {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=1&order_id=${orderId}&kitchen_status=${status}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Order status updated successfully', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification('Failed to update order status', 'error');
                }
            } catch (error) {
                console.error('Error updating kitchen status:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        // Show notification
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
            } text-white font-semibold`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Initialize
        updateTime();
        setInterval(updateTime, 1000);

        // Auto-refresh every 30 seconds
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 30000);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F5') {
                e.preventDefault();
                location.reload();
            }
        });

        // Sound notification for urgent orders
        function checkUrgentOrders() {
            const urgentOrders = document.querySelectorAll('.order-urgent');
            if (urgentOrders.length > 0) {
                try {
                    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    const oscillator = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext.destination);
                    
                    oscillator.frequency.value = 800;
                    oscillator.type = 'sine';
                    
                    gainNode.gain.setValueAtTime(0, audioContext.currentTime);
                    gainNode.gain.linearRampToValueAtTime(0.1, audioContext.currentTime + 0.1);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
                    
                    oscillator.start(audioContext.currentTime);
                    oscillator.stop(audioContext.currentTime + 0.5);
                } catch (e) {
                    // Sound not supported, ignore
                }
            }
        }

        // Check for urgent orders every 30 seconds
        setInterval(checkUrgentOrders, 30000);
        checkUrgentOrders(); // Check immediately
    </script>
</body>
</html>