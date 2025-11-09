<?php
/**
 * ERP System - Staff Management  
 * Manage employee profiles, roles, and information
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
            'name' => sanitize($_POST['name']),
            'username' => sanitize($_POST['username']),
            'password' => $_POST['password'],
            'role' => intval($_POST['role']),
            'qid_number' => sanitize($_POST['qid_number'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'email' => sanitize($_POST['email'] ?? ''),
            'joining_date' => !empty($_POST['joining_date']) ? $_POST['joining_date'] : null,
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
        // Get current user data first
        $currentStaff = $user->getUserById($staffId);
        if (!$currentStaff) {
            $error = 'Staff member not found';
        } else {
            $staffData = [
                'name' => sanitize($_POST['name']),
                'role' => intval($_POST['role']),
                'qid_number' => sanitize($_POST['qid_number'] ?? ''),
                'phone' => sanitize($_POST['phone'] ?? ''),
                'email' => sanitize($_POST['email'] ?? ''),
                'joining_date' => !empty($_POST['joining_date']) ? $_POST['joining_date'] : null,
                'salary' => floatval($_POST['salary'] ?? 0)
            ];
            
            // Only update password if provided
            if (!empty($_POST['password'])) {
                $staffData['password'] = $_POST['password'];
            }
            
            // Do NOT include username in updates - it should remain unchanged
            // Remove empty email to avoid unique constraint issues
            if (empty($staffData['email'])) {
                $staffData['email'] = null;
            }
            
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
                    
                    // Delete old photo if exists
                    if ($currentStaff['photo'] && file_exists($uploadDir . $currentStaff['photo'])) {
                        unlink($uploadDir . $currentStaff['photo']);
                    }
                }
            }
            
            $result = $user->updateUser($staffId, $staffData);
            if ($result['success']) {
                $success = 'Staff member updated successfully';
                // Refresh current staff data
                $currentStaff = $user->getUserById($staffId);
            } else {
                $error = $result['message'] ?? 'Failed to update staff member. Please try again.';
            }
        }
    }
}

// Get data
$allStaff = $user->getAllUsers(false);
$currentStaff = null;

if ($action === 'edit' && $staffId) {
    $currentStaff = $user->getUserById($staffId);
    if (!$currentStaff) {
        $error = 'Staff member not found';
        $action = 'list';
    }
}

// Handle success message from redirect
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $success = 'Staff member created successfully';
            break;
        case 'updated':
            $success = 'Staff member updated successfully';
            break;
        case 'status_changed':
            $success = 'Staff status updated successfully';
            break;
    }
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'toggle_status' && isset($_GET['id'])) {
        $staffId = intval($_GET['id']);
        $staff = $user->getUserById($staffId);
        
        if ($staff) {
            if ($staff['is_active']) {
                $result = $user->deactivateUser($staffId);
            } else {
                $result = $user->activateUser($staffId);
            }
            echo json_encode(['success' => $result]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Staff member not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
    }
    exit();
}

$pageTitle = $action === 'add' ? 'Add Staff Member' : ($action === 'edit' ? 'Edit Staff Member' : 'Staff Management');
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
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800"><?php echo $pageTitle; ?></h1>
                    <p class="text-gray-600">Manage restaurant staff and employee information</p>
                </div>
                
                <?php if ($action === 'list'): ?>
                <div class="flex space-x-3">
                    <a href="attendance.php" class="bg-secondary hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-clock mr-2"></i>View Attendance
                    </a>
                    <a href="?action=add" class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-plus mr-2"></i>Add New Staff
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Alerts -->
            <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
                <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
                <p class="text-green-700"><?php echo htmlspecialchars($success); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
            <!-- Staff List -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-800">All Staff Members</h2>
                        <input type="text" id="search" placeholder="Search staff..." 
                               class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full" id="staff-table">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Photo</th>
                                <th class="px-6 py-4">Name</th>
                                <th class="px-6 py-4">Username</th>
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
                                            <img src="../../assets/uploads/staff-photos/<?php echo htmlspecialchars($staff['photo']); ?>" 
                                                 alt="<?php echo htmlspecialchars($staff['name']); ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <i class="fas fa-user text-gray-400"></i>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($staff['name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($staff['email'] ?? 'No email'); ?></div>
                                </td>
                                <td class="px-6 py-4 font-mono text-gray-600"><?php echo htmlspecialchars($staff['username']); ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                                        <?php echo htmlspecialchars(User::getRoleName($staff['role'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-mono text-gray-600"><?php echo htmlspecialchars($staff['qid_number'] ?? '-'); ?></td>
                                <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($staff['phone'] ?? '-'); ?></td>
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
                                        <button onclick="toggleStaffStatus(<?php echo $staff['id']; ?>)" 
                                                class="text-<?php echo $staff['is_active'] ? 'red' : 'green'; ?>-600 hover:text-<?php echo $staff['is_active'] ? 'red' : 'green'; ?>-800 text-sm" 
                                                title="<?php echo $staff['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="fas fa-<?php echo $staff['is_active'] ? 'user-slash' : 'user-check'; ?>"></i>
                                        </button>
                                        <a href="attendance.php?staff_id=<?php echo $staff['id']; ?>" 
                                           class="text-purple-600 hover:text-purple-800 text-sm" title="View Attendance">
                                            <i class="fas fa-clock"></i>
                                        </a>
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
            <div class="max-w-7xl mx-auto">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="flex items-center justify-between p-8 border-b border-gray-200">
                        <h2 class="text-2xl font-semibold text-gray-800">
                            <?php echo $action === 'add' ? 'Add New Staff Member' : 'Edit Staff Member'; ?>
                        </h2>
                        <a href="staff.php" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times text-xl"></i>
                        </a>
                    </div>

                    <div class="p-8">
                        <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                            <!-- Left Column - Form Fields -->
                            <div class="lg:col-span-2 space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Full Name -->
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name *</label>
                                        <input type="text" name="name" required
                                               value="<?php echo htmlspecialchars($currentStaff['name'] ?? ''); ?>"
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary text-base"
                                               placeholder="Enter full name">
                                    </div>

                                    <!-- Username -->
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Username *</label>
                                        <?php if ($action === 'edit'): ?>
                                            <!-- For edit mode, show username but don't submit it -->
                                            <input type="text" 
                                                   value="<?php echo htmlspecialchars($currentStaff['username'] ?? ''); ?>"
                                                   class="w-full p-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-600 text-base"
                                                   readonly>
                                            <p class="text-xs text-gray-500 mt-1">Username cannot be changed after creation</p>
                                        <?php else: ?>
                                            <input type="text" name="username" required
                                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary text-base"
                                                   placeholder="Login username">
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Password -->
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                                            Password <?php echo $action === 'edit' ? '(Leave empty to keep current)' : '*'; ?>
                                        </label>
                                        <div class="relative">
                                            <input type="password" name="password" 
                                                   <?php echo $action === 'add' ? 'required' : ''; ?>
                                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary pr-12 text-base"
                                                   placeholder="Enter password" id="password-input">
                                            <button type="button" onclick="togglePasswordVisibility()" 
                                                    class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                                                <i id="password-icon" class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Role -->
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Role *</label>
                                        <select name="role" required
                                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary text-base">
                                            <?php 
                                            global $USER_ROLES;
                                            foreach ($USER_ROLES as $roleId => $roleName): ?>
                                            <option value="<?php echo $roleId; ?>" 
                                                    <?php echo ($currentStaff['role'] ?? '') == $roleId ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($roleName); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- QID Number -->
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">QID Number</label>
                                        <input type="text" name="qid_number" 
                                               value="<?php echo htmlspecialchars($currentStaff['qid_number'] ?? ''); ?>"
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary text-base"
                                               placeholder="29876543210">
                                        <p class="text-xs text-gray-500 mt-1">Used for barcode attendance scanning</p>
                                    </div>

                                    <!-- Phone -->
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                                        <input type="tel" name="phone" 
                                               value="<?php echo htmlspecialchars($currentStaff['phone'] ?? ''); ?>"
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary text-base"
                                               placeholder="+974-XXXX-XXXX">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Email -->
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                                        <input type="email" name="email" 
                                               value="<?php echo htmlspecialchars($currentStaff['email'] ?? ''); ?>"
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary text-base"
                                               placeholder="staff@minangrestaurant.com">
                                    </div>

                                    <!-- Joining Date -->
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Joining Date</label>
                                        <input type="date" name="joining_date" 
                                               value="<?php echo $currentStaff['joining_date'] ?? date('Y-m-d'); ?>"
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary text-base">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Monthly Salary -->
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Monthly Salary (QR)</label>
                                        <input type="number" name="salary" step="0.01" min="0"
                                               value="<?php echo $currentStaff['salary'] ?? ''; ?>"
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary text-base"
                                               placeholder="0.00">
                                        <p class="text-xs text-gray-500 mt-1">Optional - used for payroll calculations</p>
                                    </div>

                                    <!-- Photo Upload -->
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Staff Photo</label>
                                        <input type="file" name="photo" accept="image/*" 
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary text-base">
                                        <p class="text-xs text-gray-500 mt-1">Recommended: 300x300px, max 5MB</p>
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
                            </div>

                            <!-- Right Column - Profile Preview -->
                            <div class="lg:col-span-1">
                                <div class="bg-gray-50 rounded-xl p-6 border border-gray-200 sticky top-8">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4 text-center">Profile Preview</h3>
                                    
                                    <!-- Photo Preview -->
                                    <div class="flex justify-center mb-6">
                                        <div class="w-32 h-32 bg-gradient-to-br from-blue-100 to-purple-100 rounded-full flex items-center justify-center overflow-hidden border-4 border-white shadow-lg">
                                            <?php if (($currentStaff['photo'] ?? '') && $action === 'edit'): ?>
                                                <img src="../../assets/uploads/staff-photos/<?php echo htmlspecialchars($currentStaff['photo']); ?>" 
                                                     alt="Profile Photo" class="w-full h-full object-cover" id="profile-photo-preview">
                                            <?php else: ?>
                                                <i class="fas fa-user text-gray-400 text-4xl" id="profile-icon"></i>
                                                <img src="" alt="Profile Photo" class="w-full h-full object-cover hidden" id="profile-photo-preview">
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Profile Information -->
                                    <div class="space-y-4">
                                        <div class="text-center">
                                            <h4 class="font-bold text-xl text-gray-800" id="preview-name"><?php echo htmlspecialchars($currentStaff['name'] ?? 'Full Name'); ?></h4>
                                            <p class="text-sm text-gray-600" id="preview-role"><?php echo isset($currentStaff['role']) ? htmlspecialchars(User::getRoleName($currentStaff['role'])) : 'Role'; ?></p>
                                        </div>

                                        <div class="space-y-3">
                                            <div class="flex items-center text-sm">
                                                <i class="fas fa-id-card text-gray-400 w-5 mr-3"></i>
                                                <div>
                                                    <p class="text-gray-500">QID Number</p>
                                                    <p class="font-semibold text-gray-800" id="preview-qid"><?php echo htmlspecialchars($currentStaff['qid_number'] ?? 'Not set'); ?></p>
                                                </div>
                                            </div>

                                            <div class="flex items-center text-sm">
                                                <i class="fas fa-envelope text-gray-400 w-5 mr-3"></i>
                                                <div>
                                                    <p class="text-gray-500">Email</p>
                                                    <p class="font-semibold text-gray-800 truncate" id="preview-email"><?php echo htmlspecialchars($currentStaff['email'] ?? 'Not set'); ?></p>
                                                </div>
                                            </div>

                                            <div class="flex items-center text-sm">
                                                <i class="fas fa-phone text-gray-400 w-5 mr-3"></i>
                                                <div>
                                                    <p class="text-gray-500">Phone</p>
                                                    <p class="font-semibold text-gray-800" id="preview-phone"><?php echo htmlspecialchars($currentStaff['phone'] ?? 'Not set'); ?></p>
                                                </div>
                                            </div>

                                            <div class="flex items-center text-sm">
                                                <i class="fas fa-calendar-alt text-gray-400 w-5 mr-3"></i>
                                                <div>
                                                    <p class="text-gray-500">Joining Date</p>
                                                    <p class="font-semibold text-gray-800" id="preview-joining-date"><?php echo isset($currentStaff['joining_date']) ? formatDate($currentStaff['joining_date']) : date('d/m/Y'); ?></p>
                                                </div>
                                            </div>

                                            <div class="flex items-center text-sm">
                                                <i class="fas fa-money-bill-wave text-gray-400 w-5 mr-3"></i>
                                                <div>
                                                    <p class="text-gray-500">Monthly Salary</p>
                                                    <p class="font-semibold text-green-600" id="preview-salary"><?php echo isset($currentStaff['salary']) ? formatCurrency($currentStaff['salary']) : formatCurrency(0); ?></p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Status Badge -->
                                        <?php if ($action === 'edit'): ?>
                                        <div class="text-center pt-4 border-t border-gray-200">
                                            <span class="px-3 py-1 text-xs rounded-full <?php echo $currentStaff['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?php echo $currentStaff['is_active'] ? 'Active Employee' : 'Inactive Employee'; ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Show custom modal dialog instead of alert
        function showModal(message, type = 'info') {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg max-w-sm w-full mx-4">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-${type === 'error' ? 'exclamation-triangle text-red-500' : 'info-circle text-blue-500'} text-xl mr-3"></i>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">${type === 'error' ? 'Error' : 'Information'}</h3>
                    </div>
                    <p class="text-gray-700 dark:text-gray-300 mb-4">${message}</p>
                    <div class="flex justify-end">
                        <button class="px-4 py-2 bg-blue-500 text-white hover:bg-blue-600 rounded" onclick="this.closest('.fixed').remove()">OK</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        // Search functionality
        document.getElementById('search')?.addEventListener('input', function() {
            const search = this.value.toLowerCase();
            const rows = document.querySelectorAll('#staff-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(search) ? '' : 'none';
            });
        });

        // Toggle staff status
        async function toggleStaffStatus(id) {
            try {
                const response = await fetch(`?ajax=toggle_status&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    window.location.href = 'staff.php?success=status_changed';
                } else {
                    showModal(result.message || 'Failed to update staff status', 'error');
                }
            } catch (error) {
                showModal('An error occurred while updating status', 'error');
            }
        }

        // Toggle password visibility
        function togglePasswordVisibility() {
            const passwordField = document.getElementById('password-input');
            const passwordIcon = document.getElementById('password-icon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordField.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        }

        // Real-time form preview updates
        document.addEventListener('DOMContentLoaded', function() {
            // Update name preview
            const nameInput = document.querySelector('input[name="name"]');
            if (nameInput) {
                nameInput.addEventListener('input', function() {
                    document.getElementById('preview-name').textContent = this.value || 'Full Name';
                });
            }

            // Update role preview
            const roleSelect = document.querySelector('select[name="role"]');
            if (roleSelect) {
                roleSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    document.getElementById('preview-role').textContent = selectedOption.text;
                });
            }

            // Update QID preview
            const qidInput = document.querySelector('input[name="qid_number"]');
            if (qidInput) {
                qidInput.addEventListener('input', function() {
                    document.getElementById('preview-qid').textContent = this.value || 'Not set';
                });
            }

            // Update email preview
            const emailInput = document.querySelector('input[name="email"]');
            if (emailInput) {
                emailInput.addEventListener('input', function() {
                    document.getElementById('preview-email').textContent = this.value || 'Not set';
                });
            }

            // Update phone preview
            const phoneInput = document.querySelector('input[name="phone"]');
            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    document.getElementById('preview-phone').textContent = this.value || 'Not set';
                });
            }

            // Update joining date preview
            const joiningDateInput = document.querySelector('input[name="joining_date"]');
            if (joiningDateInput) {
                joiningDateInput.addEventListener('change', function() {
                    const date = new Date(this.value);
                    const formattedDate = date.toLocaleDateString('en-GB');
                    document.getElementById('preview-joining-date').textContent = formattedDate;
                });
            }

            // Update salary preview
            const salaryInput = document.querySelector('input[name="salary"]');
            if (salaryInput) {
                salaryInput.addEventListener('input', function() {
                    const salary = parseFloat(this.value) || 0;
                    document.getElementById('preview-salary').textContent = 'QR ' + salary.toFixed(2);
                });
            }

            // Photo preview
            const photoInput = document.querySelector('input[name="photo"]');
            if (photoInput) {
                photoInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const preview = document.getElementById('profile-photo-preview');
                            const icon = document.getElementById('profile-icon');
                            
                            if (preview) {
                                preview.src = e.target.result;
                                preview.classList.remove('hidden');
                            }
                            if (icon) {
                                icon.classList.add('hidden');
                            }
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
        });
    </script>
</body>
</html>