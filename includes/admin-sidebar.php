<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4">
            <?php
            $stmt = $db->query("SELECT full_name, profile_image FROM users WHERE id = ?", [SessionManager::getUserId()]);
            $admin_profile = $stmt->fetch();
            ?>
            <div class="mb-2">
                <?php if ($admin_profile['profile_image']): ?>
                    <img src="<?= SITE_URL . '/' . PROFILE_IMAGES_PATH . $admin_profile['profile_image'] ?>" 
                         class="rounded-circle" width="60" height="60" 
                         style="object-fit: cover;" alt="Profile">
                <?php else: ?>
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto" 
                         style="width: 60px; height: 60px;">
                        <i class="bi bi-person text-muted"></i>
                    </div>
                <?php endif; ?>
            </div>
            <small class="text-white-50"><?= htmlspecialchars($admin_profile['full_name']) ?></small>
            <br>
            <small class="text-white-50"><i class="bi bi-shield-check"></i> Administrator</small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>" 
                   href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> Ana Panel
                </a>
            </li>
            
            <!-- User Management -->
            <li class="nav-item">
                <hr class="text-white-50">
                <small class="text-white-50 px-3">İSTİFADƏÇİ İDARƏSİ</small>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>" 
                   href="users.php">
                    <i class="bi bi-people"></i> İstifadəçilər
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : '' ?>" 
                   href="customers.php">
                    <i class="bi bi-person-badge"></i> Müştərilər
                </a>
            </li>
            
            <!-- Orders Management -->
            <li class="nav-item">
                <hr class="text-white-50">
                <small class="text-white-50 px-3">SİFARİŞ İDARƏSİ</small>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : '' ?>" 
                   href="orders.php">
                    <i class="bi bi-box"></i> Bütün Sifarişlər
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../sales/new-order.php">
                    <i class="bi bi-plus-circle"></i> Yeni Sifariş
                </a>
            </li>
            
            <!-- Warehouse Management -->
            <li class="nav-item">
                <hr class="text-white-50">
                <small class="text-white-50 px-3">ANBAR İDARƏSİ</small>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'warehouse') !== false ? 'active' : '' ?>" 
                   href="../warehouse/products.php">
                    <i class="bi bi-box-seam"></i> Məhsullar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../warehouse/glass.php">
                    <i class="bi bi-window"></i> Şüşələr
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../warehouse/accessories.php">
                    <i class="bi bi-tools"></i> Aksesuarlar
                </a>
            </li>
            
            <!-- Content Management -->
            <li class="nav-item">
                <hr class="text-white-50">
                <small class="text-white-50 px-3">MƏZMUN İDARƏSİ</small>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'news.php' ? 'active' : '' ?>" 
                   href="news.php">
                    <i class="bi bi-newspaper"></i> Xəbərlər
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'gallery.php' ? 'active' : '' ?>" 
                   href="gallery.php">
                    <i class="bi bi-images"></i> Qalereya
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'support.php' ? 'active' : '' ?>" 
                   href="support.php">
                    <i class="bi bi-headset"></i> Dəstək Xidməti
                </a>
            </li>
            
            <!-- Reports -->
            <li class="nav-item">
                <hr class="text-white-50">
                <small class="text-white-50 px-3">HESABATLAR</small>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>" 
                   href="reports.php">
                    <i class="bi bi-graph-up"></i> Analitika
                </a>
            </li>
            
            <!-- System Settings -->
            <li class="nav-item">
                <hr class="text-white-50">
                <small class="text-white-50 px-3">SİSTEM</small>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>" 
                   href="settings.php">
                    <i class="bi bi-gear"></i> Ayarlar
                </a>
            </li>
            
            <!-- Back to Sales -->
            <li class="nav-item mt-3">
                <hr class="text-white-50">
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../sales/dashboard.php">
                    <i class="bi bi-arrow-left"></i> Satış Panelinə qayıt
                </a>
            </li>
            
            <li class="nav-item mt-auto">
                <hr class="text-white-50">
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../auth/logout.php">
                    <i class="bi bi-box-arrow-right"></i> Çıxış
                </a>
            </li>
        </ul>
    </div>
</nav>