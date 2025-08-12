<?php
// Main Configuration File for Alumpro.Az System
// Updated to fix Database class issues

// Environment
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'development'); // development, production
}

// Database Configuration
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'ezizov04_db');
    define('DB_PASS', 'ezizovs074');
    define('DB_NAME', 'ezizov04_alumpro');
    define('DB_CHARSET', 'utf8mb4');
}

// Site Configuration
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'Alumpro.Az');
    define('SITE_URL', 'https://alumpro.az');
    define('SITE_EMAIL', 'info@alumpro.az');
    define('SITE_PHONE', '+994 55 244 70 44');
}

// Paths
if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', 'uploads/');
    define('PROFILE_IMAGES_PATH', 'uploads/profiles/');
    define('GALLERY_IMAGES_PATH', 'uploads/gallery/');
    define('LOGS_PATH', 'logs/');
}

// Session Configuration
if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 3600); // 1 hour
    define('SESSION_TIMEOUT', 3600); // 1 hour
    define('SESSION_NAME', 'alumpro_session');
}

// Security
if (!defined('CSRF_TOKEN_NAME')) {
    define('CSRF_TOKEN_NAME', 'csrf_token');
    define('PASSWORD_MIN_LENGTH', 6);
    define('MAX_LOGIN_ATTEMPTS', 5);
    define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
}

// File Upload
if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
    define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);
}

// Twilio Configuration
if (!defined('TWILIO_ACCOUNT_SID')) {
    define('TWILIO_ACCOUNT_SID', getenv('TWILIO_ACCOUNT_SID') ?: 'AC61dec6a80dcf8e405b19217cb1b01d6c');
    define('TWILIO_AUTH_TOKEN', getenv('TWILIO_AUTH_TOKEN') ?: '4ee4c459cc905d397c81b2f21f276b15');
    define('TWILIO_PHONE_NUMBER', getenv('TWILIO_PHONE_NUMBER') ?: '+17628157642');
    define('TWILIO_WHATSAPP_NUMBER', getenv('TWILIO_WHATSAPP_NUMBER') ?: 'whatsapp:+17628157642');
}

// OneSignal Configuration  
if (!defined('ONESIGNAL_APP_ID')) {
    define('ONESIGNAL_APP_ID', getenv('ONESIGNAL_APP_ID') ?: 'c0076e07-d2e3-47d0-a45b-f2cf6343c8e3');
    define('ONESIGNAL_API_KEY', getenv('ONESIGNAL_API_KEY') ?: 'os_v2_app_yadw4b6s4nd5bjc36lhwgq6i4pi5gqleji2udn5yyxv2hsnuncrr4hh7iimghoq326ie2rfsxy6xdubnm6xduvieoabmlgmu3q2hz3a');
}

// Email Configuration
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.gmail.com');
    define('SMTP_PORT', 587);
    define('SMTP_USERNAME', '');
    define('SMTP_PASSWORD', '');
    define('SMTP_ENCRYPTION', 'tls');
}

// Default Settings
if (!defined('DEFAULT_CURRENCY')) {
    define('DEFAULT_CURRENCY', 'AZN');
    define('DEFAULT_TIMEZONE', 'Asia/Baku');
    define('DEFAULT_LANGUAGE', 'az');
}

// Glass calculation
if (!defined('DEFAULT_GLASS_REDUCTION')) {
    define('DEFAULT_GLASS_REDUCTION', 4); // mm
}

// Rate Limiting
if (!defined('API_RATE_LIMIT')) {
    define('API_RATE_LIMIT', 100); // requests per hour
    define('SMS_RATE_LIMIT', 5); // SMS per hour per phone
}

// Error Reporting
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOGS_PATH . 'error.log');
}

// Set timezone
date_default_timezone_set(DEFAULT_TIMEZONE);

// Include Database class first (before other classes that might need it)
require_once __DIR__ . '/database.php';

// Helper function to format currency
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount, $currency = DEFAULT_CURRENCY) {
        return number_format($amount, 2, '.', ',') . ' ' . $currency;
    }
}

// Helper function to check if feature is enabled
if (!function_exists('isFeatureEnabled')) {
    function isFeatureEnabled($feature) {
        try {
            $db = Database::getInstance();
            $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = ?", [$feature]);
            $result = $stmt->fetch();
            
            if ($result) {
                return (bool)$result['setting_value'];
            }
            
            // Default values for common features
            $defaults = [
                'sms_verification_enabled' => true,
                'whatsapp_enabled' => true,
                'login_notifications_enabled' => false,
                'email_notifications_enabled' => true,
                'maintenance_mode' => false,
                'registration_enabled' => true,
                'guest_checkout' => false
            ];
            
            return $defaults[$feature] ?? false;
            
        } catch (Exception $e) {
            error_log("isFeatureEnabled error for '$feature': " . $e->getMessage());
            
            // Fallback defaults when database is not available
            $fallback_defaults = [
                'sms_verification_enabled' => true,
                'whatsapp_enabled' => false, // Disable by default if DB not available
                'login_notifications_enabled' => false,
                'email_notifications_enabled' => false,
                'maintenance_mode' => false,
                'registration_enabled' => true,
                'guest_checkout' => false
            ];
            
            return $fallback_defaults[$feature] ?? false;
        }
    }
}

// Helper function to get setting value
if (!function_exists('getSetting')) {
    function getSetting($key, $default = null) {
        try {
            $db = Database::getInstance();
            $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
            $result = $stmt->fetch();
            
            return $result ? $result['setting_value'] : $default;
            
        } catch (Exception $e) {
            error_log("getSetting error for '$key': " . $e->getMessage());
            return $default;
        }
    }
}

