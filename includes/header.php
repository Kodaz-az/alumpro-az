<?php
// Get current user info if logged in
$current_user = null;
$unread_notifications = 0;

if (SessionManager::isLoggedIn()) {
    $user_id = SessionManager::getUserId();
    $db = new Database();
    
    try {
        // Get user info
        $stmt = $db->query("SELECT full_name, profile_image, role FROM users WHERE id = ?", [$user_id]);
        $current_user = $stmt->fetch();
        
        // Get unread notifications count (if notifications system exists)
        $stmt = $db->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", [$user_id]);
        $notification_result = $stmt->fetch();
        $unread_notifications = $notification_result['count'] ?? 0;
    } catch (Exception $e) {
        // Handle gracefully if tables don't exist
        $current_user = ['full_name' => 'User', 'profile_image' => null, 'role' => 'customer'];
    }
}
?>

<!DOCTYPE html>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background: var(--gradient-primary);">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= SITE_URL ?>">
            <i class="bi bi-house-door"></i> <?= SITE_NAME ?>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <?php if (SessionManager::isLoggedIn()): ?>
                <!-- Logged in navigation -->
                <ul class="navbar-nav me-auto">
                    <?php if (SessionManager::getUserRole() === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= SITE_URL ?>/admin/dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= SITE_URL ?>/admin/users.php">
                                <i class="bi bi-people"></i> İstifadəçilər
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= SITE_URL ?>/warehouse/products.php">
                                <i class="bi bi-box-seam"></i> Anbar
                            </a>
                        </li>
                    <?php elseif (SessionManager::getUserRole() === 'sales'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= SITE_URL ?>/sales/dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= SITE_URL ?>/sales/orders.php">
                                <i class="bi bi-box"></i> Sifarişlər
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= SITE_URL ?>/sales/customers.php">
                                <i class="bi bi-people"></i> Müştərilər
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= SITE_URL ?>/customer/dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= SITE_URL ?>/customer/orders.php">
                                <i class="bi bi-box"></i> Sifarişlərim
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <!-- Support Button -->
                    <li class="nav-item">
                        <button class="nav-link btn btn-link text-white" data-bs-toggle="modal" data-bs-target="#supportModal">
                            <i class="bi bi-headset"></i> Dəstək
                        </button>
                    </li>
                    
                    <!-- Notifications -->
                    <li class="nav-item dropdown">
                        <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-bell"></i>
                            <?php if ($unread_notifications > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?= $unread_notifications ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Bildirişlər</h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if ($unread_notifications > 0): ?>
                                <li><a class="dropdown-item" href="#">Yeni bildiriş var</a></li>
                            <?php else: ?>
                                <li><span class="dropdown-item-text text-muted">Yeni bildiriş yoxdur</span></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#">Bütün bildirişləri gör</a></li>
                        </ul>
                    </li>
                    
                    <!-- User Profile -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown">
                            <?php if ($current_user['profile_image']): ?>
                                <img src="<?= SITE_URL . '/' . PROFILE_IMAGES_PATH . $current_user['profile_image'] ?>" 
                                     class="rounded-circle me-2" width="32" height="32" 
                                     style="object-fit: cover;" alt="Profile">
                            <?php else: ?>
                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-2" 
                                     style="width: 32px; height: 32px;">
                                    <i class="bi bi-person text-dark"></i>
                                </div>
                            <?php endif; ?>
                            <span class="d-none d-md-inline"><?= htmlspecialchars($current_user['full_name']) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Hesab</h6></li>
                            <li>
                                <a class="dropdown-item" href="<?= SITE_URL ?>/<?= SessionManager::getUserRole() ?>/profile.php">
                                    <i class="bi bi-person"></i> Profil
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= SITE_URL ?>/<?= SessionManager::getUserRole() ?>/settings.php">
                                    <i class="bi bi-gear"></i> Ayarlar
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?= SITE_URL ?>/auth/logout.php">
                                    <i class="bi bi-box-arrow-right"></i> Çıxış
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            <?php else: ?>
                <!-- Guest navigation -->
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= SITE_URL ?>/#home">Ana Səhifə</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= SITE_URL ?>/#services">Xidmətlər</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= SITE_URL ?>/#contact">Əlaqə</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= SITE_URL ?>/auth/login.php">
                            <i class="bi bi-box-arrow-in-right"></i> Giriş
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= SITE_URL ?>/auth/register.php">
                            <i class="bi bi-person-plus"></i> Qeydiyyat
                        </a>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Support Modal -->
<div class="modal fade" id="supportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-headset"></i> Dəstək Xidməti
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <a href="tel:<?= SITE_PHONE ?>" class="btn btn-success w-100 py-3">
                            <i class="bi bi-telephone display-6 d-block mb-2"></i>
                            <strong>Zəng Edin</strong>
                            <br>
                            <small><?= SITE_PHONE ?></small>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="https://wa.me/<?= str_replace(['+', ' '], '', SITE_PHONE) ?>" 
                           target="_blank" class="btn btn-success w-100 py-3">
                            <i class="bi bi-whatsapp display-6 d-block mb-2"></i>
                            <strong>WhatsApp</strong>
                            <br>
                            <small>Birbaşa mesaj</small>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="mailto:<?= SITE_EMAIL ?>" class="btn btn-primary w-100 py-3">
                            <i class="bi bi-envelope display-6 d-block mb-2"></i>
                            <strong>Email</strong>
                            <br>
                            <small><?= SITE_EMAIL ?></small>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <button class="btn btn-info w-100 py-3" data-bs-toggle="modal" data-bs-target="#liveChatModal">
                            <i class="bi bi-chat-dots display-6 d-block mb-2"></i>
                            <strong>Canlı Söhbət</strong>
                            <br>
                            <small>Online dəstək</small>
                        </button>
                    </div>
                </div>
                
                <hr>
                
                <div class="text-center">
                    <h6 class="mb-3">Tez-tez Verilən Suallar</h6>
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    Sifarişimi necə izləyə bilərəm?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Dashboard bölməsindən "Sifarişlərim" səhifəsinə daxil olaraq sifarişinizin statusunu izləyə bilərsiniz.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    Təhvil müddəti nə qədərdir?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Orta hesabla 7-14 iş günü. Dəqiq müddət sifarişin mürəkkəbliyindən asılıdır.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    Zəmanət müddəti nə qədərdir?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Bütün məhsullarımızda 2 il zəmanət verilir. Quraşdırma işlərinə 1 il zəmanət.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <small class="text-muted">İş saatları: Bazar ertəsi - Şənbə, 09:00 - 18:00</small>
            </div>
        </div>
    </div>
</div>

<!-- Live Chat Modal -->
<div class="modal fade" id="liveChatModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-gradient-info text-white">
                <h5 class="modal-title">
                    <i class="bi bi-chat-dots"></i> Canlı Dəstək Söhbəti
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="supportChat" class="chat-container" style="height: 400px; overflow-y: auto; padding: 1rem;">
                    <div class="chat-message bot">
                        <div class="chat-bubble bot">
                            <i class="bi bi-robot"></i> Salam! Alumpro.Az dəstək xidmətinə xoş gəlmisiniz. 
                            Sizə necə kömək edə bilərəm?
                        </div>
                        <small class="text-muted"><?= date('H:i') ?></small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="input-group">
                    <input type="text" class="form-control" id="supportMessage" 
                           placeholder="Mesajınızı yazın..." maxlength="500">
                    <button class="btn btn-outline-secondary" type="button" id="voiceButton" 
                            data-voice-button data-target="supportMessage" title="Səs yazma">
                        <i class="bi bi-mic"></i>
                    </button>
                    <button class="btn btn-primary" type="button" id="sendSupportMessage">
                        <i class="bi bi-send"></i>
                    </button>
                </div>
                <div id="quickQuestions" class="mt-2">
                    <!-- Quick questions will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.chat-container {
    background: #f8f9fa;
    border-radius: 8px;
}

.chat-message {
    margin-bottom: 1rem;
    display: flex;
    flex-direction: column;
}

.chat-message.user {
    align-items: flex-end;
}

.chat-message.bot {
    align-items: flex-start;
}

.chat-bubble {
    max-width: 70%;
    padding: 0.75rem 1rem;
    border-radius: 18px;
    word-wrap: break-word;
}

.chat-bubble.user {
    background: var(--primary-color);
    color: white;
}

.chat-bubble.bot {
    background: white;
    color: #333;
    border: 1px solid #dee2e6;
}

.quick-question {
    display: inline-block;
    background: #e9ecef;
    color: #495057;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    text-decoration: none;
    font-size: 0.8rem;
    margin: 0.2rem;
    transition: background 0.3s ease;
}

.quick-question:hover {
    background: var(--primary-color);
    color: white;
    text-decoration: none;
}

#quickQuestions {
    max-height: 60px;
    overflow-y: auto;
}
</style>

<script src="assets/js/support.js"></script>