<?php
/**
 * Enhanced Mobile POS Interface
 * Advanced POS interface optimized for mobile devices and tablets
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Enhanced Mobile POS - Langit Minang Restaurant</title>
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
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="../assets/manifest.json">
    <meta name="theme-color" content="#5D5CDE">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Mobile POS">
    <link rel="apple-touch-icon" href="../assets/icons/icon-192x192.png">
    
    <style>
        [x-cloak] { display: none !important; }
        
        /* Mobile optimizations */
        * {
            -webkit-tap-highlight-color: transparent;
        }
        
        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }
        
        /* Better touch targets */
        button, input, select, textarea {
            min-height: 44px;
        }
        
        /* Animations */
        .animate-fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-scale {
            transition: transform 0.1s ease-in-out;
        }
        
        .animate-scale:active {
            transform: scale(0.95);
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 4px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 2px;
        }
        
        /* Category pills scroll */
        .category-scroll {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        
        .category-scroll::-webkit-scrollbar {
            display: none;
        }
    </style>
</head>
<body class="bg-gray-50 overflow-hidden select-none" x-data="enhancedMobilePOS()" @touchstart.passive @touchend.passive>
    
    <!-- Enhanced Mobile Header -->
    <header class="bg-gradient-to-r from-primary to-purple-600 shadow-lg h-16 fixed top-0 left-0 right-0 z-40">
        <div class="flex items-center justify-between px-4 h-full">
            <div class="flex items-center space-x-3">
                <button @click="showMenu = !showMenu" class="text-white p-2 rounded-lg hover:bg-white/10 transition-colors">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <div>
                    <h1 class="text-lg font-bold text-white">Mobile POS</h1>
                    <p class="text-xs text-white/80" x-text="orderSerial"></p>
                </div>
            </div>
            
            <div class="flex items-center space-x-3">
                <button @click="loadHeldOrders(); showHeldOrdersModal = true" class="relative text-white p-2 rounded-lg hover:bg-white/10 transition-colors">
                    <i class="fas fa-pause text-lg"></i>
                    <template x-if="heldOrders.length > 0">
                        <span class="absolute -top-1 -right-1 bg-danger text-white text-xs w-5 h-5 rounded-full flex items-center justify-center font-bold" x-text="heldOrders.length"></span>
                    </template>
                </button>
                <button @click="showCart = !showCart" class="relative text-white p-2 rounded-lg hover:bg-white/10 transition-colors">
                    <i class="fas fa-shopping-cart text-lg"></i>
                    <template x-if="cart.length > 0">
                        <span class="absolute -top-1 -right-1 bg-danger text-white text-xs w-5 h-5 rounded-full flex items-center justify-center font-bold" x-text="totalQuantity"></span>
                    </template>
                </button>
                <div class="text-right">
                    <div class="text-white font-bold text-base" x-text="'QR ' + total.toFixed(2)"></div>
                    <div class="text-white/80 text-xs" x-text="cart.length + ' items'"></div>
                </div>
            </div>
        </div>
    </header>

    <!-- Category Pills - Below Header -->
    <div class="fixed top-16 left-0 right-0 bg-white shadow-sm z-30 px-4 py-3">
        <div class="flex space-x-2 overflow-x-auto category-scroll">
            <button @click="selectedCategory = ''" 
                    :class="selectedCategory === '' ? 'bg-primary text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap flex-shrink-0 transition-all animate-scale">
                <i class="fas fa-th-large mr-2"></i>All Items
                <span class="ml-1 text-xs opacity-75" x-text="'(' + products.length + ')'"></span>
            </button>
            <?php foreach ($categories as $category): ?>
            <button @click="selectedCategory = '<?php echo $category['id']; ?>'" 
                    :class="selectedCategory === '<?php echo $category['id']; ?>' ? 'bg-primary text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap flex-shrink-0 transition-all animate-scale">
                <i class="<?php echo $category['icon'] ?? 'fas fa-circle'; ?> mr-2"></i>
                <?php echo $category['name']; ?>
                <span class="ml-1 text-xs opacity-75" x-text="'(' + getCategoryCount('<?php echo $category['id']; ?>') + ')'"></span>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Enhanced Navigation Menu -->
    <div x-show="showMenu" x-cloak class="fixed inset-0 z-50 bg-black bg-opacity-50" @click="showMenu = false">
        <div class="fixed left-0 top-0 bottom-0 w-80 bg-white shadow-xl transform transition-transform" 
             :class="showMenu ? 'translate-x-0' : '-translate-x-full'" @click.stop>
            
            <div class="p-4 bg-gradient-to-r from-primary to-purple-600 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold"><?php echo $user['name']; ?></h2>
                        <p class="text-sm opacity-90">Enhanced Mobile POS</p>
                        <p class="text-xs opacity-75" x-text="currentTime"></p>
                    </div>
                    <button @click="showMenu = false" class="p-2 rounded-lg hover:bg-white/10 transition-colors">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
            </div>

            <nav class="p-4 space-y-2 overflow-y-auto" style="height: calc(100vh - 120px);">
                <a href="dashboard.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-tachometer-alt mr-3 w-5"></i>Dashboard
                </a>
                <a href="sales.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-desktop mr-3 w-5"></i>Desktop POS
                </a>
                <button @click="showOrderTypeModal = true; showMenu = false" class="w-full flex items-center p-3 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-cog mr-3 w-5"></i>Order Settings
                </button>
                <button @click="showStatsModal = true; showMenu = false" class="w-full flex items-center p-3 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-chart-bar mr-3 w-5"></i>Today's Stats
                </button>
                <hr class="my-3">
                <button @click="confirmClearCart(); showMenu = false" class="w-full flex items-center p-3 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                    <i class="fas fa-trash mr-3 w-5"></i>Clear Cart
                </button>
                <a href="shift-close.php" class="flex items-center p-3 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                    <i class="fas fa-power-off mr-3 w-5"></i>Close Shift
                </a>
            </nav>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="pt-32 pb-20 overflow-y-auto" style="height: 100vh;">
        <!-- Search Bar -->
        <div class="px-4 mb-4">
            <div class="relative">
                <input type="text" x-model="searchTerm" placeholder="Search menu items..." 
                       class="w-full p-3 pl-10 text-base border border-gray-300 rounded-xl bg-white shadow-sm focus:ring-2 focus:ring-primary focus:border-primary">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <template x-if="searchTerm">
                    <button @click="searchTerm = ''" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                        <i class="fas fa-times"></i>
                    </button>
                </template>
            </div>
        </div>

        <!-- Enhanced Products Grid -->
        <div class="px-4">
            <template x-if="filteredProducts.length === 0">
                <div class="text-center py-12">
                    <i class="fas fa-search text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">No items found</p>
                    <p class="text-gray-400 text-sm">Try adjusting your search or category</p>
                </div>
            </template>

            <div class="grid grid-cols-2 gap-3 pb-4">
                <template x-for="product in filteredProducts" :key="product.id">
                    <div @click="addToCart(product)" 
                         :class="product.quantity <= 0 ? 'opacity-50' : 'active:scale-95'"
                         class="bg-white rounded-xl shadow-md border border-gray-200 p-3 transition-all duration-200">
                        <!-- Product Image -->
                        <div class="aspect-square bg-gray-100 rounded-lg mb-3 flex items-center justify-center overflow-hidden relative">
                            <template x-if="product.photo">
                                <img :src="'../assets/uploads/products/' + product.photo" :alt="product.name" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!product.photo">
                                <i class="fas fa-utensils text-3xl text-gray-400"></i>
                            </template>
                            
                            <!-- Stock Badge -->
                            <div :class="product.quantity <= 5 ? 'bg-red-500' : 'bg-green-500'" 
                                 class="absolute top-1 right-1 text-white text-xs px-2 py-1 rounded-full font-bold">
                                <span x-text="product.quantity"></span>
                            </div>
                            
                            <!-- Out of Stock Overlay -->
                            <template x-if="product.quantity <= 0">
                                <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                                    <span class="text-white font-bold text-sm">OUT OF STOCK</span>
                                </div>
                            </template>
                        </div>
                        
                        <h3 class="font-semibold text-gray-900 text-sm leading-tight mb-1" x-text="product.name"></h3>
                        
                        <!-- Arabic Name -->
                        <template x-if="product.name_ar">
                            <p class="text-xs text-gray-500 mb-2" x-text="product.name_ar" dir="rtl"></p>
                        </template>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-primary font-bold text-base" x-text="'QR ' + parseFloat(product.sell_price).toFixed(2)"></span>
                            <span :class="product.quantity <= 5 ? 'text-red-500' : 'text-green-500'" 
                                  class="text-xs font-medium">
                                Stock: <span x-text="product.quantity"></span>
                            </span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Enhanced Mobile Cart (Bottom Sheet) -->
    <div x-show="showCart" x-cloak class="fixed inset-0 z-50 bg-black bg-opacity-50" @click="showCart = false">
        <div class="fixed bottom-0 left-0 right-0 bg-white rounded-t-2xl shadow-2xl transform transition-transform max-h-[85vh]" 
             :class="showCart ? 'translate-y-0' : 'translate-y-full'" @click.stop>
            
            <!-- Cart Header -->
            <div class="p-4 border-b border-gray-200 bg-gray-50 rounded-t-2xl">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">Order Cart</h2>
                        <p class="text-sm text-gray-600" x-text="orderSerial + ' • ' + getOrderTypeLabel()"></p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="text-right">
                            <div class="text-primary font-bold text-lg" x-text="'QR ' + total.toFixed(2)"></div>
                            <div class="text-gray-500 text-xs" x-text="totalItems + ' items, ' + totalQuantity + ' qty'"></div>
                        </div>
                        <button @click="showCart = false" class="text-gray-500 hover:text-gray-700 p-2">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Order Type & Table Number -->
            <div class="p-4 border-b border-gray-200 bg-white">
                <!-- Order Type Toggle -->
                <div class="mb-3">
                    <div class="grid grid-cols-3 gap-1 bg-gray-100 p-1 rounded-lg">
                        <button @click="orderType = 'dine-in'" 
                                :class="orderType === 'dine-in' ? 'bg-white text-primary shadow-sm' : 'text-gray-600'"
                                class="p-2 rounded-md text-xs font-medium transition-all">
                            <i class="fas fa-utensils mr-1"></i>Dine-In
                        </button>
                        <button @click="orderType = 'takeaway'" 
                                :class="orderType === 'takeaway' ? 'bg-white text-primary shadow-sm' : 'text-gray-600'"
                                class="p-2 rounded-md text-xs font-medium transition-all">
                            <i class="fas fa-shopping-bag mr-1"></i>Take Away
                        </button>
                        <button @click="orderType = 'delivery'" 
                                :class="orderType === 'delivery' ? 'bg-white text-primary shadow-sm' : 'text-gray-600'"
                                class="p-2 rounded-md text-xs font-medium transition-all">
                            <i class="fas fa-motorcycle mr-1"></i>Delivery
                        </button>
                    </div>
                </div>

                <!-- Table Number for Dine-in -->
                <template x-if="orderType === 'dine-in'">
                    <div class="mb-3">
                        <input type="text" x-model="tableNumber" placeholder="Enter Table Number"
                               class="w-full p-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary text-center font-bold">
                    </div>
                </template>

                <!-- Delivery Info -->
                <template x-if="orderType === 'delivery'">
                    <div class="space-y-2">
                        <input type="text" x-model="deliveryInfo.customerName" placeholder="Customer Name"
                               class="w-full p-2 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                        <input type="tel" x-model="deliveryInfo.phone" placeholder="Phone Number"
                               class="w-full p-2 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                        <textarea x-model="deliveryInfo.address" placeholder="Delivery Address" rows="2"
                                  class="w-full p-2 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary resize-none"></textarea>
                    </div>
                </template>

                <!-- Discount -->
                <div class="flex items-center space-x-2">
                    <label class="text-sm font-medium text-gray-600">Discount:</label>
                    <input type="number" x-model="discount" @input="calculateTotal()" step="0.01" placeholder="0.00"
                           class="flex-1 p-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary text-right">
                    <span class="text-sm text-gray-600">QR</span>
                </div>
            </div>

            <!-- Cart Items -->
            <div class="overflow-y-auto" style="max-height: 40vh;">
                <template x-if="cart.length === 0">
                    <div class="text-center py-8">
                        <i class="fas fa-shopping-cart text-3xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500">Cart is empty</p>
                        <p class="text-gray-400 text-sm">Add items to start your order</p>
                    </div>
                </template>

                <div class="p-4 space-y-3">
                    <template x-for="(item, index) in cart" :key="index">
                        <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1">
                                    <h4 class="font-semibold text-gray-900 text-sm" x-text="item.name"></h4>
                                    <template x-if="item.name_ar">
                                        <p class="text-xs text-gray-500" x-text="item.name_ar" dir="rtl"></p>
                                    </template>
                                    <p class="text-xs text-gray-600" x-text="'QR ' + parseFloat(item.sell_price).toFixed(2) + ' each'"></p>
                                </div>
                                <button @click="removeFromCart(index)" class="text-red-500 hover:text-red-700 p-2">
                                    <i class="fas fa-trash text-sm"></i>
                                </button>
                            </div>
                            
                            <!-- Quantity Controls -->
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-3">
                                    <button @click="updateQuantity(index, item.quantity - 1)" 
                                            class="w-8 h-8 bg-red-100 text-red-600 rounded-full flex items-center justify-center hover:bg-red-200 transition-colors">
                                        <i class="fas fa-minus text-xs"></i>
                                    </button>
                                    <span class="font-bold text-lg min-w-8 text-center" x-text="item.quantity"></span>
                                    <button @click="updateQuantity(index, item.quantity + 1)" 
                                            class="w-8 h-8 bg-green-100 text-green-600 rounded-full flex items-center justify-center hover:bg-green-200 transition-colors">
                                        <i class="fas fa-plus text-xs"></i>
                                    </button>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-bold text-primary" x-text="'QR ' + getItemTotal(item).toFixed(2)"></div>
                                </div>
                            </div>

                            <!-- Item Notes -->
                            <template x-if="item.notes">
                                <div class="bg-blue-50 border border-blue-200 rounded p-2 mb-2">
                                    <p class="text-xs text-blue-800" x-text="'Note: ' + item.notes"></p>
                                </div>
                            </template>

                            <!-- Item Add-ons -->
                            <template x-if="item.addons && item.addons.length > 0">
                                <div class="bg-purple-50 border border-purple-200 rounded p-2 mb-2">
                                    <div class="text-xs font-medium text-purple-800 mb-1">Add-ons:</div>
                                    <template x-for="addon in item.addons" :key="addon.id">
                                        <div class="flex justify-between items-center text-xs">
                                            <span class="text-purple-700" x-text="addon.name"></span>
                                            <span class="text-purple-700 font-medium" x-text="addon.price > 0 ? '+QR ' + addon.price.toFixed(2) : 'FREE'"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            
                            <!-- Item Actions -->
                            <div class="flex space-x-2">
                                <button @click="addItemNote(index)" class="flex-1 py-2 text-xs text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-50 transition-colors">
                                    <i class="fas fa-sticky-note mr-1"></i>
                                    <span x-text="item.notes ? 'Edit Note' : 'Add Note'"></span>
                                </button>
                                <button @click="openAddonsModal(index)" class="flex-1 py-2 text-xs text-purple-600 border border-purple-200 rounded-lg hover:bg-purple-50 transition-colors">
                                    <i class="fas fa-plus-circle mr-1"></i>
                                    <span x-text="item.addons && item.addons.length > 0 ? 'Edit Add-ons' : 'Add Add-ons'"></span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Cart Total & Actions -->
            <div class="p-4 border-t border-gray-200 bg-white">
                <!-- Total Summary -->
                <div class="mb-4 space-y-1">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal:</span>
                        <span class="font-medium" x-text="'QR ' + subtotal.toFixed(2)"></span>
                    </div>
                    <template x-if="discount > 0">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Discount:</span>
                            <span class="font-medium text-red-600" x-text="'-QR ' + discount.toFixed(2)"></span>
                        </div>
                    </template>
                    <template x-if="orderType === 'delivery'">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Delivery Fee:</span>
                            <span class="font-medium" x-text="'QR ' + deliveryFee.toFixed(2)"></span>
                        </div>
                    </template>
                    <div class="flex justify-between text-lg font-bold border-t pt-2">
                        <span class="text-gray-900">TOTAL:</span>
                        <span class="text-primary" x-text="'QR ' + total.toFixed(2)"></span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="grid grid-cols-3 gap-2">
                    <button @click="holdOrder()" :disabled="cart.length === 0"
                            class="bg-warning hover:bg-amber-600 text-white font-semibold py-3 rounded-lg disabled:opacity-50 transition-colors">
                        <i class="fas fa-pause block mb-1"></i>
                        <span class="text-xs">Hold</span>
                    </button>
                    <button @click="printOrder()" :disabled="cart.length === 0"
                            class="bg-info hover:bg-blue-700 text-white font-semibold py-3 rounded-lg disabled:opacity-50 transition-colors">
                        <i class="fas fa-print block mb-1"></i>
                        <span class="text-xs">Print</span>
                    </button>
                    <button @click="validateAndShowPayment()" :disabled="cart.length === 0"
                            class="bg-primary hover:bg-blue-700 text-white font-semibold py-3 rounded-lg disabled:opacity-50 transition-colors">
                        <i class="fas fa-credit-card block mb-1"></i>
                        <span class="text-xs">Pay</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Payment Modal -->
    <div x-show="showPayment" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-900">Payment Processing</h3>
                    <button @click="showPayment = false" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                
                <div class="mb-4 p-4 bg-gray-50 rounded-lg text-center">
                    <p class="text-2xl font-bold text-primary" x-text="'Total: QR ' + total.toFixed(2)"></p>
                    <div class="text-sm text-gray-600 mt-1">
                        <span x-text="totalItems + ' items • ' + getOrderTypeLabel()"></span>
                        <template x-if="tableNumber">
                            <span x-text="' • Table ' + tableNumber"></span>
                        </template>
                    </div>
                </div>

                <!-- Payment Methods -->
                <div class="grid grid-cols-2 gap-2 mb-4">
                    <button @click="selectPaymentMethod('cash')" 
                            :class="paymentMethod === 'cash' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700'"
                            class="p-4 rounded-lg text-center transition-all">
                        <i class="fas fa-money-bill-wave text-2xl mb-1"></i>
                        <div class="text-sm font-medium">Cash</div>
                    </button>
                    <button @click="selectPaymentMethod('card')" 
                            :class="paymentMethod === 'card' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700'"
                            class="p-4 rounded-lg text-center transition-all">
                        <i class="fas fa-credit-card text-2xl mb-1"></i>
                        <div class="text-sm font-medium">Card</div>
                    </button>
                    <button @click="selectPaymentMethod('credit')" 
                            :class="paymentMethod === 'credit' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700'"
                            class="p-4 rounded-lg text-center transition-all">
                        <i class="fas fa-file-invoice-dollar text-2xl mb-1"></i>
                        <div class="text-sm font-medium">Credit</div>
                    </button>
                    <button @click="selectPaymentMethod('foc')" 
                            :class="paymentMethod === 'foc' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700'"
                            class="p-4 rounded-lg text-center transition-all">
                        <i class="fas fa-gift text-2xl mb-1"></i>
                        <div class="text-sm font-medium">FOC</div>
                    </button>
                </div>

                <!-- Cash Amount Input -->
                <template x-if="paymentMethod === 'cash'">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Amount Received (QR)</label>
                        <input type="number" x-model="amountReceived" @input="calculateChange()" 
                               step="0.01" placeholder="0.00"
                               class="w-full p-4 text-xl font-bold text-center border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                        <template x-if="change >= 0">
                            <p class="mt-2 text-lg font-bold text-success text-center" x-text="'Change: QR ' + change.toFixed(2)"></p>
                        </template>
                    </div>
                </template>

                <!-- FOC Reason -->
                <template x-if="paymentMethod === 'foc'">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">FOC Reason *</label>
                        <textarea x-model="focReason" rows="3" placeholder="Enter reason for free of charge..."
                                  class="w-full p-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary resize-none"></textarea>
                    </div>
                </template>

                <div class="grid grid-cols-2 gap-3">
                    <button @click="showPayment = false" 
                            class="py-3 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button @click="processPayment()" :disabled="!canProcessPayment()"
                            class="py-3 bg-success hover:bg-green-700 text-white font-semibold rounded-lg disabled:opacity-50 transition-colors">
                        Complete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add-ons Modal -->
    <div x-show="showAddonsModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Add Item Add-ons</h3>
                    <button @click="closeAddonsModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <template x-if="currentAddonIndex >= 0">
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 mb-4" x-text="'Adding add-ons for: ' + cart[currentAddonIndex]?.name"></p>
                        
                        <!-- Available Add-ons -->
                        <div class="space-y-2 max-h-60 overflow-y-auto">
                            <template x-for="addon in availableAddons" :key="addon.id">
                                <div class="flex justify-between items-center bg-gray-50 p-3 rounded border hover:bg-gray-100 transition-colors">
                                    <div class="flex items-center space-x-3">
                                        <input type="checkbox" :id="'addon_' + addon.id" :value="addon.id" x-model="selectedAddons"
                                               class="text-primary focus:ring-primary border-gray-300 rounded">
                                        <label :for="'addon_' + addon.id" class="text-sm text-gray-700 cursor-pointer" x-text="addon.name"></label>
                                    </div>
                                    <span class="text-sm font-medium text-primary" x-text="addon.price > 0 ? 'QR ' + addon.price.toFixed(2) : 'FREE'"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <div class="grid grid-cols-2 gap-3">
                    <button @click="closeAddonsModal()" 
                            class="py-3 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button @click="saveAddons()" 
                            class="py-3 bg-purple-600 hover:bg-purple-700 text-white font-semibold rounded-lg transition-colors">
                        Save Add-ons
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Note Modal -->
    <div x-show="showNoteModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Add Item Note</h3>
                    <button @click="closeNoteModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <template x-if="noteEditIndex >= 0 && cart[noteEditIndex]">
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 mb-3" x-text="'Adding note for: ' + cart[noteEditIndex]?.name"></p>
                        
                        <textarea x-model="newNote" rows="4" placeholder="Enter special instructions or notes for this item..."
                                  class="w-full p-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary resize-none"></textarea>
                        <p class="text-xs text-gray-500 mt-1">Examples: No onions, Extra spicy, Well done, etc.</p>
                    </div>
                </template>

                <div class="grid grid-cols-2 gap-3">
                    <button @click="closeNoteModal()" 
                            class="py-3 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button @click="saveItemNote()" 
                            class="py-3 bg-primary hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors">
                        Save Note
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Held Orders Modal -->
    <div x-show="showHeldOrdersModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-900">Held Orders</h3>
                    <button @click="showHeldOrdersModal = false" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>

                <template x-if="heldOrders.length === 0">
                    <div class="text-center py-8">
                        <i class="fas fa-pause text-3xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500 text-sm">No held orders</p>
                    </div>
                </template>

                <div class="space-y-3">
                    <template x-for="(order, index) in heldOrders" :key="order.id">
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h4 class="font-semibold text-gray-900" x-text="order.order_number"></h4>
                                    <p class="text-xs text-gray-500" x-text="formatDateTime(order.held_at)"></p>
                                </div>
                                <div class="text-right">
                                    <span class="text-lg font-bold text-primary" x-text="'QR ' + parseFloat(order.total).toFixed(2)"></span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <template x-for="item in order.items" :key="item.id">
                                    <div class="flex justify-between text-sm mb-1">
                                        <span x-text="item.quantity + 'x ' + item.name"></span>
                                        <span x-text="'QR ' + (item.quantity * item.sell_price).toFixed(2)"></span>
                                    </div>
                                </template>
                            </div>
                            
                            <div class="flex space-x-2">
                                <button @click="resumeHeldOrder(order.id)" 
                                        class="flex-1 bg-green-500 hover:bg-green-600 text-white text-sm py-2 px-3 rounded font-medium transition-colors">
                                    Resume
                                </button>
                                <button @click="deleteHeldOrder(order.id, index)" 
                                        class="bg-red-500 hover:bg-red-600 text-white text-sm py-2 px-3 rounded transition-colors">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div x-show="showConfirmModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-sm w-full">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <i class="fas fa-exclamation-triangle text-yellow-500 text-2xl mr-3"></i>
                    <h3 class="text-lg font-bold text-gray-900">Confirm Action</h3>
                </div>
                <p class="text-gray-600 mb-6" x-text="confirmMessage"></p>
                <div class="grid grid-cols-2 gap-3">
                    <button @click="closeConfirmModal()" 
                            class="py-3 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button @click="executeConfirmAction()" 
                            class="py-3 bg-danger hover:bg-red-700 text-white font-semibold rounded-lg transition-colors">
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
        function enhancedMobilePOS() {
            return {
                // Data
                products: <?php echo json_encode($products); ?>,
                categories: <?php echo json_encode($categories); ?>,
                customers: <?php echo json_encode($customers); ?>,
                availableAddons: <?php echo json_encode($addons); ?>,
                deliveryFee: <?php echo $deliveryFee; ?>,
                orderSerial: '<?php echo $orderSerial; ?>',
                currentSaleCount: <?php echo $dailyCount + 1; ?>,
                
                // UI State
                selectedCategory: '',
                searchTerm: '',
                showMenu: false,
                showCart: false,
                showPayment: false,
                showAddonsModal: false,
                showNoteModal: false,
                showHeldOrdersModal: false,
                showConfirmModal: false,
                
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
                
                // Order
                orderType: 'dine-in',
                tableNumber: '',
                deliveryInfo: {
                    customerName: '',
                    phone: '',
                    address: ''
                },
                
                // Payment
                paymentMethod: 'cash',
                amountReceived: 0,
                change: 0,
                focReason: '',
                
                // Modals
                currentAddonIndex: -1,
                selectedAddons: [],
                noteEditIndex: -1,
                newNote: '',
                
                // Held Orders
                heldOrders: [],
                
                // Confirmation
                confirmMessage: '',
                confirmAction: null,
                
                // Time
                currentTime: '',

                init() {
                    this.calculateTotal();
                    this.updateDateTime();
                    this.loadHeldOrders();
                    
                    setInterval(() => {
                        this.updateDateTime();
                    }, 1000);
                    
                    this.generateNewOrderSerial();
                    this.setupBeepAudio();
                },

                setupBeepAudio() {
                    this.beepAudio = document.getElementById('beepAudio');
                    if (this.beepAudio) {
                        this.beepAudio.volume = 0.5;
                        this.beepAudio.load();
                    }
                },

                playBeepSound() {
                    try {
                        if (this.beepAudio) {
                            this.beepAudio.currentTime = 0;
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

                updateDateTime() {
                    const now = new Date();
                    this.currentTime = now.toLocaleTimeString('en-US', { 
                        hour12: true, hour: '2-digit', minute: '2-digit'
                    });
                },

                formatDateTime(dateString) {
                    return new Date(dateString).toLocaleString();
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
                    const addonsTotal = item.addons ? item.addons.reduce((sum, addon) => sum + parseFloat(addon.price || 0), 0) : 0;
                    return (parseFloat(item.sell_price) + addonsTotal) * item.quantity;
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
                    
                    this.playBeepSound();
                    this.calculateTotal();
                    this.showAlert('Item added to cart', 'success');
                    
                    // Haptic feedback
                    if (navigator.vibrate) {
                        navigator.vibrate(50);
                    }
                },

                updateQuantity(index, newQuantity) {
                    if (newQuantity <= 0) {
                        this.removeFromCart(index);
                    } else {
                        const item = this.cart[index];
                        const menuItem = this.products.find(mi => mi.id === item.id);
                        if (newQuantity <= menuItem.quantity) {
                            item.quantity = newQuantity;
                            this.calculateTotal();
                        } else {
                            this.showAlert('Insufficient stock available', 'warning');
                        }
                    }
                },

                removeFromCart(index) {
                    const item = this.cart[index];
                    this.cart.splice(index, 1);
                    this.calculateTotal();
                    this.showAlert(item.name + ' removed from cart', 'info');
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
                    
                    if (this.orderType === 'dine-in' && !this.tableNumber) {
                        this.showAlert('Please enter table number', 'error');
                        return;
                    }
                    
                    if (this.orderType === 'delivery') {
                        if (!this.deliveryInfo.customerName || !this.deliveryInfo.phone || !this.deliveryInfo.address) {
                            this.showAlert('Please fill in all delivery information', 'error');
                            return;
                        }
                    }
                    
                    this.showCart = false;
                    this.showPayment = true;
                    this.amountReceived = this.total;
                },

                selectPaymentMethod(method) {
                    this.paymentMethod = method;
                    this.calculateChange();
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
                    
                    return true;
                },

                async processPayment() {
                    if (!this.canProcessPayment()) return;
                    
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
                            this.newOrder();
                            
                            // Success haptic feedback
                            if (navigator.vibrate) {
                                navigator.vibrate([100, 50, 100]);
                            }
                        } else {
                            this.showAlert(result.message || 'Failed to process payment', 'error');
                        }
                    } catch (error) {
                        this.showAlert('Network error occurred', 'error');
                    }
                    
                    this.showPayment = false;
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
                    const methods = { 'cash': 1, 'card': 2, 'credit': 3, 'foc': 4 };
                    return methods[this.paymentMethod] || 1;
                },

                newOrder() {
                    this.cart = [];
                    this.discount = 0;
                    this.amountReceived = 0;
                    this.change = 0;
                    this.paymentMethod = 'cash';
                    this.focReason = '';
                    this.tableNumber = '';
                    this.deliveryInfo = { customerName: '', phone: '', address: '' };
                    this.currentSaleCount++;
                    this.generateNewOrderSerial();
                    this.calculateTotal();
                },

                confirmClearCart() {
                    if (this.cart.length === 0) return;
                    
                    this.showConfirmDialog(
                        'Are you sure you want to clear all items from cart?',
                        () => {
                            this.cart = [];
                            this.discount = 0;
                            this.calculateTotal();
                            this.showAlert('Cart cleared', 'info');
                        }
                    );
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

                // Hold order functionality
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
                            this.loadHeldOrders();
                            this.showCart = false;
                        } else {
                            this.showAlert(result.message || 'Failed to hold order', 'error');
                        }
                    } catch (error) {
                        this.showAlert('Network error occurred', 'error');
                    }
                },

                printOrder() {
                    if (this.cart.length === 0) return;
                    
                    const printWindow = window.open('', '_blank', 'width=300,height=600');
                    const orderContent = this.generateOrderPrintContent();
                    
                    printWindow.document.write(orderContent);
                    printWindow.document.close();
                    printWindow.focus();
                    
                    setTimeout(() => {
                        printWindow.print();
                        printWindow.close();
                    }, 250);
                    
                    this.showAlert('Order printed successfully', 'success');
                    this.showCart = false;
                },

                generateOrderPrintContent() {
                    const now = new Date();
                    let content = `
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Mobile Order - ${this.orderSerial}</title>
                            <style>
                                @media print {
                                    @page { size: 80mm auto; margin: 2mm; }
                                }
                                body { font-family: 'Courier New', monospace; font-size: 12px; margin: 0; padding: 5px; }
                                .center { text-align: center; }
                                .right { text-align: right; }
                                .bold { font-weight: bold; }
                                .line { border-bottom: 1px dashed #000; margin: 5px 0; }
                                table { width: 100%; border-collapse: collapse; }
                                td { padding: 2px 0; }
                            </style>
                        </head>
                        <body>
                            <div class="center bold">
                                LANGIT MINANG RESTAURANT<br>
                                MOBILE ORDER RECEIPT<br>
                                ${this.orderType.toUpperCase()}
                            </div>
                            <div class="line"></div>
                            <table>
                                <tr><td>Order No:</td><td class="right bold">${this.orderSerial}</td></tr>
                                <tr><td>Date:</td><td class="right">${now.toLocaleDateString()}</td></tr>
                                <tr><td>Time:</td><td class="right">${now.toLocaleTimeString()}</td></tr>`;
                    
                    if (this.tableNumber) {
                        content += `<tr><td>Table:</td><td class="right bold">${this.tableNumber}</td></tr>`;
                    }
                    
                    content += `
                            </table>
                            <div class="line"></div>
                            <table>`;
                    
                    this.cart.forEach(item => {
                        content += `
                            <tr>
                                <td colspan="2" class="bold">${item.name}</td>
                            </tr>
                            <tr>
                                <td>${item.quantity} x QR ${parseFloat(item.sell_price).toFixed(2)}</td>
                                <td class="right">QR ${this.getItemTotal(item).toFixed(2)}</td>
                            </tr>`;
                        
                        if (item.notes) {
                            content += `<tr><td colspan="2" style="font-style: italic; font-size: 10px;">Note: ${item.notes}</td></tr>`;
                        }
                        
                        if (item.addons && item.addons.length > 0) {
                            item.addons.forEach(addon => {
                                content += `<tr><td colspan="2" style="font-size: 10px;">+ ${addon.name} ${addon.price > 0 ? '(+QR ' + addon.price.toFixed(2) + ')' : '(FREE)'}</td></tr>`;
                            });
                        }
                    });
                    
                    content += `
                            </table>
                            <div class="line"></div>
                            <table>
                                <tr><td>Subtotal:</td><td class="right">QR ${this.subtotal.toFixed(2)}</td></tr>`;
                    
                    if (this.discount > 0) {
                        content += `<tr><td>Discount:</td><td class="right">-QR ${this.discount.toFixed(2)}</td></tr>`;
                    }
                    
                    if (this.orderType === 'delivery') {
                        content += `<tr><td>Delivery Fee:</td><td class="right">QR ${this.deliveryFee.toFixed(2)}</td></tr>`;
                    }
                    
                    content += `
                                <tr class="bold"><td>TOTAL:</td><td class="right">QR ${this.total.toFixed(2)}</td></tr>
                            </table>
                            <div class="line"></div>
                            <div class="center">
                                Thank you for your order!<br>
                                Mobile POS Order
                            </div>
                        </body>
                        </html>`;
                    
                    return content;
                },

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
                            this.loadHeldOrders();
                            this.showAlert('Order resumed successfully', 'success');
                        } else {
                            this.showAlert(result.message || 'Failed to resume order', 'error');
                        }
                    } catch (error) {
                        this.showAlert('Network error occurred', 'error');
                    }
                },

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

                // Add-ons functionality
                openAddonsModal(index) {
                    this.currentAddonIndex = index;
                    const currentAddons = this.cart[index].addons || [];
                    this.selectedAddons = currentAddons.filter(addon => addon.id).map(addon => addon.id);
                    this.showAddonsModal = true;
                },

                closeAddonsModal() {
                    this.showAddonsModal = false;
                    this.currentAddonIndex = -1;
                    this.selectedAddons = [];
                },

                saveAddons() {
                    if (this.currentAddonIndex >= 0) {
                        const standardAddons = this.selectedAddons.map(addonId => {
                            const addon = this.availableAddons.find(a => a.id == addonId);
                            return {
                                id: addon.id,
                                name: addon.name,
                                price: parseFloat(addon.price)
                            };
                        });
                        
                        this.cart[this.currentAddonIndex].addons = standardAddons;
                        this.calculateTotal();
                        this.showAlert('Add-ons saved', 'success');
                    }
                    this.closeAddonsModal();
                },

                // Note functionality
                addItemNote(index) {
                    this.noteEditIndex = index;
                    this.newNote = this.cart[index].notes || '';
                    this.showNoteModal = true;
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

                showAlert(message, type = 'info') {
                    const colors = {
                        success: 'bg-success',
                        error: 'bg-danger',
                        warning: 'bg-warning',
                        info: 'bg-info'
                    };
                    
                    const alertDiv = document.createElement('div');
                    alertDiv.className = `fixed top-20 left-4 right-4 ${colors[type]} text-white px-4 py-3 rounded-lg shadow-lg z-50 text-sm font-medium animate-fade-in`;
                    alertDiv.textContent = message;
                    document.body.appendChild(alertDiv);
                    
                    setTimeout(() => {
                        alertDiv.remove();
                    }, 3000);
                }
            }
        }
    </script>
</body>
</html>