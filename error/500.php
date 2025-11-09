<?php
/**
 * 500 Error Page
 * Handle server errors gracefully
 */

http_response_code(500);

define('MINANG_SYSTEM', true);
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Error - <?php echo BUSINESS_NAME; ?></title>
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
<body class="bg-gradient-to-br from-red-50 to-orange-50 min-h-screen flex items-center justify-center">
    <div class="text-center max-w-md mx-auto px-4">
        <!-- Error Illustration -->
        <div class="mb-8">
            <div class="inline-flex items-center justify-center w-32 h-32 bg-gradient-to-br from-red-500 to-orange-600 rounded-full mb-6">
                <i class="fas fa-exclamation-triangle text-white text-5xl"></i>
            </div>
            <h1 class="text-6xl font-bold text-red-600 mb-2">500</h1>
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Server Error</h2>
        </div>

        <div class="mb-8">
            <p class="text-gray-600 mb-4">
                Something went wrong on our end. We're working to fix this issue.
            </p>
            <p class="text-sm text-gray-500">
                Please try again in a few moments or contact support if the problem persists.
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-3">
            <button onclick="location.reload()" 
                    class="w-full bg-primary hover:bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                <i class="fas fa-redo mr-2"></i>Try Again
            </button>
            
            <button onclick="history.back()" 
                    class="w-full bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold py-3 px-6 rounded-lg transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Go Back
            </button>
            
            <a href="/" class="block text-primary hover:text-blue-700 font-medium py-2">
                <i class="fas fa-home mr-2"></i>Return to Homepage
            </a>
        </div>

        <!-- Support Contact -->
        <div class="mt-12 pt-8 border-t border-gray-200">
            <p class="text-sm text-gray-500 mb-2">Technical Support</p>
            <div class="flex items-center justify-center space-x-4 text-sm">
                <a href="tel:<?php echo BUSINESS_PHONE; ?>" class="text-red-600 hover:text-red-700">
                    <i class="fas fa-phone mr-1"></i><?php echo BUSINESS_PHONE; ?>
                </a>
            </div>
            <p class="text-xs text-gray-400 mt-2">
                Error Code: 500 | Time: <?php echo date('Y-m-d H:i:s'); ?>
            </p>
        </div>
    </div>

    <script>
        // Auto-retry after 30 seconds
        setTimeout(() => {
            if (confirm('Would you like to automatically retry loading the page?')) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>