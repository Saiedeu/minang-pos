<?php
/**
 * ERP Header Component
 * Reusable header for ERP pages
 */

if (!defined('MINANG_SYSTEM')) {
    exit('Direct access not allowed');
}

$currentUser = User::getCurrentUser();
$pageTitle = $pageTitle ?? 'ERP Dashboard';
?>

<header class="bg-white shadow-sm border-b border-gray-200">
    <div class="flex items-center justify-between px-8 py-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?php echo $pageTitle; ?></h1>
            <div class="flex items-center space-x-4 mt-1">
                <span class="text-sm text-gray-600">Welcome back, <?php echo $currentUser['name']; ?>!</span>
                <!-- Breadcrumb could be added here -->
            </div>
        </div>
        
        <div class="flex items-center space-x-6">
            <!-- Real-time Clock -->
            <div class="text-right text-gray-600">
                <div id="header-current-time" class="text-lg font-semibold"></div>
                <div id="header-current-date" class="text-sm"></div>
            </div>
            
            <!-- Quick Actions -->
            <div class="flex items-center space-x-3">
                <!-- Notifications -->
                <button class="relative p-2 text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-bell text-lg"></i>
                    <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></span>
                </button>
                
                <!-- Quick POS Access -->
                <a href="../../pos/sales.php" 
                   class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-cash-register mr-2"></i>Open POS
                </a>
                
                <!-- User Menu -->
                <div class="relative">
                    <button onclick="toggleHeaderUserMenu()" class="flex items-center text-gray-700 hover:text-gray-900 transition-colors">
                        <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center mr-2">
                            <i class="fas fa-user text-white text-sm"></i>
                        </div>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </button>
                    
                    <div id="header-user-menu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-2 z-50">
                        <div class="px-4 py-2 border-b border-gray-200">
                            <p class="text-sm font-semibold text-gray-800"><?php echo $currentUser['name']; ?></p>
                            <p class="text-xs text-gray-500"><?php echo User::getRoleName($currentUser['role']); ?></p>
                        </div>
                        <a href="../settings/users.php?action=edit&id=<?php echo $currentUser['id']; ?>" 
                           class="flex items-center px-4 py-2 text-gray-700 hover:bg-gray-100 transition-colors">
                            <i class="fas fa-user-edit mr-3"></i>Edit Profile
                        </a>
                        <a href="../settings/system.php" 
                           class="flex items-center px-4 py-2 text-gray-700 hover:bg-gray-100 transition-colors">
                            <i class="fas fa-cog mr-3"></i>System Settings
                        </a>
                        <div class="border-t border-gray-200 my-1"></div>
                        <a href="../logout.php" 
                           class="flex items-center px-4 py-2 text-red-600 hover:bg-red-50 transition-colors">
                            <i class="fas fa-sign-out-alt mr-3"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
    // Update header clock
    function updateHeaderClock() {
        const now = new Date();
        const timeElement = document.getElementById('header-current-time');
        const dateElement = document.getElementById('header-current-date');
        
        if (timeElement) {
            timeElement.textContent = now.toLocaleTimeString('en-US', {
                hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
        }
        
        if (dateElement) {
            dateElement.textContent = now.toLocaleDateString('en-US', {
                weekday: 'short', year: 'numeric', month: 'short', day: 'numeric'
            });
        }
    }

    // Toggle user menu
    function toggleHeaderUserMenu() {
        const menu = document.getElementById('header-user-menu');
        menu.classList.toggle('hidden');
    }

    // Close menu when clicking outside
    document.addEventListener('click', function(e) {
        const userMenu = document.getElementById('header-user-menu');
        const userButton = e.target.closest('button');
        
        if (!userButton || !userButton.onclick.toString().includes('toggleHeaderUserMenu')) {
            userMenu.classList.add('hidden');
        }
    });

    // Initialize clock
    updateHeaderClock();
    setInterval(updateHeaderClock, 1000);
</script>