<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireCustomer();

$user_id = SessionManager::getUserId();
$db = new Database();
$message = '';
$error = '';

// Handle profile update
if ($_POST) {
    try {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        $company_name = trim($_POST['company_name']);
        $notes = trim($_POST['notes']);
        
        if (empty($full_name)) {
            throw new Exception('Ad və soyad mütləqdir');
        }
        
        // Validate email if provided
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email formatı düzgün deyil');
        }
        
        $db->getConnection()->beginTransaction();
        
        // Update user table
        $stmt = $db->query("UPDATE users SET full_name = ?, email = ? WHERE id = ?", 
                          [$full_name, $email, $user_id]);
        
        // Update customer table
        $stmt = $db->query("UPDATE customers SET contact_person = ?, email = ?, address = ?, company_name = ?, notes = ? WHERE user_id = ?", 
                          [$full_name, $email, $address, $company_name, $notes, $user_id]);
        
        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $image_name = Utils::uploadFile($_FILES['profile_image'], PROFILE_IMAGES_PATH, ['jpg', 'jpeg', 'png', 'gif']);
            if ($image_name) {
                // Delete old image
                $stmt = $db->query("SELECT profile_image FROM users WHERE id = ?", [$user_id]);
                $old_image = $stmt->fetch()['profile_image'];
                if ($old_image && file_exists(PROFILE_IMAGES_PATH . $old_image)) {
                    unlink(PROFILE_IMAGES_PATH . $old_image);
                }
                
                // Update with new image
                $stmt = $db->query("UPDATE users SET profile_image = ? WHERE id = ?", [$image_name, $user_id]);
            }
        }
        
        $db->getConnection()->commit();
        
        Utils::logActivity($user_id, 'profile_updated', 'Profil məlumatları yeniləndi');
        
        $message = 'Profil məlumatları uğurla yeniləndi';
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $error = $e->getMessage();
    }
}

// Handle password change
if ($_POST && isset($_POST['change_password'])) {
    try {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password)) {
            throw new Exception('Bütün şifrə sahələri mütləqdir');
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception('Yeni şifrələr uyğun gəlmir');
        }
        
        if (strlen($new_password) < PASSWORD_MIN_LENGTH) {
            throw new Exception('Yeni şifrə ən azı ' . PASSWORD_MIN_LENGTH . ' simvol olmalıdır');
        }
        
        // Verify current password
        $stmt = $db->query("SELECT password FROM users WHERE id = ?", [$user_id]);
        $user = $stmt->fetch();
        
        if (!password_verify($current_password, $user['password'])) {
            throw new Exception('Hazırki şifrə səhvdir');
        }
        
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->query("UPDATE users SET password = ?, remember_token = NULL WHERE id = ?", 
                          [$hashed_password, $user_id]);
        
        Utils::logActivity($user_id, 'password_changed', 'Şifrə dəyişdirildi');
        
        $message = 'Şifrə uğurla dəyişdirildi';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get user and customer data
