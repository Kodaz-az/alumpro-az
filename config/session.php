<?php
// Session Management Class for Alumpro.Az

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Session configuration
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    
    // Session name and lifetime
    if (!defined('SESSION_NAME')) {
        define('SESSION_NAME', 'alumpro_session');
    }
    if (!defined('SESSION_TIMEOUT')) {
        define('SESSION_TIMEOUT', 3600); // 1 hour default
    }
    
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

class SessionManager {
    
    /**
     * Login user and create session
     */
    public static function login($user_id, $role, $store_id = null) {
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user_id;
        $_SESSION['role'] = $role;
        $_SESSION['store_id'] = $store_id;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        return true;
    }
    
    /**
     * Logout user and destroy session
     */
    public static function logout() {
        // Log the logout activity if user is logged in
        if (self::isLoggedIn()) {
            try {
                require_once __DIR__ . '/database.php';
                require_once __DIR__ . '/../includes/functions.php';
                Utils::logActivity(self::getUserId(), 'user_logout', 'İstifadəçi sistemdən çıxdı');
            } catch (Exception $e) {
                // Silently handle error if logging fails
                error_log("Logout logging failed: " . $e->getMessage());
            }
        }
        
        // Clear all session data
        $_SESSION = array();
        
        // Delete session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // Destroy session
        session_destroy();
        
        return true;
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
            return false;
        }
        
        // Check session timeout
        if (!self::checkTimeout()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check session timeout
     */
    public static function checkTimeout() {
        if (!isset($_SESSION['last_activity'])) {
            return false;
        }
        
        $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600;
        
        if (time() - $_SESSION['last_activity'] > $timeout) {
            self::logout();
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Require user to be logged in
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            $redirect_url = urlencode($_SERVER['REQUEST_URI']);
            header("Location: " . self::getAuthUrl() . "/login.php?redirect=" . $redirect_url);
            exit;
        }
    }
    
    /**
     * Require specific role
     */
    public static function requireRole($required_role) {
        self::requireLogin();
        
        $user_role = self::getUserRole();
        if ($user_role !== $required_role) {
            header("Location: " . self::getErrorUrl() . "/403.php");
            exit;
        }
    }
    
    /**
     * Require admin role
     */
    public static function requireAdmin() {
        self::requireRole('admin');
    }
    
    /**
     * Require sales role
     */
    public static function requireSales() {
        self::requireLogin();
        
        $user_role = self::getUserRole();
        if (!in_array($user_role, ['admin', 'sales'])) {
            header("Location: " . self::getErrorUrl() . "/403.php");
            exit;
        }
    }
    
    /**
     * Require customer role
     */
    public static function requireCustomer() {
        self::requireRole('customer');
    }
    
    /**
     * Get current user ID
     */
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get current user role
     */
    public static function getUserRole() {
        return $_SESSION['role'] ?? null;
    }
    
    /**
     * Get current user store ID
     */
    public static function getStoreId() {
        return $_SESSION['store_id'] ?? null;
    }
    
    /**
     * Get CSRF token
     */
    public static function getCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Check if user has permission
     */
    public static function hasPermission($permission) {
        if (!self::isLoggedIn()) {
            return false;
        }
        
        $role = self::getUserRole();
        
        // Admin has all permissions
        if ($role === 'admin') {
            return true;
        }
        
        // Define role-based permissions
        $permissions = [
            'sales' => [
                'view_orders', 'create_orders', 'edit_orders', 
                'view_customers', 'create_customers', 'edit_customers',
                'view_warehouse', 'view_reports'
            ],
            'customer' => [
                'view_own_orders', 'create_orders', 'view_profile', 'edit_profile'
            ]
        ];
        
        return isset($permissions[$role]) && in_array($permission, $permissions[$role]);
    }
    
    /**
     * Get session info for debugging
     */
    public static function getSessionInfo() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return [
            'user_id' => self::getUserId(),
            'role' => self::getUserRole(),
            'store_id' => self::getStoreId(),
            'login_time' => $_SESSION['login_time'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? null,
            'session_id' => session_id(),
            'expires_in' => self::getSessionTimeRemaining()
        ];
    }
    
    /**
     * Get remaining session time in seconds
     */
    public static function getSessionTimeRemaining() {
        if (!isset($_SESSION['last_activity'])) {
            return 0;
        }
        
        $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600;
        $remaining = $timeout - (time() - $_SESSION['last_activity']);
        
        return max(0, $remaining);
    }
    
    /**
     * Extend session
     */
    public static function extendSession() {
        if (self::isLoggedIn()) {
            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }
    
    /**
     * Get auth URL helper
     */
    private static function getAuthUrl() {
        // Get the base URL for auth directory
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $uri = $_SERVER['REQUEST_URI'];
        
        // Find the position of current directory in URI
        $path_parts = explode('/', trim($uri, '/'));
        $base_path = '';
        
        // Build path to auth directory
        foreach ($path_parts as $i => $part) {
            if (in_array($part, ['admin', 'sales', 'customer', 'warehouse', 'api'])) {
                break;
            }
            if (!empty($part)) {
                $base_path .= '/' . $part;
            }
        }
        
        return $protocol . '://' . $host . $base_path . '/auth';
    }
    
    /**
     * Get error URL helper
     */
    private static function getErrorUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $uri = $_SERVER['REQUEST_URI'];
        
        $path_parts = explode('/', trim($uri, '/'));
        $base_path = '';
        
        foreach ($path_parts as $i => $part) {
            if (in_array($part, ['admin', 'sales', 'customer', 'warehouse', 'api'])) {
                break;
            }
            if (!empty($part)) {
                $base_path .= '/' . $part;
            }
        }
        
        return $protocol . '://' . $host . $base_path . '/error';
    }
}

// Auto-extend session on AJAX requests
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    SessionManager::extendSession();
}
?>