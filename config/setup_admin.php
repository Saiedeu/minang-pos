<?php
/**
 * Admin Setup File
 * Creates or validates admin user credentials for initial system setup
 */

// Define system constant
define('MINANG_SYSTEM', true);

// Include configuration
require_once 'config.php';

// Initialize response array
$response = ['success' => false, 'message' => ''];

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get and sanitize input
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    
    // Validation
    if (empty($username)) {
        $response['message'] = 'Username is required';
    } elseif (strlen($username) < 3) {
        $response['message'] = 'Username must be at least 3 characters long';
    } elseif (empty($password)) {
        $response['message'] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $response['message'] = 'Password must be at least 6 characters long';
    } elseif ($password !== $confirm_password) {
        $response['message'] = 'Passwords do not match';
    } elseif (empty($name)) {
        $response['message'] = 'Full name is required';
    } else {
        
        try {
            // Initialize User class
            $userManager = new User();
            
            // Check if admin user already exists
            $existingAdmins = $userManager->getUsersByRole(ROLE_ADMIN);
            
            if (!empty($existingAdmins)) {
                // Admin exists, validate credentials
                if ($userManager->login($username, $password)) {
                    $currentUser = User::getCurrentUser();
                    if ($currentUser['role'] == ROLE_ADMIN) {
                        $response['success'] = true;
                        $response['message'] = 'Admin credentials validated successfully';
                        $response['action'] = 'login';
                    } else {
                        $response['message'] = 'User is not an administrator';
                    }
                } else {
                    $response['message'] = 'Invalid username or password';
                }
            } else {
                // No admin exists, create new admin
                $adminData = [
                    'name' => $name,
                    'username' => $username,
                    'password' => $password,
                    'role' => ROLE_ADMIN,
                    'email' => $email,
                    'phone' => $phone,
                    'qid_number' => 'ADMIN-' . date('Ymd'),
                    'joining_date' => date('Y-m-d'),
                    'is_active' => 1
                ];
                
                $result = $userManager->createUser($adminData);
                
                if ($result['success']) {
                    // Auto-login the new admin
                    if ($userManager->login($username, $password)) {
                        $response['success'] = true;
                        $response['message'] = 'Admin user created and logged in successfully';
                        $response['action'] = 'created';
                        $response['user_id'] = $result['user_id'];
                    } else {
                        $response['message'] = 'Admin created but login failed';
                    }
                } else {
                    $response['message'] = $result['message'];
                }
            }
            
        } catch (Exception $e) {
            $response['message'] = 'System error: ' . $e->getMessage();
        }
    }
    
    // Return JSON response for AJAX requests
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Check system status
$systemStatus = checkSystemStatus();
$hasAdminUser = false;

try {
    $userManager = new User();
    $admins = $userManager->getUsersByRole(ROLE_ADMIN);
    $hasAdminUser = !empty($admins);
} catch (Exception $e) {
    $systemStatus['database'] = false;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SYSTEM_NAME; ?> - Admin Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    
    <div class="bg-white rounded-xl shadow-2xl p-8 w-full max-w-md">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-utensils text-3xl text-blue-600"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800"><?php echo BUSINESS_NAME; ?></h1>
            <p class="text-gray-600 mt-2">
                <?php if ($hasAdminUser): ?>
                    Admin Login
                <?php else: ?>
                    Initial Admin Setup
                <?php endif; ?>
            </p>
        </div>

        <!-- System Status -->
        <div class="mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">System Status</h3>
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Database</span>
                    <span class="text-xs px-2 py-1 rounded-full <?php echo $systemStatus['database'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo $systemStatus['database'] ? 'Connected' : 'Error'; ?>
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Uploads Directory</span>
                    <span class="text-xs px-2 py-1 rounded-full <?php echo $systemStatus['uploads_writable'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                        <?php echo $systemStatus['uploads_writable'] ? 'Writable' : 'Read-only'; ?>
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Session</span>
                    <span class="text-xs px-2 py-1 rounded-full <?php echo $systemStatus['session_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo $systemStatus['session_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Error/Success Message -->
        <?php if (!empty($response['message'])): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $response['success'] ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?>">
            <div class="flex items-center">
                <i class="fas <?php echo $response['success'] ? 'fa-check-circle text-green-500' : 'fa-exclamation-triangle text-red-500'; ?> mr-3"></i>
                <span class="<?php echo $response['success'] ? 'text-green-700' : 'text-red-700'; ?> text-sm">
                    <?php echo htmlspecialchars($response['message']); ?>
                </span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Setup Form -->
        <?php if ($systemStatus['database']): ?>
        <form method="POST" id="adminSetupForm" class="space-y-4">
            
            <?php if (!$hasAdminUser): ?>
            <!-- Full Name (only for new admin creation) -->
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       required>
            </div>
            <?php endif; ?>

            <!-- Username -->
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       required>
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <div class="relative">
                    <input type="password" id="password" name="password"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pr-10"
                           required>
                    <button type="button" onclick="togglePassword('password')" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-eye" id="password-eye"></i>
                    </button>
                </div>
            </div>

            <?php if (!$hasAdminUser): ?>
            <!-- Confirm Password (only for new admin creation) -->
            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                <div class="relative">
                    <input type="password" id="confirm_password" name="confirm_password"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pr-10"
                           required>
                    <button type="button" onclick="togglePassword('confirm_password')" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-eye" id="confirm_password-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Email (optional for new admin) -->
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email (Optional)</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <!-- Phone (optional for new admin) -->
            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone (Optional)</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <?php endif; ?>

            <!-- Submit Button -->
            <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200">
                <i class="fas fa-key mr-2"></i>
                <?php echo $hasAdminUser ? 'Login as Admin' : 'Create Admin Account'; ?>
            </button>
        </form>
        <?php else: ?>
        <!-- Database Error -->
        <div class="text-center">
            <i class="fas fa-exclamation-triangle text-4xl text-red-500 mb-4"></i>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">Database Connection Error</h3>
            <p class="text-gray-600 text-sm mb-4">Please check your database configuration in config.php</p>
            <button onclick="location.reload()" class="bg-gray-600 text-white py-2 px-4 rounded-lg hover:bg-gray-700">
                <i class="fas fa-refresh mr-2"></i>Retry
            </button>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="mt-8 text-center text-xs text-gray-500">
            <p><?php echo SYSTEM_NAME; ?> v<?php echo SYSTEM_VERSION; ?></p>
            <p>&copy; <?php echo date('Y'); ?> <?php echo BUSINESS_NAME; ?></p>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const eye = document.getElementById(inputId + '-eye');
            
            if (input.type === 'password') {
                input.type = 'text';
                eye.classList.remove('fa-eye');
                eye.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                eye.classList.remove('fa-eye-slash');
                eye.classList.add('fa-eye');
            }
        }

        // Form validation
        document.getElementById('adminSetupForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password');
            
            if (confirmPassword && password !== confirmPassword.value) {
                e.preventDefault();
                alert('Passwords do not match!');
                return;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return;
            }
        });

        // Auto-redirect on successful setup
        <?php if (!empty($response['success']) && $response['success']): ?>
        setTimeout(function() {
            window.location.href = 'pos/index.php'; // Redirect to POS system
        }, 2000);
        <?php endif; ?>
    </script>

</body>
</html>