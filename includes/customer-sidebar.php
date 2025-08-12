<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4">
            <?php if (isset($customer) && $customer): ?>
                <?php
                $stmt = $db->query("SELECT profile_image FROM users WHERE id = ?", [SessionManager::getUserId()]);
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
                <small class="text-white-50"><?= htmlspecialchars($customer['contact_person']) ?></small>
            <?php endif; ?>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>" 
                   href="dashboard.php">
                    <i class="bi bi-house-door"></i> Ana Səh