<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

// If already logged in, redirect
if (SessionManager::isLoggedIn()) {
    $role = SessionManager::getUserRole();
    header("Location: ../{$role}/dashboard.php");
    exit;
}

$db = Database::getInstance();
$error = '';
$success = '';

if ($_POST) {
    try {
        $username = trim($_POST['username']);
        $phone = Utils::formatPhone(trim($_POST['phone']));
        $email = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name']);
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];
        $terms_accepted = isset($_POST['terms_accepted']);
        
        // Validation
        if (empty($username) || empty($phone) || empty($full_name) || empty($password)) {
            throw new Exception('Bütün məcburi sahələr doldurulmalıdır');
        }
        
        if (!$terms_accepted) {
            throw new Exception('İstifadə şərtlərini qəbul etməlisiniz');
        }
        
        if ($password !== $password_confirm) {
            throw new Exception('Şifrələr uyğun gəlmir');
        }
        
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            throw new Exception('Şifrə ən azı ' . PASSWORD_MIN_LENGTH . ' simvol olmalıdır');
        }
        
        // Validate phone format
        if (!preg_match('/^\+994[0-9]{9}$/', $phone)) {
            throw new Exception('Telefon nömrəsi düzgün formatda deyil (+994XXXXXXXXX)');
        }
        
        // Check if username already exists
        $stmt = $db->query("SELECT id FROM users WHERE username = ?", [$username]);
        if ($stmt->fetch()) {
            throw new Exception('Bu istifadəçi adı artıq mövcuddur');
        }
        
        // Check if phone already exists
        $stmt = $db->query("SELECT id, is_verified FROM users WHERE phone = ?", [$phone]);
        $existing_user = $stmt->fetch();
        if ($existing_user) {
            if ($existing_user['is_verified']) {
                throw new Exception('Bu telefon nömrəsi artıq qeydiyyatdan keçib');
            } else {
                // Delete unverified user and allow re-registration
                $db->query("DELETE FROM users WHERE phone = ? AND is_verified = 0", [$phone]);
                $db->query("DELETE FROM verification_codes WHERE phone = ?", [$phone]);
            }
        }
        
        // Check if email already exists (if provided)
        if ($email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email formatı düzgün deyil');
            }
            
            $stmt = $db->query("SELECT id FROM users WHERE email = ?", [$email]);
            if ($stmt->fetch()) {
                throw new Exception('Bu email artıq istifadə olunur');
            }
        }
        
        $db->getConnection()->beginTransaction();
        
        // Create user
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->query("INSERT INTO users (username, phone, email, password, full_name, role, is_verified, status, created_at) VALUES (?, ?, ?, ?, ?, 'customer', 0, 'pending', NOW())", 
                          [$username, $phone, $email, $hashed_password, $full_name]);
        
        $user_id = $db->lastInsertId();
        
        // Check if SMS verification is enabled and configured
        $sms_enabled = isFeatureEnabled('sms_verification');
        $sms_configured = isSMSConfigured();
        
        $sms_sent = false;
        
        if ($sms_enabled && $sms_configured) {
            try {
                // Generate verification code
                $verification_code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                
                // Save verification code
                $stmt = $db->query("INSERT INTO verification_codes (phone, code, type, created_at) VALUES (?, ?, 'registration', NOW())", 
                                  [$phone, $verification_code]);
                
                // Send verification SMS
                require_once '../includes/twilio.php';
                $twilio = new TwilioManager();
                $result = $twilio->sendVerificationCode($phone, $verification_code, 'registration');
                
                if ($result['success']) {
                    $sms_sent = true;
                } else {
                    error_log("SMS verification failed: " . $result['error']);
                }
            } catch (Exception $e) {
                error_log("Twilio error during registration: " . $e->getMessage());
            }
        }
        
        $db->getConnection()->commit();
        
        // Log activity
        Utils::logActivity($user_id, 'user_registered', 'İstifadəçi qeydiyyatdan keçdi');
        
        if ($sms_sent) {
            // Redirect to verification page
            header("Location: verify.php?phone=" . urlencode($phone));
            exit;
        } else {
            // If SMS not sent or not configured, auto-verify and redirect to login
            $db->query("UPDATE users SET is_verified = 1, status = 'active' WHERE id = ?", [$user_id]);
            
            // Create customer profile
            $db->query("INSERT INTO customers (user_id, contact_person, phone, email) VALUES (?, ?, ?, ?)", 
                      [$user_id, $full_name, $phone, $email]);
            
            if (!$sms_configured) {
                $success = 'Qeydiyyat tamamlandı! SMS xidməti konfiqurasiya edilmədiyi üçün hesabınız avtomatik təsdiqləndi.';
            } else {
                $success = 'Qeydiyyat tamamlandı! SMS göndərilmədi, lakin hesabınız aktiv edildi.';
            }
            
            // Auto-redirect after 3 seconds
            echo "<script>
                    setTimeout(function() {
                        window.location.href = 'login.php?registered=1';
                    }, 3000);
                  </script>";
        }
        
    } catch (Exception $e) {
        if ($db->getConnection()->inTransaction()) {
            $db->getConnection()->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qeydiyyat - Alumpro.Az</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #20B2AA, #4682B4);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .register-container {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
        }
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .register-icon {
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
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #dee2e6;
            padding: 0.75rem 1rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #20B2AA;
            box-shadow: 0 0 0 0.2rem rgba(32, 178, 170, 0.25);
        }
        .btn-register {
            background: linear-gradient(135deg, #20B2AA, #4682B4);
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(32, 178, 170, 0.3);
        }
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #198754; }
        .terms-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            max-height: 150px;
            overflow-y: auto;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        .sms-info {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 1rem;
        }
        @media (max-width: 576px) {
            .register-container {
                margin: 1rem;
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="register-header">
                <div class="register-icon">
                    <i class="bi bi-person-plus"></i>
                </div>
                <h2 class="fw-bold text-primary">Qeydiyyat</h2>
                <p class="text-muted">Yeni hesab yaradın</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="bi bi-check-circle"></i> <?= $success ?>
                </div>
            <?php endif; ?>

            <?php if (isFeatureEnabled('sms_verification') && isSMSConfigured()): ?>
                <div class="sms-info">
                    <i class="bi bi-info-circle text-primary"></i>
                    <strong>Qeyd:</strong> Qeydiyyatdan sonra telefonunuza təsdiq kodu göndəriləcək.
                </div>
            <?php elseif (isFeatureEnabled('sms_verification') && !isSMSConfigured()): ?>
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Qeyd:</strong> SMS xidməti konfiqurasiya edilməyib. Hesabınız avtomatik təsdiqlənəcək.
                </div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="full_name" class="form-label">Ad Soyad *</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-person"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="full_name" 
                                   name="full_name" 
                                   required 
                                   placeholder="Adınızı və soyadınızı daxil edin"
                                   value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                        </div>
                        <div class="invalid-feedback">
                            Ad və soyad mütləqdir
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="username" class="form-label">İstifadəçi Adı *</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-at"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="username" 
                                   name="username" 
                                   required 
                                   pattern="[a-zA-Z0-9_]{3,20}"
                                   placeholder="İstifadəçi adınız"
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                        </div>
                        <div class="invalid-feedback">
                            3-20 simvol, yalnız hərf, rəqəm və _ istifadə edin
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Telefon Nömrəsi *</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-telephone"></i>
                            </span>
                            <input type="tel" 
                                   class="form-control" 
                                   id="phone" 
                                   name="phone" 
                                   required 
                                   pattern="\+994[0-9]{9}"
                                   placeholder="+994501234567"
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>
                        <div class="invalid-feedback">
                            Düzgün telefon nömrəsi daxil edin (+994XXXXXXXXX)
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email (İstəyə bağlı)</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   placeholder="email@example.com"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="invalid-feedback">
                            Düzgün email adresi daxil edin
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Şifrə *</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   required 
                                   minlength="<?= PASSWORD_MIN_LENGTH ?>"
                                   placeholder="Güclü şifrə seçin">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                <i class="bi bi-eye" id="passwordToggleIcon"></i>
                            </button>
                        </div>
                        <div class="password-strength" id="passwordStrength"></div>
                        <div class="invalid-feedback">
                            Şifrə ən azı <?= PASSWORD_MIN_LENGTH ?> simvol olmalıdır
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="password_confirm" class="form-label">Şifrə Təkrarı *</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-lock-fill"></i>
                            </span>
                            <input type="password" 
                                   class="form-control" 
                                   id="password_confirm" 
                                   name="password_confirm" 
                                   required 
                                   placeholder="Şifrəni təkrarlayın">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password_confirm')">
                                <i class="bi bi-eye" id="passwordConfirmToggleIcon"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">
                            Şifrələr uyğun gəlmir
                        </div>
                    </div>
                </div>

                <div class="terms-box">
                    <h6>İstifadə Şərtləri və Məxfilik Siyasəti</h6>
                    <p><strong>1. Ümumi Şərtlər:</strong> Bu platformadan istifadə edərək, siz aşağıdakı şərtləri qəbul edirsiniz.</p>
                    <p><strong>2. Məxfilik:</strong> Şəxsi məlumatlarınız təhlükəsizdir və üçüncü tərəflərlə paylaşılmır.</p>
                    <p><strong>3. İstifadə Qaydaları:</strong> Platformadan yalnız qanuni məqsədlər üçün istifadə edin.</p>
                    <p><strong>4. Məlumatların Emalı:</strong> Telefon nömrəniz SMS təsdiq kodu göndərmək üçün istifadə olunur.</p>
                    <p><strong>5. Dəstək:</strong> Hər hansı sual olarsa, bizimlə əlaqə saxlayın: <?= SITE_EMAIL ?></p>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="terms_accepted" name="terms_accepted" required>
                    <label class="form-check-label" for="terms_accepted">
                        <strong>İstifadə şərtlərini və məxfilik siyasətini oxudum və qəbul edirəm</strong>
                    </label>
                    <div class="invalid-feedback">
                        İstifadə şərtlərini qəbul etməlisiniz
                    </div>
                </div>

                <div class="d-grid gap-2 mb-3">
                    <button type="submit" class="btn btn-primary btn-register">
                        <i class="bi bi-person-plus"></i> Qeydiyyatdan Keç
                    </button>
                </div>
            </form>

            <div class="text-center">
                <p class="text-muted mb-0">
                    Artıq hesabınız var? 
                    <a href="login.php" class="fw-bold text-decoration-none">
                        Giriş edin
                    </a>
                </p>
            </div>

            <div class="text-center mt-3">
                <a href="../index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-house"></i> Ana Səhifə
                </a>
            </div>
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
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const toggleIcon = document.getElementById(fieldId + 'ToggleIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.className = 'bi bi-eye-slash';
            } else {
                passwordField.type = 'password';
                toggleIcon.className = 'bi bi-eye';
            }
        }

        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.startsWith('994')) {
                value = '+' + value;
            } else if (value.startsWith('0')) {
                value = '+994' + value.substring(1);
            } else if (!value.startsWith('+994') && value.length > 0) {
                value = '+994' + value;
            }
            e.target.value = value;
        });

        // Password strength checker
        document.getElementById('password').addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthElement = document.getElementById('passwordStrength');
            
            let strength = 0;
            let feedback = [];

            if (password.length >= 8) strength++;
            else feedback.push('ən azı 8 simvol');

            if (/[a-z]/.test(password)) strength++;
            else feedback.push('kiçik hərf');

            if (/[A-Z]/.test(password)) strength++;
            else feedback.push('böyük hərf');

            if (/[0-9]/.test(password)) strength++;
            else feedback.push('rəqəm');

            if (/[^A-Za-z0-9]/.test(password)) strength++;
            else feedback.push('xüsusi simvol');

            if (strength < 2) {
                strengthElement.className = 'password-strength strength-weak';
                strengthElement.textContent = 'Zəif şifrə. Lazım: ' + feedback.join(', ');
            } else if (strength < 4) {
                strengthElement.className = 'password-strength strength-medium';
                strengthElement.textContent = 'Orta şifrə. Daha güclü edin: ' + feedback.join(', ');
            } else {
                strengthElement.className = 'password-strength strength-strong';
                strengthElement.textContent = 'Güclü şifrə!';
            }
        });

        // Password confirmation validation
        document.getElementById('password_confirm').addEventListener('input', function(e) {
            const password = document.getElementById('password').value;
            const passwordConfirm = e.target.value;
            
            if (password !== passwordConfirm) {
                e.target.setCustomValidity('Şifrələr uyğun gəlmir');
            } else {
                e.target.setCustomValidity('');
            }
        });

        // Username validation
        document.getElementById('username').addEventListener('input', function(e) {
            const username = e.target.value;
            if (username.length > 0 && !/^[a-zA-Z0-9_]{3,20}$/.test(username)) {
                e.target.setCustomValidity('3-20 simvol, yalnız hərf, rəqəm və _ istifadə edin');
            } else {
                e.target.setCustomValidity('');
            }
        });

        // Focus on first input
        window.addEventListener('load', function() {
            document.getElementById('full_name').focus();
        });
    </script>
</body>
</html>