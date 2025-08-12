<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

//$db = new Database();
$error = '';
$message = '';

// Check for logout message
if (isset($_GET['message']) && $_GET['message'] === 'logged_out') {
    $message = 'Uğurla sistemdən çıxdınız';
}

// Check for registration success
if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $message = 'Qeydiyyat tamamlandı! İndi giriş edə bilərsiniz.';
}

// If already logged in, redirect appropriately  
if (SessionManager::isLoggedIn()) {
    $role = SessionManager::getUserRole();
    header("Location: ../{$role}/dashboard.php");
    exit;
}

if ($_POST) {
    try {
        $username_or_phone = trim($_POST['username_or_phone']);
        $password = $_POST['password'];
        $remember_me = isset($_POST['remember_me']);
        
        if (empty($username_or_phone) || empty($password)) {
            throw new Exception('İstifadəçi adı/telefon və şifrə mütləqdir');
        }
        
        // Check for rate limiting
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt = $db->query("SELECT COUNT(*) as attempts FROM login_attempts 
                           WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)", 
                           [$ip_address]);
        $attempts = $stmt->fetch()['attempts'];
        
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            throw new Exception('Çox sayda uğursuz giriş cəhdi. 15 dəqiqə gözləyin.');
        }
        
        // Try to find user by username or phone
        $stmt = $db->query("SELECT * FROM users WHERE (username = ? OR phone = ?) AND status = 'active'", 
                          [$username_or_phone, $username_or_phone]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password'])) {
            // Log failed attempt
            $db->query("INSERT INTO login_attempts (ip_address, username, success, created_at) VALUES (?, ?, 0, NOW())", 
                      [$ip_address, $username_or_phone]);
            
            throw new Exception('İstifadəçi adı/telefon və ya şifrə səhvdir');
        }
        
        // Check if user is verified
        if (!$user['is_verified']) {
            // Send new verification code if needed
            if (isFeatureEnabled('sms_verification')) {
                try {
                    require_once '../includes/twilio.php';
                    
                    // Generate new verification code
                    $verification_code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    
                    // Save verification code
                    $db->query("INSERT INTO verification_codes (phone, code, type, created_at) VALUES (?, ?, 'login', NOW())", 
                              [$user['phone'], $verification_code]);
                    
                    // Send SMS
                    $twilio = new TwilioManager();
                    $result = $twilio->sendVerificationCode($user['phone'], $verification_code);
                    
                    if ($result['success']) {
                        header("Location: verify.php?phone=" . urlencode($user['phone']) . "&type=login");
                        exit;
                    }
                } catch (Exception $e) {
                    error_log("Verification SMS failed: " . $e->getMessage());
                }
            }
            
            throw new Exception('Hesabınız təsdiqlənməyib. Telefon nömrənizi təsdiqləyin.');
        }
        
        // Successful login
        $db->query("INSERT INTO login_attempts (ip_address, username, success, created_at) VALUES (?, ?, 1, NOW())", 
                  [$ip_address, $username_or_phone]);
        
        // Update last login
        $db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
        
        // Create session
        SessionManager::login($user['id'], $user['role'], $user['store_id']);
        
        // Set remember me cookie if requested
        if ($remember_me) {
            $remember_token = bin2hex(random_bytes(32));
            $expires = time() + (30 * 24 * 60 * 60); // 30 days
            
            setcookie('remember_token', $remember_token, $expires, '/', '', true, true);
            $db->query("UPDATE users SET remember_token = ? WHERE id = ?", [$remember_token, $user['id']]);
        }
        
        // Log successful login
        Utils::logActivity($user['id'], 'user_login', 'İstifadəçi sistemə daxil oldu');
        
        // Send login notification via WhatsApp if enabled
        if (isFeatureEnabled('login_notifications')) {
            try {
                require_once '../includes/twilio.php';
                $twilio = new TwilioManager();
                
                $login_message = "Salam {$user['full_name']}!\n\n";
                $login_message .= "Hesabınıza yeni giriş edildi:\n";
                $login_message .= "📅 Tarix: " . date('d.m.Y H:i') . "\n";
                $login_message .= "📍 IP: {$ip_address}\n\n";
                $login_message .= "Əgər bu siz deyilsinizsə, dərhal bizimlə əlaqə saxlayın.\n\n";
                $login_message .= "Alumpro.Az";
                
                $twilio->sendWhatsAppMessage($user['phone'], $login_message);
            } catch (Exception $e) {
                error_log("Login notification failed: " . $e->getMessage());
            }
        }
        
        // Redirect to appropriate dashboard
        $redirect_url = $_GET['redirect'] ?? "../{$user['role']}/dashboard.php";
        header("Location: $redirect_url");
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş - Alumpro.Az</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #20B2AA, #4682B4);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 450px;
            width: 100%;
            margin: 1rem;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #20B2AA, #4682B4);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #dee2e6;
            padding: 0.75rem 1rem;
        }
        .form-control:focus {
            border-color: #20B2AA;
            box-shadow: 0 0 0 0.2rem rgba(32, 178, 170, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #20B2AA, #4682B4);
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(32, 178, 170, 0.3);
        }
        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
        }
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #dee2e6;
        }
        .divider span {
            background: white;
            padding: 0 1rem;
            color: #6c757d;
        }
        .social-login {
            display: flex;
            gap: 0.5rem;
        }
        .social-btn {
            flex: 1;
            padding: 0.75rem;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            background: white;
            color: #6c757d;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .social-btn:hover {
            border-color: #20B2AA;
            color: #20B2AA;
            text-decoration: none;
        }
        .features-list {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1.5rem;
        }
        @media (max-width: 576px) {
            .login-container {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-icon">
                <i class="bi bi-box-arrow-in-right"></i>
            </div>
            <h2 class="fw-bold text-primary">Sistemə Giriş</h2>
            <p class="text-muted">Hesabınıza daxil olun</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle"></i> <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="needs-validation" novalidate>
            <div class="mb-3">
                <label for="username_or_phone" class="form-label">İstifadəçi Adı və ya Telefon</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-person"></i>
                    </span>
                    <input type="text" 
                           class="form-control" 
                           id="username_or_phone" 
                           name="username_or_phone" 
                           required 
                           placeholder="İstifadəçi adınız və ya telefon nömrəniz"
                           autocomplete="username">
                </div>
                <div class="invalid-feedback">
                    İstifadəçi adı və ya telefon nömrəsi mütləqdir
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Şifrə</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-lock"></i>
                    </span>
                    <input type="password" 
                           class="form-control" 
                           id="password" 
                           name="password" 
                           required 
                           placeholder="Şifrənizi daxil edin"
                           autocomplete="current-password">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                        <i class="bi bi-eye" id="passwordToggleIcon"></i>
                    </button>
                </div>
                <div class="invalid-feedback">
                    Şifrə mütləqdir
                </div>
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                <label class="form-check-label" for="remember_me">
                    Məni xatırla (30 gün)
                </label>
            </div>

            <div class="d-grid gap-2 mb-3">
                <button type="submit" class="btn btn-primary btn-login">
                    <i class="bi bi-box-arrow-in-right"></i> Giriş Et
                </button>
            </div>
        </form>

        <div class="text-center">
            <a href="forgot-password.php" class="text-decoration-none">
                <i class="bi bi-key"></i> Şifrəni unutmusunuz?
            </a>
        </div>

        <div class="divider">
            <span>və ya</span>
        </div>

        <div class="social-login mb-3">
            <a href="tel:+994501234567" class="social-btn">
                <i class="bi bi-telephone"></i>
                <span class="ms-1 d-none d-sm-inline">Zəng</span>
            </a>
            <a href="https://wa.me/994501234567" target="_blank" class="social-btn">
                <i class="bi bi-whatsapp"></i>
                <span class="ms-1 d-none d-sm-inline">WhatsApp</span>
            </a>
            <a href="mailto:info@alumpro.az" class="social-btn">
                <i class="bi bi-envelope"></i>
                <span class="ms-1 d-none d-sm-inline">Email</span>
            </a>
        </div>

        <div class="text-center">
            <p class="text-muted mb-0">
                Hesabınız yoxdur? 
                <a href="register.php" class="fw-bold text-decoration-none">
                    Qeydiyyatdan keçin
                </a>
            </p>
        </div>

        <div class="features-list">
            <h6 class="fw-bold mb-2">
                <i class="bi bi-star text-warning"></i> Nə əldə edirsiniz:
            </h6>
            <ul class="list-unstyled small mb-0">
                <li><i class="bi bi-check text-success"></i> Sürətli sifariş vermə</li>
                <li><i class="bi bi-check text-success"></i> Sifarişlərinizi izləmə</li>
                <li><i class="bi bi-check text-success"></i> WhatsApp bildirişləri</li>
                <li><i class="bi bi-check text-success"></i> Xüsusi endirimlər</li>
                <li><i class="bi bi-check text-success"></i> 24/7 dəstək</li>
            </ul>
        </div>

        <div class="text-center mt-3">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="bi bi-house"></i> Ana Səhifə
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                const forms = document.getElementsByClassName('needs-validation');
                Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // Password toggle
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('passwordToggleIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.className = 'bi bi-eye-slash';
            } else {
                passwordField.type = 'password';
                toggleIcon.className = 'bi bi-eye';
            }
        }

        // Auto-format phone number
        document.getElementById('username_or_phone').addEventListener('input', function(e) {
            let value = e.target.value;
            
            // If it looks like a phone number, format it
            if (/^\+?[0-9\s\-\(\)]+$/.test(value) && value.length > 5) {
                value = value.replace(/\D/g, '');
                if (value.startsWith('994')) {
                    value = '+' + value;
                } else if (value.startsWith('0')) {
                    value = '+994' + value.substring(1);
                } else if (!value.startsWith('+994')) {
                    value = '+994' + value;
                }
                e.target.value = value;
            }
        });

        // Focus on first input
        window.addEventListener('load', function() {
            document.getElementById('username_or_phone').focus();
        });

        // Enter key navigation
        document.getElementById('username_or_phone').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('password').focus();
            }
        });
    </script>
</body>
</html>