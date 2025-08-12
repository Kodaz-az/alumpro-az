<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireAdmin();

$db = new Database();
$message = '';
$error = '';

// Handle settings update
if ($_POST) {
    try {
        $db->getConnection()->beginTransaction();
        
        foreach ($_POST as $key => $value) {
            if ($key !== 'action') {
                $stmt = $db->query("UPDATE settings SET setting_value = ? WHERE setting_key = ?", 
                                   [trim($value), $key]);
                
                // Insert if not exists
                if ($stmt->rowCount() === 0) {
                    $db->query("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)", 
                               [$key, trim($value)]);
                }
            }
        }
        
        $db->getConnection()->commit();
        Utils::logActivity(SessionManager::getUserId(), 'settings_updated', 'Sistem ayarları yeniləndi');
        
        $message = 'Ayarlar uğurla yeniləndi';
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $error = $e->getMessage();
    }
}

// Get current settings
$stmt = $db->query("SELECT setting_key, setting_value, description FROM settings ORDER BY setting_key");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row;
}
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Ayarları - Alumpro.Az</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin-sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-gear text-primary"></i> Sistem Ayarları
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

                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="update_settings">
                    
                    <!-- Company Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-building"></i> Şirkət Məlumatları
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="company_name" class="form-label">Şirkət Adı</label>
                                    <input type="text" class="form-control" name="company_name" 
                                           value="<?= htmlspecialchars($settings['company_name']['setting_value'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="company_phone" class="form-label">Şirkət Telefonu</label>
                                    <input type="tel" class="form-control" name="company_phone" 
                                           value="<?= htmlspecialchars($settings['company_phone']['setting_value'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="company_email" class="form-label">Şirkət Email</label>
                                    <input type="email" class="form-control" name="company_email" 
                                           value="<?= htmlspecialchars($settings['company_email']['setting_value'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="company_address" class="form-label">Şirkət Ünvanı</label>
                                    <input type="text" class="form-control" name="company_address" 
                                           value="<?= htmlspecialchars($settings['company_address']['setting_value'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Settings -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-box"></i> Sifariş Ayarları
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="order_number_prefix" class="form-label">Sifariş Nömrə Prefiksi</label>
                                    <input type="text" class="form-control" name="order_number_prefix" 
                                           value="<?= htmlspecialchars($settings['order_number_prefix']['setting_value'] ?? 'ALM') ?>">
                                    <small class="form-text text-muted">Məsələn: ALM</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="glass_size_reduction" class="form-label">Şüşə Ölçü Azalması (mm)</label>
                                    <input type="number" class="form-control" name="glass_size_reduction" min="0" step="0.1"
                                           value="<?= htmlspecialchars($settings['glass_size_reduction']['setting_value'] ?? '4') ?>">
                                    <small class="form-text text-muted">Qapaq ölçülərindən çıxılacaq miqdar</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- WhatsApp Integration -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-whatsapp"></i> WhatsApp İnteqrasiyası
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="whatsapp_api_url" class="form-label">WhatsApp API URL</label>
                                    <input type="url" class="form-control" name="whatsapp_api_url" 
                                           value="<?= htmlspecialchars($settings['whatsapp_api_url']['setting_value'] ?? '') ?>"
                                           placeholder="https://api.whatsapp.com/...">
                                    <small class="form-text text-muted">WhatsApp Business API endpoint</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Push Notifications -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-bell"></i> Push Bildirişlər (OneSignal)
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="onesignal_app_id" class="form-label">OneSignal App ID</label>
                                    <input type="text" class="form-control" name="onesignal_app_id" 
                                           value="<?= htmlspecialchars($settings['onesignal_app_id']['setting_value'] ?? '') ?>"
                                           placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="onesignal_api_key" class="form-label">OneSignal API Key</label>
                                    <input type="password" class="form-control" name="onesignal_api_key" 
                                           value="<?= htmlspecialchars($settings['onesignal_api_key']['setting_value'] ?? '') ?>"
                                           placeholder="API Key daxil edin...">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Settings -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-sliders"></i> Sistem Ayarları
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="default_currency" class="form-label">Valyuta</label>
                                    <select class="form-select" name="default_currency">
                                        <option value="AZN" <?= ($settings['default_currency']['setting_value'] ?? 'AZN') === 'AZN' ? 'selected' : '' ?>>Azərbaycan Manatı (AZN)</option>
                                        <option value="USD" <?= ($settings['default_currency']['setting_value'] ?? 'AZN') === 'USD' ? 'selected' : '' ?>>ABŞ Dolları (USD)</option>
                                        <option value="EUR" <?= ($settings['default_currency']['setting_value'] ?? 'AZN') === 'EUR' ? 'selected' : '' ?>>Avro (EUR)</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="default_language" class="form-label">Dil</label>
                                    <select class="form-select" name="default_language">
                                        <option value="az" <?= ($settings['default_language']['setting_value'] ?? 'az') === 'az' ? 'selected' : '' ?>>Azərbaycanca</option>
                                        <option value="tr" <?= ($settings['default_language']['setting_value'] ?? 'az') === 'tr' ? 'selected' : '' ?>>Türkçe</option>
                                        <option value="en" <?= ($settings['default_language']['setting_value'] ?? 'az') === 'en' ? 'selected' : '' ?>>English</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="session_timeout" class="form-label">Session Timeout (saniyə)</label>
                                    <input type="number" class="form-control" name="session_timeout" min="300" max="86400"
                                           value="<?= htmlspecialchars($settings['session_timeout']['setting_value'] ?? '3600') ?>">
                                    <small class="form-text text-muted">İstifadəçi avtomatik çıxarılma vaxtı</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="max_file_size" class="form-label">Maksimum Fayl Ölçüsü (MB)</label>
                                    <input type="number" class="form-control" name="max_file_size" min="1" max="100"
                                           value="<?= htmlspecialchars($settings['max_file_size']['setting_value'] ?? '10') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Settings -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-envelope"></i> Email Ayarları
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="smtp_host" class="form-label">SMTP Host</label>
                                    <input type="text" class="form-control" name="smtp_host" 
                                           value="<?= htmlspecialchars($settings['smtp_host']['setting_value'] ?? '') ?>"
                                           placeholder="smtp.gmail.com">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="smtp_port" class="form-label">SMTP Port</label>
                                    <input type="number" class="form-control" name="smtp_port" 
                                           value="<?= htmlspecialchars($settings['smtp_port']['setting_value'] ?? '587') ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="smtp_username" class="form-label">SMTP İstifadəçi Adı</label>
                                    <input type="text" class="form-control" name="smtp_username" 
                                           value="<?= htmlspecialchars($settings['smtp_username']['setting_value'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="smtp_password" class="form-label">SMTP Şifrə</label>
                                    <input type="password" class="form-control" name="smtp_password" 
                                           value="<?= htmlspecialchars($settings['smtp_password']['setting_value'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-check-circle"></i> Ayarları Saxla
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>