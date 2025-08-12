<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/twilio.php';
require_once '../includes/functions.php';

// Verify webhook signature for security
function verifyTwilioSignature($url, $data, $signature) {
    $expectedSignature = base64_encode(hash_hmac('sha1', $url . $data, TWILIO_AUTH_TOKEN, true));
    return hash_equals($expectedSignature, $signature);
}

try {
    // Get the raw POST data
    $input = file_get_contents('php://input');
    $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $signature = $_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? '';
    
    // Verify the signature (recommended for production)
    if (ENVIRONMENT === 'production' && !verifyTwilioSignature($url, $input, $signature)) {
        http_response_code(403);
        exit('Forbidden');
    }
    
    // Parse the webhook data
    $webhook_data = $_POST;
    
    $db = new Database();
    $twilio = new TwilioManager();
    
    // Handle the webhook
    $result = $twilio->handleWebhook($webhook_data);
    
    // Log webhook activity
    Utils::logActivity(0, 'twilio_webhook', 'WhatsApp webhook received', json_encode($webhook_data));
    
    // Respond with success
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log('Twilio webhook error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
?>