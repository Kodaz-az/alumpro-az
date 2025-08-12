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

$db = new Database();
$error = '';
$message = '';
$step = $_GET['step'] ?? '1'; // 1: phone input, 2: code verification, 3: new password

if ($_POST) {
    try {
        if ($_POST['action'] === 'send_reset_code') {
            $phone = Utils::formatPhone(trim($_POST['phone']));
            
            if (empty($phone)) {
                throw new Exception('Telefon nömrəsi mütləqdir');
            }
            
            // Check if user exists
            $stmt = $db->query("SELECT id, full_name FROM users WHERE phone = ? AND status = 'active'", [$phone]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('Bu telefon nömrəsi ilə qeydiyyatdan keçmiş istifadəçi tapılmadı');
            }
            
            // Check rate limiting
            $stmt = $db->query("SELECT COUNT(*) as count FROM verification_codes 
                               WHERE phone = ? AND type = 'password_reset' 
                               AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)", [$phone]);
            $recent_codes = $stmt->fetch()['count'];
            
            if ($recent_codes >= 3) {
                throw new Exception('Saatda maksimum 3 dəfə kod göndərə bilərsiniz');
            }
            
            // Generate reset code
            $reset_code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Save to database
            $stmt = $db->query("INSERT INTO verification_codes (phone, code, type, created_at) VALUES (?, ?, 'password_reset', NOW())", 
                              [$phone, $reset_code]);
            
            // Send SMS via Twilio
            if (isFeatureEnabled('sms_verification') && !empty(TWILIO_ACCOUNT_SID)) {
                try {
                    require_once '../includes/twilio.php';
                    $twilio = new TwilioManager();
                    $result = $twilio->sendPasswordResetCode($phone, $reset_code);
                    
                    if ($result['success']) {
                        Utils::logActivity($user['id'], 'password_reset_requested', 'Şifrə bərpa kodu göndərildi');
                        header("Location: forgot-password.php?step=2&phone=" . urlencode($phone));
                        exit;
                    } else {
                        throw new Exception('SMS göndərilmədi: ' . $result['error']);
                    }
                } catch (Exception $e) {
                    throw new Exception('SMS xidməti hazırda əlçatan deyil: ' . $e->getMessage());
                }
            } else {
                throw new Exception('SMS xidməti konfiqurasiya edilməyib');
            }
        }
        
        if ($_POST['action'] === 'verify_reset_code') {
            $phone = Utils::formatPhone($_POST['phone']);
            $code = trim($_POST['code']);
            
            if (empty($code)) {
                throw new Exception('Təsdiq kodu mütləqdir');
            }
            
            // Verify code
            $stmt = $db->query("SELECT v.*, u.id as user_id FROM verification_codes v
                               JOIN users u ON v.phone = u.phone
                               WHERE v.phone = ? AND v.code = ? AND v.type = 'password_reset'
                               AND v.is_used = 0 AND v.created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                               ORDER BY v.created_at DESC LIMIT 1", 
                               [$phone, $code]);
            $verification = $stmt->fetch();
            
            if (!$verification) {
                throw new Exception('Yanlış və ya vaxtı keçmiş təsdiq kodu');
            }
            
            // Generate secure token for password reset
            $reset_token = bin2hex(random_bytes(32));
            $_SESSION['password_reset_token'] = $reset_token;
            $_SESSION['password_reset_user_id'] = $verification['user_id'];
            $_SESSION['password_reset_expires'] = time() + 900; // 15 minutes
            
            // Mark code as used
            $db->query("UPDATE verification_codes SET is_used = 1, used_at = NOW() WHERE id = ?", 
                      [$verification['id']]);
            
            header("Location: forgot-password.php?step=3&token=" . $reset_token);
            exit;
        }
        
        if ($_POST['action'] === 'reset_password') {
            $token = $_POST['token'];
            $password = $_POST['password'];
            $password_confirm = $_POST['password_confirm'];
            
            // Verify token
            if (!isset($_SESSION['password_reset_token']) || 
                $_SESSION['password_reset_token'] !== $token ||
                $_SESSION['password_reset_expires'] < time()) {
                throw new Exception('Token vaxtı keçib və ya yanlışdır');
            }
            
            if ($password !== $password_confirm) {
                throw new Exception('Şifrələr uyğun gəlmir');
            }
            
            if (strlen($password) < PASSWORD_MIN_LENGTH) {
                throw new Exception('Şifrə ən azı ' . PASSWORD_MIN_LENGTH . ' simvol olmalıdır');
            }
            
            $user_id = $_SESSION['password_reset_user_id'];
            
            // Update password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->query("UPDATE users SET password = ?, remember_token = NULL WHERE id = ?", 
                              [$hashed_password, $user_id]);
            
            // Clear session data
            unset($_SESSION['password_reset_token']);
            unset($_SESSION['password_reset_user_id']);
            unset($_SESSION['password_reset_expires']);
            
            // Log activity
            Utils::logActivity($user_id, 'password_reset', 'Şifrə bərpa edildi');
            
            // Send notification SMS
            try {
                $stmt = $db->query("SELECT phone, full_name FROM users WHERE id = ?", [$user_id]);
                $user = $stmt->fetch();
                
                require_once '../includes/twilio.php';
                $twilio = new TwilioManager();
                $notification = "Salam {$user['full_name']}!\n\n";
                $notification .= "Hesabınızın şifrəsi uğurla dəyişdirildi.\n";
                $notification .= "Tarix: " . date('d.m.Y H:i') . "\n\n";
                $notification .= "Əgər bu siz deyilsinizsə, dərhal bizimlə əlaqə saxlayın.\n\n";
                $notification .= "Alumpro.Az";
                
                $twilio->sendSMS($user['phone'], $notification);
            } catch (Exception $e) {
                error_log("Password reset notification failed: " . $e->getMessage());
            }
            
            $message = 'Şifrə uğurla dəyişdirildi! İndi yeni şifrə ilə giriş edə bilərsiniz.';
            
            // Auto redirect after 3 seconds
            echo "<script>
                    setTimeout(function() {
                        window.location.href = 'login.php?message=password_reset';
                    }, 3000);
                  </script>";
        }
        
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
    <title>Şifrə Bərpası - Alumpro.Az</title>
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
        .reset-container {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 450px;
            width: 100%;
            margin: 1rem;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 0.5rem;
            font-weight: bold;
            color: #6c757d;
        }
        .step.active {
            background: var(--primary-color);
            color: white;
        }
        .step.completed {
            background: #198754;
            color: white;
        }
        .step-line {
            width: 30px;
            height: 2px;
            background: #dee2e6;
            margin-top: 19px;
        }
        .step-line.completed {
            background: #198754;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step <?= $step >= 1 ? ($step > 1 ? 'completed' : 'active') : '' ?>">1</div>
            <div class="step-line <?= $step > 1 ? 'completed' : '' ?>"></div>
            <div class="step <?= $step >= 2 ? ($step > 2 ? 'completed' : 'active') : '' ?>">2</div>
            <div class="step-line <?= $step > 2 ? 'completed' : '' ?>"></div>
            <div class="step <?= $step >= 3 ? 'active' : '' ?>">3</div>
        </div>

        <div class="text-center mb-4">
            <div class="mb-3">
                <i class="bi bi-key display-4 text-primary"></i>
            </div>
            <h2 class="fw-bold text-primary">Şifrə Bərpası</h2>
            
            <?php if ($step == 1): ?>
                <p class="text-muted">Telefon nömrənizi daxil edin</p>
            <?php elseif ($step == 2): ?>
                <p class="text-muted">Telefonunuza göndərilən kodu daxil edin</p>
            <?php else: ?>
                <p class="text-muted">Yeni şifrənizi təyin edin</p>
            <?php endif; ?>
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

        <!-- Step 1: Phone Input -->
        <?php if ($step == 1): ?>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="send_reset_code">
                
                <div class="mb-3">
                    <label for="phone" class="form-label">Telefon Nömrəsi</label>
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
                               value="<?= htmlspecialchars($_GET['phone'] ?? '') ?>">
                    </div>
                    <div class="form-text">Qeydiyyat zamanı istifadə etdiyiniz telefon nömrəsi</div>
                    <div class="invalid-feedback">
                        Düzgün telefon nömrəsi daxil edin
                    </div>
                </div>

                <div class="d-grid gap-2 mb-3">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-send"></i> Təsdiq Kodu Göndər
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <!-- Step 2: Code Verification -->
        <?php if ($step == 2): ?>
            <div class="alert alert-info" role="alert">
                <i class="bi bi-info-circle"></i>
                Təsdiq kodu <strong><?= substr($_GET['phone'], 0, 4) ?>***<?= substr($_GET['phone'], -2) ?></strong> nömrəsinə göndərildi
            </div>

            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="verify_reset_code">
                <input type="hidden" name="phone" value="<?= htmlspecialchars($_GET['phone']) ?>">
                
                <div class="mb-3">
                    <label for="code" class="form-label">Təsdiq Kodu</label>
                    <input type="text" 
                           class="form-control text-center" 
                           id="code" 
                           name="code" 
                           maxlength="6" 
                           pattern="[0-9]{6}"
                           placeholder="000000"
                           required
                           style="font-size: 1.5rem; letter-spacing: 0.5rem;">
                    <div class="form-text">6 rəqəmli kod (15 dəqiqə etibarlı)</div>
                    <div class="invalid-feedback">
                        6 rəqəmli təsdiq kodunu daxil edin
                    </div>
                </div>

                <div class="d-grid gap-2 mb-3">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle"></i> Kodu Təsdiq Et
                    </button>
                </div>
            </form>

            <div class="text-center">
                <p class="small text-muted">Kod almadınız?</p>
                <a href="forgot-password.php?step=1&phone=<?= urlencode($_GET['phone']) ?>" 
                   class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-clockwise"></i> Yenidən Göndər
                </a>
            </div>
        <?php endif; ?>

        <!-- Step 3: New Password -->
        <?php if ($step == 3): ?>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token']) ?>">
                
                <div class="mb-3">
                    <label for="password" class="form-label">Yeni Şifrə</label>
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
                               placeholder="Yeni şifrənizi daxil edin">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                            <i class="bi bi-eye" id="passwordToggleIcon"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback">
                        Şifrə ən azı <?= PASSWORD_MIN_LENGTH ?> simvol olmalıdır
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password_confirm" class="form-label">Şifrə Təkrarı</label>
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

                <div class="d-grid gap-2 mb-3">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-check-circle"></i> Şifrəni Dəyişdir
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <hr>

        <div class="text-center">
            <a href="login.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Girişə Qayıt
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

        // Phone number formatting
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
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
        }

        // Code input formatting
        const codeInput = document.getElementById('code');
        if (codeInput) {
            codeInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 6) {
                    value = value.substring(0, 6);
                }
                e.target.value = value;
                
                if (value.length === 6) {
                    e.target.form.submit();
                }
            });

            codeInput.focus();
        }

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

        // Password confirmation validation
        const passwordConfirm = document.getElementById('password_confirm');
        if (passwordConfirm) {
            passwordConfirm.addEventListener('input', function(e) {
                const password = document.getElementById('password').value;
                const passwordConfirmValue = e.target.value;
                
                if (password !== passwordConfirmValue) {
                    e.target.setCustomValidity('Şifrələr uyğun gəlmir');
                } else {
                    e.target.setCustomValidity('');
                }
            });
        }

        // Auto-focus first input
        window.addEventListener('load', function() {
            const firstInput = document.querySelector('input[type="tel"], input[type="text"], input[type="password"]');
            if (firstInput) {
                firstInput.focus();
            }
        });
    </script>
</body>
</html>