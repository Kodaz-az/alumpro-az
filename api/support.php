<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

$db = new Database();
$response = ['success' => false];

try {
    if ($_GET['action'] === 'get_quick_questions') {
        $stmt = $db->query("SELECT keyword, response FROM support_keywords ORDER BY keyword");
        $questions = $stmt->fetchAll();
        
        $response = ['success' => true, 'questions' => $questions];
    }
    
    if ($_GET['action'] === 'identify_user') {
        if (SessionManager::isLoggedIn()) {
            $user_id = SessionManager::getUserId();
            $stmt = $db->query("SELECT full_name FROM users WHERE id = ?", [$user_id]);
            $user = $stmt->fetch();
            
            if ($user) {
                $response = ['success' => true, 'user' => ['name' => $user['full_name']]];
            }
        }
    }
    
    if ($_POST['action'] === 'send_message') {
        $message = trim($_POST['message']);
        $session_id = $_POST['session_id'] ?? '';
        $user_id = SessionManager::isLoggedIn() ? SessionManager::getUserId() : null;
        
        if (empty($message)) {
            throw new Exception('Mesaj boş ola bilməz');
        }
        
        // Save user message
        $stmt = $db->query("INSERT INTO support_conversations (session_id, user_id, message, is_from_user, created_at) VALUES (?, ?, ?, 1, NOW())", 
                           [$session_id, $user_id, $message]);
        
        // Check for keyword matches
        $stmt = $db->query("SELECT response FROM support_keywords WHERE ? LIKE CONCAT('%', keyword, '%') ORDER BY LENGTH(keyword) DESC LIMIT 1", 
                           [strtolower($message)]);
        $keyword_match = $stmt->fetch();
        
        $bot_response = '';
        $sales_person_connected = false;
        
        if ($keyword_match) {
            $bot_response = $keyword_match['response'];
        } else {
            // Check if sales person should be notified
            $urgent_keywords = ['təcili', 'problem', 'şikayət', 'satış meneceri'];
            $is_urgent = false;
            
            foreach ($urgent_keywords as $keyword) {
                if (strpos(strtolower($message), $keyword) !== false) {
                    $is_urgent = true;
                    break;
                }
            }
            
            if ($is_urgent) {
                // Notify sales person (placeholder for real-time notification)
                $sales_person_connected = true;
                $bot_response = 'Sorğunuz satış menecerimizə ötürüldü. Tezliklə cavab alacaqsınız.';
            } else {
                $bot_response = 'Sualınızı başa düşmədim. Zəhmət olmasa daha dəqiq yazın və ya aşağıdakı seçimlərdən birini istifadə edin.';
            }
        }
        
        // Save bot response
        if ($bot_response) {
            $stmt = $db->query("INSERT INTO support_conversations (session_id, user_id, message, response, is_from_user, created_at) VALUES (?, ?, ?, ?, 0, NOW())", 
                               [$session_id, $user_id, $message, $bot_response]);
        }
        
        $response = [
            'success' => true, 
            'response' => $bot_response,
            'sales_person_connected' => $sales_person_connected
        ];
    }
    
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

header('Content-Type: application/json');
echo json_encode($response);
?>