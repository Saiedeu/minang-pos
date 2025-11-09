<?php
/**
 * POS System - Sales Interface - ENHANCED VERSION
 * Main point of sale interface with enhanced features and keyboard shortcuts
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

// Check authentication and permissions
if (!User::isLoggedIn() || !User::hasPermission('pos_sales')) {
    header('Location: index.php');
    exit();
}

$user = User::getCurrentUser();
$db = Database::getInstance();

// Check active shift
$activeShift = $db->fetchOne(
    "SELECT * FROM shifts WHERE user_id = ? AND is_closed = 0 ORDER BY start_time DESC LIMIT 1", 
    [$user['id']]
);

if (!$activeShift) {
    header('Location: dashboard.php?error=no_active_shift');
    exit();
}

// Get daily order count for serial number
$today = date('Y-m-d');
$dailyCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM sales WHERE DATE(created_at) = ?",
    [$today]
)['count'] ?? 0;

// Generate order serial
$month = date('m');
$day = date('d');
$serial = str_pad($dailyCount + 1, 2, '0', STR_PAD_LEFT);
$orderSerial = "LMR-{$month}{$day}{$serial}";

// Get categories and products
$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name");
$products = $db->fetchAll("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.is_active = 1 AND p.list_in_pos = 1 
    ORDER BY p.name
");

// Get customers
$customers = $db->fetchAll("SELECT * FROM users WHERE role = 8 OR id IN (SELECT DISTINCT customer_id FROM sales WHERE customer_id IS NOT NULL) ORDER BY name");

// Get settings
$deliveryFee = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'delivery_fee'")['setting_value'] ?? 15.00;
$autoPrint = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'receipt_printing'")['setting_value'] ?? '1';

// Sample add-ons
$addons = [
    ['id' => 1, 'name' => 'Extra Rice', 'price' => 2.00],
    ['id' => 2, 'name' => 'Extra Sauce', 'price' => 1.50],
    ['id' => 3, 'name' => 'Extra Spicy', 'price' => 0.00],
    ['id' => 4, 'name' => 'No Spicy', 'price' => 0.00],
    ['id' => 5, 'name' => 'Extra Vegetables', 'price' => 3.00]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Sales - Langit Minang Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#5D5CDE',
                        secondary: '#374151',
                        accent: '#059669',
                        dark: '#111827',
                        light: '#f8fafc',
                        success: '#059669',
                        warning: '#d97706',
                        danger: '#dc2626',
                        info: '#0369a1'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen overflow-hidden" x-data="posSystem()" @keydown.window="handleKeydown($event)">
    <!-- Dark Mode Support -->
    <script>
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

    <!-- Professional Header -->
    <header class="bg-gradient-to-r from-primary to-purple-600 shadow-lg h-16 sticky top-0 z-30">
        <div class="flex items-center justify-between px-4 h-full">
            <!-- Left: Back & Brand -->
            <div class="flex items-center space-x-3">
                <a href="dashboard.php" class="p-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all">
                    <i class="fas fa-arrow-left text-lg"></i>
                </a>
                <div class="flex items-center space-x-2">
                    <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center border border-white/30">
                        <i class="fas fa-cash-register text-white text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-white">Langit Minang Restaurant</h1>
                        <p class="text-xs text-white/80">POS Sales • Shift: <?php echo formatDateTime($activeShift['start_time']); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Center: Date & Time -->
            <div class="text-center text-white">
                <div class="text-lg font-bold" x-text="currentTime"></div>
                <div class="text-xs text-white/80" x-text="currentDate"></div>
            </div>
            
            <!-- Right: User Info & Controls -->
            <div class="flex items-center space-x-3">
                <div class="text-right text-sm text-white">
                    <p class="font-semibold"><?php echo $user['name']; ?></p>
                    <p class="text-xs text-white/80"><?php echo User::getRoleName($user['role']); ?></p>
                </div>
                <button @click="loadHeldOrders(); showHeldOrdersModal = true" class="relative p-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all">
                    <i class="fas fa-pause text-lg"></i>
                    <template x-if="heldOrders.length > 0">
                        <span class="absolute -top-1 -right-1 bg-danger text-white text-xs w-5 h-5 rounded-full flex items-center justify-center font-bold" x-text="heldOrders.length"></span>
                    </template>
                </button>
                <?php if (User::hasPermission('shift_close')): ?>
                <button onclick="location.href='shift-close.php'" class="p-2 text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-all" title="Close Shift (Ctrl+Shift+L)">
                    <i class="fas fa-power-off text-lg"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="flex h-screen" style="height: calc(100vh - 64px);">
        <!-- Categories Sidebar -->
        <div class="w-60 bg-white dark:bg-gray-800 shadow-sm border-r border-gray-200 dark:border-gray-700 flex flex-col">
            <div class="p-3">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Categories</h2>
                <div class="space-y-1 overflow-y-auto max-h-96">
                    <button @click="selectedCategory = ''" 
                            :class="selectedCategory === '' ? 'bg-primary text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                            class="w-full p-3 rounded-lg text-left text-sm font-medium transition-all">
                        <i class="fas fa-th-large mr-2 text-sm"></i>
                        <span>All Items</span>
                        <span class="float-right text-xs opacity-75" x-text="products.length"></span>
                    </button>
                    <?php foreach ($categories as $category): ?>
                    <button @click="selectedCategory = '<?php echo $category['id']; ?>'" 
                            :class="selectedCategory === '<?php echo $category['id']; ?>' ? 'bg-primary text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                            class="w-full p-3 rounded-lg text-left text-sm font-medium transition-all">
                        <i class="<?php echo $category['icon'] ?? 'fas fa-circle'; ?> mr-2 text-sm"></i>
                        <span><?php echo $category['name']; ?></span>
                        <span class="float-right text-xs opacity-75" x-text="getCategoryCount('<?php echo $category['id']; ?>')"></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Customer Section - Fixed at Bottom -->
            <div class="mt-auto p-3 border-t border-gray-200 dark:border-gray-700">
                <h3 class="text-xs font-semibold text-gray-900 dark:text-white mb-2">Customer Details</h3>
                <div class="space-y-2">
                    <input type="text" x-model="customerSearch" @input="searchCustomers()" 
                           placeholder="Search customer..." 
                           class="w-full p-2 text-base border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary">
                    <select x-model="selectedCustomer" @change="loadCustomerForDelivery()" class="w-full p-2 text-base border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary">
                        <option value="">Walk-in Customer</option>
                        <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo $customer['id']; ?>"><?php echo $customer['name']; ?> (<?php echo $customer['phone'] ?? 'No phone'; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Menu Items -->
        <div class="flex-1 p-3 overflow-hidden bg-white dark:bg-gray-800">
            <div class="mb-3 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    <span x-text="getCategoryName()"></span>
                    <span class="text-sm text-gray-500 ml-2" x-text="'(' + filteredProducts.length + ' items)'"></span>
                </h2>
                <input type="text" x-model="searchTerm" placeholder="Search menu items..." 
                       class="p-2 text-base w-64 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary">
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-3 overflow-y-auto" style="height: calc(100% - 70px);">
                <template x-for="product in filteredProducts" :key="product.id">
                    <div @click="addToCart(product)" 
                         :class="product.quantity <= 0 ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:shadow-md hover:border-primary hover:scale-105'"
                         class="bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 p-3 transition-all duration-200">
                        <!-- Product Image -->
                        <div class="aspect-square bg-gray-100 dark:bg-gray-600 rounded-lg mb-2 flex items-center justify-center overflow-hidden">
                            <template x-if="product.photo">
                                <img :src="'../assets/uploads/products/' + product.photo" :alt="product.name" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!product.photo">
                                <i class="fas fa-utensils text-2xl text-gray-400 dark:text-gray-500"></i>
                            </template>
                        </div>
                        
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-1 text-sm leading-tight" x-text="product.name"></h3>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-2 line-clamp-2" x-text="product.name_ar"></p>
                        
                        <div class="space-y-1">
                            <div class="flex justify-between items-center">
                                <span class="text-lg font-bold text-primary" x-text="'QAR ' + parseFloat(product.sell_price).toFixed(2)"></span>
                                <span :class="product.quantity <= 5 ? 'text-danger' : 'text-success'" 
                                      class="text-xs font-medium" x-text="'Stock: ' + product.quantity"></span>
                            </div>
                            <template x-if="product.quantity <= 0">
                                <div class="text-xs text-danger font-medium text-center">OUT OF STOCK</div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Enhanced Cart -->
        <div class="w-96 bg-white dark:bg-gray-800 shadow-lg border-l border-gray-200 dark:border-gray-700 flex flex-col">
            <!-- Cart Header -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="'Current Order: #' + currentSaleCount"></h2>
                    <button @click="showClearCartConfirmation()" class="text-danger hover:text-red-700 text-sm font-medium" title="Clear All (Ctrl+Delete)">
                        <i class="fas fa-trash mr-1"></i>Clear All
                    </button>
                </div>

                <!-- Order Type Toggle -->
                <div class="mb-3">
                    <div class="grid grid-cols-3 gap-1 bg-gray-200 dark:bg-gray-600 p-1 rounded-lg">
                        <button @click="orderType = 'dine-in'" 
                                :class="orderType === 'dine-in' ? 'bg-white dark:bg-gray-700 text-primary shadow-sm' : 'text-gray-600 dark:text-gray-400'"
                                class="p-2 rounded-md text-xs font-medium transition-all">
                            <i class="fas fa-utensils mr-1"></i>Dine-In
                        </button>
                        <button @click="orderType = 'takeaway'" 
                                :class="orderType === 'takeaway' ? 'bg-white dark:bg-gray-700 text-primary shadow-sm' : 'text-gray-600 dark:text-gray-400'"
                                class="p-2 rounded-md text-xs font-medium transition-all">
                            <i class="fas fa-shopping-bag mr-1"></i>Take Away
                        </button>
                        <button @click="orderType = 'delivery'" 
                                :class="orderType === 'delivery' ? 'bg-white dark:bg-gray-700 text-primary shadow-sm' : 'text-gray-600 dark:text-gray-400'"
                                class="p-2 rounded-md text-xs font-medium transition-all">
                            <i class="fas fa-motorcycle mr-1"></i>Delivery
                        </button>
                    </div>
                </div>
                
                <!-- Order Details -->
                <div class="space-y-2">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Order No.</label>
                            <input type="text" x-model="orderSerial" readonly
                                   class="w-full p-2 text-sm border border-gray-300 dark:border-gray-600 rounded bg-gray-100 dark:bg-gray-600 text-gray-900 dark:text-white">
                        </div>
                        
                        <!-- Dine-in: Table Number -->
                        <template x-if="orderType === 'dine-in'">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Table No. (Ctrl+T)</label>
                                <input type="text" x-model="tableNumber" x-ref="tableInput" placeholder="Enter table number"
                                       class="w-full p-2 text-base border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary">
                            </div>
                        </template>
                        
                        <!-- Take Away: Order Type -->
                        <template x-if="orderType === 'takeaway'">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Type</label>
                                <div class="p-2 text-sm bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-200 rounded border font-medium">
                                    <i class="fas fa-shopping-bag mr-1"></i>Take Away
                                </div>
                            </div>
                        </template>
                        
                        <!-- Delivery: Order Type -->
                        <template x-if="orderType === 'delivery'">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Type</label>
                                <div class="p-2 text-sm bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 rounded border font-medium">
                                    <i class="fas fa-motorcycle mr-1"></i>Delivery
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Delivery Customer Details -->
                    <template x-if="orderType === 'delivery'">
                        <div class="space-y-2">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Customer Name *</label>
                                    <input type="text" x-model="deliveryInfo.customerName" 
                                           class="w-full p-2 text-base border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Phone Number *</label>
                                    <input type="tel" x-model="deliveryInfo.phone" 
                                           class="w-full p-2 text-base border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Delivery Address *</label>
                                <textarea x-model="deliveryInfo.address" rows="2"
                                          class="w-full p-2 text-base border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary resize-none"></textarea>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Cart Items -->
            <div class="flex-1 overflow-y-auto p-4">
                <template x-if="cart.length === 0">
                    <div class="text-center py-8">
                        <i class="fas fa-shopping-cart text-3xl text-gray-300 dark:text-gray-600 mb-3"></i>
                        <p class="text-gray-500 dark:text-gray-400 text-sm">No items in cart</p>
                        <p class="text-gray-400 dark:text-gray-500 text-xs mt-1">Start adding items to begin order</p>
                    </div>
                </template>

                <div class="space-y-3">
                    <template x-for="(item, index) in cart" :key="index">
                        <div class="bg-white dark:bg-gray-700 rounded-lg p-3 border border-gray-200 dark:border-gray-600 shadow-sm"
                             :class="selectedCartIndex === index ? 'ring-2 ring-primary border-primary' : ''">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex-1" @click="selectCartItem(index)">
                                    <h4 class="font-semibold text-gray-900 dark:text-white text-sm leading-tight" x-text="item.name"></h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400" x-text="item.name_ar"></p>
                                </div>
                                <button @click="removeFromCart(index)" class="text-danger hover:text-red-700 transition-colors ml-2" title="Remove item">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            
                            <!-- Quantity Controls -->
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex items-center space-x-2">
                                    <button @click="updateQuantity(index, item.quantity - 1)" 
                                            class="w-7 h-7 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-full flex items-center justify-center hover:bg-red-200 transition-colors">
                                        <i class="fas fa-minus text-xs"></i>
                                    </button>
                                    <button @click="showQuantityModal(index)" 
                                            class="px-3 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 rounded-lg hover:bg-blue-200 transition-colors">
                                        <span class="text-sm font-bold" x-text="item.quantity"></span>
                                        <i class="fas fa-edit text-xs ml-1"></i>
                                    </button>
                                    <button @click="updateQuantity(index, item.quantity + 1)" 
                                            class="w-7 h-7 bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded-full flex items-center justify-center hover:bg-green-200 transition-colors">
                                        <i class="fas fa-plus text-xs"></i>
                                    </button>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs text-gray-500 dark:text-gray-400" x-text="'QAR ' + parseFloat(item.sell_price).toFixed(2) + ' each'"></div>
                                    <div class="text-sm font-bold text-primary" x-text="'QAR ' + getItemTotal(item).toFixed(2)"></div>
                                </div>
                            </div>
                            
                            <!-- Item Notes -->
                            <template x-if="item.notes">
                                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded p-2 mb-2">
                                    <div class="flex justify-between items-start">
                                        <p class="text-xs text-blue-800 dark:text-blue-200" x-text="'Note: ' + item.notes"></p>
                                        <button @click="editItemNote(index)" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <!-- Item Add-ons -->
                            <template x-if="item.addons && item.addons.length > 0">
                                <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded p-2 mb-2">
                                    <div class="text-xs font-medium text-purple-800 dark:text-purple-200 mb-1">Add-ons:</div>
                                    <template x-for="addon in item.addons" :key="addon.id">
                                        <div class="flex justify-between items-center text-xs">
                                            <span class="text-purple-700 dark:text-purple-300" x-text="addon.name"></span>
                                            <span class="text-purple-700 dark:text-purple-300 font-medium" x-text="addon.price > 0 ? '+QAR ' + addon.price.toFixed(2) : 'FREE'"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            
                            <div class="flex space-x-2">
                                <button @click="addItemNote(index)" class="text-xs text-primary hover:text-primary/80 font-medium">
                                    <i class="fas fa-sticky-note mr-1"></i>
                                    <span x-text="item.notes ? 'Edit Note' : 'Add Note'"></span>
                                </button>
                                <button @click="openAddonsModal(index)" class="text-xs text-purple-600 hover:text-purple-800 font-medium">
                                    <i class="fas fa-plus-circle mr-1"></i>
                                    <span x-text="item.addons && item.addons.length > 0 ? 'Edit Add-ons' : 'Add Add-ons'"></span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Total & Actions -->
            <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                <!-- Compact Summary -->
                <div class="grid grid-cols-2 gap-4 mb-4 text-sm">
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Items:</span>
                            <span class="font-semibold text-gray-900 dark:text-white" x-text="totalItems"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Quantity:</span>
                            <span class="font-semibold text-gray-900 dark:text-white" x-text="totalQuantity"></span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Subtotal:</span>
                            <span class="font-semibold text-gray-900 dark:text-white" x-text="'QAR ' + subtotal.toFixed(2)"></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 dark:text-gray-400">Discount (F3):</span>
                            <div class="flex items-center space-x-2">
                                <input type="number" x-model="discount" @input="calculateTotal()" step="0.01" x-ref="discountInput"
                                       class="w-16 p-1 text-xs text-right border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary">
                                <span class="text-gray-600 dark:text-gray-400 text-xs">QAR</span>
                            </div>
                        </div>
                    </div>
                </div>

                <template x-if="orderType === 'delivery'">
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-gray-600 dark:text-gray-400">Delivery Fee:</span>
                        <span class="font-semibold text-gray-900 dark:text-white" x-text="'QAR ' + deliveryFee.toFixed(2)"></span>
                    </div>
                </template>

                <div class="flex justify-between text-lg font-bold border-t pt-2 mb-4">
                    <span class="text-gray-900 dark:text-white">TOTAL:</span>
                    <span class="text-primary text-xl" x-text="'QAR ' + total.toFixed(2)"></span>
                </div>

                <!-- Updated Action Buttons - Two Columns -->
                <div class="grid grid-cols-2 gap-2 mb-2">
                    <button @click="holdOrder()" :disabled="cart.length === 0"
                            class="p-3 bg-warning hover:bg-amber-600 text-white rounded-lg font-semibold transition-all disabled:opacity-50 text-sm">
                        <i class="fas fa-pause mr-1"></i>Hold (F5)
                    </button>
                    <button @click="printOrder()" :disabled="cart.length === 0"
                            class="p-3 bg-info hover:bg-blue-700 text-white rounded-lg font-semibold transition-all disabled:opacity-50 text-sm">
                        <i class="fas fa-print mr-1"></i>Print (F4)
                    </button>
                </div>
                <button @click="validateAndShowPayment()" :disabled="cart.length === 0"
                        class="w-full p-3 bg-primary hover:bg-blue-800 text-white rounded-lg font-semibold transition-all disabled:opacity-50">
                    <i class="fas fa-credit-card mr-2"></i>Checkout (Enter)
                </button>
            </div>
        </div>
    </div>

    <!-- Enhanced Add-ons Modal -->
    <div x-show="showAddonsModalDialog" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Add Item Add-ons</h3>
                    <button @click="closeAddonsModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <template x-if="currentAddonIndex >= 0">
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4" x-text="'Adding add-ons for: ' + cart[currentAddonIndex]?.name"></p>
                        
                        <!-- Free Text Add-on -->
                        <div class="mb-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                            <h4 class="text-sm font-semibold text-green-800 dark:text-green-200 mb-2">Custom Add-on</h4>
                            <input type="text" x-model="customAddonText" placeholder="Enter custom add-on (e.g., Extra spicy, No onions...)"
                                   class="w-full p-2 text-sm border border-green-300 dark:border-green-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-green-500 mb-2">
                            
                            <!-- Charge Toggle -->
                            <div class="flex items-center justify-between">
                                <label class="flex items-center space-x-2 cursor-pointer">
                                    <input type="checkbox" x-model="chargeForCustomAddon" class="text-green-600 focus:ring-green-500 rounded">
                                    <span class="text-sm text-green-700 dark:text-green-300 font-medium">Charge for Add-on</span>
                                </label>
                                
                                <template x-if="chargeForCustomAddon">
                                    <div class="flex items-center space-x-1">
                                        <span class="text-xs text-green-600">QAR</span>
                                        <input type="number" x-model="customAddonPrice" step="0.50" min="0" placeholder="5.00"
                                               class="w-16 p-1 text-sm text-right border border-green-300 dark:border-green-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-green-500">
                                    </div>
                                </template>
                            </div>
                            
                            <button @click="addCustomAddon()" :disabled="!customAddonText.trim()" 
                                    class="w-full mt-2 p-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white text-sm rounded font-medium transition-colors">
                                <i class="fas fa-plus mr-1"></i>Add Custom Add-on
                            </button>
                        </div>

                        <!-- Available Add-ons -->
                        <div class="mb-4">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Available Add-ons:</h4>
                            <div class="space-y-2 max-h-60 overflow-y-auto">
                                <template x-for="addon in availableAddons" :key="addon.id">
                                    <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-700 p-3 rounded border hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                        <div class="flex items-center space-x-3">
                                            <input type="checkbox" :id="'addon_' + addon.id" :value="addon.id" x-model="selectedAddons"
                                                   class="text-primary focus:ring-primary border-gray-300 rounded">
                                            <label :for="'addon_' + addon.id" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer" x-text="addon.name"></label>
                                        </div>
                                        <span class="text-sm font-medium text-primary" x-text="addon.price > 0 ? 'QAR ' + addon.price.toFixed(2) : 'FREE'"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Selected Add-ons Preview -->
                        <template x-if="selectedAddons.length > 0 || tempCustomAddons.length > 0">
                            <div class="mb-4">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Selected Add-ons:</h4>
                                <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded p-3">
                                    <!-- Standard Add-ons -->
                                    <template x-for="addonId in selectedAddons" :key="addonId">
                                        <div class="flex justify-between items-center text-sm text-purple-700 dark:text-purple-300">
                                            <span x-text="getAddonName(addonId)"></span>
                                            <span x-text="getAddonPrice(addonId) > 0 ? 'QAR ' + getAddonPrice(addonId).toFixed(2) : 'FREE'"></span>
                                        </div>
                                    </template>
                                    <!-- Custom Add-ons -->
                                    <template x-for="(customAddon, cIndex) in tempCustomAddons" :key="'custom_' + cIndex">
                                        <div class="flex justify-between items-center text-sm text-purple-700 dark:text-purple-300">
                                            <span x-text="customAddon.name"></span>
                                            <div class="flex items-center space-x-2">
                                                <span x-text="customAddon.price > 0 ? 'QAR ' + customAddon.price.toFixed(2) : 'FREE'"></span>
                                                <button @click="tempCustomAddons.splice(cIndex, 1)" class="text-red-500 hover:text-red-700">
                                                    <i class="fas fa-times text-xs"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <div class="flex space-x-3">
                    <button @click="closeAddonsModal()" 
                            class="flex-1 p-3 bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-300 rounded-lg font-semibold transition-colors">
                        Cancel
                    </button>
                    <button @click="saveAddons()" 
                            class="flex-1 p-3 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-semibold transition-all">
                        <i class="fas fa-save mr-2"></i>Save Add-ons
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quantity Input Modal -->
    <div x-show="showQuantityInput" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @keydown.window="handleQuantityModalKeydown($event)">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-sm w-full mx-4">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Set Quantity (F11)</h3>
                    <button @click="closeQuantityModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <template x-if="quantityEditIndex >= 0 && cart[quantityEditIndex]">
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2" x-text="'Product: ' + cart[quantityEditIndex]?.name"></p>
                        <p class="text-xs text-gray-500 dark:text-gray-500 mb-3" x-text="'Available Stock: ' + getItemStock(cart[quantityEditIndex]?.id)"></p>
                        
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Enter Quantity</label>
                        <input type="number" x-model="newQuantity" x-ref="quantityModalInput" min="1" 
                               :max="getItemStock(cart[quantityEditIndex]?.id)"
                               class="w-full p-3 text-lg font-bold text-center border-2 border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>
                </template>

                <div class="flex space-x-3">
                    <button @click="closeQuantityModal()" 
                            class="flex-1 p-3 bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-300 rounded-lg font-semibold transition-colors">
                        Cancel (ESC)
                    </button>
                    <button @click="updateItemQuantity()" 
                            class="flex-1 p-3 bg-primary hover:bg-blue-800 text-white rounded-lg font-semibold transition-all">
                        <i class="fas fa-check mr-2"></i>Update (Enter)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Note Modal -->
    <div x-show="showNoteModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Add Item Note</h3>
                    <button @click="closeNoteModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <template x-if="noteEditIndex >= 0 && cart[noteEditIndex]">
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3" x-text="'Adding note for: ' + cart[noteEditIndex]?.name"></p>
                        
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Special Instructions</label>
                        <textarea x-model="newNote" rows="4" placeholder="Enter special instructions or notes for this item..." x-ref="noteTextarea"
                                  class="w-full p-3 text-base border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary resize-none"></textarea>
                        <p class="text-xs text-gray-500 mt-1">Examples: No onions, Extra spicy, Well done, etc.</p>
                    </div>
                </template>

                <div class="flex space-x-3">
                    <button @click="closeNoteModal()" 
                            class="flex-1 p-3 bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-300 rounded-lg font-semibold transition-colors">
                        Cancel
                    </button>
                    <button @click="saveItemNote()" 
                            class="flex-1 p-3 bg-primary hover:bg-blue-800 text-white rounded-lg font-semibold transition-all">
                        <i class="fas fa-save mr-2"></i>Save Note
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div x-show="showPayment" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @keydown.window="handlePaymentModalKeydown($event)">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Payment Processing</h3>
                    <button @click="showPayment = false" class="text-gray-500 hover:text-gray-700 dark:text-gray-400">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>

                <div class="mb-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border">
                    <p class="text-2xl font-bold text-primary text-center" x-text="'Total: QAR ' + total.toFixed(2)"></p>
                    <div class="text-center text-sm text-gray-600 dark:text-gray-400 mt-1">
                        <span x-text="totalItems + ' items, ' + totalQuantity + ' qty'"></span>
                        <span x-text="' • ' + getOrderTypeLabel()"></span>
                        <template x-if="orderSerial">
                            <span x-text="' • ' + orderSerial"></span>
                        </template>
                    </div>
                </div>

                <!-- Payment Methods with Keyboard Shortcuts -->
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Payment Method</label>
                    
                    <div class="grid grid-cols-2 gap-2">
                        <button @click="selectPaymentMethod('cash')" 
                                :class="paymentMethod === 'cash' ? 'bg-primary text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200'"
                                class="p-3 rounded-lg text-center transition-all">
                            <i class="fas fa-money-bill-wave block mb-1"></i>
                            <span class="text-xs">Cash (F10)</span>
                        </button>
                        <button @click="selectPaymentMethod('card')" 
                                :class="paymentMethod === 'card' ? 'bg-primary text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200'"
                                class="p-3 rounded-lg text-center transition-all">
                            <i class="fas fa-credit-card block mb-1"></i>
                            <span class="text-xs">Card (F9)</span>
                        </button>
                        <button @click="selectPaymentMethod('credit')" 
                                :class="paymentMethod === 'credit' ? 'bg-primary text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200'"
                                class="p-3 rounded-lg text-center transition-all">
                            <i class="fas fa-file-invoice-dollar block mb-1"></i>
                            <span class="text-xs">Credit (F8)</span>
                        </button>
                        <button @click="selectPaymentMethod('foc')" 
                                :class="paymentMethod === 'foc' ? 'bg-primary text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200'"
                                class="p-3 rounded-lg text-center transition-all">
                            <i class="fas fa-gift block mb-1"></i>
                            <span class="text-xs">FOC (F7)</span>
                        </button>
                    </div>
                </div>

                <!-- Cash Amount Input -->
                <template x-if="paymentMethod === 'cash'">
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Amount Received (QAR)</label>
                        <input type="number" x-model="amountReceived" @input="calculateChange()" 
                               x-ref="amountInput" step="0.01" 
                               class="w-full p-4 text-xl font-bold text-center border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                        <template x-if="change >= 0">
                            <p class="mt-2 text-lg font-bold text-success text-center" x-text="'Change: QAR ' + change.toFixed(2)"></p>
                        </template>
                    </div>
                </template>

                <!-- FOC Reason Input -->
                <template x-if="paymentMethod === 'foc'">
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">FOC Reason *</label>
                        <textarea x-model="focReason" rows="3" placeholder="Enter reason for free of charge..." x-ref="focReasonInput"
                                  class="w-full p-3 text-base border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white resize-none focus:ring-2 focus:ring-primary"></textarea>
                        <p class="text-xs text-gray-500 mt-1">Please provide a reason for free of charge order</p>
                    </div>
                </template>

                <!-- Credit Customer Validation -->
                <template x-if="paymentMethod === 'credit'">
                    <div class="mb-4">
                        <div :class="selectedCustomer ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200'" class="p-3 rounded-lg">
                            <div class="flex items-center">
                                <i :class="selectedCustomer ? 'fas fa-check-circle text-green-600' : 'fas fa-exclamation-triangle text-yellow-600'" class="mr-2"></i>
                                <span :class="selectedCustomer ? 'text-green-800' : 'text-yellow-800'" class="text-sm font-medium" x-text="selectedCustomer ? 'Customer selected for credit payment' : 'Please select a customer from customer details for credit payment'"></span>
                            </div>
                            <template x-if="selectedCustomer">
                                <p class="text-xs text-green-600 mt-1" x-text="'Customer: ' + getCustomerName(selectedCustomer)"></p>
                            </template>
                        </div>
                    </div>
                </template>

                <div class="flex space-x-3">
                    <button @click="showPayment = false" class="flex-1 p-3 bg-gray-300 hover:bg-gray-400 text-gray-700 rounded-lg font-semibold">Cancel (ESC)</button>
                    <button @click="processPayment()" :disabled="!canProcessPayment()" 
                            class="flex-1 p-3 bg-success hover:bg-green-700 text-white rounded-lg font-semibold disabled:opacity-50">
                        Complete Payment (Enter)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Held Orders Modal -->
    <div x-show="showHeldOrdersModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Held Orders</h3>
                    <button @click="showHeldOrdersModal = false" class="text-gray-500 hover:text-gray-700 dark:text-gray-400">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>

                <template x-if="heldOrders.length === 0">
                    <div class="text-center py-8">
                        <i class="fas fa-pause text-3xl text-gray-300 dark:text-gray-600 mb-3"></i>
                        <p class="text-gray-500 dark:text-gray-400 text-sm">No held orders</p>
                    </div>
                </template>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <template x-for="(order, index) in heldOrders" :key="order.id">
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h4 class="font-semibold text-gray-900 dark:text-white" x-text="order.order_number"></h4>
                                    <p class="text-xs text-gray-500" x-text="formatDateTime(order.held_at)"></p>
                                </div>
                                <div class="text-right">
                                    <span class="text-lg font-bold text-primary" x-text="'QAR ' + parseFloat(order.total).toFixed(2)"></span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <template x-for="item in order.items" :key="item.id">
                                    <div class="flex justify-between text-sm mb-1">
                                        <span x-text="item.quantity + 'x ' + item.name"></span>
                                        <span x-text="'QAR ' + (item.quantity * item.sell_price).toFixed(2)"></span>
                                    </div>
                                </template>
                            </div>
                            
                            <div class="flex space-x-2">
                                <button @click="resumeHeldOrder(order.id)" 
                                        class="flex-1 bg-green-500 hover:bg-green-600 text-white text-sm py-2 px-3 rounded font-medium">
                                    Resume
                                </button>
                                <button @click="deleteHeldOrder(order.id, index)" 
                                        class="bg-red-500 hover:bg-red-600 text-white text-sm py-2 px-3 rounded">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Order Modal -->
    <div x-show="showPrintModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Print Order Confirmation</h3>
                    <button @click="showPrintModal = false" class="text-gray-500 hover:text-gray-700 dark:text-gray-400">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Print order details for kitchen/preparation?</p>
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded p-3">
                        <div class="text-sm">
                            <div class="flex justify-between mb-1">
                                <span class="font-medium">Order:</span>
                                <span x-text="orderSerial"></span>
                            </div>
                            <div class="flex justify-between mb-1">
                                <span class="font-medium">Type:</span>
                                <span x-text="getOrderTypeLabel()"></span>
                            </div>
                            <div class="flex justify-between mb-1">
                                <span class="font-medium">Items:</span>
                                <span x-text="totalItems + ' items (' + totalQuantity + ' qty)'"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Total:</span>
                                <span class="font-bold text-primary" x-text="'QAR ' + total.toFixed(2)"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex space-x-3">
                    <button @click="showPrintModal = false" 
                            class="flex-1 p-3 bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-300 rounded-lg font-semibold transition-colors">
                        Cancel
                    </button>
                    <button @click="confirmPrintOrder()" 
                            class="flex-1 p-3 bg-info hover:bg-blue-700 text-white rounded-lg font-semibold transition-all">
                        <i class="fas fa-print mr-2"></i>Print Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Confirmation Modal -->
    <div x-show="showConfirmModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-sm w-full mx-4">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <i class="fas fa-exclamation-triangle text-yellow-500 text-2xl mr-3"></i>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Confirm Action</h3>
                </div>
                <p class="text-gray-600 dark:text-gray-400 mb-6" x-text="confirmMessage"></p>
                <div class="flex space-x-3">
                    <button @click="closeConfirmModal()" 
                            class="flex-1 p-3 bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-300 rounded-lg font-semibold transition-colors">
                        Cancel
                    </button>
                    <button @click="executeConfirmAction()" 
                            class="flex-1 p-3 bg-danger hover:bg-red-700 text-white rounded-lg font-semibold transition-all">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Beep Audio Element -->
    <audio id="beepAudio" preload="auto">
        <source src="../assets/sounds/beep.mp3" type="audio/mpeg">
        <source src="../assets/sounds/beep.wav" type="audio/wav">
        <source src="../assets/sounds/beep.ogg" type="audio/ogg">
    </audio>

    <script>
        function posSystem() {
            return {
                // Data
                products: <?php echo json_encode($products); ?>,
                categories: <?php echo json_encode($categories); ?>,
                customers: <?php echo json_encode($customers); ?>,
                availableAddons: <?php echo json_encode($addons); ?>,
                deliveryFee: <?php echo $deliveryFee; ?>,
                orderSerial: '<?php echo $orderSerial; ?>',
                currentSaleCount: <?php echo $dailyCount + 1; ?>,
                autoPrint: <?php echo $autoPrint; ?> === '1',
                
                // UI State
                selectedCategory: '',
                searchTerm: '',
                customerSearch: '',
                selectedCustomer: '',
                orderType: 'dine-in',
                tableNumber: '',
                selectedCartIndex: -1,
                
                // Cart
                cart: [],
                subtotal: 0,
                discount: 0,
                total: 0,
                
                // Computed values
                get totalItems() {
                    return this.cart.length;
                },
                get totalQuantity() {
                    return this.cart.reduce((sum, item) => sum + item.quantity, 0);
                },
                
                // Delivery Info
                deliveryInfo: {
                    customerName: '',
                    phone: '',
                    address: ''
                },
                
                // Payment
                showPayment: false,
                paymentMethod: 'cash',
                amountReceived: 0,
                change: 0,
                focReason: '',
                
                // Modals - Fixed naming conflicts
                showQuantityInput: false,
                quantityEditIndex: -1,
                newQuantity: 1,
                showNoteModal: false,
                noteEditIndex: -1,
                newNote: '',
                showAddonsModalDialog: false,
                currentAddonIndex: -1,
                selectedAddons: [],
                
                // Enhanced Add-ons
                customAddonText: '',
                chargeForCustomAddon: false,
                customAddonPrice: 0,
                tempCustomAddons: [],
                
                // Held Orders
                showHeldOrdersModal: false,
                heldOrders: [],
                
                // Print Modal
                showPrintModal: false,
                
                // Custom Confirmation Modal
                showConfirmModal: false,
                confirmMessage: '',
                confirmAction: null,
                
                // Time
                currentTime: '',
                currentDate: '',

                init() {
                    this.filteredCustomers = this.customers;
                    this.calculateTotal();
                    this.updateDateTime();
                    this.loadHeldOrders();
                    
                    setInterval(() => {
                        this.updateDateTime();
                    }, 1000);
                    
                    // Generate new order serial on init
                    this.generateNewOrderSerial();
                    
                    // Setup beep audio
                    this.setupBeepAudio();
                },

                // Fixed: Simplified beep audio setup
                setupBeepAudio() {
                    this.beepAudio = document.getElementById('beepAudio');
                    if (this.beepAudio) {
                        this.beepAudio.volume = 0.5;
                        this.beepAudio.load(); // Preload the audio
                    }
                },

                // Fixed: Simplified beep sound function
                playBeepSound() {
                    try {
                        if (this.beepAudio) {
                            this.beepAudio.currentTime = 0; // Reset to start
                            const playPromise = this.beepAudio.play();
                            
                            if (playPromise !== undefined) {
                                playPromise.catch(error => {
                                    console.log('Beep sound failed to play:', error);
                                });
                            }
                        }
                    } catch (error) {
                        console.log('Beep sound not available:', error);
                    }
                },

                generateNewOrderSerial() {
                    const month = new Date().getMonth() + 1;
                    const day = new Date().getDate();
                    const paddedMonth = month.toString().padStart(2, '0');
                    const paddedDay = day.toString().padStart(2, '0');
                    const serialNum = this.currentSaleCount.toString().padStart(2, '0');
                    this.orderSerial = `LMR-${paddedMonth}${paddedDay}${serialNum}`;
                },

                formatDateTime(dateString) {
                    return new Date(dateString).toLocaleString();
                },

                updateDateTime() {
                    const now = new Date();
                    this.currentTime = now.toLocaleTimeString('en-US', { 
                        hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit'
                    });
                    this.currentDate = now.toLocaleDateString('en-US', { 
                        weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' 
                    });
                },

                getCategoryName() {
                    if (!this.selectedCategory) return 'All Items';
                    const category = this.categories.find(c => c.id == this.selectedCategory);
                    return category ? category.name : 'All Items';
                },

                getCategoryCount(categoryId) {
                    if (!categoryId) return this.products.length;
                    return this.products.filter(p => p.category_id == categoryId).length;
                },

                get filteredProducts() {
                    let filtered = this.products;
                    
                    if (this.selectedCategory) {
                        filtered = filtered.filter(p => p.category_id == this.selectedCategory);
                    }
                    
                    if (this.searchTerm) {
                        const search = this.searchTerm.toLowerCase();
                        filtered = filtered.filter(p => 
                            p.name.toLowerCase().includes(search) ||
                            (p.name_ar && p.name_ar.toLowerCase().includes(search)) ||
                            (p.code && p.code.toLowerCase().includes(search))
                        );
                    }
                    
                    return filtered;
                },

                getItemTotal(item) {
                    const addonsTotal = item.addons ? item.addons.reduce((sum, addon) => sum + parseFloat(addon.price), 0) : 0;
                    return (parseFloat(item.sell_price) + addonsTotal) * item.quantity;
                },

                selectCartItem(index) {
                    this.selectedCartIndex = this.selectedCartIndex === index ? -1 : index;
                },

                addToCart(product) {
                    if (product.quantity <= 0) {
                        this.showAlert('Item is out of stock', 'error');
                        return;
                    }

                    const existingItem = this.cart.find(item => 
                        item.id === product.id && 
                        (!item.addons || item.addons.length === 0) &&
                        (!item.notes || item.notes === '')
                    );

                    if (existingItem) {
                        if (existingItem.quantity < product.quantity) {
                            existingItem.quantity++;
                        } else {
                            this.showAlert('Insufficient stock', 'warning');
                            return;
                        }
                    } else {
                        this.cart.push({
                            id: product.id,
                            name: product.name,
                            name_ar: product.name_ar || '',
                            sell_price: product.sell_price,
                            quantity: 1,
                            stock: product.quantity,
                            notes: '',
                            addons: []
                        });
                    }
                    
                    // Play beep sound on every add to cart
                    this.playBeepSound();
                    
                    this.calculateTotal();
                    this.showAlert('Item added to cart', 'success');
                },

                calculateTotal() {
                    this.subtotal = this.cart.reduce((sum, item) => {
                        return sum + this.getItemTotal(item);
                    }, 0);
                    
                    const afterDiscount = Math.max(0, this.subtotal - this.discount);
                    const deliveryCharge = this.orderType === 'delivery' ? this.deliveryFee : 0;
                    this.total = afterDiscount + deliveryCharge;
                    this.change = this.amountReceived - this.total;
                },

                validateAndShowPayment() {
                    if (this.cart.length === 0) return;
                    
                    if (this.orderType === 'delivery') {
                        if (!this.deliveryInfo.customerName || !this.deliveryInfo.phone || !this.deliveryInfo.address) {
                            this.showAlert('Please fill in all delivery information', 'error');
                            return;
                        }
                    }
                    
                    this.showPayment = true;
                    this.amountReceived = this.total;
                    
                    this.$nextTick(() => {
                        if (this.$refs.amountInput) {
                            this.$refs.amountInput.focus();
                            this.$refs.amountInput.select();
                        }
                    });
                },

                selectPaymentMethod(method) {
                    this.paymentMethod = method;
                    if (method === 'cash') {
                        this.$nextTick(() => {
                            if (this.$refs.amountInput) {
                                this.$refs.amountInput.focus();
                                this.$refs.amountInput.select();
                            }
                        });
                    } else if (method === 'foc') {
                        this.$nextTick(() => {
                            if (this.$refs.focReasonInput) {
                                this.$refs.focReasonInput.focus();
                            }
                        });
                    }
                },

                calculateChange() {
                    this.change = this.amountReceived - this.total;
                },

                canProcessPayment() {
                    if (this.cart.length === 0) return false;
                    
                    if (this.paymentMethod === 'cash') {
                        return this.amountReceived >= this.total;
                    }
                    
                    if (this.paymentMethod === 'foc') {
                        return this.focReason.trim() !== '';
                    }
                    
                    if (this.paymentMethod === 'credit') {
                        return this.selectedCustomer !== '';
                    }
                    
                    return true;
                },

                getCustomerName(customerId) {
                    const customer = this.customers.find(c => c.id == customerId);
                    return customer ? customer.name : 'Unknown Customer';
                },

                async processPayment() {
                    if (!this.canProcessPayment()) {
                        if (this.paymentMethod === 'credit' && !this.selectedCustomer) {
                            this.showAlert('Please select a customer for credit payment', 'error');
                        } else if (this.paymentMethod === 'foc' && !this.focReason.trim()) {
                            this.showAlert('Please enter FOC reason', 'error');
                        }
                        return;
                    }
                    
                    // Fixed: Ensure proper data structure for Sale.php
                    const saleData = {
                        order_number: this.orderSerial,
                        order_type: this.getOrderTypeId(),
                        table_number: this.tableNumber || '',
                        customer_name: this.orderType === 'delivery' ? this.deliveryInfo.customerName : '',
                        customer_phone: this.orderType === 'delivery' ? this.deliveryInfo.phone : '',
                        customer_address: this.orderType === 'delivery' ? this.deliveryInfo.address : '',
                        subtotal: this.subtotal,
                        discount: this.discount,
                        delivery_fee: this.orderType === 'delivery' ? this.deliveryFee : 0,
                        total: this.total,
                        payment_method: this.getPaymentMethodId(),
                        amount_received: this.amountReceived,
                        change_amount: this.change,
                        notes: this.paymentMethod === 'foc' ? this.focReason : ''
                    };
                    
                    const saleItems = this.cart.map(item => ({
                        product_id: item.id,
                        product_name: item.name,
                        product_name_ar: item.name_ar,
                        quantity: item.quantity,
                        unit_price: parseFloat(item.sell_price),
                        total_price: this.getItemTotal(item),
                        notes: item.notes || ''
                    }));
                    
                    try {
                        const response = await fetch('../api/sales.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                action: 'create_sale',
                                sale_data: saleData,
                                sale_items: saleItems
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            this.showAlert('Payment processed successfully!', 'success');
                            
                            // Auto-print receipt if enabled
                            if (this.autoPrint) {
                                this.printReceipt(result);
                            }
                            
                            this.newOrder();
                        } else {
                            this.showAlert(result.message || 'Failed to process payment', 'error');
                        }
                    } catch (error) {
                        this.showAlert('Network error occurred', 'error');
                        console.error('Payment processing error:', error);
                    }
                    
                    this.showPayment = false;
                },

                printReceipt(saleResult) {
                    // Fixed: Auto-print without confirmation
                    const receiptWindow = window.open('', '_blank', 'width=400,height=600');
                    const receiptContent = this.generateReceiptHTML(saleResult);
                    receiptWindow.document.write(receiptContent);
                    receiptWindow.document.close();
                    receiptWindow.print();
                },

                generateReceiptHTML(saleResult) {
                    const now = new Date();
                    let html = `
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Receipt - ${saleResult.receipt_number}</title>
                            <style>
                                @media print { @page { size: 80mm auto; margin: 0; } }
                                body { 
                                    font-family: 'Courier New', monospace; 
                                    font-size: 11px; 
                                    line-height: 1.2; 
                                    width: 76mm; 
                                    margin: 0; 
                                    padding: 2mm; 
                                }
                                .center { text-align: center; }
                                .right { text-align: right; }
                                .bold { font-weight: bold; }
                                hr { border: none; border-top: 1px dashed #000; margin: 3px 0; }
                                .total-line { border-top: 2px solid #000; padding-top: 5px; }
                            </style>
                        </head>
                        <body onload="window.print()">
                            <div class="center bold">LANGIT MINANG RESTAURANT</div>
                            <div class="center">Point of Sale Receipt</div>
                            <hr>
                            <div>Receipt: ${saleResult.receipt_number}</div>
                            <div>Order: ${this.orderSerial}</div>
                            <div>Date: ${now.toLocaleDateString()}</div>
                            <div>Time: ${now.toLocaleTimeString()}</div>
                            <div>Type: ${this.getOrderTypeLabel()}</div>
                            ${this.tableNumber ? `<div>Table: ${this.tableNumber}</div>` : ''}
                            <hr>
                    `;
                    
                    // Add items with Arabic names
                    this.cart.forEach(item => {
                        html += `
                            <div>${item.name}</div>
                        `;
                        // Fixed: Add Arabic name according to Qatar law
                        if (item.name_ar && item.name_ar.trim()) {
                            html += `<div style="font-size: 10px; color: #666;" dir="rtl">${item.name_ar}</div>`;
                        }
                        html += `
                            <div>${item.quantity} x ${parseFloat(item.sell_price).toFixed(2)} <span class="right">QAR ${this.getItemTotal(item).toFixed(2)}</span></div>
                        `;
                        if (item.notes) {
                            html += `<div style="font-size: 0.8em; font-style: italic;">Note: ${item.notes}</div>`;
                        }
                    });
                    
                    html += `
                            <hr>
                            <div>Subtotal: <span class="right">QAR ${this.subtotal.toFixed(2)}</span></div>
                    `;
                    
                    if (this.discount > 0) {
                        html += `<div>Discount: <span class="right">-QAR ${this.discount.toFixed(2)}</span></div>`;
                    }
                    
                    if (this.orderType === 'delivery') {
                        html += `<div>Delivery Fee: <span class="right">QAR ${this.deliveryFee.toFixed(2)}</span></div>`;
                    }
                    
                    html += `
                            <div class="total-line bold">TOTAL: <span class="right">QAR ${this.total.toFixed(2)}</span></div>
                            <div>Payment: ${this.getPaymentMethodLabel()}</div>
                    `;
                    
                    if (this.paymentMethod === 'cash') {
                        html += `
                            <div>Received: <span class="right">QAR ${this.amountReceived.toFixed(2)}</span></div>
                            <div>Change: <span class="right">QAR ${this.change.toFixed(2)}</span></div>
                        `;
                    }
                    
                    html += `
                            <hr>
                            <div class="center">Thank you for your business!</div>
                            <div class="center">Please come again</div>
                        </body>
                        </html>
                    `;
                    
                    return html;
                },

                getPaymentMethodLabel() {
                    const labels = {
                        'cash': 'Cash',
                        'card': 'Card',
                        'credit': 'Credit',
                        'foc': 'Free of Charge',
                        'cod': 'Cash on Delivery'
                    };
                    return labels[this.paymentMethod] || 'Cash';
                },

                getOrderTypeId() {
                    const types = { 'dine-in': 1, 'takeaway': 2, 'delivery': 3 };
                    return types[this.orderType] || 1;
                },

                getOrderTypeLabel() {
                    switch(this.orderType) {
                        case 'dine-in': return 'Dine-In';
                        case 'takeaway': return 'Take Away';
                        case 'delivery': return 'Delivery';
                        default: return 'Dine-In';
                    }
                },

                getPaymentMethodId() {
                    const methods = { 'cash': 1, 'card': 2, 'credit': 3, 'foc': 4, 'cod': 5 };
                    return methods[this.paymentMethod] || 1;
                },

                newOrder() {
                    this.cart = [];
                    this.discount = 0;
                    this.selectedCustomer = '';
                    this.amountReceived = 0;
                    this.change = 0;
                    this.paymentMethod = 'cash';
                    this.focReason = '';
                    this.tableNumber = '';
                    this.selectedCartIndex = -1;
                    this.deliveryInfo = { customerName: '', phone: '', address: '' };
                    this.currentSaleCount++;
                    this.generateNewOrderSerial();
                    this.calculateTotal();
                },

                removeFromCart(index) {
                    const item = this.cart[index];
                    this.cart.splice(index, 1);
                    this.selectedCartIndex = -1;
                    this.calculateTotal();
                    this.showAlert(item.name + ' removed from cart', 'info');
                },

                updateQuantity(index, newQuantity) {
                    if (newQuantity <= 0) {
                        this.removeFromCart(index);
                    } else {
                        const item = this.cart[index];
                        const menuItem = this.products.find(mi => mi.id === item.id);
                        if (newQuantity <= menuItem.quantity) {
                            item.quantity = newQuantity;
                        } else {
                            this.showAlert('Insufficient stock available', 'warning');
                            return;
                        }
                    }
                    this.calculateTotal();
                },

                showQuantityModal(index) {
                    this.quantityEditIndex = index;
                    this.newQuantity = this.cart[index].quantity;
                    this.showQuantityInput = true;
                    
                    this.$nextTick(() => {
                        if (this.$refs.quantityModalInput) {
                            this.$refs.quantityModalInput.focus();
                            this.$refs.quantityModalInput.select();
                        }
                    });
                },

                closeQuantityModal() {
                    this.showQuantityInput = false;
                    this.quantityEditIndex = -1;
                    this.newQuantity = 1;
                },

                updateItemQuantity() {
                    if (this.quantityEditIndex >= 0 && this.newQuantity > 0) {
                        const item = this.cart[this.quantityEditIndex];
                        const menuItem = this.products.find(mi => mi.id === item.id);
                        
                        if (this.newQuantity <= menuItem.quantity) {
                            item.quantity = this.newQuantity;
                            this.calculateTotal();
                            this.showAlert('Quantity updated', 'success');
                        } else {
                            this.showAlert('Insufficient stock available', 'warning');
                            return;
                        }
                    }
                    
                    this.closeQuantityModal();
                },

                getItemStock(itemId) {
                    const menuItem = this.products.find(item => item.id === itemId);
                    return menuItem ? menuItem.quantity : 0;
                },

                // Custom confirmation modal functions
                showClearCartConfirmation() {
                    if (this.cart.length === 0) return;
                    this.showConfirmDialog('Are you sure you want to clear all items from cart?', () => {
                        this.cart = [];
                        this.discount = 0;
                        this.selectedCartIndex = -1;
                        this.calculateTotal();
                        this.showAlert('Cart cleared', 'info');
                    });
                },

                showConfirmDialog(message, action) {
                    this.confirmMessage = message;
                    this.confirmAction = action;
                    this.showConfirmModal = true;
                },

                executeConfirmAction() {
                    if (this.confirmAction) {
                        this.confirmAction();
                    }
                    this.closeConfirmModal();
                },

                closeConfirmModal() {
                    this.showConfirmModal = false;
                    this.confirmMessage = '';
                    this.confirmAction = null;
                },

                // Fixed: Hold order to use database
                async holdOrder() {
                    if (this.cart.length === 0) return;

                    const orderData = {
                        order_number: this.orderSerial,
                        order_type: this.getOrderTypeId(),
                        table_number: this.tableNumber,
                        customer_name: this.orderType === 'delivery' ? this.deliveryInfo.customerName : '',
                        customer_phone: this.orderType === 'delivery' ? this.deliveryInfo.phone : '',
                        customer_address: this.orderType === 'delivery' ? this.deliveryInfo.address : '',
                        subtotal: this.subtotal,
                        discount: this.discount,
                        total: this.total,
                        items: this.cart
                    };

                    try {
                        const response = await fetch('../api/sales.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                action: 'hold_order',
                                order_data: orderData
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            this.showAlert('Order held successfully', 'success');
                            this.newOrder();
                            this.loadHeldOrders(); // Refresh held orders
                        } else {
                            this.showAlert(result.message || 'Failed to hold order', 'error');
                        }
                    } catch (error) {
                        this.showAlert('Network error occurred', 'error');
                    }
                },

                // Print Order functionality
                printOrder() {
                    if (this.cart.length === 0) return;
                    this.showPrintModal = true;
                },

                confirmPrintOrder() {
                    const printWindow = window.open('', '_blank');
                    const printContent = this.generateKitchenOrderHTML();
                    printWindow.document.write(printContent);
                    printWindow.document.close();
                    printWindow.print();
                    
                    this.showPrintModal = false;
                    this.showAlert('Order sent to kitchen printer', 'success');
                },

                generateKitchenOrderHTML() {
                    const now = new Date();
                    let html = `
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Kitchen Order - ${this.orderSerial}</title>
                            <style>
                                body { font-family: monospace; width: 300px; margin: 0; padding: 10px; }
                                .center { text-align: center; }
                                .bold { font-weight: bold; font-size: 1.2em; }
                                hr { border: none; border-top: 1px dashed #000; margin: 10px 0; }
                                .order-type { background: #000; color: #fff; padding: 5px; margin: 5px 0; }
                                .item { margin: 5px 0; }
                                .notes { font-style: italic; font-size: 0.9em; margin-left: 10px; }
                            </style>
                        </head>
                        <body onload="window.print()">
                            <div class="center bold">KITCHEN ORDER</div>
                            <hr>
                            <div class="bold">Order: ${this.orderSerial}</div>
                            <div>Time: ${now.toLocaleTimeString()}</div>
                            <div class="order-type center">${this.getOrderTypeLabel().toUpperCase()}</div>
                    `;

                    if (this.tableNumber) {
                        html += `<div class="bold">TABLE: ${this.tableNumber}</div>`;
                    }

                    if (this.orderType === 'delivery') {
                        html += `
                            <div>Customer: ${this.deliveryInfo.customerName}</div>
                            <div>Phone: ${this.deliveryInfo.phone}</div>
                        `;
                    }

                    html += `<hr>`;

                    // Add items with Arabic names
                    this.cart.forEach(item => {
                        html += `
                            <div class="item">
                                <div class="bold">${item.quantity}x ${item.name}</div>
                        `;
                        if (item.name_ar) {
                            html += `<div dir="rtl">${item.name_ar}</div>`;
                        }
                        if (item.notes) {
                            html += `<div class="notes">*** ${item.notes} ***</div>`;
                        }
                        if (item.addons && item.addons.length > 0) {
                            item.addons.forEach(addon => {
                                html += `<div class="notes">+ ${addon.name}</div>`;
                            });
                        }
                        html += `</div>`;
                    });

                    html += `
                            <hr>
                            <div class="center">Total Items: ${this.totalQuantity}</div>
                            <div class="center">TOTAL: QAR ${this.total.toFixed(2)}</div>
                        </body>
                        </html>
                    `;

                    return html;
                },

                // Fixed: Load held orders from database
                async loadHeldOrders() {
                    try {
                        const response = await fetch('../api/sales.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                action: 'get_held_orders'
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            this.heldOrders = result.data;
                        }
                    } catch (error) {
                        console.error('Failed to load held orders:', error);
                    }
                },

                // Fixed: Resume held order from database
                async resumeHeldOrder(heldId) {
                    try {
                        const response = await fetch('../api/sales.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                action: 'resume_held_order',
                                held_id: heldId
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            const order = result.order;
                            
                            // Restore order data
                            this.cart = order.items || [];
                            this.orderSerial = order.order_number;
                            this.orderType = this.getOrderTypeFromId(order.order_type);
                            this.tableNumber = order.table_number || '';
                            this.subtotal = parseFloat(order.subtotal);
                            this.discount = parseFloat(order.discount);
                            this.total = parseFloat(order.total);
                            
                            if (order.order_type === 3) { // Delivery
                                this.deliveryInfo = {
                                    customerName: order.customer_name || '',
                                    phone: order.customer_phone || '',
                                    address: order.customer_address || ''
                                };
                            }
                            
                            this.calculateTotal();
                            this.showHeldOrdersModal = false;
                            this.loadHeldOrders(); // Refresh held orders
                            this.showAlert('Order resumed successfully', 'success');
                        } else {
                            this.showAlert(result.message || 'Failed to resume order', 'error');
                        }
                    } catch (error) {
                        this.showAlert('Network error occurred', 'error');
                    }
                },

                // Fixed: Delete held order from database
                async deleteHeldOrder(heldId, index) {
                    this.showConfirmDialog('Are you sure you want to delete this held order?', async () => {
                        try {
                            const response = await fetch('../api/sales.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    action: 'delete_held_order',
                                    held_id: heldId
                                })
                            });
                            
                            const result = await response.json();
                            
                            if (result.success) {
                                this.heldOrders.splice(index, 1);
                                this.showAlert('Held order deleted', 'success');
                            } else {
                                this.showAlert(result.message || 'Failed to delete held order', 'error');
                            }
                        } catch (error) {
                            this.showAlert('Network error occurred', 'error');
                        }
                    });
                },

                getOrderTypeFromId(orderTypeId) {
                    const types = { 1: 'dine-in', 2: 'takeaway', 3: 'delivery' };
                    return types[orderTypeId] || 'dine-in';
                },

                searchCustomers() {
                    // Customer search implementation
                },

                loadCustomerForDelivery() {
                    if (this.selectedCustomer && this.orderType === 'delivery') {
                        const customer = this.customers.find(c => c.id == this.selectedCustomer);
                        if (customer) {
                            this.deliveryInfo.customerName = customer.name;
                            this.deliveryInfo.phone = customer.phone;
                            this.deliveryInfo.address = customer.address || '';
                        }
                    }
                },

                // Note functionality
                addItemNote(index) {
                    this.noteEditIndex = index;
                    this.newNote = this.cart[index].notes || '';
                    this.showNoteModal = true;
                    
                    this.$nextTick(() => {
                        if (this.$refs.noteTextarea) {
                            this.$refs.noteTextarea.focus();
                        }
                    });
                },

                editItemNote(index) {
                    this.addItemNote(index);
                },

                closeNoteModal() {
                    this.showNoteModal = false;
                    this.noteEditIndex = -1;
                    this.newNote = '';
                },

                saveItemNote() {
                    if (this.noteEditIndex >= 0) {
                        this.cart[this.noteEditIndex].notes = this.newNote.trim();
                        this.showAlert(this.newNote.trim() ? 'Note saved' : 'Note removed', 'success');
                    }
                    this.closeNoteModal();
                },

                // Enhanced Add-ons functionality
                openAddonsModal(index) {
                    this.currentAddonIndex = index;
                    const currentAddons = this.cart[index].addons || [];
                    this.selectedAddons = currentAddons.filter(addon => addon.id).map(addon => addon.id);
                    this.tempCustomAddons = currentAddons.filter(addon => !addon.id);
                    this.customAddonText = '';
                    this.chargeForCustomAddon = false;
                    this.customAddonPrice = 0;
                    this.showAddonsModalDialog = true;
                },

                closeAddonsModal() {
                    this.showAddonsModalDialog = false;
                    this.currentAddonIndex = -1;
                    this.selectedAddons = [];
                    this.tempCustomAddons = [];
                    this.customAddonText = '';
                    this.chargeForCustomAddon = false;
                    this.customAddonPrice = 0;
                },

                addCustomAddon() {
                    if (!this.customAddonText.trim()) return;
                    
                    const customAddon = {
                        name: this.customAddonText.trim(),
                        price: this.chargeForCustomAddon ? parseFloat(this.customAddonPrice) || 0 : 0
                    };
                    
                    this.tempCustomAddons.push(customAddon);
                    this.customAddonText = '';
                    this.chargeForCustomAddon = false;
                    this.customAddonPrice = 0;
                },

                getAddonName(addonId) {
                    const addon = this.availableAddons.find(a => a.id == addonId);
                    return addon ? addon.name : 'Unknown Add-on';
                },

                getAddonPrice(addonId) {
                    const addon = this.availableAddons.find(a => a.id == addonId);
                    return addon ? parseFloat(addon.price) : 0;
                },

                saveAddons() {
                    if (this.currentAddonIndex >= 0) {
                        // Combine standard and custom add-ons
                        const standardAddons = this.selectedAddons.map(addonId => {
                            const addon = this.availableAddons.find(a => a.id == addonId);
                            return {
                                id: addon.id,
                                name: addon.name,
                                price: parseFloat(addon.price)
                            };
                        });
                        
                        const allAddons = [...standardAddons, ...this.tempCustomAddons];
                        
                        this.cart[this.currentAddonIndex].addons = allAddons;
                        this.calculateTotal();
                        this.showAlert('Add-ons saved', 'success');
                    }
                    this.closeAddonsModal();
                },

                // Fixed: Enhanced keyboard shortcuts
                handleKeydown(event) {
                    // Handle modals first
                    if (this.showQuantityInput) {
                        this.handleQuantityModalKeydown(event);
                        return;
                    }

                    if (this.showPayment) {
                        this.handlePaymentModalKeydown(event);
                        return;
                    }

                    // Function keys for payment methods - Fixed: Only open payment modal, don't process
                    if (event.key === 'F7') {
                        event.preventDefault();
                        if (this.cart.length > 0) {
                            this.paymentMethod = 'foc';
                            this.validateAndShowPayment();
                        }
                    } else if (event.key === 'F8') {
                        event.preventDefault();
                        if (this.cart.length > 0) {
                            this.paymentMethod = 'credit';
                            this.validateAndShowPayment();
                        }
                    } else if (event.key === 'F9') {
                        event.preventDefault();
                        if (this.cart.length > 0) {
                            this.paymentMethod = 'card';
                            this.validateAndShowPayment();
                        }
                    } else if (event.key === 'F10') {
                        event.preventDefault();
                        if (this.cart.length > 0) {
                            this.paymentMethod = 'cash';
                            this.validateAndShowPayment();
                        }
                    } else if (event.key === 'F11') {
                        event.preventDefault();
                        if (this.selectedCartIndex >= 0) {
                            this.showQuantityModal(this.selectedCartIndex);
                        } else if (this.cart.length > 0) {
                            this.showQuantityModal(this.cart.length - 1);
                        }
                    }
                    // Other function keys
                    else if (event.key === 'F4') {
                        event.preventDefault();
                        this.printOrder();
                    } else if (event.key === 'F5') {
                        event.preventDefault();
                        this.holdOrder();
                    }
                    // Plus/Minus for quantity
                    else if (event.key === '+' || event.key === '=') {
                        event.preventDefault();
                        if (this.selectedCartIndex >= 0) {
                            this.updateQuantity(this.selectedCartIndex, this.cart[this.selectedCartIndex].quantity + 1);
                        } else if (this.cart.length > 0) {
                            const lastIndex = this.cart.length - 1;
                            this.updateQuantity(lastIndex, this.cart[lastIndex].quantity + 1);
                        }
                    } else if (event.key === '-') {
                        event.preventDefault();
                        if (this.selectedCartIndex >= 0) {
                            this.updateQuantity(this.selectedCartIndex, this.cart[this.selectedCartIndex].quantity - 1);
                        } else if (this.cart.length > 0) {
                            const lastIndex = this.cart.length - 1;
                            this.updateQuantity(lastIndex, this.cart[lastIndex].quantity - 1);
                        }
                    }
                    // Fixed: Enter should open payment modal, not complete payment
                    else if (event.key === 'Enter' && this.cart.length > 0) {
                        event.preventDefault();
                        this.validateAndShowPayment(); // This opens the payment modal
                    } else if (event.key === 'F3') {
                        event.preventDefault();
                        this.$refs.discountInput?.focus();
                    } else if (event.ctrlKey && event.key === 't') {
                        event.preventDefault();
                        this.$refs.tableInput?.focus();
                    } else if (event.ctrlKey && event.shiftKey && event.key === 'L') {
                        event.preventDefault();
                        window.location.href = 'shift-close.php';
                    } else if (event.key === 'Delete') {
                        event.preventDefault();
                        if (event.ctrlKey) {
                            this.showClearCartConfirmation();
                        } else if (this.selectedCartIndex >= 0) {
                            this.removeFromCart(this.selectedCartIndex);
                        } else if (this.cart.length > 0) {
                            const lastIndex = this.cart.length - 1;
                            this.removeFromCart(lastIndex);
                        }
                    }
                },

                handleQuantityModalKeydown(event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        this.updateItemQuantity();
                    } else if (event.key === 'Escape') {
                        event.preventDefault();
                        this.closeQuantityModal();
                    }
                },

                handlePaymentModalKeydown(event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        this.processPayment();
                    } else if (event.key === 'Escape') {
                        event.preventDefault();
                        this.showPayment = false;
                    } else if (event.key === ' ' && this.paymentMethod === 'cash') {
                        event.preventDefault();
                        this.amountReceived = this.total;
                        this.calculateChange();
                    }
                    // Payment method shortcuts within payment modal
                    else if (event.key === 'F7') {
                        event.preventDefault();
                        this.selectPaymentMethod('foc');
                    } else if (event.key === 'F8') {
                        event.preventDefault();
                        this.selectPaymentMethod('credit');
                    } else if (event.key === 'F9') {
                        event.preventDefault();
                        this.selectPaymentMethod('card');
                    } else if (event.key === 'F10') {
                        event.preventDefault();
                        this.selectPaymentMethod('cash');
                    }
                },

                showAlert(message, type = 'info') {
                    const colors = {
                        success: 'bg-success',
                        error: 'bg-danger',
                        warning: 'bg-warning',
                        info: 'bg-info'
                    };
                    
                    const alertDiv = document.createElement('div');
                    alertDiv.className = `fixed top-20 right-4 ${colors[type]} text-white px-4 py-2 rounded-lg shadow-lg z-50 text-sm font-medium animate-fade-in`;
                    alertDiv.textContent = message;
                    document.body.appendChild(alertDiv);
                    
                    setTimeout(() => {
                        alertDiv.remove();
                    }, 3000);
                }
            }
        }
    </script>

    <style>
        [x-cloak] { display: none !important; }
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .animate-fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</body>
</html>