<?php
session_start();
require_once __DIR__ . '/../config/database.php';

class SessionManager {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    // Check if user is logged in
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    // Check if parent is logged in
    public function isParentLoggedIn() {
        return isset($_SESSION['child_id']) && !empty($_SESSION['child_id']);
    }
    
    // Get current user info
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $query = "SELECT u.*, h.hospital_name 
                  FROM users u 
                  LEFT JOIN hospitals h ON u.hospital_id = h.hospital_id 
                  WHERE u.user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get current child info (for parent login)
    public function getCurrentChild() {
        if (!$this->isParentLoggedIn()) {
            return null;
        }
        
        $query = "SELECT c.*, h.hospital_name 
                  FROM children c 
                  LEFT JOIN hospitals h ON c.hospital_id = h.hospital_id 
                  WHERE c.child_id = :child_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':child_id', $_SESSION['child_id']);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Check user permission
    public function hasPermission($required_role) {
        $user = $this->getCurrentUser();
        if (!$user) return false;
        
        $role_hierarchy = [
            'super_admin' => 4,
            'hospital_admin' => 3,
            'doctor' => 2,
            'nurse' => 1
        ];
        
        $user_level = $role_hierarchy[$user['role']] ?? 0;
        $required_level = $role_hierarchy[$required_role] ?? 0;
        
        return $user_level >= $required_level;
    }
    
    // Login user
    public function login($username, $password) {
        $query = "SELECT * FROM users WHERE username = :username AND is_active = 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $password === $user['password']) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['hospital_id'] = $user['hospital_id'];
            $_SESSION['full_name'] = $user['full_name'];
            return true;
        }
        
        return false;
    }
    
    // Parent login
    public function parentLogin($child_name, $registration_number) {
        $query = "SELECT * FROM children WHERE child_name = :child_name AND registration_number = :reg_number";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':child_name', $child_name);
        $stmt->bindParam(':reg_number', $registration_number);
        $stmt->execute();
        
        $child = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($child) {
            $_SESSION['child_id'] = $child['child_id'];
            $_SESSION['child_name'] = $child['child_name'];
            $_SESSION['parent_name'] = $child['parent_name'];
            $_SESSION['hospital_id'] = $child['hospital_id'];
            $_SESSION['registration_number'] = $child['registration_number'];
            $_SESSION['is_parent'] = true;
            return true;
        }
        
        return false;
    }
    
    // Logout
    public function logout() {
        session_destroy();
        header('Location: ../index.php');
        exit();
    }
    
    // Redirect if not logged in
    public function requireLogin() {
        if (!$this->isLoggedIn() && !$this->isParentLoggedIn()) {
            header('Location: ../index.php');
            exit();
        }
    }
    
    // Redirect if not authorized
    public function requireRole($required_role) {
        $this->requireLogin();
        
        if (!$this->hasPermission($required_role)) {
            header('Location: ../dashboard.php?error=unauthorized');
            exit();
        }
    }
}

$session = new SessionManager();
?>
