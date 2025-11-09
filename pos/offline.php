<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - <?php echo BUSINESS_NAME ?? 'Minang Restaurant POS'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .offline-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .connection-dots {
            display: inline-block;
        }
        
        .connection-dots::after {
            content: '...';
            animation: dots 1.5s infinite step-end;
        }
        
        @keyframes dots {
            0%, 20% { content: ''; }
            40% { content: '.'; }
            60% { content: '..'; }
            80%, 100% { content: '...'; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-900 to-gray-800 text-white min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4 text-center">
        <!-- Offline Icon -->
        <div class="mb-8">
            <div class="w-24 h-24 bg-red-500 rounded-full flex items-center justify-center mx-auto offline-animation">
                <i class="fas fa-wifi text-4xl text-white transform rotate-45"></i>
                <div class="absolute w-6 h-1 bg-red-500 transform rotate-45 translate-x-1"></div>
            </div>
        </div>

        <!-- Offline Message -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold mb-4">You're Offline</h1>
            <p class="text-gray-300 text-lg mb-2">No internet connection detected</p>
            <p class="text-gray-400 text-sm">Some features may be limited while offline</p>
        </div>

        <!-- Connection Status -->
        <div class="mb-8 p-4 bg-gray-800 rounded-lg">
            <div class="flex items-center justify-center mb-2">
                <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                <span class="text-sm">Attempting to reconnect</span>
                <span class="connection-dots text-red-400 ml-1"></span>
            </div>
            <div class="text-xs text-gray-400">Please check your internet connection</div>
        </div>

        <!-- Offline Features Available -->
        <div class="bg-gray-800 rounded-lg p-6 mb-8">
            <h2 class="text-lg font-semibold mb-4 text-green-400">
                <i class="fas fa-check-circle mr-2"></i>Available Offline
            </h2>
            <ul class="text-left space-y-2 text-sm text-gray-300">
                <li><i class="fas fa-circle text-xs text-green-400 mr-2"></i>View cached products</li>
                <li><i class="fas fa-circle text-xs text-green-400 mr-2"></i>Create sales (saved locally)</li>
                <li><i class="fas fa-circle text-xs text-green-400 mr-2"></i>Basic inventory check</li>
                <li><i class="fas fa-circle text-xs text-green-400 mr-2"></i>Print receipts</li>
            </ul>
        </div>

        <!-- Offline Features Not Available -->
        <div class="bg-red-900 bg-opacity-30 rounded-lg p-6 mb-8">
            <h2 class="text-lg font-semibold mb-4 text-red-400">
                <i class="fas fa-times-circle mr-2"></i>Requires Connection
            </h2>
            <ul class="text-left space-y-2 text-sm text-red-300">
                <li><i class="fas fa-circle text-xs text-red-400 mr-2"></i>Real-time inventory sync</li>
                <li><i class="fas fa-circle text-xs text-red-400 mr-2"></i>Customer display updates</li>
                <li><i class="fas fa-circle text-xs text-red-400 mr-2"></i>Kitchen display notifications</li>
                <li><i class="fas fa-circle text-xs text-red-400 mr-2"></i>ERP system access</li>
            </ul>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-3">
            <button onclick="checkConnection()" class="w-full bg-primary hover:bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                <i class="fas fa-sync-alt mr-2"></i>Check Connection
            </button>
            
            <button onclick="goOfflineMode()" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                <i class="fas fa-play mr-2"></i>Continue Offline
            </button>
            
            <a href="dashboard.php" class="block w-full bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-6 rounded-lg text-center transition-colors">
                <i class="fas fa-home mr-2"></i>Go to Dashboard
            </a>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-xs text-gray-500">
            <p><?php echo BUSINESS_NAME ?? 'Minang Restaurant POS'; ?></p>
            <p>Offline support enabled</p>
        </div>
    </div>

    <script>
        // Check internet connection
        function checkConnection() {
            const button = event.target;
            const originalText = button.innerHTML;
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Checking...';
            button.disabled = true;
            
            // Try to fetch a small resource
            fetch('/api/ping.php', { 
                method: 'GET',
                cache: 'no-cache'
            })
            .then(response => {
                if (response.ok) {
                    // Connection restored
                    showConnectionRestored();
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 2000);
                } else {
                    throw new Error('Server not responding');
                }
            })
            .catch(error => {
                // Still offline
                button.innerHTML = originalText;
                button.disabled = false;
                
                showConnectionStatus('Still offline. Please try again.', 'error');
            });
        }

        // Continue in offline mode
        function goOfflineMode() {
            // Set offline mode flag
            localStorage.setItem('offline_mode', 'true');
            localStorage.setItem('offline_since', new Date().toISOString());
            
            // Redirect to offline-capable POS
            window.location.href = 'dashboard.php?offline=1';
        }

        // Show connection status
        function showConnectionStatus(message, type = 'info') {
            const statusDiv = document.createElement('div');
            statusDiv.className = `fixed top-4 left-4 right-4 p-4 rounded-lg text-center font-semibold z-50 ${
                type === 'error' ? 'bg-red-600' : 
                type === 'success' ? 'bg-green-600' : 'bg-blue-600'
            } text-white`;
            statusDiv.textContent = message;
            
            document.body.appendChild(statusDiv);
            
            setTimeout(() => {
                statusDiv.remove();
            }, 3000);
        }

        // Show connection restored message
        function showConnectionRestored() {
            const statusDiv = document.createElement('div');
            statusDiv.className = 'fixed top-4 left-4 right-4 p-4 rounded-lg text-center font-semibold z-50 bg-green-600 text-white';
            statusDiv.innerHTML = '<i class="fas fa-wifi mr-2"></i>Connection Restored! Redirecting...';
            
            document.body.appendChild(statusDiv);
        }

        // Auto-check connection every 30 seconds
        setInterval(() => {
            fetch('/api/ping.php', { 
                method: 'GET', 
                cache: 'no-cache' 
            })
            .then(response => {
                if (response.ok) {
                    window.location.href = 'dashboard.php';
                }
            })
            .catch(error => {
                // Still offline, continue waiting
            });
        }, 30000);

        // Listen for online/offline events
        window.addEventListener('online', () => {
            showConnectionRestored();
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 2000);
        });

        window.addEventListener('offline', () => {
            showConnectionStatus('Connection lost again', 'error');
        });
    </script>
</body>
</html>