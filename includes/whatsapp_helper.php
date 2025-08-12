<?php
// WhatsApp utility functions

class WhatsAppHelper {
    
    /**
     * Send order notification automatically
     */
    public static function notifyOrderCreated($order_id) {
        try {
            $db = new Database();
            
            // Get order and customer details
            $stmt = $db->query("SELECT o.order_number, o.total_amount, c.contact_person, c.phone
                                FROM orders o 
                                JOIN customers c ON o.customer_id = c.id 
                                WHERE o.id = ?", [$order_id]);
            $order = $stmt->fetch();
            
            if ($order) {
                $twilio = new TwilioManager();
                return $twilio->sendOrderConfirmation(
                    $order['phone'],
                    $order['order_number'],
                    formatCurrency($order['total_amount']),
                    $order['contact_person']
                );
            }
            
        } catch (Exception $e) {
            error_log("WhatsApp order notification failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send order status update automatically
     */
    public static function notifyOrderStatusChanged($order_id, $new_status) {
        try {
            $db = new Database();
            
            $stmt = $db->query("SELECT o.order_number, c.contact_person, c.phone
                                FROM orders o 
                                JOIN customers c ON o.customer_id = c.id 
                                WHERE o.id = ?", [$order_id]);
            $order = $stmt->fetch();
            
            if ($order) {
                $twilio = new TwilioManager();
                return $twilio->sendOrderStatusUpdate(
                    $order['phone'],
                    $order['order_number'],
                    $new_status,
                    $order['contact_person']
                );
            }
            
        } catch (Exception $e) {
            error_log("WhatsApp status notification failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Schedule reminder messages
     */
    public static function scheduleReminders() {
        try {
            $db = new Database();
            $twilio = new TwilioManager();
            
            // Send pickup reminders for completed orders
            $stmt = $db->query("SELECT o.id, o.order_number, c.contact_person, c.phone
                                FROM orders o 
                                JOIN customers c ON o.customer_id = c.id 
                                WHERE o.status = 'completed' 
                                AND o.delivery_date IS NULL 
                                AND o.created_at <= DATE_SUB(NOW(), INTERVAL 2 DAY)
                                AND NOT EXISTS (
                                    SELECT 1 FROM whatsapp_messages wm 
                                    WHERE wm.phone_number LIKE CONCAT('%', c.phone, '%') 
                                    AND wm.message LIKE '%təhvil%' 
                                    AND wm.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                                )");
            
            $orders = $stmt->fetchAll();
            
            foreach ($orders as $order) {
                $twilio->sendReminderMessage(
                    $order['phone'],
                    $order['contact_person'],
                    'order_pickup'
                );
                
                // Small delay to avoid rate limiting
                sleep(1);
            }
            
            return count($orders);
            
        } catch (Exception $e) {
            error_log("WhatsApp reminder scheduling failed: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Check rate limits
     */
    public static function checkRateLimit($phone_number) {
        try {
            $db = new Database();
            
            // Clean phone number
            $phone = preg_replace('/\D/', '', $phone_number);
            
            $stmt = $db->query("SELECT * FROM whatsapp_rate_limits WHERE phone_number = ?", [$phone]);
            $limits = $stmt->fetch();
            
            $now = new DateTime();
            $current_minute = $now->format('Y-m-d H:i:00');
            $current_hour = $now->format('Y-m-d H:00:00');
            $current_day = $now->format('Y-m-d');
            
            if (!$limits) {
                // Create new rate limit record
                $db->query("INSERT INTO whatsapp_rate_limits (phone_number, minute_count, hour_count, day_count, last_minute, last_hour, last_day) VALUES (?, 1, 1, 1, ?, ?, ?)", 
                    [$phone, $current_minute, $current_hour, $current_day]);
                return true;
            }
            
            // Check limits
            $minute_limit = WHATSAPP_RATE_LIMIT['max_messages_per_minute'];
            $hour_limit = WHATSAPP_RATE_LIMIT['max_messages_per_hour'];
            $day_limit = WHATSAPP_RATE_LIMIT['max_messages_per_day'];
            
            // Reset counters if time periods have passed
            if ($limits['last_minute'] < $current_minute) {
                $limits['minute_count'] = 0;
            }
            if ($limits['last_hour'] < $current_hour) {
                $limits['hour_count'] = 0;
            }
            if ($limits['last_day'] < $current_day) {
                $limits['day_count'] = 0;
            }
            
            // Check if limits exceeded
            if ($limits['minute_count'] >= $minute_limit ||
                $limits['hour_count'] >= $hour_limit ||
                $limits['day_count'] >= $day_limit) {
                return false;
            }
            
            // Update counters
            $db->query("UPDATE whatsapp_rate_limits SET 
                        minute_count = minute_count + 1,
                        hour_count = hour_count + 1, 
                        day_count = day_count + 1,
                        last_minute = ?, 
                        last_hour = ?, 
                        last_day = ?
                        WHERE phone_number = ?", 
                [$current_minute, $current_hour, $current_day, $phone]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Rate limit check failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if within business hours
     */
    public static function isBusinessHours() {
        $timezone = new DateTimeZone(WHATSAPP_SCHEDULE['timezone']);
        $now = new DateTime('now', $timezone);
        
        $current_day = (int)$now->format('N'); // 1 = Monday, 7 = Sunday
        $current_time = $now->format('H:i');
        
        // Check if current day is a business day
        if (!in_array($current_day, WHATSAPP_SCHEDULE['business_days'])) {
            return false;
        }
        
        // Check if current time is within business hours
        $start_time = WHATSAPP_SCHEDULE['business_hours_start'];
        $end_time = WHATSAPP_SCHEDULE['business_hours_end'];
        
        return ($current_time >= $start_time && $current_time <= $end_time);
    }
    
    /**
     * Queue message for later sending if outside business hours
     */
    public static function queueMessage($phone, $message, $send_time = null) {
        try {
            $db = new Database();
            
            if (!$send_time) {
                // Schedule for next business day at 9 AM
                $timezone = new DateTimeZone(WHATSAPP_SCHEDULE['timezone']);
                $send_time = new DateTime('tomorrow 09:00', $timezone);
                
                // If tomorrow is not a business day, find next business day
                while (!in_array((int)$send_time->format('N'), WHATSAPP_SCHEDULE['business_days'])) {
                    $send_time->modify('+1 day');
                }
            }
            
            $db->query("INSERT INTO whatsapp_queue (phone_number, message, scheduled_at, status) VALUES (?, ?, ?, 'queued')", 
                [$phone, $message, $send_time->format('Y-m-d H:i:s')]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Message queueing failed: " . $e->getMessage());
            return false;
        }
    }
}

// Create WhatsApp queue table if not exists
$db = new Database();
$db->query("CREATE TABLE IF NOT EXISTS whatsapp_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    scheduled_at TIMESTAMP NOT NULL,
    status ENUM('queued', 'sent', 'failed') DEFAULT 'queued',
    attempts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    INDEX idx_scheduled (scheduled_at, status)
)");