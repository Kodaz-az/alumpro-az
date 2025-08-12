<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

// If user is already logged in, redirect to appropriate dashboard
if (SessionManager::isLoggedIn()) {
    $role = SessionManager::getUserRole();
    header("Location: {$role}/dashboard.php");
    exit;
}

//$db = new Database();

// Get some basic statistics for display
$stats = [];
try {
    // Get total customers (publicly safe numbers)
    $stmt = $db->query("SELECT COUNT(*) as count FROM customers");
    $stats['customers'] = $stmt->fetch()['count'] ?? 0;
    
    // Get total completed orders
    $stmt = $db->query("SELECT COUNT(*) as count FROM orders WHERE status = 'completed'");
    $stats['completed_orders'] = $stmt->fetch()['count'] ?? 0;
    
    // Get active news count
    $stmt = $db->query("SELECT COUNT(*) as count FROM news WHERE is_active = 1");
    $stats['news'] = $stmt->fetch()['count'] ?? 0;
    
    // Get gallery images count
    $stmt = $db->query("SELECT COUNT(*) as count FROM gallery WHERE is_active = 1");
    $stats['gallery'] = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    // If tables don't exist yet, use default values
    $stats = ['customers' => 0, 'completed_orders' => 0, 'news' => 0, 'gallery' => 0];
}

// Get recent news
$recent_news = [];
try {
    $stmt = $db->query("SELECT title, content, image, created_at FROM news WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3");
    $recent_news = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_news = [];
}

