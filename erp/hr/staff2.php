<?php
/**
 * ERP System - Staff Management
 * Manage staff profiles, roles, and information
 */

define('MINANG_SYSTEM', true);
require_once '../../config/config.php';
require_once '../includes/auth-check.php';

$user = new User();
$action = $_GET['action'] ?? 'list';
$staffId = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $staffData = [
            'name' => sanitize($_POST['name'] ?? ''),
            'username' => sanitize($_POST['username'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'role' => intval($_POST['role'] ?? ROLE_WAITER),
            'qid_number' => sanitize($_POST['qid_number'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'email' => sanitize($_POST['email'] ?? ''),
            'joining_date' => $_POST['joining_date'] ?? date('Y-m-d'),
            'salary' => floatval($_POST['salary'] ?? 0)
        ];
        
        // Handle photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../assets/uploads/staff-photos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = 'STAFF_' . uniqid() . '_' . $_FILES['photo']['name'];
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
                $staffData['photo'] = $fileName;
            }
        }
        
        $result = $user->createUser($staffData);
        if ($result['success']) {
            $success = 'Staff member created successfully';
            header('Location: staff.php?success=created');
            exit();
        } else {
            $error = $result['message'];
        }
    }
    
    if ($action === 'edit' && $staffId) {
        $staffData = [
            'name' => sanitize($_POST['name'] ?? ''),
            'role' => intval($_POST['role'] ?? ROLE_WAITER),
            'qid_number' => sanitize($_POST['qid_number'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'email' => sanitize($_POST['email'] ?? ''),
            'joining_date' => $_POST['joining_date'] ?? date('Y-m-d'),
            'salary' => floatval($_POST['salary'] ?? 0)
        ];
        
        // Handle password update
        if (!empty($_POST['password'])) {
            $staffData['password'] = $_POST['password'];
        }
        
        // Handle photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../assets/uploads/staff-photos/';
            $fileName = 'STAFF_' . uniqid() . '_' . $_FILES['photo']['name'];
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
                $staffData['photo'] = $fileName;
            }
        }
        
        $result = $user->updateUser($staffId, $staffData);
        if ($result['success']) {
            $success = 'Staff member updated successfully';
        } else {
            $error = $result['message'];
        }
    }
}

// Get staff data
$allStaff = $user->getAllUsers();
$currentStaff = null;

if ($action === 'edit' && $staffId) {
    $currentStaff = $user->getUserById($staffId);
}

// Get staff statistics
$staffStats = $user->getUserStats();

