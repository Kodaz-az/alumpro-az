<?php
// Updated TwilioManager with all required methods

// Include composer autoload if not already included
if (!class_exists('Twilio\Rest\Client')) {
    $autoload_paths = [
        __DIR__ . '/../vendor/autoload.php',           
        __DIR__ . '/../../vendor/autoload.php',       
        dirname(__DIR__, 2) . '/vendor/autoload.php', 
        $_SERVER['DOCUMENT_ROOT'] . '/../vendor/autoload.php'
    ];
    
    foreach ($autoload_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
    
    if (!class_exists('Twilio\Rest\Client')) {
        throw new Exception('Twilio SDK not found. Please install via Composer: composer require twilio/sdk');
    }
}

use Twilio\Rest\Client;

class TwilioManager {
    private $client;
    private $from_number;
    private $whatsapp_number;
    private $account_sid;
    private $auth_token;
    
    public function __construct() {
        // Get credentials from config or environment
        $this->account_sid = $this->getConfigValue('TWILIO_ACCOUNT_SID');
        $this->auth_token = $this->getConfigValue('TWILIO_AUTH_TOKEN');
        $this->from_number = $this->getConfigValue('TWILIO_PHONE_NUMBER');
        $this->whatsapp_number = $this->getConfigValue('TWILIO_WHATSAPP_NUMBER', 'whatsapp:+14155238886');
        
        if (empty($this->account_sid) || empty($this->auth_token)) {
            error_log('Twilio credentials not configured properly');
            throw new Exception('Twilio credentials not configured. Please set TWILIO_ACCOUNT_SID and TWILIO_AUTH_TOKEN');
        }
        
        try {
            $this->client = new Client($this->account_sid, $this->auth_token);
        } catch (Exception $e) {
            error_log('Twilio client initialization failed: ' . $e->getMessage());
            throw new Exception('Failed to initialize Twilio client: ' . $e->getMessage());
        }
    }
    
    /**
     * Get configuration value from multiple sources
     */
    private function getConfigValue($key, $default = '') {
        // Try environment variable first
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        // Try PHP constant
        if (defined($key)) {
            return constant($key);
        }
        
        // Try database settings
        try {
            require_once __DIR__ . '/../config/database.php';
            $db = Database::getInstance();
            $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = ?", [strtolower($key)]);
            $result = $stmt->fetch();
            if ($result) {
                return $result['setting_value'];
            }
        } catch (Exception $e) {
            // Database not available, continue with default
        }
        
        return $default;
    }
    
    /**
     * Send SMS message
     */
    public function sendSMS($to, $message) {
        try {
            if (empty($this->from_number)) {
                throw new Exception('SMS phone number not configured');
            }
            
            $to = $this->formatPhoneNumber($to);
            
            $result = $this->client->messages->create($to, [
                'from' => $this->from_number,
                'body' => $message
            ]);
            
            $this->logMessage($to, $message, 'sent', $result->sid, 'sms');
            
            return [
                'success' => true,
                'message_sid' => $result->sid,
                'status' => $result->status,
                'message' => 'SMS sent successfully'
            ];
            
        } catch (Exception $e) {
            $this->logMessage($to ?? 'unknown', $message, 'failed', null, 'sms', $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to send SMS'
            ];
        }
    }
    
    /**
     * Send verification code via SMS
     */
    public function sendVerificationCode($phone, $code, $type = 'registration') {
        $templates = [
            'registration' => "Alumpro.Az təsdiq kodu: {code}\n\nBu kod 10 dəqiqə etibarlıdır.\nKodu heç kimlə paylaşmayın.\n\nAlumpro.Az",
            'login' => "Alumpro.Az giriş təsdiq kodu: {code}\n\nBu kod 10 dəqiqə etibarlıdır.\n\nAlumpro.Az",
            'password_reset' => "Alumpro.Az şifrə bərpa kodu: {code}\n\nBu kod 15 dəqiqə etibarlıdır.\nƏgər bu sifarişi verməmişsinizsə, bu mesajı nəzərə almayın.\n\nAlumpro.Az"
        ];
        
        $template = $templates[$type] ?? $templates['registration'];
        $message = str_replace('{code}', $code, $template);
        
        return $this->sendSMS($phone, $message);
    }
    
    /**
     * Send password reset code via SMS
     */
    public function sendPasswordResetCode($phone, $code) {
        return $this->sendVerificationCode($phone, $code, 'password_reset');
    }
    
    /**
     * Send WhatsApp message
     */
    public function sendWhatsAppMessage($to, $message, $media_url = null) {
        try {
            $to = $this->formatWhatsAppNumber($to);
            
            $messageData = [
                'from' => $this->whatsapp_number,
                'body' => $message
            ];
            
            if ($media_url) {
                $messageData['mediaUrl'] = [$media_url];
            }
            
            $result = $this->client->messages->create($to, $messageData);
            
            $this->logMessage($to, $message, 'sent', $result->sid, 'whatsapp');
            
            return [
                'success' => true,
                'message_sid' => $result->sid,
                'status' => $result->status,
                'message' => 'WhatsApp message sent successfully'
            ];
            
        } catch (Exception $e) {
            $this->logMessage($to ?? 'unknown', $message, 'failed', null, 'whatsapp', $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to send WhatsApp message'
            ];
        }
    }
    
    /**
     * Send WhatsApp template message
     */
    public function sendWhatsAppTemplate($to, $template_name, $template_params = []) {
        try {
            $to = $this->formatWhatsAppNumber($to);
            
            $messageData = [
                'from' => $this->whatsapp_number,
                'contentSid' => $template_name
            ];
            
            if (!empty($template_params)) {
                $messageData['contentVariables'] = json_encode($template_params);
            }
            
            $result = $this->client->messages->create($to, $messageData);
            
            $this->logMessage($to, "Template: $template_name", 'sent', $result->sid, 'whatsapp');
            
            return [
                'success' => true,
                'message_sid' => $result->sid,
                'status' => $result->status
            ];
            
        } catch (Exception $e) {
            $this->logMessage($to ?? 'unknown', "Template: $template_name", 'failed', null, 'whatsapp', $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send order confirmation message
     */
    public function sendOrderConfirmation($customer_phone, $order_number, $total_amount, $customer_name) {
        $message = "Salam {$customer_name}! 🎉\n\n";
        $message .= "Sifarişiniz uğurla qəbul edildi:\n";
        $message .= "📋 Sifariş nömrəsi: {$order_number}\n";
        $message .= "💰 Məbləğ: {$total_amount}\n\n";
        $message .= "Sifarişinizin hazırlanma prosesi başladı. Yeniliklərdən xəbərdar olmaq üçün bu nömrədə qalın.\n\n";
        $message .= "Təşəkkür edirik! 🙏\n";
        $message .= "Alumpro.Az";
        
        return $this->sendWhatsAppMessage($customer_phone, $message);
    }
    
    /**
     * Send order status update
     */
    public function sendOrderStatusUpdate($customer_phone, $order_number, $status, $customer_name) {
        $status_messages = [
            'pending' => "Gözləyir - sifarişiniz qəbul edildi 📋",
            'in_production' => "İstehsalat mərhələsinə keçdi 🔨",
            'completed' => "Tamamlandı və təhvil üçün hazırdır ✅",
            'cancelled' => "Ləğv edildi ❌"
        ];
        
        $status_text = $status_messages[$status] ?? 'Status yeniləndi';
        
        $message = "Salam {$customer_name}!\n\n";
        $message .= "📋 Sifariş nömrəsi: {$order_number}\n";
        $message .= "🔄 Status: {$status_text}\n\n";
        
        if ($status === 'completed') {
            $message .= "Sifarişinizi mağazamızdan təhvil ala bilərsiniz.\n";
            $message .= "📍 Ünvan: Bakı şəhəri\n";
            $message .= "📞 Əlaqə: +994 50 123 45 67\n\n";
        } elseif ($status === 'in_production') {
            $message .= "Sifarişiniz hazırda istehsal mərhələsindədir. Tezliklə hazır olacaq.\n\n";
        }
        
        $message .= "Alumpro.Az";
        
        return $this->sendWhatsAppMessage($customer_phone, $message);
    }
    
    /**
     * Send reminder message
     */
    public function sendReminderMessage($customer_phone, $customer_name, $reminder_type) {
        $messages = [
            'order_pickup' => [
                'title' => 'Sifariş Təhvil Xatırlatması',
                'text' => "Sifarişiniz hazırdır və təhvil gözləyir. Zəhmət olmasa tezliklə mağazamıza müraciət edin."
            ],
            'feedback' => [
                'title' => 'Rəy Xahişi',
                'text' => "Xidmətimizlə bağlı rəyinizi bildirin. Sizin məmnuniyyətiniz bizim üçün çox vacibdir."
            ],
            'new_products' => [
                'title' => 'Yeni Məhsullar',
                'text' => "Yeni aluminum profil və şüşə sistemlərimizlə tanış olun. Məlumat üçün bizimlə əlaqə saxlayın."
            ]
        ];
        
        $reminder = $messages[$reminder_type] ?? $messages['feedback'];
        
        $message = "Salam {$customer_name}!\n\n";
        $message .= "🔔 {$reminder['title']}\n\n";
        $message .= $reminder['text'] . "\n\n";
        $message .= "📞 Əlaqə: +994 50 123 45 67\n";
        $message .= "Alumpro.Az";
        
        return $this->sendWhatsAppMessage($customer_phone, $message);
    }
    
    /**
     * Send promotional message
     */
    public function sendPromotionalMessage($customer_phone, $customer_name, $promotion_details) {
        $message = "Salam {$customer_name}! 🎊\n\n";
        $message .= "🔥 Xüsusi Təklif!\n\n";
        $message .= $promotion_details . "\n\n";
        $message .= "Bu fürsəti qaçırmayın! Ətraflı məlumat üçün bizimlə əlaqə saxlayın.\n\n";
        $message .= "📞 +994 50 123 45 67\n";
        $message .= "🌐 alumpro.az\n\n";
        $message .= "Alumpro.Az";
        
        return $this->sendWhatsAppMessage($customer_phone, $message);
    }
    
    /**
     * Format phone number for SMS (international format)
     */
    private function formatPhoneNumber($phone) {
        // Remove all non-digit characters
        $phone = preg_replace('/\D/', '', $phone);
        
        // Handle Azerbaijan phone numbers
        if (strlen($phone) === 9 && !str_starts_with($phone, '0')) {
            // 9 digits, add country code
            $phone = '994' . $phone;
        } elseif (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            // 10 digits starting with 0, replace 0 with country code
            $phone = '994' . substr($phone, 1);
        } elseif (strlen($phone) === 12 && str_starts_with($phone, '994')) {
            // Already has country code
            // Keep as is
        } else {
            // Invalid format, try to salvage
            if (strlen($phone) >= 9) {
                $phone = '994' . substr($phone, -9);
            }
        }
        
        return '+' . $phone;
    }
    
    /**
     * Format phone number for WhatsApp
     */
    private function formatWhatsAppNumber($phone) {
        $phone = $this->formatPhoneNumber($phone);
        return 'whatsapp:' . $phone;
    }
    
    /**
     * Log message to database
     */
    private function logMessage($to, $message, $status, $message_sid = null, $type = 'sms', $error = null) {
        try {
            require_once __DIR__ . '/../config/database.php';
            $db = Database::getInstance();
            
            $db->query("INSERT INTO messaging_log (phone_number, message, type, status, message_sid, error_message, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())", 
                      [$to, $message, $type, $status, $message_sid, $error]);
                      
        } catch (Exception $e) {
            error_log("Failed to log Twilio message: " . $e->getMessage());
        }
    }
    
    /**
     * Get message delivery status
     */
    public function getMessageStatus($message_sid) {
        try {
            $message = $this->client->messages($message_sid)->fetch();
            
            return [
                'success' => true,
                'status' => $message->status,
                'error_code' => $message->errorCode,
                'error_message' => $message->errorMessage,
                'date_sent' => $message->dateSent ? $message->dateSent->format('Y-m-d H:i:s') : null,
                'date_updated' => $message->dateUpdated ? $message->dateUpdated->format('Y-m-d H:i:s') : null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Handle incoming webhook from Twilio
     */
    public function handleWebhook($webhook_data) {
        try {
            $message_sid = $webhook_data['MessageSid'] ?? '';
            $status = $webhook_data['MessageStatus'] ?? '';
            $from = $webhook_data['From'] ?? '';
            $body = $webhook_data['Body'] ?? '';
            
            // Update message status in database
            if ($message_sid && $status) {
                require_once __DIR__ . '/../config/database.php';
                $db = Database::getInstance();
                $db->query("UPDATE messaging_log SET status = ?, updated_at = NOW() WHERE message_sid = ?", 
                          [$status, $message_sid]);
            }
            
            // Handle incoming messages (auto-replies)
            if ($from && $body) {
                $this->handleIncomingMessage($from, $body);
            }
            
            return ['success' => true, 'message' => 'Webhook processed successfully'];
            
        } catch (Exception $e) {
            error_log("Webhook processing failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Handle incoming messages with auto-replies
     */
    private function handleIncomingMessage($from, $body) {
        try {
            $phone = str_replace(['whatsapp:', '+'], '', $from);
            $message = trim(strtolower($body));
            
            // Get auto-replies from database or use defaults
            $auto_replies = $this->getAutoReplies();
            
            foreach ($auto_replies as $keyword => $reply) {
                if (strpos($message, $keyword) !== false) {
                    if (str_starts_with($from, 'whatsapp:')) {
                        $this->sendWhatsAppMessage($phone, $reply);
                    } else {
                        $this->sendSMS($phone, $reply);
                    }
                    return;
                }
            }
            
            // If no keyword matched, send general help message
            $help_message = "Salam! Alumpro.Az-a müraciət etdiyiniz üçün təşəkkür edirik.\n\n";
            $help_message .= "Sizə aşağıdakı mövzularda kömək edə bilərik:\n";
            $help_message .= "• Qiymətlər\n• Sifariş\n• Ünvan\n• İş vaxtı\n• Məhsullar\n\n";
            $help_message .= "Birbaşa əlaqə üçün: +994 50 123 45 67";
            
            if (str_starts_with($from, 'whatsapp:')) {
                $this->sendWhatsAppMessage($phone, $help_message);
            } else {
                $this->sendSMS($phone, $help_message);
            }
            
        } catch (Exception $e) {
            error_log("Auto-reply failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get auto-reply keywords and responses
     */
    private function getAutoReplies() {
        $default_replies = [
            'salam' => 'Salam! Alumpro.Az-a xoş gəlmisiniz! Sizə necə kömək edə bilərəm?',
            'sağol' => 'Rica edirik! Həmişə xidmətinizdəyik. 😊',
            'təşəkkür' => 'Rica edirik! Həmişə xidmətinizdəyik. 😊',
            'qiymət' => 'Qiymətlər haqqında məlumat üçün zəhmət olmasa +994 50 123 45 67 nömrəsinə zəng edin.',
            'sifariş' => 'Sifariş vermək üçün bizimlə əlaqə saxlayın: +994 50 123 45 67',
            'ünvan' => 'Ünvanımız: Bakı şəhəri. Dəqiq ünvan üçün zəng edin: +994 50 123 45 67',
            'vaxt' => 'İş saatlarımız: Bazar ertəsi - Şənbə, 09:00 - 18:00',
            'məhsul' => 'Aluminum profil və şüşə sistemləri haqqında məlumat üçün veb saytımızı ziyarət edin: alumpro.az',
            'kömək' => 'Bizə müraciət etdiyiniz üçün təşəkkür edirik! Kömək üçün: +994 50 123 45 67'
        ];
        
        try {
            require_once __DIR__ . '/../config/database.php';
            $db = Database::getInstance();
            $stmt = $db->query("SELECT keyword, response FROM whatsapp_auto_replies WHERE is_active = 1");
            $custom_replies = [];
            
            while ($row = $stmt->fetch()) {
                $custom_replies[$row['keyword']] = $row['response'];
            }
            
            return array_merge($default_replies, $custom_replies);
            
        } catch (Exception $e) {
            error_log("Failed to load custom auto-replies: " . $e->getMessage());
            return $default_replies;
        }
    }
    
    /**
     * Test Twilio connection
     */
    public function testConnection() {
        try {
            // Try to fetch account info
            $account = $this->client->api->v2010->accounts($this->account_sid)->fetch();
            
            return [
                'success' => true,
                'message' => 'Twilio connection successful',
                'account_sid' => $account->sid,
                'account_status' => $account->status,
                'account_type' => $account->type
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Twilio connection failed'
            ];
        }
    }
}

// Helper function for backwards compatibility
if (!function_exists('createTwilioManager')) {
    function createTwilioManager() {
        try {
            return new TwilioManager();
        } catch (Exception $e) {
            error_log("Failed to create TwilioManager: " . $e->getMessage());
            return null;
        }
    }
}
?>