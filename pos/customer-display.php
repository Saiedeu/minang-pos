<?php
/**
 * POS System - Customer Display
 * External display for customers showing order info and promotions
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

$db = Database::getInstance();

// Get display settings
$settings = [];
$settingsData = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('customer_display', 'customer_display_message', 'shop_name', 'shop_name_ar')");
foreach ($settingsData as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

// Check if customer display is enabled
if (($settings['customer_display'] ?? '0') !== '1') {
    echo '<h1>Customer Display is currently disabled</h1>';
    echo '<p>Please enable it from POS Settings</p>';
    exit();
}

// Get current order info from session or database
$currentOrder = $_SESSION['customer_display_order'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Display - <?php echo BUSINESS_NAME; ?></title>
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
    <style>
        body {
            overflow: hidden;
            background: linear-gradient(-45deg, #667eea, #764ba2, #f093fb, #f5576c);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
        }
        
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .slideshow-container {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        
        .slide {
            display: none;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            position: absolute;
            top: 0;
            left: 0;
        }
        
        .slide.active {
            display: block;
            animation: fadeInSlide 1s ease-in-out;
        }
        
        @keyframes fadeInSlide {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
        .pulse-glow {
            animation: pulseGlow 2s ease-in-out infinite;
        }
        
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 20px rgba(37, 99, 235, 0.5); }
            50% { box-shadow: 0 0 30px rgba(37, 99, 235, 0.8), 0 0 40px rgba(37, 99, 235, 0.3); }
        }
    </style>
</head>
<body class="h-screen">
    <!-- Main Display Area -->
    <div class="h-full relative">
        <!-- Background Slideshow -->
        <div class="slideshow-container">
            <!-- Slide 1: Welcome -->
            <div class="slide active flex items-center justify-center">
                <div class="text-center text-white">
                    <div class="floating mb-8">
                        <div class="w-32 h-32 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-6 backdrop-blur-sm">
                            <i class="fas fa-utensils text-6xl text-white"></i>
                        </div>
                    </div>
                    <h1 class="text-6xl font-bold mb-4 text-shadow"><?php echo $settings['shop_name'] ?? BUSINESS_NAME; ?></h1>
                    <h2 class="text-4xl mb-6 opacity-90" dir="rtl"><?php echo $settings['shop_name_ar'] ?? BUSINESS_NAME_AR; ?></h2>
                    <p class="text-2xl opacity-80"><?php echo $settings['customer_display_message'] ?? 'Welcome to our restaurant!'; ?></p>
                </div>
            </div>
            
            <!-- Slide 2: Menu Highlights -->
            <div class="slide flex items-center justify-center">
                <div class="text-center text-white">
                    <h1 class="text-5xl font-bold mb-8 text-shadow">Today's Specials</h1>
                    <div class="grid grid-cols-2 gap-8 max-w-4xl mx-auto">
                        <div class="bg-white bg-opacity-20 backdrop-blur-sm rounded-2xl p-6">
                            <i class="fas fa-fire text-4xl text-orange-300 mb-4"></i>
                            <h3 class="text-2xl font-bold mb-2">Rendang Daging</h3>
                            <p class="text-xl opacity-90">QR 45.00</p>
                        </div>
                        <div class="bg-white bg-opacity-20 backdrop-blur-sm rounded-2xl p-6">
                            <i class="fas fa-leaf text-4xl text-green-300 mb-4"></i>
                            <h3 class="text-2xl font-bold mb-2">Nasi Padang</h3>
                            <p class="text-xl opacity-90">QR 38.00</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Slide 3: Promotions -->
            <div class="slide flex items-center justify-center">
                <div class="text-center text-white">
                    <div class="floating">
                        <i class="fas fa-percentage text-8xl text-yellow-300 mb-8"></i>
                    </div>
                    <h1 class="text-6xl font-bold mb-6 text-shadow">Special Offers</h1>
                    <p class="text-3xl mb-4">Free Delivery on Orders Above</p>
                    <p class="text-5xl font-bold text-yellow-300">QR 100</p>
                </div>
            </div>
        </div>

        <!-- Order Information Overlay -->
        <div id="order-overlay" class="absolute top-4 right-4 bg-white bg-opacity-95 backdrop-blur-sm rounded-2xl shadow-2xl p-6 transform transition-all duration-500 <?php echo $currentOrder ? 'translate-x-0' : 'translate-x-full'; ?>" style="min-width: 350px;">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-gray-800">Current Order</h3>
                <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center pulse-glow">
                    <i class="fas fa-shopping-cart text-white"></i>
                </div>
            </div>
            
            <!-- Order Items -->
            <div id="order-items" class="space-y-3 mb-4 max-h-64 overflow-y-auto">
                <!-- Dynamic order items will be inserted here -->
            </div>
            
            <!-- Order Total -->
            <div class="border-t border-gray-300 pt-4">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-gray-600">Subtotal:</span>
                    <span class="font-semibold" id="display-subtotal">QR 0.00</span>
                </div>
                <div class="flex justify-between items-center mb-2" id="display-discount-row" style="display: none;">
                    <span class="text-gray-600">Discount:</span>
                    <span class="font-semibold text-green-600" id="display-discount">QR 0.00</span>
                </div>
                <div class="flex justify-between items-center text-xl font-bold text-primary">
                    <span>Total:</span>
                    <span id="display-total">QR 0.00</span>
                </div>
                <div class="flex justify-between items-center mt-2 text-sm text-gray-600" id="display-change-row" style="display: none;">
                    <span>Change:</span>
                    <span class="font-semibold text-green-600" id="display-change">QR 0.00</span>
                </div>
            </div>
        </div>

        <!-- Time Display -->
        <div class="absolute top-4 left-4 text-white">
            <div class="bg-black bg-opacity-30 backdrop-blur-sm rounded-xl p-4">
                <div class="text-2xl font-bold" id="display-time"></div>
                <div class="text-sm opacity-80" id="display-date"></div>
            </div>
        </div>
    </div>

    <script>
        // Slideshow functionality
        let currentSlide = 0;
        const slides = document.querySelectorAll('.slide');
        const totalSlides = slides.length;
        
        function showSlide(index) {
            slides.forEach((slide, i) => {
                slide.classList.toggle('active', i === index);
            });
        }
        
        function nextSlide() {
            currentSlide = (currentSlide + 1) % totalSlides;
            showSlide(currentSlide);
        }
        
        // Auto-advance slides every 10 seconds
        setInterval(nextSlide, 10000);
        
        // Clock update
        function updateClock() {
            const now = new Date();
            document.getElementById('display-time').textContent = now.toLocaleTimeString('en-US', {
                hour12: true, hour: '2-digit', minute: '2-digit'
            });
            document.getElementById('display-date').textContent = now.toLocaleDateString('en-US', {
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
            });
        }
        
        // Order update functionality
        let currentOrderData = null;
        
        function updateOrderDisplay(orderData) {
            currentOrderData = orderData;
            const overlay = document.getElementById('order-overlay');
            const itemsContainer = document.getElementById('order-items');
            
            if (!orderData || !orderData.items || orderData.items.length === 0) {
                overlay.classList.add('translate-x-full');
                return;
            }
            
            // Show overlay
            overlay.classList.remove('translate-x-full');
            
            // Update items
            itemsContainer.innerHTML = orderData.items.map(item => `
                <div class="flex items-center justify-between p-3 bg-gray-100 rounded-lg">
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-800">${item.name}</h4>
                        <p class="text-sm text-gray-600">${item.name_ar || ''}</p>
                    </div>
                    <div class="text-right ml-4">
                        <div class="font-semibold text-primary">QR ${(item.sell_price * item.quantity).toFixed(2)}</div>
                        <div class="text-xs text-gray-500">${item.quantity} × QR ${item.sell_price}</div>
                    </div>
                </div>
            `).join('');
            
            // Update totals
            document.getElementById('display-subtotal').textContent = `QR ${orderData.subtotal.toFixed(2)}`;
            document.getElementById('display-total').textContent = `QR ${orderData.total.toFixed(2)}`;
            
            // Show/hide discount
            const discountRow = document.getElementById('display-discount-row');
            if (orderData.discount > 0) {
                discountRow.style.display = 'flex';
                document.getElementById('display-discount').textContent = `QR ${orderData.discount.toFixed(2)}`;
            } else {
                discountRow.style.display = 'none';
            }
            
            // Show/hide change
            const changeRow = document.getElementById('display-change-row');
            if (orderData.change && orderData.change > 0) {
                changeRow.style.display = 'flex';
                document.getElementById('display-change').textContent = `QR ${orderData.change.toFixed(2)}`;
            } else {
                changeRow.style.display = 'none';
            }
        }
        
        // Poll for order updates
        async function pollOrderUpdates() {
            try {
                const response = await fetch('api/customer-display.php');
                const data = await response.json();
                
                if (data.success) {
                    updateOrderDisplay(data.order);
                }
            } catch (error) {
                console.error('Failed to update order display:', error);
            }
        }
        
        // Initialize
        updateClock();
        setInterval(updateClock, 1000);
        
        // Poll for order updates every 2 seconds
        setInterval(pollOrderUpdates, 2000);
        
        // Initial order load
        pollOrderUpdates();
        
        // Welcome message rotation
        const welcomeMessages = [
            '<?php echo $settings['customer_display_message'] ?? 'Welcome to our restaurant!'; ?>',
            'Enjoy authentic Minang cuisine',
            'Fresh ingredients, traditional recipes',
            'Thank you for choosing us!'
        ];
        
        let messageIndex = 0;
        
        setInterval(() => {
            // Only change message when no active order
            if (!currentOrderData || currentOrderData.items.length === 0) {
                messageIndex = (messageIndex + 1) % welcomeMessages.length;
                // Update welcome message in slide 1 if needed
            }
        }, 8000);
    </script>
</body>
</html>