$pageTitle = 'Staff Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo BUSINESS_NAME; ?></title>
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
</head>
<body class="bg-gray-50">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="ml-64 min-h-screen">
        <?php include '../includes/header.php'; ?>
        
        <main class="p-8">
            <?php if ($action === 'list'): ?>
            <!-- Staff List -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Staff Management</h1>
                    <p class="text-gray-600">Manage restaurant staff and their roles</p>
                </div>
                <div class="flex space-x-3">
                    <a href="attendance.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-clock mr-2"></i>View Attendance
                    </a>
                    <a href="?action=add" class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-plus mr-2"></i>Add New Staff
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Staff</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $staffStats['total']; ?></p>
                        </div>
                        <i class="fas fa-users text-3xl text-blue-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Active Staff</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo $staffStats['active']; ?></p>
                        </div>
                        <i class="fas fa-user-check text-3xl text-green-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Managers</p>
                            <p class="text-2xl font-bold text-purple-600"><?php echo $staffStats['by_role'][ROLE_MANAGER] ?? 0; ?></p>
                        </div>
                        <i class="fas fa-user-tie text-3xl text-purple-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Cashiers</p>
                            <p class="text-2xl font-bold text-orange-600"><?php echo $staffStats['by_role'][ROLE_CASHIER] ?? 0; ?></p>
                        </div>
                        <i class="fas fa-cash-register text-3xl text-orange-500"></i>
                    </div>
                </div>
            </div>

            <!-- Staff Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-800">All Staff Members</h2>
                        <input type="text" id="staff-search" placeholder="Search staff..." 
                               class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full" id="staff-table">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Photo</th>
                                <th class="px-6 py-4">Name</th>
                                <th class="px-6 py-4">Role</th>
                                <th class="px-6 py-4">QID Number</th>
                                <th class="px-6 py-4">Phone</th>
                                <th class="px-6 py-4">Joining Date</th>
                                <th class="px-6 py-4">Salary</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($allStaff as $staff): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center overflow-hidden">
                                        <?php if ($staff['photo']): ?>
                                            <img src="../../assets/uploads/staff-photos/<?php echo $staff['photo']; ?>" 
                                                 alt="<?php echo $staff['name']; ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <i class="fas fa-user text-gray-400"></i>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900"><?php echo $staff['name']; ?></div>
                                    <div class="text-sm text-gray-500">@<?php echo $staff['username']; ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full <?php 
                                        $roleColors = [
                                            ROLE_ADMIN => 'bg-red-100 text-red-800',
                                            ROLE_MANAGER => 'bg-purple-100 text-purple-800',
                                            ROLE_TOP_MANAGEMENT => 'bg-indigo-100 text-indigo-800',
                                            ROLE_CASHIER => 'bg-green-100 text-green-800',
                                            ROLE_WAITER => 'bg-blue-100 text-blue-800',
                                            ROLE_KITCHEN_STAFF => 'bg-yellow-100 text-yellow-800',
                                            ROLE_CHEF => 'bg-orange-100 text-orange-800'
                                        ];
                                        echo $roleColors[$staff['role']] ?? 'bg-gray-100 text-gray-800';
                                    ?>">
                                        <?php echo User::getRoleName($staff['role']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-600"><?php echo $staff['qid_number'] ?? '-'; ?></td>
                                <td class="px-6 py-4 text-gray-600"><?php echo $staff['phone'] ?? '-'; ?></td>
                                <td class="px-6 py-4 text-gray-600"><?php echo $staff['joining_date'] ? formatDate($staff['joining_date']) : '-'; ?></td>
                                <td class="px-6 py-4 text-gray-600"><?php echo $staff['salary'] ? formatCurrency($staff['salary']) : '-'; ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $staff['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $staff['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <a href="?action=edit&id=<?php echo $staff['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-800 text-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="attendance.php?user_id=<?php echo $staff['id']; ?>" 
                                           class="text-green-600 hover:text-green-800 text-sm" title="View Attendance">
                                            <i class="fas fa-clock"></i>
                                        </a>
                                        <?php if ($staff['is_active']): ?>
                                        <button onclick="toggleStaffStatus(<?php echo $staff['id']; ?>, 0)" 
                                                class="text-red-600 hover:text-red-800 text-sm" title="Deactivate">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                        <?php else: ?>
                                        <button onclick="toggleStaffStatus(<?php echo $staff['id']; ?>, 1)" 
                                                class="text-green-600 hover:text-green-800 text-sm" title="Activate">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- Add/Edit Staff Form -->
            <div class="max-w-4xl">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">
                            <?php echo $action === 'add' ? 'Add New Staff Member' : 'Edit Staff Member'; ?>
                        </h1>
                        <p class="text-gray-600">
                            <?php echo $action === 'add' ? 'Register a new team member' : 'Update staff information'; ?>
                        </p>
                    </div>
                    <a href="staff.php" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-2xl"></i>
                    </a>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-8">
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <!-- Personal Information -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-gray-200 pb-2">
                                Personal Information
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name *</label>
                                    <input type="text" name="name" required
                                           value="<?php echo $currentStaff['name'] ?? ''; ?>"
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                           placeholder="Enter full name">
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">QID Number</label>
                                    <input type="text" name="qid_number" 
                                           value="<?php echo $currentStaff['qid_number'] ?? ''; ?>"
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                           placeholder="29876543210">
                                    <p class="text-xs text-gray-500 mt-1">Required for attendance barcode scanning</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                                    <input type="tel" name="phone" 
                                           value="<?php echo $currentStaff['phone'] ?? ''; ?>"
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                           placeholder="+974-XXXX-XXXX">
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                                    <input type="email" name="email" 
                                           value="<?php echo $currentStaff['email'] ?? ''; ?>"
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                           placeholder="staff@email.com">
                                </div>
                            </div>
                        </div>

                        <!-- System Access -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-gray-200 pb-2">
                                System Access
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <?php if ($action === 'add'): ?>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Username *</label>
                                    <input type="text" name="username" required
                                           value="<?php echo $currentStaff['username'] ?? ''; ?>"
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                           placeholder="Unique username for system login">
                                </div>
                                <?php endif; ?>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <?php echo $action === 'add' ? 'Password *' : 'New Password'; ?>
                                    </label>
                                    <input type="password" name="password" <?php echo $action === 'add' ? 'required' : ''; ?>
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                           placeholder="<?php echo $action === 'add' ? 'Enter secure password' : 'Leave empty to keep current'; ?>">
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Role *</label>
                                    <select name="role" required
                                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                                        <?php
                                        global $USER_ROLES;
                                        foreach ($USER_ROLES as $roleId => $roleName):
                                        ?>
                                        <option value="<?php echo $roleId; ?>" 
                                                <?php echo ($currentStaff['role'] ?? ROLE_WAITER) == $roleId ? 'selected' : ''; ?>>
                                            <?php echo $roleName; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Employment Details -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-gray-200 pb-2">
                                Employment Details
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Joining Date</label>
                                    <input type="date" name="joining_date" 
                                           value="<?php echo $currentStaff['joining_date'] ?? date('Y-m-d'); ?>"
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Monthly Salary (<?php echo CURRENCY_SYMBOL; ?>)</label>
                                    <input type="number" name="salary" step="0.01" min="0"
                                           value="<?php echo $currentStaff['salary'] ?? ''; ?>"
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                           placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <!-- Photo Upload -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Staff Photo</label>
                            <div class="flex items-center space-x-4">
                                <?php if ($action === 'edit' && $currentStaff['photo']): ?>
                                <div class="w-20 h-20 bg-gray-200 rounded-full overflow-hidden">
                                    <img src="../../assets/uploads/staff-photos/<?php echo $currentStaff['photo']; ?>" 
                                         alt="Current photo" class="w-full h-full object-cover">
                                </div>
                                <?php endif; ?>
                                <div class="flex-1">
                                    <input type="file" name="photo" accept="image/*" 
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                                    <p class="text-xs text-gray-500 mt-1">Recommended: Square image, max 5MB</p>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                            <a href="staff.php" 
                               class="px-6 py-3 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold rounded-lg transition-colors">
                                Cancel
                            </a>
                            <button type="submit" 
                                    class="px-6 py-3 bg-primary hover:bg-blue-600 text-white font-semibold rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>
                                <?php echo $action === 'add' ? 'Add Staff Member' : 'Update Staff Member'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Search functionality
        document.getElementById('staff-search').addEventListener('input', function() {
            const search = this.value.toLowerCase();
            const rows = document.querySelectorAll('#staff-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(search) ? '' : 'none';
            });
        });

        // Toggle staff status
        async function toggleStaffStatus(userId, status) {
            const action = status ? 'activate' : 'deactivate';
            const message = status ? 'activate' : 'deactivate';
            
            if (confirm(`Are you sure you want to ${message} this staff member?`)) {
                try {
                    const response = await fetch('../api/users.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: action,
                            user_id: userId
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        location.reload();
                    } else {
                        alert('Failed to update staff status');
                    }
                } catch (error) {
                    alert('An error occurred');
                }
            }
        }

        // Auto-generate username from name
        document.querySelector('input[name="name"]')?.addEventListener('input', function() {
            const usernameInput = document.querySelector('input[name="username"]');
            if (usernameInput && !usernameInput.value) {
                const name = this.value.toLowerCase().replace(/\s+/g, '').replace(/[^a-z0-9]/g, '');
                usernameInput.value = name.substring(0, 20);
            }
        });
    </script>
</body>
</html>