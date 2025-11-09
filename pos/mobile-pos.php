<?php
/**
 * Mobile POS Interface
 * Optimized POS interface for mobile devices and tablets
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';
require_once 'includes/auth-check.php';

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

// Get categories and products optimized for mobile
$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name");
$products = $db->fetchAll("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.is_active = 1 AND p.list_in_pos = 1 AND p.quantity > 0
    ORDER BY p.name
");

$pageTitle = 'Mobile POS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?php echo $pageTitle; ?> - <?php echo BUSINESS_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
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
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="../assets/manifest.json">
    <meta name="theme-color" content="<?php echo PRIMARY_COLOR; ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Minang POS">
    <link rel="apple-touch-icon" href="../assets/icons/icon-192x192.png">
</head>
<body class="bg-gray-100 overflow-hidden select-none" x-data="mobilePOS()" @touchstart.passive @touchend.passive>
    <!-- Mobile Header -->
    <header class="bg-gradient-to-r from-primary to-purple-600 shadow-lg h-16 fixed top-0 left-0 right-0 z-40">
        <div class="flex items-center justify-between px-4 h-full">
            <div class="flex items-center space-x-3">
                <button @click="showMenu = !showMenu" class="text-white">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <div>
                    <h1 class="text-lg font-bold text-white">Mobile POS</h1>
                    <p class="text-xs text-white/80" x-text="cart.length + ' items'"></p>
                </div>
            </div>
            
            <div class="flex items-center space-x-3">
                <button @click="showCart = !showCart" class="relative text-white">
                    <i class="fas fa-shopping-cart text-lg"></i>
                    <template x-if="cart.length > 0">
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center font-bold" x-text="cart.length"></span>
                    </template>
                </button>
                <span class="text-white font-bold text-lg" x-text="'QR ' + total.toFixed(2)"></span>
            </div>
        </div>
    </header>

    <!-- Mobile Navigation Menu -->
    <div x-show="showMenu" x-cloak class="fixed inset-0 z-50 bg-black bg-opacity-50" @click="showMenu = false">
        <div class="fixed left-0 top-0 bottom-0 w-72 bg-white shadow-xl transform transition-transform" 
             :class="showMenu ? 'translate-x-0' : '-translate-x-full'" @click.stop>
            
            <div class="p-4 bg-primary text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold"><?php echo $user['name']; ?></h2>
                        <p class="text-sm opacity-90">Mobile POS Menu</p>
                    </div>
                    <button @click="showMenu = false">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
            </div>

            <nav class="p-4 space-y-2">
                <a href="dashboard.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-tachometer-alt mr-3"></i>Dashboard
                </a>
                <a href="sales.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-desktop mr-3"></i>Desktop POS
                </a>
                <a href="shift-close.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-power-off mr-3"></i>Close Shift
                </a>
                <button @click="clearCart(); showMenu = false" class="w-full flex items-center p-3 text-red-600 hover:bg-red-50 rounded-lg">
                    <i class="fas fa-trash mr-3"></i>Clear Cart
                </button>
            </nav>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="pt-16 pb-20" style="height: 100vh; overflow-y: auto;">
        <!-- Category Tabs -->
        <div class="sticky top-16 bg-white shadow-sm z-30 px-4 py-3">
            <div class="flex space-x-2 overflow-x-auto">
                <button @click="selectedCategory = ''" 
                        :class="selectedCategory === '' ? 'bg-primary text-white' : 'bg-gray-200 text-gray-700'"
                        class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap flex-shrink-0">
                    All Items
                </button>
                <?php foreach ($categories as $category): ?>
                <button @click="selectedCategory = '<?php echo $category['id']; ?>'" 
                        :class="selectedCategory === '<?php echo $category['id']; ?>' ? 'bg-primary text-white' : 'bg-gray-200 text-gray-700'"
                        class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap flex-shrink-0">
                    <i class="<?php echo $category['icon'] ?? 'fas fa-circle'; ?> mr-2"></i>
                    <?php echo $category['name']; ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="p-4">
            <div class="grid grid-cols-2 gap-3">
                <template x-for="product in filteredProducts" :key="product.id">
                    <div @click="addToCart(product)" 
                         class="bg-white rounded-xl shadow-md border border-gray-200 p-3 active:scale-95 transition-transform">
                        <!-- Product Image -->
                        <div class="aspect-square bg-gray-100 rounded-lg mb-2 flex items-center justify-center overflow-hidden">
                            <template x-if="product.photo">
                                <img :src="'../assets/uploads/products/' + product.photo" :alt="product.name" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!product.photo">
                                <i class="fas fa-utensils text-2xl text-gray-400"></i>
                            </template>
                        </div>
                        
                        <h3 class="font-semibold text-gray-900 text-sm leading-tight mb-1" x-text="product.name"></h3>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-primary font-bold text-base" x-text="'QR ' + parseFloat(product.sell_price).toFixed(2)"></span>
                            <span class="text-xs text-gray-500" x-text="'Stock: ' + product.quantity"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Mobile Cart (Bottom Sheet) -->
    <div x-show="showCart" x-cloak class="fixed inset-0 z-50 bg-black bg-opacity-50" @click="showCart = false">
        <div class="fixed bottom-0 left-0 right-0 bg-white rounded-t-2xl shadow-2xl transform transition-transform max-h-[80vh]" 
             :class="showCart ? 'translate-y-0' : 'translate-y-full'" @click.stop>
            
            <!-- Cart Header -->
            <div class="p-4 border-b border-gray-200 bg-gray-50 rounded-t-2xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-800">Order Cart</h2>
                    <div class="flex items-center space-x-3">
                        <span class="text-primary font-bold text-lg" x-text="'QR ' + total.toFixed(2)"></span>
                        <button @click="showCart = false">
                            <i class="fas fa-times text-gray-500"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Cart Items -->
            <div class="overflow-y-auto" style="max-height: 50vh;">
                <template x-if="cart.length === 0">
                    <div class="text-center py-8">
                        <i class="fas fa-shopping-cart text-3xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500">Cart is empty</p>
                    </div>
                </template>

                <div class="p-4 space-y-3">
                    <template x-for="(item, index) in cart" :key="index">
                        <div class="flex items-center justify-between bg-gray-50 rounded-lg p-3">
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-900 text-sm" x-text="item.name"></h4>
                                <p class="text-xs text-gray-600" x-text="'QR ' + parseFloat(item.sell_price).toFixed(2) + ' each'"></p>
                            </div>
                            
                            <div class="flex items-center space-x-3">
                                <div class="flex items-center space-x-2">
                                    <button @click="updateQuantity(index, item.quantity - 1)" 
                                            class="w-8 h-8 bg-red-100 text-red-600 rounded-full flex items-center justify-center">
                                        <i class="fas fa-minus text-xs"></i>
                                    </button>
                                    <span class="font-bold text-lg min-w-8 text-center" x-text="item.quantity"></span>
                                    <button @click="updateQuantity(index, item.quantity + 1)" 
                                            class="w-8 h-8 bg-green-100 text-green-600 rounded-full flex items-center justify-center">
                                        <i class="fas fa-plus text-xs"></i>
                                    </button>
                                </div>
                                
                                <button @click="removeFromCart(index)" class="text-red-500 ml-2">
                                    <i class="fas fa-trash text-sm"></i>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Cart Actions -->
            <div class="p-4 border-t border-gray-200">
                <div class="grid grid-cols-2 gap-3">
                    <button @click="holdOrder()" :disabled="cart.length === 0"
                            class="bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-3 rounded-lg disabled:opacity-50">
                        <i class="fas fa-pause mr-2"></i>Hold
                    </button>
                    <button @click="checkout()" :disabled="cart.length === 0"
                            class="bg-primary hover:bg-blue-600 text-white font-semibold py-3 rounded-lg disabled:opacity-50">
                        <i class="fas fa-credit-card mr-2"></i>Checkout
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Checkout Modal -->
    <div x-show="showCheckout" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm">
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4 text-center">Quick Checkout</h3>
                
                <div class="mb-4 p-4 bg-primary bg-opacity-10 rounded-lg text-center">
                    <p class="text-2xl font-bold text-primary" x-text="'QR ' + total.toFixed(2)"></p>
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
                </div>

                <!-- Order Type -->
                <div class="mb-4">
                    <div class="grid grid-cols-3 gap-1 bg-gray-100 p-1 rounded-lg">
                        <button @click="orderType = 'dine-in'" 
                                :class="orderType === 'dine-in' ? 'bg-white text-primary shadow-sm' : 'text-gray-600'"
                                class="p-2 rounded text-xs font-medium">Dine-In</button>
                        <button @click="orderType = 'takeaway'" 
                                :class="orderType === 'takeaway' ? 'bg-white text-primary shadow-sm' : 'text-gray-600'"
                                class="p-2 rounded text-xs font-medium">Take Away</button>
                        <button @click="orderType = 'delivery'" 
                                :class="orderType === 'delivery' ? 'bg-white text-primary shadow-sm' : 'text-gray-600'"
                                class="p-2 rounded text-xs font-medium">Delivery</button>
                    </div>
                </div>

                <!-- Table Number for Dine-in -->
                <template x-if="orderType === 'dine-in'">
                    <div class="mb-4">
                        <input type="text" x-model="tableNumber" placeholder="Table Number"
                               class="w-full p-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary text-center font-bold">
                    </div>
                </template>

                <div class="grid grid-cols-2 gap-3">
                    <button @click="showCheckout = false" 
                            class="py-3 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold rounded-lg">
                        Cancel
                    </button>
                    <button @click="processPayment()" 
                            class="py-3 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-lg">
                        Pay Now
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function mobilePOS() {
            return {
                // Data
                categories: <?php echo json_encode($categories); ?>,
                products: <?php echo json_encode($products); ?>,
                
                // State
                selectedCategory: '',
                showMenu: false,
                showCart: false,
                showCheckout: false,
                
                // Cart
                cart: [],
                total: 0,
                
                // Order
                orderType: 'dine-in',
                tableNumber: '',
                paymentMethod: 'cash',

                get filteredProducts() {
                    let filtered = this.products;
                    
                    if (this.selectedCategory) {
                        filtered = filtered.filter(p => p.category_id == this.selectedCategory);
                    }
                    
                    return filtered;
                },

                addToCart(product) {
                    if (product.quantity <= 0) return;

                    const existingItem = this.cart.find(item => item.id === product.id);
                    if (existingItem) {
                        if (existingItem.quantity < product.quantity) {
                            existingItem.quantity++;
                        }
                    } else {
                        this.cart.push({
                            id: product.id,
                            name: product.name,
                            sell_price: product.sell_price,
                            quantity: 1,
                            stock: product.quantity
                        });
                    }
                    
                    this.calculateTotal();
                    
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
                        if (newQuantity <= item.stock) {
                            item.quantity = newQuantity;
                            this.calculateTotal();
                        }
                    }
                },

                removeFromCart(index) {
                    this.cart.splice(index, 1);
                    this.calculateTotal();
                },

                calculateTotal() {
                    this.total = this.cart.reduce((sum, item) => {
                        return sum + (parseFloat(item.sell_price) * item.quantity);
                    }, 0);
                },

                clearCart() {
                    this.cart = [];
                    this.calculateTotal();
                },

                selectPaymentMethod(method) {
                    this.paymentMethod = method;
                },

                checkout() {
                    if (this.cart.length === 0) return;
                    
                    if (this.orderType === 'dine-in' && !this.tableNumber) {
                        alert('Please enter table number');
                        return;
                    }
                    
                    this.showCart = false;
                    this.showCheckout = true;
                },

                async processPayment() {
                    if (this.cart.length === 0) return;
                    
                    const orderData = {
                        order_type: this.getOrderTypeId(),
                        table_number: this.tableNumber,
                        subtotal: this.total,
                        total: this.total,
                        payment_method: this.getPaymentMethodId()
                    };
                    
                    const saleItems = this.cart.map(item => ({
                        product_id: item.id,
                        product_name: item.name,
                        quantity: item.quantity,
                        unit_price: item.sell_price
                    }));
                    
                    try {
                        const response = await fetch('../api/sales.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                action: 'create_sale',
                                sale_data: orderData,
                                sale_items: saleItems
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            this.showSuccess('Payment processed successfully!');
                            this.newOrder();
                        } else {
                            alert('Payment failed: ' + result.message);
                        }
                    } catch (error) {
                        alert('Network error occurred');
                    }
                    
                    this.showCheckout = false;
                },

                holdOrder() {
                    // Implementation for holding order
                    alert('Order held successfully');
                    this.newOrder();
                    this.showCart = false;
                },

                newOrder() {
                    this.cart = [];
                    this.tableNumber = '';
                    this.calculateTotal();
                },

                getOrderTypeId() {
                    const types = { 'dine-in': 1, 'takeaway': 2, 'delivery': 3 };
                    return types[this.orderType] || 1;
                },

                getPaymentMethodId() {
                    const methods = { 'cash': 1, 'card': 2 };
                    return methods[this.paymentMethod] || 1;
                },

                showSuccess(message) {
                    const successDiv = document.createElement('div');
                    successDiv.className = 'fixed top-20 left-4 right-4 bg-green-500 text-white p-4 rounded-lg shadow-lg z-50 text-center font-semibold';
                    successDiv.textContent = message;
                    document.body.appendChild(successDiv);
                    
                    setTimeout(() => {
                        successDiv.remove();
                    }, 3000);
                    
                    // Haptic feedback
                    if (navigator.vibrate) {
                        navigator.vibrate([100, 50, 100]);
                    }
                }
            }
        }
    </script>

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
    </style>
</body>
</html>