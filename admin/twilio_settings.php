<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireAdmin();

$db = Database::getInstance();
$message = '';
$error = '';

if ($_POST) {
    try {
        $settings = [
            'twilio_account_sid' => trim($_POST['twilio_account_sid']),
            'twilio_auth_token' => trim($_POST['twilio_auth_token']),
            'twilio_phone_number' => trim($_POST['twilio_phone_number']),
            'twilio_whatsapp_number' => trim($_POST['twilio_whatsapp_number']),
            'sms_verification_enabled' => isset($_POST['sms_verification_enabled']) ? '1' : '0',
            'whatsapp_enabled' => isset($_POST['whatsapp_enabled']) ? '1' : '0'
        ];
        
        foreach ($settings as $key => $value) {
            $db->query("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", 
                      [$key, $value]);
        }
        
        // Test connection if credentials provided
        if (!empty($settings['twilio_account_sid']) && !empty($settings['twilio_auth_token'])) {
            require_once '../includes/twilio.php';
            $twilio = new TwilioManager();
            $test_result = $twilio->testConnection();
            
            if ($test_result['success']) {
                $message = 'Twilio ayarları yadda saxlanıldı və əlaqə uğurla test edildi!';
            } else {
                $message = 'Ayarlar yadda saxlanıldı, lakin əlaqə testində xəta: ' . $test_result['error'];
            }
        } else {
            $message = 'Twilio ayarları yadda saxlanıldı';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current settings
$stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'twilio%' OR setting_key LIKE '%sms%' OR setting_key LIKE '%whatsapp%'");
$current_settings = [];
while ($row = $stmt->fetch()) {
    $current_settings[$row['setting_key']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Twilio Ayarları - Alumpro.Az</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin-sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-phone text-primary"></i> Twilio SMS/WhatsApp Ayarları
                    </h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?= $message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-gear"></i> Twilio Konfiqurasiyası
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Account SID *</label>
                                    <input type="text" class="form-control" name="twilio_account_sid" 
                                           value="<?= htmlspecialchars($current_settings['twilio_account_sid'] ?? '') ?>" 
                                           placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                                    <div class="form-text">Twilio Console-dan alın</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Auth Token *</label>
                                    <input type="password" class="form-control" name="twilio_auth_token" 
                                           value="<?= htmlspecialchars($current_settings['twilio_auth_token'] ?? '') ?>" 
                                           placeholder="Auth Token">
                                    <div class="form-text">Twilio Console-dan alın</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">SMS Phone Number</label>
                                    <input type="text" class="form-control" name="twilio_phone_number" 
                                           value="<?= htmlspecialchars($current_settings['twilio_phone_number'] ?? '') ?>" 
                                           placeholder="+1234567890">
                                    <div class="form-text">SMS göndərmək üçün Twilio nömrəsi</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">WhatsApp Number</label>
                                    <input type="text" class="form-control" name="twilio_whatsapp_number" 
                                           value="<?= htmlspecialchars($current_settings['twilio_whatsapp_number'] ?? 'whatsapp:+14155238886') ?>" 
                                           placeholder="whatsapp:+14155238886">
                                    <div class="form-text">WhatsApp mesajları üçün</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sms_verification_enabled" 
                                               <?= ($current_settings['sms_verification_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label">
                                            SMS Təsdiq Sistemi Aktiv
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="whatsapp_enabled" 
                                               <?= ($current_settings['whatsapp_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label">
                                            WhatsApp Bildirişləri Aktiv
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check"></i> Ayarları Saxla
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-info-circle"></i> Quraşdırma Təlimatları
                        </h5>
                    </div>
                    <div class="card-body">
                        <ol>
                            <li>
                                <strong>Twilio hesabı yaradın:</strong>
                                <a href="https://www.twilio.com" target="_blank">https://www.twilio.com</a>
                            </li>
                            <li>
                                <strong>Console-dan Account SID və Auth Token alın</strong>
                            </li>
                            <li>
                                <strong>SMS üçün telefon nömrəsi alın:</strong> 
                                Phone Numbers bölməsindən
                            </li>
                            <li>
                                <strong>WhatsApp üçün:</strong> 
                                WhatsApp Business API aktivləşdirin
                            </li>
                            <li>
                                <strong>Webhook URL-ni təyin edin:</strong>
                                <code><?= SITE_URL ?>/api/twilio_webhook.php</code>
                            </li>
                        </ol>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>