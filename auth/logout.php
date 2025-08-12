<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';

$user_id = SessionManager::getUserId();

if ($user_id) {
    $db = new Database();
    
    // Log the logout activity
    Utils::logActivity($user_id, 'user_logout', 'İstifadəçi sistemdən çıxdı');
    
    // Deactivate user sessions
    $db->query("UPDATE user_sessions SET is_active = 0 WHERE user_id = ?", [$user_id]);
}

// Destroy session
SessionManager::logout();

// Redirect to login page
header('Location: ' . SITE_URL . '/auth/login.php?message=logged_out');
exit;
?>