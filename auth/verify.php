<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';
require_once '../includes/twilio.php';

// If already logged in, redirect
if (SessionManager::isLoggedIn()) {
    $role = SessionManager::getUserRole();
    header("Location: ../{$role}/dashboard.php");
    exit;
}

$db = new Database();
$error = '';
$message = '';
$phone = $_GET['phone'] ?? $_POST['phone'] ?? '';
$type = $_GET['type'] ?? 'registration'; // registration or login

// Clean phone number
$phone = Utils::formatPhone($phone);

if (!$phone) {
    header('Location: ' . ($type === 'login' ? 'login.php' : 'register.php'));
    exit;
}

// Check if user exists
$stmt = $db->query("SELECT * FROM users WHERE phone = ?", [$phone]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: register.php?error=user_not_found');
    exit;
}

// Check verification type
if ($type === 'login' && $user['is_verified']) {
    // User is already verified but needs additional verification for login
    $verification_type = 'login';
} elseif ($type === 'registration' && !$user['is_verified']) {
    $verification_type = 'registration';
} else {
    header('Location: login.php');
    exit;
}

// Handle verification code submission
if ($_POST && isset($_POST['verification_code'])) {
    try {
        $submitted_code = trim($_POST['verification_code']);
        
        if (empty($submitted_code)) {
            throw new Exception('Təsdiq kodu daxil edin');
        }
        
        // Check if code matches and is not expired (valid for 10 minutes)
        $stmt = $db->query("SELECT * FROM verification_codes 
                           WHERE phone = ? AND code = ? AND type = ? 
                           AND is_used = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE) 
                           ORDER BY created_at DESC LIMIT 1", 
                           [$phone, $submitted_code, $verification_type]);
        $verification = $stmt->fetch();
        
        if (!$verification) {
            // Check if code exists but expired
            $stmt = $db->query("SELECT * FROM verification_codes 
                               WHERE phone = ? AND code = ? AND type = ? 
                               ORDER BY created_at DESC LIMIT 1", 
                               [$phone, $submitted_code, $verification_type]);
            $expired_code = $stmt->fetch();
            
            if ($expired_code) {
                throw new Exception('Təsdiq kodunun müddəti bitib. Yeni kod istəyin.');
            } else {
                throw new Exception('Yanlış təsdiq kodu. Yenidən cəhd edin.');
            }
        }
        
        $db->getConnection()->beginTransaction();
        
        if ($verification_type === 'registration') {
            // Mark user as verified for registration
            $stmt = $db->query("UPDATE users SET is_verified = 1, status = 'active' WHERE id = ?", [$user['id']]);
            
            // Create customer profile if user role is customer
            if ($user['role'] === 'customer') {
                $stmt = $db->query("INSERT INTO customers (user_id, contact_person, phone, email) VALUES (?, ?, ?, ?)", 
                                  [$user['id'], $user['full_name'], $user['phone'], $user['email']]);
            }
            
            // Send welcome message
            try {
                $twilio = new TwilioManager();
                $welcome_message = "Salam {$user['full_name']}! 🎉\n\n";
                $welcome_message .= "Alumpro.Az sistemində qeydiyyatınız tamamlandı!\n\n";
                $welcome_message .= "Artıq sistemdən istifadə edə bilərsiniz:\n";
                $welcome_message .= "🌐 " . SITE_URL . "\n\n";
                $welcome_message .= "Bizə qoşulduğunuz üçün təşəkkür edirik!\n";
                $welcome_message .= "Alumpro.Az";
                
                $twilio->sendWhatsAppMessage($phone, $welcome_message);
            } catch (Exception $e) {
                error_log("Welcome WhatsApp message failed: " . $e->getMessage());
            }
            
            $success_message = 'Qeydiyyat tamamlandı! Sistemə daxil olursunuz...';
        } else {
            // Login verification successful
            $success_message = 'Təsdiq uğurlu! Sistemə daxil olursunuz...';
        }
        
        // Mark verification code as used
        $stmt = $db->query("UPDATE verification_codes SET is_used = 1, used_at = NOW() WHERE id = ?", [$verification['id']]);
        
        $db->getConnection()->commit();
        
        // Log successful verification
        Utils::logActivity($user['id'], 'phone_verified', "Telefon təsdiqi: $verification_type");
        
        // Auto login user
        SessionManager::login($user['id'], $user['role'], $user['store_id']);
        
        $message = $success_message;
        
        // Redirect after 2 seconds
        echo "<script>
                setTimeout(function() {
                    window.location.href = '../{$user['role']}/dashboard.php';
                }, 2000);
              </script>";
        
    } catch (Exception $e) {
        if ($db->getConnection()->inTransaction()) {
            $db->getConnection()->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Handle resend verification code
if ($_POST && isset($_POST['resend_code'])) {
    try {
        // Check if last code was sent less than 1 minute ago
        $stmt = $db->query("SELECT created_at FROM verification_codes 
                           WHERE phone = ? AND type = ? 
                           ORDER BY created_at DESC LIMIT 1", [$phone, $verification_type]);
        $last_code = $stmt->fetch();
        
        if ($last_code && strtotime($last_code['created_at']) > (time() - 60)) {
            throw new Exception('Çox tez cəhd. 1 dəqiqə gözləyin.');
        }
        
        // Generate new verification code
        $verification_code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Save to database
        $stmt = $db->query("INSERT INTO verification_codes (phone, code, type, created_at) VALUES (?, ?, ?, NOW())", 
                          [$phone, $verification_code, $verification_type]);
        
        // Send SMS via Twilio
        $twilio = new TwilioManager();
        $result = $twilio->sendVerificationCode($phone, $verification_code);
        
        if ($result['success']) {
            $message = 'Yeni təsdiq kodu göndərildi';
            Utils::logActivity($user['id'], 'verification_code_resent', "Təsdiq kodu yenidən göndərildi: $verification_type");
        } else {
            throw new Exception('SMS göndərilmədi. Yenidən cəhd edin.');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get user info for display
$masked_phone = substr($phone, 0, 4) . '***' . substr($phone, -2);
$page_title = $verification_type === 'login' ? 'Giriş Təsdiqi' : 'Telefon Təsdiqi';
$page_description = $verification_type === 'login' ? 
    'Hesabınıza giriş üçün təsdiq kodu daxil edin' : 
    'Telefonunuza göndərilən təsdiq kodunu daxil edin';
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Alumpro.Az</title>
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
        .verify-container {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 450px;
            width: 100%;
            margin: 1rem;
        }
        .verify-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #20B2AA, #4682B4);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin: 0 auto 1.5rem;
        }
        .verification-input {
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 0.5rem;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .verification-input:focus {
            border-color: #20B2AA;
            box-shadow: 0 0 0 0.2rem rgba(32, 178, 170, 0.25);
        }
        .resend-timer {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .phone-display {
            background: #f8f9fa;
            padding: 0.75rem;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 1rem;
        }
        .verification-type-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .verification-registration {
            background: #d1ecf1;
            color: #0c5460;
        }
        .verification-login {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="text-center mb-4">
            <div class="verify-icon">
                <i class="bi bi-shield-check"></i>
            </div>
            <span class="verification-type-badge verification-<?= $verification_type ?>">
                <?= $verification_type === 'login' ? 'Giriş Təsdiqi' : 'Qeydiyyat Təsdiqi' ?>
            </span>
            <h2 class="fw-bold text-primary"><?= $page_title ?></h2>
            <p class="text-muted"><?= $page_description ?></p>
        </div>

        <div class="phone-display">
            <i class="bi bi-phone text-primary"></i>
            <strong><?= htmlspecialchars($masked_phone) ?></strong>
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
            <input type="hidden" name="phone" value="<?= htmlspecialchars($phone) ?>">
            <input type="hidden" name="type" value="<?= htmlspecialchars($verification_type) ?>">
            
            <div class="mb-4">
                <label for="verification_code" class="form-label">Təsdiq Kodu</label>
                <input type="text" 
                       class="form-control verification-input" 
                       id="verification_code" 
                       name="verification_code" 
                       maxlength="6" 
                       pattern="[0-9]{6}"
                       placeholder="000000"
                       required
                       autocomplete="one-time-code"
                       inputmode="numeric">
                <div class="invalid-feedback">
                    6 rəqəmli təsdiq kodunu daxil edin
                </div>
            </div>

            <div class="d-grid gap-2 mb-3">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-circle"></i> Təsdiq Et
                </button>
            </div>
        </form>

        <div class="text-center">
            <p class="small text-muted mb-2">Kod almadınız?</p>
            
            <form method="POST" style="display: inline;">
                <input type="hidden" name="phone" value="<?= htmlspecialchars($phone) ?>">
                <input type="hidden" name="type" value="<?= htmlspecialchars($verification_type) ?>">
                <button type="submit" name="resend_code" class="btn btn-outline-secondary btn-sm" id="resendBtn">
                    <i class="bi bi-arrow-clockwise"></i> Yenidən Göndər
                </button>
            </form>
            
            <div class="resend-timer mt-2" id="timer" style="display: none;">
                Yenidən göndərmək üçün <span id="countdown">60</span> saniyə gözləyin
            </div>
        </div>

        <hr>

        <div class="text-center">
            <p class="small text-muted mb-2">
                <?= $verification_type === 'login' ? 'Giriş etməkdən imtina?' : 'Telefon nömrəsi səhvdir?' ?>
            </p>
            <a href="<?= $verification_type === 'login' ? 'login.php' : 'register.php' ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Geri Qayıt
            </a>
        </div>

        <!-- Help Section -->
        <div class="mt-4 p-3 bg-light rounded">
            <h6 class="fw-bold mb-2">
                <i class="bi bi-info-circle text-info"></i> Kömək
            </h6>
            <ul class="list-unstyled small mb-0">
                <li>• Kod 10 dəqiqə etibarlıdır</li>
                <li>• SMS gəlmirsə, spam qovluğunu yoxlayın</li>
                <li>• 1 dəqiqədə bir yeni kod istəyə bilərsiniz</li>
                <li>• Problem davam edərsə: <a href="tel:<?= SITE_PHONE ?>"><?= SITE_PHONE ?></a></li>
            </ul>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-format verification code input
        document.getElementById('verification_code').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 6) {
                value = value.substring(0, 6);
            }
            e.target.value = value;
            
            // Auto-submit when 6 digits entered
            if (value.length === 6) {
                e.target.form.submit();
            }
        });

        // Resend timer
        let resendTimer = 0;
        const resendBtn = document.getElementById('resendBtn');
        const timerDiv = document.getElementById('timer');
        const countdown = document.getElementById('countdown');

        function startResendTimer() {
            resendTimer = 60;
            resendBtn.disabled = true;
            timerDiv.style.display = 'block';
            
            const interval = setInterval(() => {
                resendTimer--;
                countdown.textContent = resendTimer;
                
                if (resendTimer <= 0) {
                    clearInterval(interval);
                    resendBtn.disabled = false;
                                    timerDiv.style.display = 'none';
                }
            }, 1000);
        }

        // Start timer if page was loaded after resend
        <?php if ($message && strpos($message, 'göndərildi') !== false): ?>
            startResendTimer();
        <?php endif; ?>

        // Start timer when resend button is clicked
        resendBtn.addEventListener('click', function() {
            setTimeout(startResendTimer, 100);
        });

        // Focus on input when page loads
        window.addEventListener('load', function() {
            document.getElementById('verification_code').focus();
        });

        // Paste support for verification code
        document.getElementById('verification_code').addEventListener('paste', function(e) {
            setTimeout(() => {
                const value = e.target.value.replace(/\D/g, '');
                if (value.length === 6) {
                    e.target.form.submit();
                }
            }, 100);
        });

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

        // Auto-refresh page every 5 minutes to prevent session timeout
        setTimeout(function() {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>