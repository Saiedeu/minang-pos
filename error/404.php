<?php
/**
 * 404 Error Page
 * Handle missing pages gracefully
 */

http_response_code(404);

define('MINANG_SYSTEM', true);
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - <?php echo BUSINESS_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '<?php echo PRIMARY_COLOR; ?>'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen flex items-center justify-center">
    <div class="text-center max-w-md mx-auto px-4">
        <!-- 404 Illustration -->
        <div class="mb-8">
            <div class="inline-flex items-center justify-center w-32 h-32 bg-gradient-to-br from-primary to-blue-600 rounded-full mb-6">
                <i class="fas fa-search text-white text-5xl"></i>
            </div>
            <h1 class="text-6xl font-bold text-gray-800 mb-2">404</h1>
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Page Not Found</h2>
        </div>

        <div class="mb-8">
            <p class="text-gray-600 mb-4">
                Sorry, the page you are looking for doesn't exist or has been moved.
            </p>
            <p class="text-sm text-gray-500">
                The requested URL was not found on this server.
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-3">
            <button onclick="history.back()" 
                    class="w-full bg-primary hover:bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Go Back
            </button>
            
            <div class="grid grid-cols-2 gap-3">
                <a href="/pos/" class="bg-green-500 hover:bg-green-600 text-white font-semibold py-3 px-4 rounded-lg transition-colors text-sm">
                    <i class="fas fa-cash-register mr-2"></i>POS System
                </a>
                <a href="/erp/" class="bg-purple-500 hover:bg-purple-600 text-white font-semibold py-3 px-4 rounded-lg transition-colors text-sm">
                    <i class="fas fa-chart-line mr-2"></i>ERP System
                </a>
            </div>
            
            <a href="/" class="block text-primary hover:text-blue-700 font-medium py-2">
                <i class="fas fa-home mr-2"></i>Return to Homepage
            </a>
        </div>

        <!-- Support Contact -->
        <div class="mt-12 pt-8 border-t border-gray-200">
            <p class="text-sm text-gray-500 mb-2">Need help?</p>
            <div class="flex items-center justify-center space-x-4 text-sm">
                <a href="tel:<?php echo BUSINESS_PHONE; ?>" class="text-primary hover:text-blue-700">
                    <i class="fas fa-phone mr-1"></i><?php echo BUSINESS_PHONE; ?>
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-xs text-gray-400">
            <p><?php echo SYSTEM_NAME; ?> v<?php echo SYSTEM_VERSION; ?></p>
            <p>© <?php echo date('Y'); ?> <?php echo BUSINESS_NAME; ?></p>
        </div>
    </div>

    <script>
        // Auto-redirect after 10 seconds
        let countdown = 10;
        const countdownElement = document.createElement('p');
        countdownElement.className = 'text-sm text-gray-500 mt-4';
        countdownElement.textContent = `Redirecting to homepage in ${countdown} seconds...`;
        
        setTimeout(() => {
            document.querySelector('.space-y-3').after(countdownElement);
            
            const timer = setInterval(() => {
                countdown--;
                countdownElement.textContent = `Redirecting to homepage in ${countdown} seconds...`;
                
                if (countdown <= 0) {
                    clearInterval(timer);
                    window.location.href = '/';
                }
            }, 1000);
        }, 3000);
    </script>
</body>
</html>