<?php
// Updated Utils class with WhatsApp integration

class Utils {
    // ... existing methods ...

    /**
     * Send WhatsApp notification (updated)
     */
    public static function sendWhatsAppNotification($phone, $message) {
        try {
            // Check if WhatsApp is enabled
            $db = new Database();
            $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'whatsapp_enabled'");
            $setting = $stmt->fetch();
            
            if (!$setting || $setting['setting_value'] !== '1') {
                return false;
            }
            
            // Check rate limits
            if (!WhatsAppHelper::checkRateLimit($phone)) {
                error_log("WhatsApp rate limit exceeded for: $phone");
                return false;
            }
            
            // Check business hours setting
            $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'whatsapp_business_hours_only'");
            $business_hours_only = $stmt->fetch();
            
            if ($business_hours_only && $business_hours_only['setting_value'] === '1') {
                if (!WhatsAppHelper::isBusinessHours()) {
                    // Queue for later
                    return WhatsAppHelper::queueMessage($phone, $message);
                }
            }
            
            // Send immediately
            $twilio = new TwilioManager();
            $result = $twilio->sendWhatsAppMessage($phone, $message);
            
            return $result['success'];
            
        } catch (Exception $e) {
            error_log("WhatsApp notification failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process queued WhatsApp messages
     */
    public static function processWhatsAppQueue() {
        try {
            $db = new Database();
            
            // Get messages ready to send
            $stmt = $db->query("SELECT * FROM whatsapp_queue 
                                WHERE status = 'queued' 
                                AND scheduled_at <= NOW() 
                                AND attempts < 3 
                                ORDER BY scheduled_at ASC 
                                LIMIT 50");
            $messages = $stmt->fetchAll();
            
            $twilio = new TwilioManager();
            $processed = 0;
            
            foreach ($messages as $message) {
                // Update attempts
                $db->query("UPDATE whatsapp_queue SET attempts = attempts + 1 WHERE id = ?", [$message['id']]);
                
                $result = $twilio->sendWhatsAppMessage($message['phone_number'], $message['message']);
                
                if ($result['success']) {
                    $db->query("UPDATE whatsapp_queue SET status = 'sent', sent_at = NOW() WHERE id = ?", [$message['id']]);
                    $processed++;
                } else {
                    if ($message['attempts'] >= 2) {
                        $db->query("UPDATE whatsapp_queue SET status = 'failed' WHERE id = ?", [$message['id']]);
                    }
                }
                
                // Small delay to avoid rate limiting
                usleep(500000); // 0.5 second
            }
            
            return $processed;
            
        } catch (Exception $e) {
            error_log("WhatsApp queue processing failed: " . $e->getMessage());
            return 0;
        }
    }
}