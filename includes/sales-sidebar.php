<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4">
            <?php
            $stmt = $db->query("SELECT full_name, profile_image FROM users WHERE id = ?", [SessionManager::getUserId()]);
            $user_profile = $stmt->fetch();
            ?>
            <div class="mb-2">
                <?php if ($user_profile['profile_image']): ?>
                    <img src="<?= SITE_URL . '/' . PROFILE_IMAGES_PATH . $user_profile['profile_image'] ?>" 
                         class="rounded-circle" width="60" height="60" 
                         style="object-fit: cover;" alt="Profile">
                <?php else: ?>
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto" 
                         style="width: 60px; height: 60px;">
                        <i class="bi bi-person text-muted"></i>
                    </div>
                <?php endif; ?>
            </div>
            <small class="text-white-50"><?= htmlspecialchars($user_profile['full_name']) ?></small>
            <br>
            <small class="text-white-50">Satış Meneceri</small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>" 
                   href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> Panel
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'new-order.php' ? 'active' : '' ?>" 
                   href="new-order.php">
                    <i class="bi bi-plus-circle"></i> Yeni Sifariş
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : '' ?>" 
                   href="orders.php">
                    <i class="bi bi-box"></i> Sifarişlər
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : '' ?>" 
                   href="customers.php">
                    <i class="bi bi-people"></i> Müştərilər
                </a>
            </li>
            
            <!-- Warehouse Access (if permitted) -->
            <?php if (isset($user['warehouse_access']) && $user['warehouse_access']): ?>
                <li class="nav-item">
                    <hr class="text-white-50">
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../warehouse/products.php">
                        <i class="bi bi-box-seam"></i> Anbar
                    </a>
                </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <hr class="text-white-50">
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>" 
                   href="reports.php">
                    <i class="bi bi-graph-up"></i> Hesabatlar
                </a>
            </li>
            
            <!-- Admin Access -->
            <?php if (SessionManager::getUserRole() === 'admin'): ?>
                <li class="nav-item">
                    <hr class="text-white-50">
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../admin/dashboard.php">
                        <i class="bi bi-gear"></i> Admin Panel
                    </a>
                </li>
            <?php endif; ?>
            
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