-- WhatsApp messages log table
CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    message_sid VARCHAR(50),
    status ENUM('sent', 'delivered', 'read', 'failed', 'pending') DEFAULT 'pending',
    direction ENUM('outbound', 'inbound') DEFAULT 'outbound',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_phone (phone_number),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

-- WhatsApp campaigns table
CREATE TABLE IF NOT EXISTS whatsapp_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    message_template TEXT NOT NULL,
    target_audience JSON, -- customer filters
    scheduled_at TIMESTAMP NULL,
    status ENUM('draft', 'scheduled', 'sending', 'completed', 'cancelled') DEFAULT 'draft',
    total_recipients INT DEFAULT 0,
    sent_count INT DEFAULT 0,
    delivered_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- WhatsApp auto-replies table
CREATE TABLE IF NOT EXISTS whatsapp_auto_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    keyword VARCHAR(100) NOT NULL,
    response TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    match_type ENUM('exact', 'contains', 'starts_with', 'regex') DEFAULT 'contains',
    priority INT DEFAULT 0,
    usage_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_keyword (keyword),
    INDEX idx_active (is_active)
);

-- Insert default auto-replies
INSERT INTO whatsapp_auto_replies (keyword, response, match_type) VALUES
('salam', 'Salam! Alumpro.Az-a xoş gəlmisiniz! Sizə necə kömək edə bilərəm? 🏢', 'contains'),
('sağol', 'Rica edirik! Həmişə xidmətinizdəyik. 😊', 'contains'),
('təşəkkür', 'Rica edirik! Həmişə xidmətinizdəyik. 😊', 'contains'),
('qiymət', 'Qiymətlər haqqında məlumat üçün zəhmət olmasa +994 50 123 45 67 nömrəsinə zəng edin. 📞', 'contains'),
('sifariş', 'Sifariş vermək üçün bizimlə əlaqə saxlayın: +994 50 123 45 67 📋', 'contains'),
('ünvan', 'Ünvanımız: Bakı şəhəri. Dəqiq ünvan üçün zəng edin: +994 50 123 45 67 📍', 'contains'),
('vaxt', 'İş saatlarımız: Bazar ertəsi - Şənbə, 09:00 - 18:00 🕘', 'contains'),
('məhsul', 'Aluminum profil və şüşə sistemləri haqqında məlumat üçün veb saytımızı ziyarət edin: alumpro.az 🌐', 'contains'),
('kömək', 'Bizə müraciət etdiyiniz üçün təşəkkür edirik! Kömək üçün: +994 50 123 45 67 ☎️', 'contains');

-- WhatsApp rate limiting table
CREATE TABLE IF NOT EXISTS whatsapp_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL,
    minute_count INT DEFAULT 0,
    hour_count INT DEFAULT 0,
    day_count INT DEFAULT 0,
    last_minute TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_hour TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_day DATE DEFAULT (CURRENT_DATE),
    INDEX idx_phone (phone_number),
    INDEX idx_last_minute (last_minute)
);

-- Add WhatsApp settings to settings table
INSERT INTO settings (setting_key, setting_value, description) VALUES
('twilio_account_sid', '', 'Twilio Account SID'),
('twilio_auth_token', '', 'Twilio Auth Token'),
('twilio_whatsapp_number', 'whatsapp:+14155238886', 'Twilio WhatsApp Phone Number'),
('whatsapp_auto_reply_enabled', '1', 'Enable WhatsApp auto-replies'),
('whatsapp_business_hours_only', '0', 'Send messages only during business hours'),
('whatsapp_rate_limit_enabled', '1', 'Enable WhatsApp rate limiting')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);