// Get gallery images
$gallery_images = [];
try {
    $stmt = $db->query("SELECT title, image, description FROM gallery WHERE is_active = 1 ORDER BY created_at DESC LIMIT 6");
    $gallery_images = $stmt->fetchAll();
} catch (Exception $e) {
    $gallery_images = [];
}
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumpro.Az - Aluminum Profil və Şüşə Sistemləri</title>
    <meta name="description" content="Azərbaycanda ən keyfiyyətli aluminum profil və şüşə sistemləri. Peşəkar quraşdırma və satış xidməti.">
    <meta name="keywords" content="aluminum, profil, şüşə, Bakı, Azərbaycan, qapı, pəncərə">
    
    <!-- Open Graph -->
    <meta property="og:title" content="Alumpro.Az - Aluminum Profil Sistemləri">
    <meta property="og:description" content="Azərbaycanda ən keyfiyyətli aluminum profil və şüşə sistemləri">
    <meta property="og:image" content="<?= SITE_URL ?>/assets/images/og-image.jpg">
    <meta property="og:url" content="<?= SITE_URL ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#20B2AA">
    
    <style>
        .hero-section {
            background: linear-gradient(135deg, rgba(32, 178, 170, 0.9), rgba(70, 130, 180, 0.9)), 
                        url('assets/images/hero-bg.jpg') center/cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            color: white;
            position: relative;
        }
        
        .hero-content {
            z-index: 2;
        }
        
        .stats-section {
            background: #f8f9fa;
            padding: 4rem 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            border-top: 4px solid var(--primary-color);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            color: var(--primary-color);
            display: block;
        }
        
        .features-section {
            padding: 4rem 0;
        }
        
        .feature-card {
            text-align: center;
            padding: 2rem 1rem;
            border-radius: 10px;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .feature-card:hover {
            background: #f8f9fa;
            transform: translateY(-5px);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }
        
        .news-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .news-card:hover {
            transform: translateY(-5px);
        }
        
        .news-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
        
        .gallery-item {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            position: relative;
            cursor: pointer;
        }
        
        .gallery-item:hover {
            transform: scale(1.05);
        }
        
        .gallery-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(32, 178, 170, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            color: white;
            font-weight: bold;
        }
        
        .gallery-item:hover .gallery-overlay {
            opacity: 1;
        }
        
        .cta-section {
            background: var(--gradient-primary);
            color: white;
            padding: 4rem 0;
        }
        
        .floating-support {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .support-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--gradient-primary);
            border: none;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .support-btn:hover {
            transform: scale(1.1);
            color: white;
        }
        
        .support-menu {
            position: absolute;
            bottom: 70px;
            right: 0;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 1rem;
            min-width: 200px;
            display: none;
        }
        
        .support-menu.show {
            display: block;
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .support-option {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            transition: background 0.3s ease;
            margin-bottom: 0.5rem;
        }
        
        .support-option:hover {
            background: #f8f9fa;
            color: #333;
        }
        
        .support-option i {
            margin-right: 0.75rem;
            width: 20px;
        }
        
        @media (max-width: 768px) {
            .hero-section {
                min-height: 80vh;
                padding: 2rem 0;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .floating-support {
                bottom: 80px; /* Above mobile navigation */
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background: rgba(32, 178, 170, 0.95); backdrop-filter: blur(10px);">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/">
                <i class="bi bi-house-door"></i> Alumpro.Az
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Ana Səhifə</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#services">Xidmətlər</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#gallery">Qalereya</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#news">Xəbərlər</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Əlaqə</a>
                    </li>
                </ul>
                
                <div class="d-flex gap-2">
                    <a href="auth/login.php" class="btn btn-outline-light">
                        <i class="bi bi-box-arrow-in-right"></i> Giriş
                    </a>
                    <a href="auth/register.php" class="btn btn-light">
                        <i class="bi bi-person-plus"></i> Qeydiyyat
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <h1 class="display-4 fw-bold mb-4">
                            Keyfiyyətli Aluminum Profil Sistemləri
                        </h1>
                        <p class="lead mb-4">
                            Azərbaycanda ən etibarlı aluminum profil və şüşə sistemləri təchizatçısı. 
                            Peşəkar quraşdırma və satış xidməti ilə yanınızdayıq.
                        </p>
                        <div class="d-flex gap-3 flex-wrap">
                            <a href="auth/register.php" class="btn btn-light btn-lg">
                                <i class="bi bi-person-plus"></i> Qeydiyyat
                            </a>
                            <a href="tel:+994501234567" class="btn btn-outline-light btn-lg">
                                <i class="bi bi-telephone"></i> Zəng Edin
                            </a>
                            <a href="https://wa.me/994501234567" target="_blank" class="btn btn-success btn-lg">
                                <i class="bi bi-whatsapp"></i> WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <img src="assets/images/hero-image.png" alt="Aluminum Systems" class="img-fluid" style="max-height: 500px;">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card">
                        <i class="bi bi-people text-primary display-4 mb-3"></i>
                        <span class="stat-number" data-counter="<?= $stats['customers'] ?>">0</span>
                        <h6>Məmnun Müştəri</h6>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card">
                        <i class="bi bi-check-circle text-success display-4 mb-3"></i>
                        <span class="stat-number" data-counter="<?= $stats['completed_orders'] ?>">0</span>
                        <h6>Tamamlanan Layihə</h6>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card">
                        <i class="bi bi-award text-warning display-4 mb-3"></i>
                        <span class="stat-number" data-counter="10">0</span>
                        <h6>İl Təcrübə</h6>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card">
                        <i class="bi bi-geo-alt text-info display-4 mb-3"></i>
                        <span class="stat-number" data-counter="50">0</span>
                        <h6>Şəhər</h6>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="features-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">Xidmətlərimiz</h2>
                <p class="lead text-muted">Aluminum profil sahəsində tam həllər təklif edirik</p>
            </div>
            
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-window"></i>
                        </div>
                        <h5>Pəncərə Sistemləri</h5>
                        <p class="text-muted">Enerji səmərəli və hava keçirməz pəncərə sistemləri</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-door-open"></i>
                        </div>
                        <h5>Qapı Sistemləri</h5>
                        <p class="text-muted">Təhlükəsiz və davamlı aluminum qapı həlləri</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-building"></i>
                        </div>
                        <h5>Fasad Sistemləri</h5>
                        <p class="text-muted">Müasir və estetik fasad üçün aluminum həllər</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h5>Keyfiyyət Zəmanəti</h5>
                        <p class="text-muted">Bütün məhsullarımızda uzunmüddətli zəmanət</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-tools"></i>
                        </div>
                        <h5>Peşəkar Quraşdırma</h5>
                        <p class="text-muted">Təcrübəli mütəxəssislər tərəfindən quraşdırma</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-headset"></i>
                        </div>
                        <h5>24/7 Dəstək</h5>
                        <p class="text-muted">Hər zaman əlçatan müştəri xidməti</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Gallery Section -->
    <?php if (!empty($gallery_images)): ?>
    <section id="gallery" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">Layihələrimiz</h2>
                <p class="lead text-muted">Tamamlanan işlərimizə nəzər yetirin</p>
            </div>
            
            <div class="row">
                <?php foreach (array_slice($gallery_images, 0, 6) as $image): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="gallery-item" onclick="openGalleryModal('<?= SITE_URL . '/' . GALLERY_IMAGES_PATH . $image['image'] ?>', '<?= htmlspecialchars($image['title']) ?>')">
                        <img src="<?= SITE_URL . '/' . GALLERY_IMAGES_PATH . $image['image'] ?>" 
                             alt="<?= htmlspecialchars($image['title']) ?>" 
                             class="img-fluid" style="height: 250px; width: 100%; object-fit: cover;">
                        <div class="gallery-overlay">
                            <div class="text-center">
                                <i class="bi bi-eye display-6 mb-2"></i>
                                <p class="mb-0"><?= htmlspecialchars($image['title']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="auth/login.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-images"></i> Bütün Layihələri Gör
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- News Section -->
    <?php if (!empty($recent_news)): ?>
    <section id="news" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">Son Xəbərlər</h2>
                <p class="lead text-muted">Ən son yeniliklər və xəbərlərdən xəbərdar olun</p>
            </div>
            
            <div class="row">
                <?php foreach ($recent_news as $news): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card news-card">
                        <?php if ($news['image']): ?>
                            <img src="<?= SITE_URL . '/' . GALLERY_IMAGES_PATH . $news['image'] ?>" 
                                 class="news-image" alt="<?= htmlspecialchars($news['title']) ?>">
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($news['title']) ?></h5>
                            <p class="card-text">
                                <?= htmlspecialchars(substr(strip_tags($news['content']), 0, 150)) ?>...
                            </p>
                            <small class="text-muted">
                                <i class="bi bi-calendar"></i> <?= date('d.m.Y', strtotime($news['created_at'])) ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container text-center">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h2 class="display-5 fw-bold mb-4">Layihənizi həyata keçirməyə hazırsınız?</h2>
                    <p class="lead mb-4">
                        Bizimlə əlaqə saxlayın və aluminum profil sistemləri üçün ən yaxşı həlləri əldə edin
                    </p>
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <a href="auth/register.php" class="btn btn-light btn-lg">
                            <i class="bi bi-person-plus"></i> İndi Qeydiyyatdan Keçin
                        </a>
                        <a href="tel:+994501234567" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-telephone"></i> Zəng Edin
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">Bizimlə Əlaqə</h2>
                <p class="lead text-muted">Suallarınız və ya sifarişləriniz üçün bizə müraciət edin</p>
            </div>
            
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="text-center">
                                <div class="feature-icon mx-auto mb-3">
                                    <i class="bi bi-telephone"></i>
                                </div>
                                <h5>Telefon</h5>
                                <p><a href="tel:+994501234567" class="text-decoration-none">+994 50 123 45 67</a></p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="text-center">
                                <div class="feature-icon mx-auto mb-3">
                                    <i class="bi bi-envelope"></i>
                                </div>
                                <h5>Email</h5>
                                <p><a href="mailto:info@alumpro.az" class="text-decoration-none">info@alumpro.az</a></p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="text-center">
                                <div class="feature-icon mx-auto mb-3">
                                    <i class="bi bi-geo-alt"></i>
                                </div>
                                <h5>Ünvan</h5>
                                <p>Bakı şəhəri, Azərbaycan</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="fw-bold mb-3">
                        <i class="bi bi-house-door text-primary"></i> Alumpro.Az
                    </h5>
                    <p class="mb-2">Aluminum profil və şüşə sistemləri sahəsində peşəkar xidmət.</p>
                    <p class="small text-muted mb-0">© <?= date('Y') ?> Alumpro.Az. Bütün hüquqlar qorunur.</p>
                </div>
                <div class="col-md-3">
                    <h6 class="fw-bold mb-3">Əlaqə</h6>
                    <p class="mb-1">
                        <i class="bi bi-telephone text-primary"></i> 
                        <a href="tel:+994501234567" class="text-white-50">+994 50 123 45 67</a>
                    </p>
                    <p class="mb-1">
                        <i class="bi bi-envelope text-primary"></i> 
                        <a href="mailto:info@alumpro.az" class="text-white-50">info@alumpro.az</a>
                    </p>
                    <p class="mb-0">
                        <i class="bi bi-geo-alt text-primary"></i> 
                        <span class="text-white-50">Bakı, Azərbaycan</span>
                    </p>
                </div>
                <div class="col-md-3">
                    <h6 class="fw-bold mb-3">Sosial Şəbəkələr</h6>
                    <div class="d-flex gap-2">
                        <a href="#" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-facebook"></i>
                        </a>
                        <a href="#" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-instagram"></i>
                        </a>
                        <a href="https://wa.me/994501234567" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-whatsapp"></i>
                        </a>
                        <a href="#" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-telegram"></i>
                        </a>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">Sistem versiyası: 1.0.0</small>
                        <br>
                        <small class="text-muted">
                            <a href="https://kodaz.az" target="_blank" class="text-white-50">Kodaz.az</a> tərəfindən hazırlanıb
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Floating Support Button -->
    <div class="floating-support">
        <div class="support-menu" id="supportMenu">
            <a href="tel:+994501234567" class="support-option">
                <i class="bi bi-telephone text-success"></i>
                <span>Zəng Edin</span>
            </a>
            <a href="https://wa.me/994501234567" target="_blank" class="support-option">
                <i class="bi bi-whatsapp text-success"></i>
                <span>WhatsApp</span>
            </a>
            <a href="mailto:info@alumpro.az" class="support-option">
                <i class="bi bi-envelope text-primary"></i>
                <span>Email</span>
            </a>
            <a href="auth/login.php" class="support-option">
                <i class="bi bi-box-arrow-in-right text-info"></i>
                <span>Sistemə Giriş</span>
            </a>
        </div>
        <button class="support-btn" id="supportBtn" data-bs-toggle="tooltip" title="Dəstək və Əlaqə">
            <i class="bi bi-headset"></i>
        </button>
    </div>

    <!-- Gallery Modal -->
    <div class="modal fade" id="galleryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="galleryModalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="galleryModalImage" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    <!-- PWA Install Prompt -->
    <div class="modal fade" id="pwaModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-phone"></i> Tətbiqi Yükləyin
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="bi bi-download display-4 text-primary mb-3"></i>
                    <p>Alumpro.Az tətbiqini telefonunuza yükləyib offline istifadə edin.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Sonra</button>
                    <button type="button" class="btn btn-primary" id="installPWA">Yüklə</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/pwa.js"></script>
    
    <script>
        // Support button toggle
        document.getElementById('supportBtn').addEventListener('click', function() {
            const menu = document.getElementById('supportMenu');
            menu.classList.toggle('show');
        });

        // Close support menu when clicking outside
        document.addEventListener('click', function(event) {
            const supportBtn = document.getElementById('supportBtn');
            const supportMenu = document.getElementById('supportMenu');
            
            if (!supportBtn.contains(event.target) && !supportMenu.contains(event.target)) {
                supportMenu.classList.remove('show');
            }
        });

        // Gallery modal
        function openGalleryModal(imageSrc, title) {
            document.getElementById('galleryModalImage').src = imageSrc;
            document.getElementById('galleryModalTitle').textContent = title;
            const modal = new bootstrap.Modal(document.getElementById('galleryModal'));
            modal.show();
        }

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Navbar background on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 100) {
                navbar.style.background = 'rgba(32, 178, 170, 0.95)';
            } else {
                navbar.style.background = 'rgba(32, 178, 170, 0.95)';
            }
        });
    </script>
</body>
</html>