$stmt = $db->query("SELECT u.*, c.address, c.company_name, c.notes, c.total_orders, c.total_spent 
                    FROM users u 
                    LEFT JOIN customers c ON u.id = c.user_id 
                    WHERE u.id = ?", [$user_id]);
$user_data = $stmt->fetch();

// Get order statistics
$stmt = $db->query("SELECT 
                    COUNT(*) as total_orders,
                    COALESCE(SUM(total_amount), 0) as total_spent,
                    COALESCE(AVG(total_amount), 0) as average_order,
                    MAX(order_date) as last_order_date
                    FROM orders WHERE customer_id = (SELECT id FROM customers WHERE user_id = ?)", 
                    [$user_id]);
$order_stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Alumpro.Az</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/customer-sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-person text-primary"></i> Profil
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

                <div class="row">
                    <!-- Profile Info -->
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-person-fill"></i> Şəxsi Məlumatlar
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-4 text-center mb-4">
                                            <div class="position-relative d-inline-block">
                                                <?php if ($user_data['profile_image']): ?>
                                                    <img src="<?= SITE_URL . '/' . PROFILE_IMAGES_PATH . $user_data['profile_image'] ?>" 
                                                         class="rounded-circle" width="150" height="150" 
                                                         style="object-fit: cover;" alt="Profile" id="profileImagePreview">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" 
                                                         style="width: 150px; height: 150px;" id="profileImagePreview">
                                                        <i class="bi bi-person display-4 text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <label for="profile_image" class="position-absolute bottom-0 end-0 btn btn-sm btn-primary rounded-circle" 
                                                       style="width: 40px; height: 40px;" title="Şəkil dəyişdir">
                                                    <i class="bi bi-camera"></i>
                                                </label>
                                                <input type="file" class="d-none" id="profile_image" name="profile_image" 
                                                       accept="image/*" onchange="previewImage(this)">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-8">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="full_name" class="form-label">Ad Soyad *</label>
                                                    <input type="text" class="form-control" name="full_name" 
                                                           value="<?= htmlspecialchars($user_data['full_name']) ?>" required>
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label for="phone" class="form-label">Telefon</label>
                                                    <input type="text" class="form-control" 
                                                           value="<?= htmlspecialchars($user_data['phone']) ?>" readonly>
                                                    <small class="form-text text-muted">Telefon nömrəsi dəyişdirilə bilməz</small>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="email" class="form-label">Email</label>
                                                    <input type="email" class="form-control" name="email" 
                                                           value="<?= htmlspecialchars($user_data['email']) ?>">
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label for="company_name" class="form-label">Şirkət Adı</label>
                                                    <input type="text" class="form-control" name="company_name" 
                                                           value="<?= htmlspecialchars($user_data['company_name']) ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Ünvan</label>
                                        <textarea class="form-control" name="address" rows="3"><?= htmlspecialchars($user_data['address']) ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Əlavə Qeydlər</label>
                                        <textarea class="form-control" name="notes" rows="3"><?= htmlspecialchars($user_data['notes']) ?></textarea>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check"></i> Saxla
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Password Change -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-lock"></i> Şifrə Dəyişikliyi
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="change_password" value="1">
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="current_password" class="form-label">Hazırki Şifrə *</label>
                                            <input type="password" class="form-control" name="current_password" required>
                                        </div>
                                        
                                        <div class="col-md-4 mb-3">
                                            <label for="new_password" class="form-label">Yeni Şifrə *</label>
                                            <input type="password" class="form-control" name="new_password" 
                                                   minlength="<?= PASSWORD_MIN_LENGTH ?>" required>
                                        </div>
                                        
                                        <div class="col-md-4 mb-3">
                                            <label for="confirm_password" class="form-label">Şifrə Təkrarı *</label>
                                            <input type="password" class="form-control" name="confirm_password" required>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" class="btn btn-warning">
                                            <i class="bi bi-key"></i> Şifrəni Dəyişdir
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Sidebar -->
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-graph-up"></i> Sifariş Statistikası
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6 mb-3">
                                        <div class="border-end">
                                            <h4 class="text-primary"><?= $order_stats['total_orders'] ?></h4>
                                            <small class="text-muted">Ümumi Sifariş</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <h4 class="text-success"><?= formatCurrency($order_stats['total_spent']) ?></h4>
                                        <small class="text-muted">Ümumi Xərc</small>
                                    </div>
                                </div>
                                
                                <div class="row text-center">
                                    <div class="col-12 mb-2">
                                        <h5 class="text-info"><?= formatCurrency($order_stats['average_order']) ?></h5>
                                        <small class="text-muted">Orta Sifariş Dəyəri</small>
                                    </div>
                                </div>
                                
                                <?php if ($order_stats['last_order_date']): ?>
                                    <div class="text-center mt-3">
                                        <small class="text-muted">
                                            Son sifariş: <?= date('d.m.Y', strtotime($order_stats['last_order_date'])) ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-info-circle"></i> Hesab Məlumatları
                                </h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless table-sm">
                                    <tr>
                                        <td><strong>İstifadəçi adı:</strong></td>
                                        <td><?= htmlspecialchars($user_data['username']) ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Qeydiyyat tarixi:</strong></td>
                                        <td><?= date('d.m.Y', strtotime($user_data['created_at'])) ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Son giriş:</strong></td>
                                        <td>
                                            <?php if ($user_data['last_login']): ?>
                                                <?= date('d.m.Y H:i', strtotime($user_data['last_login'])) ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td>
                                            <span class="badge bg-<?= $user_data['status'] === 'active' ? 'success' : 'warning' ?>">
                                                <?= $user_data['status'] === 'active' ? 'Aktiv' : 'Gözləyir' ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('profileImagePreview');
                    if (preview.tagName === 'IMG') {
                        preview.src = e.target.result;
                    } else {
                        preview.innerHTML = `<img src="${e.target.result}" class="rounded-circle" width="150" height="150" style="object-fit: cover;" alt="Profile">`;
                    }
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Password confirmation validation
        document.querySelector('input[name="confirm_password"]').addEventListener('input', function(e) {
            const newPassword = document.querySelector('input[name="new_password"]').value;
            const confirmPassword = e.target.value;
            
            if (newPassword !== confirmPassword) {
                e.target.setCustomValidity('Şifrələr uyğun gəlmir');
            } else {
                e.target.setCustomValidity('');
            }
        });
    </script>
</body>
</html>