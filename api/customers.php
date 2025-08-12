<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireSales();

$db = new Database();
$response = ['success' => false];

try {
    if ($_GET['action'] === 'search') {
        $query = trim($_GET['q'] ?? '');
        
        if (strlen($query) >= 2) {
            $searchTerm = "%$query%";
            $stmt = $db->query("SELECT c.*, COUNT(o.id) as total_orders, COALESCE(SUM(o.total_amount), 0) as total_spent
                                FROM customers c 
                                LEFT JOIN orders o ON c.id = o.customer_id
                                WHERE c.contact_person LIKE ? OR c.phone LIKE ? OR c.company_name LIKE ?
                                GROUP BY c.id
                                ORDER BY c.contact_person
                                LIMIT 10", 
                                [$searchTerm, $searchTerm, $searchTerm]);
            
            $customers = $stmt->fetchAll();
            $response = ['success' => true, 'customers' => $customers];
        }
    }
    
    if ($_POST['action'] === 'add_customer') {
        $contact_person = trim($_POST['contact_person']);
        $phone = Utils::formatPhone(trim($_POST['phone']));
        $email = trim($_POST['email'] ?? '');
        $company_name = trim($_POST['company_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        if (empty($contact_person) || empty($phone)) {
            throw new Exception('Ad və telefon mütləqdir');
        }
        
        // Check if phone already exists
        $stmt = $db->query("SELECT id FROM customers WHERE phone = ?", [$phone]);
        if ($stmt->fetch()) {
            throw new Exception('Bu telefon nömrəsi artıq mövcuddur');
        }
        
        $db->getConnection()->beginTransaction();
        
        // Create user account
        $username = 'customer_' . substr($phone, -8);
        $password = substr(md5($phone), 0, 8); // Simple password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->query("INSERT INTO users (username, phone, email, password, full_name, role, is_verified) VALUES (?, ?, ?, ?, ?, 'customer', 1)", 
                           [$username, $phone, $email, $hashed_password, $contact_person]);
        
        $user_id = $db->lastInsertId();
        
        // Create customer profile
        $stmt = $db->query("INSERT INTO customers (user_id, contact_person, phone, email, company_name, address) VALUES (?, ?, ?, ?, ?, ?)", 
                           [$user_id, $contact_person, $phone, $email, $company_name, $address]);
        
        $customer_id = $db->lastInsertId();
        
        $db->getConnection()->commit();
        
        // Send WhatsApp message with login info (placeholder)
        $message = "Salam {$contact_person}! Alumpro.Az sistemində hesabınız yaradıldı. İstifadəçi adı: {$username}, Şifrə: {$password}. Sayt: " . SITE_URL;
        // Utils::sendWhatsAppMessage($phone, $message);
        
        $response = [
            'success' => true, 
            'customer' => [
                'id' => $customer_id,
                'contact_person' => $contact_person,
                'phone' => $phone
            ]
        ];
    }
    
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

header('Content-Type: application/json');
echo json_encode($response);
?>