<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireLogin();

$db = new Database();
$user_role = SessionManager::getUserRole();
$store_id = SessionManager::getStoreId();

try {
    $stats = Utils::getStats($db, $user_role, $store_id);
    
    // Add real-time data
    $current_time = date('Y-m-d H:i:s');
    $stats['last_updated'] = $current_time;
    
    // Get active users count
    $stmt = $db->query("SELECT COUNT(*) as active_users FROM user_sessions WHERE is_active = 1 AND last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $stats['active_users'] = $stmt->fetch()['active_users'];
    
    $response = ['success' => true, 'stats' => $stats];
    
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

header('Content-Type: application/json');
echo json_encode($response);
?>