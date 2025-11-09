<?php
/**
 * POS System - Kitchen Display System
 * Real-time order display for kitchen staff
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

// Kitchen display doesn't require full authentication, just basic validation
if (!isset($_SESSION)) {
    session_start();
}

$db = Database::getInstance();

// Get today's pending orders (orders that need preparation)
// Since kitchen_status column doesn't exist, we'll use notes field or add logic
$pendingOrders = $db->fetchAll("
    SELECT s.*, 
           TIMESTAMPDIFF(MINUTE, s.created_at, NOW()) as minutes_ago
    FROM sales s
    WHERE DATE(s.created_at) = CURDATE()
    AND s.order_type IN (1, 3)
    AND (s.notes NOT LIKE '%KITCHEN:READY%' OR s.notes IS NULL)
    AND (s.notes NOT LIKE '%KITCHEN:COMPLETED%' OR s.notes IS NULL)
    ORDER BY s.created_at ASC
");

// Ensure we have an array
if (!$pendingOrders) {
    $pendingOrders = [];
}

// Get order items for each pending order
foreach ($pendingOrders as &$order) {
    $orderItems = $db->fetchAll("
        SELECT * FROM sale_items 
        WHERE sale_id = ? 
        ORDER BY id ASC
    ", [$order['id']]);
    
    $order['items'] = $orderItems ? $orderItems : [];
    
    // Extract kitchen status from notes if exists
    $order['kitchen_status'] = 'pending';
    if (strpos($order['notes'] ?? '', 'KITCHEN:PREPARING') !== false) {
        $order['kitchen_status'] = 'preparing';
    } elseif (strpos($order['notes'] ?? '', 'KITCHEN:READY') !== false) {
        $order['kitchen_status'] = 'ready';
    } elseif (strpos($order['notes'] ?? '', 'KITCHEN:COMPLETED') !== false) {
        $order['kitchen_status'] = 'completed';
    }
}

// Handle AJAX status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $saleId = intval($_POST['sale_id'] ?? 0);
    $status = $_POST['status'] ?? 'pending';
    
    if (in_array($status, ['pending', 'preparing', 'ready', 'completed'])) {
        // Get current order
        $currentOrder = $db->fetchOne("SELECT notes FROM sales WHERE id = ?", [$saleId]);
        $currentNotes = $currentOrder['notes'] ?? '';
        
        // Remove existing kitchen status from notes
        $cleanNotes = preg_replace('/KITCHEN:(PENDING|PREPARING|READY|COMPLETED)\s*/', '', $currentNotes);
        $cleanNotes = trim($cleanNotes);
        
        // Add new kitchen status
        $newNotes = $cleanNotes ? $cleanNotes . ' KITCHEN:' . strtoupper($status) : 'KITCHEN:' . strtoupper($status);
        
        $updated = $db->update('sales', 
            ['notes' => $newNotes, 'updated_at' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$saleId]
        );
        
        echo json_encode(['success' => $updated > 0]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen Display - <?php echo BUSINESS_NAME; ?></title>
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
        
        // Dark mode support
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        }
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', event => {
            if (event.matches) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        });
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
    <header class="bg-gradient-to-r from-gray-800 to-gray-900 border-b border-gray-700 p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 bg-primary rounded-lg flex items-center justify-center">
                    <i class="fas fa-utensils text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold">Kitchen Display System</h1>
                    <p class="text-gray-400"><?php echo BUSINESS_NAME; ?></p>
                </div>
            </div>
            
            <div class="flex items-center space-x-6">
                <div class="text-center">
                    <div id="current-time" class="text-2xl font-bold text-primary"></div>
                    <div id="current-date" class="text-sm text-gray-400"></div>
                </div>
                
                <div class="text-center">
                    <div class="text-3xl font-bold text-yellow-400"><?php echo count($pendingOrders); ?></div>
                    <div class="text-sm text-gray-400">Pending Orders</div>
                </div>
                
                <button onclick="location.reload()" class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-sync-alt mr-2"></i>Refresh
                </button>
            </div>
        </div>
    </header>

    <!-- Orders Display -->
    <div class="p-6 h-screen overflow-y-auto" style="height: calc(100vh - 80px);">
        <?php if (empty($pendingOrders)): ?>
        <!-- No Pending Orders -->
        <div class="flex items-center justify-center h-full">
            <div class="text-center">
                <div class="w-32 h-32 bg-green-500 bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-check-circle text-green-400 text-6xl"></i>
                </div>
                <h2 class="text-3xl font-bold text-gray-300 mb-4">All Caught Up!</h2>
                <p class="text-gray-500 text-xl">No pending orders in the kitchen</p>
                <div class="mt-8">
                    <div id="no-orders-time" class="text-4xl font-bold text-primary mb-2"></div>
                    <p class="text-gray-400">Ready for the next order</p>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- Pending Orders Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php foreach ($pendingOrders as $order): ?>
            <div class="bg-gray-800 rounded-xl border-2 <?php 
                echo $order['minutes_ago'] > 15 ? 'border-red-500 order-urgent' : 
                    ($order['minutes_ago'] > 10 ? 'border-yellow-500' : 'border-gray-600'); 
            ?> p-6" id="order-<?php echo $order['id']; ?>">
                
                <!-- Order Header -->
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 <?php 
                            echo $order['order_type'] == 1 ? 'bg-blue-500' : 'bg-green-500'; 
                        ?> rounded-lg flex items-center justify-center">
                            <i class="fas fa-<?php 
                                echo $order['order_type'] == 1 ? 'utensils' : 'motorcycle'; 
                            ?> text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold"><?php echo htmlspecialchars($order['order_number']); ?></h3>
                            <p class="text-sm text-gray-400">
                                <?php 
                                    echo $order['order_type'] == 1 ? 'Dine-In' : 'Delivery';
                                    if ($order['table_number']) echo ' - Table ' . htmlspecialchars($order['table_number']);
                                ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="text-right">
                        <div class="text-2xl font-bold <?php 
                            echo $order['minutes_ago'] > 15 ? 'text-red-400' : 
                                ($order['minutes_ago'] > 10 ? 'text-yellow-400' : 'text-gray-300'); 
                        ?>">
                            <?php echo $order['minutes_ago']; ?>min
                        </div>
                        <div class="text-xs text-gray-500"><?php echo date('H:i', strtotime($order['created_at'])); ?></div>
                    </div>
                </div>

                <!-- Kitchen Status Indicator -->
                <div class="mb-4">
                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?php
                        switch($order['kitchen_status']) {
                            case 'preparing':
                                echo 'bg-yellow-500 text-black';
                                break;
                            case 'ready':
                                echo 'bg-green-500 text-white';
                                break;
                            default:
                                echo 'bg-gray-500 text-white';
                        }
                    ?>">
                        <?php echo ucfirst($order['kitchen_status']); ?>
                    </span>
                </div>

                <!-- Customer Info for Delivery -->
                <?php if ($order['order_type'] == 3 && $order['customer_name']): ?>
                <div class="mb-4 p-3 bg-gray-700 rounded-lg">
                    <div class="text-sm">
                        <div><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></div>
                        <?php if ($order['customer_phone']): ?>
                        <div><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></div>
                        <?php endif; ?>
                        <?php if ($order['customer_address']): ?>
                        <div><strong>Address:</strong> <?php echo htmlspecialchars($order['customer_address']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Order Items -->
                <div class="mb-6">
                    <h4 class="font-semibold mb-3 text-gray-300">Order Items:</h4>
                    <div class="space-y-2">
                        <?php foreach ($order['items'] as $item): ?>
                        <div class="flex justify-between items-center p-3 bg-gray-700 rounded-lg">
                            <div class="flex-1">
                                <div class="font-semibold"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <?php if ($item['product_name_ar']): ?>
                                <div class="text-sm text-gray-400" dir="rtl"><?php echo htmlspecialchars($item['product_name_ar']); ?></div>
                                <?php endif; ?>
                                <?php if ($item['notes']): ?>
                                <div class="text-sm text-yellow-400 mt-1">
                                    <i class="fas fa-sticky-note mr-1"></i><?php echo htmlspecialchars($item['notes']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-right ml-4">
                                <div class="text-2xl font-bold text-primary"><?php echo $item['quantity']; ?></div>
                                <div class="text-xs text-gray-400">qty</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex space-x-2">
                    <?php if ($order['kitchen_status'] === 'pending'): ?>
                    <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'preparing')"
                            class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-black font-semibold py-3 rounded-lg transition-colors">
                        <i class="fas fa-play mr-2"></i>Start Prep
                    </button>
                    <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'ready')"
                            class="flex-1 bg-green-500 hover:bg-green-600 text-white font-semibold py-3 rounded-lg transition-colors">
                        <i class="fas fa-check mr-2"></i>Ready
                    </button>
                    <?php elseif ($order['kitchen_status'] === 'preparing'): ?>
                    <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'ready')"
                            class="flex-1 bg-green-500 hover:bg-green-600 text-white font-semibold py-3 rounded-lg transition-colors">
                        <i class="fas fa-check mr-2"></i>Mark Ready
                    </button>
                    <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'pending')"
                            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-semibold py-3 rounded-lg transition-colors">
                        <i class="fas fa-undo mr-2"></i>Back to Pending
                    </button>
                    <?php else: ?>
                    <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'completed')"
                            class="flex-1 bg-blue-500 hover:bg-blue-600 text-white font-semibold py-3 rounded-lg transition-colors">
                        <i class="fas fa-truck mr-2"></i>Complete
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Update clock
        function updateClock() {
            const now = new Date();
            document.getElementById('current-time').textContent = now.toLocaleTimeString('en-US', {
                hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
            document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', {
                weekday: 'long', month: 'long', day: 'numeric'
            });
            
            // Update no-orders time if visible
            const noOrdersTime = document.getElementById('no-orders-time');
            if (noOrdersTime) {
                noOrdersTime.textContent = now.toLocaleTimeString('en-US', {
                    hour12: true, hour: '2-digit', minute: '2-digit'
                });
            }
        }

        // Update order status
        async function updateOrderStatus(saleId, status) {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=1&sale_id=${saleId}&status=${status}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Show success notification
                    showNotification(`Order ${saleId} marked as ${status}`, 'success');
                    
                    // Reload page after a short delay to update the display
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification('Failed to update order status', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
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
        updateClock();
        setInterval(updateClock, 1000);

        // Auto-refresh every 30 seconds
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 30000);

        // Sound notification for urgent orders
        function checkUrgentOrders() {
            const urgentOrders = document.querySelectorAll('.order-urgent');
            if (urgentOrders.length > 0) {
                // Create a subtle notification sound
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