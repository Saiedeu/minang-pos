<?php
/**
 * POS System - Login Interface
 * Entry point for Point of Sale system
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

$error = '';
$success = '';

// Check if user is already logged in
if (User::isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $user = new User();
        if ($user->login($username, $password)) {
            // Check if user has POS access
            if (User::hasPermission('pos_sales')) {
                header('Location: dashboard.php');
                exit();
            } else {
                User::logout();
                $error = 'You do not have permission to access POS system';
            }
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Login - <?php echo BUSINESS_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '<?php echo PRIMARY_COLOR; ?>',
                        secondary: '<?php echo SECONDARY_COLOR; ?>',
                        success: '<?php echo SUCCESS_COLOR; ?>',
                        warning: '<?php echo WARNING_COLOR; ?>',
                        danger: '<?php echo DANGER_COLOR; ?>'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full">
            <!-- Logo and Title -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-primary rounded-full shadow-lg mb-4">
                    <i class="fas fa-cash-register text-white text-2xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">POS System</h1>
                <h2 class="text-xl text-gray-600 mb-2"><?php echo BUSINESS_NAME; ?></h2>
                <p class="text-gray-500"><?php echo BUSINESS_NAME_AR; ?></p>
                <p class="text-sm text-gray-400 mt-2"><?php echo BUSINESS_ADDRESS; ?></p>
            </div>

            <!-- Login Form -->
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <div class="mb-6">
                    <h3 class="text-2xl font-semibold text-gray-800 mb-2">Sign In</h3>
                    <p class="text-gray-600">Enter your credentials to access the POS system</p>
                </div>

                <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                    <div class="flex">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-2 mt-0.5"></i>
                        <p class="text-red-700"><?php echo $error; ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
                    <div class="flex">
                        <i class="fas fa-check-circle text-green-500 mr-2 mt-0.5"></i>
                        <p class="text-green-700"><?php echo $success; ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-6">
                    <div>
                        <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-user mr-2"></i>Username
                        </label>
                        <input type="text" id="username" name="username" 
                               class="w-full p-3 border-2 border-gray-300 rounded-lg focus:border-primary focus:ring-2 focus:ring-primary focus:ring-opacity-20 transition-all duration-200"
                               placeholder="Enter your username" 
                               value="<?php echo htmlspecialchars($username ?? ''); ?>"
                               required autofocus>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-lock mr-2"></i>Password
                        </label>
                        <div class="relative">
                            <input type="password" id="password" name="password" 
                                   class="w-full p-3 border-2 border-gray-300 rounded-lg focus:border-primary focus:ring-2 focus:ring-primary focus:ring-opacity-20 transition-all duration-200 pr-12"
                                   placeholder="Enter your password" 
                                   required>
                            <button type="button" onclick="togglePassword()" 
                                    class="absolute right-3 top-3 text-gray-400 hover:text-gray-600 focus:outline-none">
                                <i id="password-icon" class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input type="checkbox" id="remember" name="remember" 
                                   class="h-4 w-4 text-primary border-gray-300 rounded focus:ring-primary">
                            <label for="remember" class="ml-2 text-sm text-gray-600">
                                Remember me
                            </label>
                        </div>
                        <a href="#" class="text-sm text-primary hover:text-blue-700 font-medium">
                            Forgot password?
                        </a>
                    </div>

                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-primary to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-all duration-300 transform hover:scale-105 shadow-lg">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Sign In to POS
                    </button>
                </form>

                <!-- Quick Login Buttons (Development Only) -->
                <?php if (defined('DB_HOST') && DB_HOST === 'localhost'): ?>
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <p class="text-xs text-gray-500 mb-3 text-center">Quick Login (Development)</p>
                    <div class="grid grid-cols-2 gap-2">
                        <button onclick="quickLogin('yanti', 'cas@yanti')" 
                                class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-3 rounded transition-colors">
                            <i class="fas fa-user mr-1"></i>Yanti (Cashier)
                        </button>
                        <button onclick="quickLogin('admin', 'admin123')" 
                                class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-3 rounded transition-colors">
                            <i class="fas fa-user-shield mr-1"></i>Admin
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="text-center mt-8">
                <p class="text-sm text-gray-500 mb-2">
                    <a href="../" class="text-primary hover:text-blue-700 font-medium">
                        <i class="fas fa-arrow-left mr-1"></i>Back to Main Menu
                    </a>
                </p>
                <p class="text-xs text-gray-400">
                    <?php echo SYSTEM_NAME; ?> v<?php echo SYSTEM_VERSION; ?>
                </p>
                <p class="text-xs text-gray-400 mt-1">
                    © <?php echo date('Y'); ?> <?php echo BUSINESS_NAME; ?>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordField.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        }

        // Quick login function (development only)
        function quickLogin(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            document.querySelector('form').submit();
        }

        // Auto-focus on username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });

        // Enter key submission
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('form').submit();
            }
        });
    </script>
</body>
</html>