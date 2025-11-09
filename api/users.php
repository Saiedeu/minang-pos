<?php
/**
 * Users API Handler
 * Handle user management operations
 */

define('MINANG_SYSTEM', true);
require_once '../config/config.php';

header('Content-Type: application/json');

// Check authentication
if (!User::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

$user = new User();
$response = ['success' => false, 'message' => 'Invalid action'];

switch ($action) {
    case 'get_all':
        if (User::hasPermission('user_manage')) {
            $users = $user->getAllUsers();
            $response = ['success' => true, 'data' => $users];
        } else {
            $response = ['success' => false, 'message' => 'No permission'];
        }
        break;
        
    case 'get_by_role':
        $role = $input['role'] ?? $_GET['role'] ?? null;
        if ($role !== null) {
            $users = $user->getUsersByRole($role);
            $response = ['success' => true, 'data' => $users];
        } else {
            $response = ['success' => false, 'message' => 'Role parameter required'];
        }
        break;
        
    case 'search':
        $search = $input['search'] ?? $_GET['search'] ?? '';
        if ($search) {
            $users = $user->searchUsers($search);
            $response = ['success' => true, 'data' => $users];
        } else {
            $response = ['success' => false, 'message' => 'Search term required'];
        }
        break;
        
    case 'activate':
    case 'deactivate':
        if (User::hasPermission('user_manage')) {
            $userId = $input['user_id'] ?? $_GET['user_id'] ?? 0;
            $status = $action === 'activate' ? 1 : 0;
            
            $updated = Database::getInstance()->update('users', 
                ['is_active' => $status], 
                'id = ?', 
                [$userId]
            );
            
            $response = $updated ? 
                ['success' => true, 'message' => 'User status updated'] : 
                ['success' => false, 'message' => 'Failed to update user status'];
        } else {
            $response = ['success' => false, 'message' => 'No permission'];
        }
        break;
        
    case 'change_password':
        $userId = $input['user_id'] ?? 0;
        $newPassword = $input['new_password'] ?? '';
        $currentUser = User::getCurrentUser();
        
        // Allow users to change their own password or admins to change any password
        if ($userId == $currentUser['id'] || User::hasPermission('user_manage')) {
            if (strlen($newPassword) >= 6) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updated = Database::getInstance()->update('users', 
                    ['password' => $hashedPassword], 
                    'id = ?', 
                    [$userId]
                );
                
                $response = $updated ? 
                    ['success' => true, 'message' => 'Password updated successfully'] : 
                    ['success' => false, 'message' => 'Failed to update password'];
            } else {
                $response = ['success' => false, 'message' => 'Password must be at least 6 characters'];
            }
        } else {
            $response = ['success' => false, 'message' => 'No permission'];
        }
        break;
        
    case 'get_permissions':
        $userId = $input['user_id'] ?? $_GET['user_id'] ?? 0;
        $targetUser = $user->getUserById($userId);
        
        if ($targetUser) {
            $permissions = User::getRolePermissions($targetUser['role']);
            $response = ['success' => true, 'data' => $permissions];
        } else {
            $response = ['success' => false, 'message' => 'User not found'];
        }
        break;
        
    case 'update_profile':
        $userId = $input['user_id'] ?? 0;
        $currentUser = User::getCurrentUser();
        
        // Allow users to update their own profile
        if ($userId == $currentUser['id']) {
            $updateData = [
                'name' => $input['name'] ?? '',
                'phone' => $input['phone'] ?? '',
                'email' => $input['email'] ?? ''
            ];
            
            $result = $user->updateUser($userId, $updateData);
            $response = $result;
        } else {
            $response = ['success' => false, 'message' => 'Can only update own profile'];
        }
        break;
        
    case 'get_stats':
        $stats = $user->getUserStats();
        $response = ['success' => true, 'data' => $stats];
        break;
}

echo json_encode($response);
?>