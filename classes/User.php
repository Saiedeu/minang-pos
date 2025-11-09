<?php
/**
 * User Management Class
 * Handles user authentication and management
 */

if (!defined('MINANG_SYSTEM')) {
    exit('Direct access not allowed');
}

class User {
    private $db;
    private $userData;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Authenticate user
    public function login($username, $password) {
        $sql = "SELECT * FROM users WHERE username = ? AND is_active = 1";
        $user = $this->db->fetchOne($sql, [$username]);
        
        if ($user && verifyPassword($password, $user['password'])) {
            $this->userData = $user;
            $this->setSession();
            $this->updateLastLogin();
            return true;
        }
        return false;
    }

    // Set user session
    private function setSession() {
        $_SESSION['user_id'] = $this->userData['id'];
        $_SESSION['username'] = $this->userData['username'];
        $_SESSION['name'] = $this->userData['name'];
        $_SESSION['role'] = $this->userData['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
    }

    // Check if user is logged in
    public static function isLoggedIn() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
            return false;
        }
        
        // Check session timeout
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            self::logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }

    // Get current user data
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        $db = Database::getInstance();
        $sql = "SELECT * FROM users WHERE id = ?";
        return $db->fetchOne($sql, [$_SESSION['user_id']]);
    }

    // Check user permission
    public static function hasPermission($requiredRole) {
        if (!self::isLoggedIn()) {
            return false;
        }
        
        $userRole = $_SESSION['role'];
        
        // Admin has all permissions
        if ($userRole == ROLE_ADMIN) {
            return true;
        }
        
        // Check specific role permissions
        switch ($requiredRole) {
            case 'shift_close':
                return in_array($userRole, [ROLE_ADMIN, ROLE_MANAGER, ROLE_CASHIER]);
            case 'inventory_manage':
                return in_array($userRole, [ROLE_ADMIN, ROLE_MANAGER]);
            case 'reports_view':
                return in_array($userRole, [ROLE_ADMIN, ROLE_MANAGER, ROLE_TOP_MANAGEMENT]);
            case 'user_manage':
                return in_array($userRole, [ROLE_ADMIN, ROLE_MANAGER]);
            case 'pos_sales':
                return in_array($userRole, [ROLE_ADMIN, ROLE_MANAGER, ROLE_CASHIER, ROLE_WAITER]);
            default:
                return false;
        }
    }

    // Logout user
    public static function logout() {
        session_destroy();
        session_start();
    }

    // Update last login time
    private function updateLastLogin() {
        $sql = "UPDATE users SET updated_at = NOW() WHERE id = ?";
        $this->db->query($sql, [$this->userData['id']]);
    }

    // Create new user
    public function createUser($data) {
        // Validate required fields
        $required = ['name', 'username', 'password', 'role'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Field {$field} is required"];
            }
        }
        
        // Check if username exists
        if ($this->usernameExists($data['username'])) {
            return ['success' => false, 'message' => 'Username already exists'];
        }
        
        // Hash password
        $data['password'] = hashPassword($data['password']);
        
        // Set defaults
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['is_active'] = 1;
        
        // Ensure all expected fields exist with default values
        $defaultFields = [
            'qid_number' => '',
            'phone' => '',
            'email' => '',
            'joining_date' => null,
            'salary' => 0,
            'photo' => ''
        ];
        
        foreach ($defaultFields as $field => $defaultValue) {
            if (!isset($data[$field])) {
                $data[$field] = $defaultValue;
            }
        }
        
        try {
            $userId = $this->db->insert('users', $data);
            
            if ($userId) {
                return ['success' => true, 'user_id' => $userId];
            }
            return ['success' => false, 'message' => 'Failed to create user'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    // Update user
    public function updateUser($id, $data) {
        try {
            // Get current user data to check if it exists
            $currentUser = $this->getUserById($id);
            if (!$currentUser) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            // Hash password if provided
            if (!empty($data['password'])) {
                $data['password'] = hashPassword($data['password']);
            } else {
                unset($data['password']);
            }
            
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            // Ensure all fields that might be updated exist in the data
            $allowedFields = [
                'name', 'username', 'password', 'role', 'qid_number', 
                'phone', 'email', 'joining_date', 'salary', 'photo', 
                'updated_at', 'is_active'
            ];
            
            $updateData = [];
            foreach ($data as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    $updateData[$field] = $value;
                }
            }
            
            if (empty($updateData)) {
                return ['success' => false, 'message' => 'No valid fields to update'];
            }
            
            $updated = $this->db->update('users', $updateData, 'id = ?', [$id]);
            
            // Check if update was successful
            if ($updated !== false) {
                return ['success' => true];
            }
            return ['success' => false, 'message' => 'Failed to update user - database error'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    // Get user by ID
    public function getUserById($id) {
        try {
            $sql = "SELECT * FROM users WHERE id = ?";
            return $this->db->fetchOne($sql, [$id]);
        } catch (Exception $e) {
            return false;
        }
    }

    // Get all users
    public function getAllUsers($activeOnly = true) {
        try {
            $where = $activeOnly ? 'WHERE is_active = 1' : '';
            $sql = "SELECT * FROM users {$where} ORDER BY name";
            return $this->db->fetchAll($sql);
        } catch (Exception $e) {
            return [];
        }
    }

    // Check if username exists
    public function usernameExists($username, $excludeId = null) {
        try {
            $sql = "SELECT id FROM users WHERE username = ?";
            $params = [$username];
            
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $result = $this->db->fetchOne($sql, $params);
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    // Get users by role
    public function getUsersByRole($role) {
        try {
            $sql = "SELECT * FROM users WHERE role = ? AND is_active = 1 ORDER BY name";
            return $this->db->fetchAll($sql, [$role]);
        } catch (Exception $e) {
            return [];
        }
    }

    // Deactivate user
    public function deactivateUser($id) {
        try {
            // Check if user exists first
            $user = $this->getUserById($id);
            if (!$user) {
                return false;
            }
            
            $result = $this->db->update('users', ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    // Activate user
    public function activateUser($id) {
        try {
            // Check if user exists first
            $user = $this->getUserById($id);
            if (!$user) {
                return false;
            }
            
            $result = $this->db->update('users', ['is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    // Get role name
    public static function getRoleName($roleId) {
        global $USER_ROLES;
        return isset($USER_ROLES[$roleId]) ? $USER_ROLES[$roleId] : 'Unknown';
    }

    // Change password
    public function changePassword($userId, $newPassword) {
        try {
            $hashedPassword = hashPassword($newPassword);
            $updated = $this->db->update('users', 
                ['password' => $hashedPassword, 'updated_at' => date('Y-m-d H:i:s')], 
                'id = ?', 
                [$userId]
            );
            return $updated !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    // Get user statistics
    public function getUserStats() {
        try {
            $stats = [];
            
            // Total users
            $stats['total'] = $this->db->count('users');
            
            // Active users
            $stats['active'] = $this->db->count('users', 'is_active = 1');
            
            // Users by role
            $sql = "SELECT role, COUNT(*) as count FROM users WHERE is_active = 1 GROUP BY role";
            $roleStats = $this->db->fetchAll($sql);
            $stats['by_role'] = [];
            foreach ($roleStats as $role) {
                $stats['by_role'][$role['role']] = $role['count'];
            }
            
            return $stats;
        } catch (Exception $e) {
            return ['total' => 0, 'active' => 0, 'by_role' => []];
        }
    }
}
?>