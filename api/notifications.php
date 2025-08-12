<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireLogin();

$db = new Database();
$user_id = SessionManager::getUserId();
$response = ['success' => false];

try {
    if ($_GET['action'] === 'get_notifications') {
        $limit = $_GET['limit'] ?? 10;
        $offset = $_GET['offset'] ?? 0;
        
        $stmt = $db->query("SELECT * FROM notifications 
                           WHERE user_id = ? 
                           ORDER BY created_at DESC 
                           LIMIT ? OFFSET ?", 
                           [$user_id, $limit, $offset]);
        $notifications = $stmt->fetchAll();
        
        $stmt = $db->query("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?", [$user_id]);
        $total = $stmt->fetch()['total'];
        
        $stmt = $db->query("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0", [$user_id]);
        $unread = $stmt->fetch()['unread'];
        
        $response = [
            'success' => true,
            'notifications' => $notifications,
            'total' => $total,
            'unread' => $unread
        ];
    }
    
    if ($_POST['action'] === 'mark_as_read') {
        $notification_id = $_POST['notification_id'] ?? null;
        
        if ($notification_id) {
            $stmt = $db->query("UPDATE notifications SET is_read = 1, read_at = NOW() 
                               WHERE id = ? AND user_id = ?", 
                               [$notification_id, $user_id]);
        } else {
            // Mark all as read
            $stmt = $db->query("UPDATE notifications SET is_read = 1, read_at = NOW() 
                               WHERE user_id = ? AND is_read = 0", 
                               [$user_id]);
        }
        
        $response = ['success' => true, 'message' => 'Bildiriş oxundu'];
    }
    
    if ($_POST['action'] === 'delete_notification') {
        $notification_id = $_POST['notification_id'];
        
        $stmt = $db->query("DELETE FROM notifications WHERE id = ? AND user_id = ?", 
                          [$notification_id, $user_id]);
        
        $response = ['success' => true, 'message' => 'Bildiriş silindi'];
    }
    
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

header('Content-Type: application/json');
echo json_encode($response);

// Helper function to create notifications
function createNotification($user_id, $title, $message, $type = 'info', $action_url = null) {
    $db = new Database();
    
    try {
        $stmt = $db->query("INSERT INTO notifications (user_id, title, message, type, action_url, created_at) VALUES (?, ?, ?, ?, ?, NOW())", 
                          [$user_id, $title, $message, $type, $action_url]);
        
        return $db->lastInsertId();
    } catch (Exception $e) {
        error_log("Notification creation failed: " . $e->getMessage());
        return false;
    }
}
?>