<?php
// Utility functions for Alumpro.Az

class Utils {
    
    /**
     * Format phone number to international format
     */
    public static function formatPhone($phone) {
        // Remove all non-digit characters
        $phone = preg_replace('/\D/', '', $phone);
        
        // Handle Azerbaijan phone numbers
        if (strlen($phone) === 9 && !str_starts_with($phone, '0')) {
            // 9 digits, add country code
            return '+994' . $phone;
        } elseif (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            // 10 digits starting with 0, replace 0 with country code
            return '+994' . substr($phone, 1);
        } elseif (strlen($phone) === 12 && str_starts_with($phone, '994')) {
            // Already has country code
            return '+' . $phone;
        } elseif (strlen($phone) === 13 && str_starts_with($phone, '+994')) {
            // Already in correct format
            return $phone;
        } else {
            // Invalid format, try to salvage
            if (strlen($phone) >= 9) {
                return '+994' . substr($phone, -9);
            }
        }
        
        return $phone; // Return as-is if can't format
    }
    
    /**
     * Log user activity
     */
    public static function logActivity($user_id, $action, $description = '', $ip_address = null, $user_agent = null) {
        try {
            if (!class_exists('Database')) {
                require_once __DIR__ . '/../config/database.php';
            }
            
            $db = Database::getInstance();
            
            $ip_address = $ip_address ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $user_agent = $user_agent ?: ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
            
            $stmt = $db->query("INSERT INTO activity_log (user_id, action, description, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())", 
                              [$user_id, $action, $description, $ip_address, $user_agent]);
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Upload file with validation
     */
    public static function uploadFile($file, $upload_path, $allowed_types = null, $max_size = null) {
        try {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload error: ' . $file['error']);
            }
            
            $allowed_types = $allowed_types ?: ALLOWED_IMAGE_TYPES;
            $max_size = $max_size ?: MAX_FILE_SIZE;
            
            if ($file['size'] > $max_size) {
                throw new Exception('File too large. Maximum size: ' . ($max_size / 1024 / 1024) . 'MB');
            }
            
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($file_extension, $allowed_types)) {
                throw new Exception('Invalid file type. Allowed: ' . implode(', ', $allowed_types));
            }
            
            // Create upload directory if it doesn't exist
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0755, true);
            }
            
            // Generate unique filename
            $filename = uniqid() . '_' . time() . '.' . $file_extension;
            $target_path = $upload_path . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                return $filename;
            } else {
                throw new Exception('Failed to move uploaded file');
            }
            
        } catch (Exception $e) {
            error_log("File upload failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Delete file safely
     */
    public static function deleteFile($filepath) {
        try {
            if (file_exists($filepath) && is_file($filepath)) {
                return unlink($filepath);
            }
            return true;
        } catch (Exception $e) {
            error_log("File deletion failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sanitize input
     */
    public static function sanitize($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Generate random string
     */
    public static function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $string = '';
        
        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $string;
    }
    
    /**
     * Format date for display
     */
    public static function formatDate($date, $format = 'd.m.Y') {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '-';
        }
        
        return date($format, strtotime($date));
    }
    
    /**
     * Format datetime for display
     */
    public static function formatDateTime($datetime, $format = 'd.m.Y H:i') {
        return self::formatDate($datetime, $format);
    }
    
    /**
     * Calculate age from date
     */
    public static function calculateAge($birthdate) {
        $today = new DateTime();
        $birth = new DateTime($birthdate);
        return $today->diff($birth)->y;
    }
    
    /**
     * Validate email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate phone number (Azerbaijan format)
     */
    public static function validatePhone($phone) {
        $formatted = self::formatPhone($phone);
        return preg_match('/^\+994[0-9]{9}$/', $formatted);
    }
    
    /**
     * Get time ago string
     */
    public static function timeAgo($datetime) {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) {
            return 'indi';
        } elseif ($time < 3600) {
            return floor($time / 60) . ' dəqiqə əvvəl';
        } elseif ($time < 86400) {
            return floor($time / 3600) . ' saat əvvəl';
        } elseif ($time < 2592000) {
            return floor($time / 86400) . ' gün əvvəl';
        } elseif ($time < 31536000) {
            return floor($time / 2592000) . ' ay əvvəl';
        } else {
            return floor($time / 31536000) . ' il əvvəl';
        }
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIP() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Check if request is AJAX
     */
    public static function isAjax() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Send JSON response
     */
    public static function jsonResponse($data, $status_code = 200) {
        http_response_code($status_code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Redirect with message
     */
    public static function redirect($url, $message = null, $type = 'info') {
        if ($message) {
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $type;
        }
        
        header("Location: $url");
        exit;
    }
    
    /**
     * Get and clear flash message
     */
    public static function getFlashMessage() {
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            $type = $_SESSION['flash_type'] ?? 'info';
            
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_type']);
            
            return ['message' => $message, 'type' => $type];
        }
        
        return null;
    }
    
    /**
     * Generate order number
     */
    public static function generateOrderNumber() {
        $prefix = getSetting('order_number_prefix', 'ALM');
        $number = date('ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        return $prefix . $number;
    }
    
    /**
     * Calculate glass dimensions based on profile dimensions
     */
    public static function calculateGlassDimensions($profile_height, $profile_width, $reduction = null) {
        $reduction = $reduction ?: getSetting('glass_size_reduction', DEFAULT_GLASS_REDUCTION);
        
        return [
            'height' => max(0, $profile_height - $reduction),
            'width' => max(0, $profile_width - $reduction)
        ];
    }
}

// Additional helper functions

/**
 * Debug function (only in development)
 */
function dd($data) {
    if (ENVIRONMENT === 'development') {
        echo '<pre>';
        var_dump($data);
        echo '</pre>';
        exit;
    }
}

/**
 * Check if user has permission
 */
function hasPermission($permission) {
    return SessionManager::hasPermission($permission);
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return SessionManager::getUserId();
}

/**
 * Get current user role
 */
function getCurrentUserRole() {
    return SessionManager::getUserRole();
}

/**
 * Generate CSRF token field
 */
function csrfField() {
    $token = SessionManager::getCsrfToken();
    return "<input type='hidden' name='" . CSRF_TOKEN_NAME . "' value='$token'>";
}

/**
 * Verify CSRF token
 */
function verifyCsrf($token) {
    return SessionManager::verifyCsrfToken($token);
}
?>