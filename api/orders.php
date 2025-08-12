<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireLogin();

$db = new Database();
$response = ['success' => false];

try {
    if ($_GET['action'] === 'get_statuses') {
        $input = json_decode(file_get_contents('php://input'), true);
        $order_ids = $input['order_ids'] ?? [];
        
        if (!empty($order_ids)) {
            $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
            $stmt = $db->query("SELECT id, status FROM orders WHERE id IN ($placeholders)", $order_ids);
            $orders = $stmt->fetchAll();
            
            $response = ['success' => true, 'orders' => $orders];
        }
    }
    
    if ($_POST['action'] === 'update_status') {
        $order_id = $_POST['order_id'];
        $new_status = $_POST['status'];
        $user_id = SessionManager::getUserId();
        
        // Check permissions
        $user_role = SessionManager::getUserRole();
        if ($user_role !== 'admin' && $user_role !== 'sales') {
            throw new Exception('Bu əməliyyat üçün icazəniz yoxdur');
        }
        
        $stmt = $db->query("UPDATE orders SET status = ? WHERE id = ?", [$new_status, $order_id]);
        
        Utils::logActivity($user_id, 'order_status_updated', "Sifariş statusu dəyişdirildi: $order_id -> $new_status");
        
        $response = ['success' => true, 'message' => 'Status yeniləndi'];
    }
    
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

header('Content-Type: application/json');
echo json_encode($response);
?>