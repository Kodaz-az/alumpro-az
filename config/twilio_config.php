<?php
// Twilio Configuration

// Twilio credentials - Add these to your main config.php or environment variables
define('TWILIO_ACCOUNT_SID', getenv('TWILIO_ACCOUNT_SID') ?: 'AC61dec6a80dcf8e405b19217cb1b01d6c');
define('TWILIO_AUTH_TOKEN', getenv('TWILIO_AUTH_TOKEN') ?: '4ee4c459cc905d397c81b2f21f276b15');
define('TWILIO_WHATSAPP_NUMBER', getenv('TWILIO_WHATSAPP_NUMBER') ?: 'whatsapp:+17628157642');

// Webhook configuration
define('TWILIO_WEBHOOK_URL', SITE_URL . '/api/twilio_webhook.php');

// Message templates
define('TWILIO_MESSAGE_TEMPLATES', [
    'order_confirmation' => [
        'name' => 'Sifariş Təsdiqi',
        'variables' => ['customer_name', 'order_number', 'total_amount']
    ],
    'order_status_update' => [
        'name' => 'Status Yeniləməsi',
        'variables' => ['customer_name', 'order_number', 'status']
    ],
    'payment_reminder' => [
        'name' => 'Ödəniş Xatırlatması',
        'variables' => ['customer_name', 'order_number', 'amount']
    ],
    'delivery_notification' => [
        'name' => 'Təhvil Bildirişi',
        'variables' => ['customer_name', 'order_number']
    ]
]);

// Auto-reply keywords in Azerbaijani
define('WHATSAPP_AUTO_REPLIES', [
    'salam' => 'Salam! Alumpro.Az-a xoş gəlmisiniz! 🏢',
    'sağol' => 'Rica edirik! Həmişə xidmətinizdəyik. 😊',
    'qiymət' => 'Qiymətlər üçün: +994 55 244 70 44 📞',
    'sifariş' => 'Sifariş üçün: +994 55 244 70 44 📋',
    'ünvan' => 'Ünvan: Bakı şəhəri , Əhməd Rəcəbli 254📍',
    'vaxt' => 'İş vaxtı: B.e - Şənbə 09:00-18:00 🕘',
    'məhsul' => 'Məhsullar: alumpro.az 🌐',
    'kömək' => 'Kömək üçün: +994 55 244 70 44 ☎️'
]);

// Rate limiting settings
define('WHATSAPP_RATE_LIMIT', [
    'max_messages_per_minute' => 10,
    'max_messages_per_hour' => 100,
    'max_messages_per_day' => 1000
]);

// Message scheduling settings
define('WHATSAPP_SCHEDULE', [
    'business_hours_start' => '09:00',
    'business_hours_end' => '18:00',
    'business_days' => [1, 2, 3, 4, 5, 6], // Monday to Saturday
    'timezone' => 'Asia/Baku'
]);