// Helper function to set setting value
if (!function_exists('setSetting')) {
    function setSetting($key, $value, $description = '') {
        try {
            $db = Database::getInstance();
            $stmt = $db->query("INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?) 
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", 
                              [$key, $value, $description]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("setSetting error for '$key': " . $e->getMessage());
            return false;
        }
    }
}

// Helper function to check if SMS is configured
if (!function_exists('isSMSConfigured')) {
    function isSMSConfigured() {
        $account_sid = getSetting('twilio_account_sid', TWILIO_ACCOUNT_SID);
        $auth_token = getSetting('twilio_auth_token', TWILIO_AUTH_TOKEN);
        
        return !empty($account_sid) && !empty($auth_token);
    }
}

// Helper function to check if WhatsApp is configured
if (!function_exists('isWhatsAppConfigured')) {
    function isWhatsAppConfigured() {
        return isSMSConfigured(); // Same credentials needed
    }
}

// Auto-load classes
if (!function_exists('alumpro_autoload')) {
    function alumpro_autoload($class_name) {
        $directories = [
            __DIR__ . '/../includes/',
            __DIR__ . '/../classes/',
        ];
        
        // Try to load vendor autoload first
        $vendor_autoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($vendor_autoload)) {
            require_once $vendor_autoload;
        }
        
        foreach ($directories as $directory) {
            $file = $directory . $class_name . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
    
    spl_autoload_register('alumpro_autoload');
}

// Create necessary directories if they don't exist
$directories = [
    UPLOAD_PATH,
    PROFILE_IMAGES_PATH,
    GALLERY_IMAGES_PATH,
    LOGS_PATH
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Initialize error log file
$error_log_file = LOGS_PATH . 'error.log';
if (!file_exists($error_log_file)) {
    @touch($error_log_file);
    @chmod($error_log_file, 0644);
}

// Set custom error handler for production
if (ENVIRONMENT === 'production') {
    set_error_handler(function($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $error_message = "[" . date('Y-m-d H:i:s') . "] ";
        $error_message .= "Error: $message in $file on line $line\n";
        
        error_log($error_message, 3, LOGS_PATH . 'error.log');
        
        // For fatal errors, show friendly message
        if ($severity === E_ERROR) {
            if (!headers_sent()) {
                header('HTTP/1.1 500 Internal Server Error');
                if (file_exists(__DIR__ . '/../error/500.php')) {
                    include __DIR__ . '/../error/500.php';
                } else {
                    echo "System temporarily unavailable. Please try again later.";
                }
            }
            exit;
        }
        
        return true;
    });
}

// Global exception handler
set_exception_handler(function($exception) {
    $error_message = "[" . date('Y-m-d H:i:s') . "] ";
    $error_message .= "Uncaught Exception: " . $exception->getMessage();
    $error_message .= " in " . $exception->getFile() . " on line " . $exception->getLine() . "\n";
    $error_message .= "Stack trace:\n" . $exception->getTraceAsString() . "\n";
    
    error_log($error_message, 3, LOGS_PATH . 'error.log');
    
    if (ENVIRONMENT === 'production') {
        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
            if (file_exists(__DIR__ . '/../error/500.php')) {
                include __DIR__ . '/../error/500.php';
            } else {
                echo "System temporarily unavailable. Please try again later.";
            }
        }
    } else {
        echo "<h1>Uncaught Exception</h1>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($exception->getFile()) . "</p>";
        echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>";
        echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
    }
    exit;
});

// Initialize default settings if database is available
try {
    $db = Database::getInstance();
    
    // Check if settings table exists and has data
    if ($db->tableExists('settings')) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM settings");
        $count = $stmt->fetch()['count'];
        
        // If settings table is empty, populate with defaults
        if ($count == 0) {
            $default_settings = [
                ['sms_verification_enabled', '1', 'Enable SMS verification for registration'],
                ['whatsapp_enabled', '1', 'Enable WhatsApp notifications'],
                ['login_notifications_enabled', '0', 'Send login notifications via WhatsApp'],
                ['email_notifications_enabled', '1', 'Enable email notifications'],
                ['maintenance_mode', '0', 'Enable maintenance mode'],
                ['registration_enabled', '1', 'Allow new user registrations'],
                ['guest_checkout', '0', 'Allow guest checkout'],
                ['company_name', 'Alumpro.Az', 'Company name'],
                ['company_phone', '+994 50 123 45 67', 'Company phone number'],
                ['company_email', 'info@alumpro.az', 'Company email address'],
                ['order_number_prefix', 'ALM', 'Order number prefix'],
                ['glass_size_reduction', '4', 'Glass size reduction in mm'],
                ['default_currency', 'AZN', 'Default currency'],
                ['system_version', '1.0.0', 'Current system version'],
                ['twilio_account_sid', '', 'Twilio Account SID'],
                ['twilio_auth_token', '', 'Twilio Auth Token'],
                ['twilio_phone_number', '', 'Twilio SMS Phone Number'],
                ['twilio_whatsapp_number', 'whatsapp:+14155238886', 'Twilio WhatsApp Number']
            ];
            
            foreach ($default_settings as $setting) {
                $db->query("INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)", $setting);
            }
        }
    }
} catch (Exception $e) {
    // Database not available or settings table doesn't exist yet
    error_log("Could not initialize default settings: " . $e->getMessage());
}
?>