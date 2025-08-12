<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireRole('customer');

$user_id = SessionManager::getUserId();
$db = new Database();

// Get customer info
$stmt = $db->query("SELECT c.*, u.full_name, u.email, u.phone, u.profile_image 
                    FROM customers c 
                    JOIN users u ON c.user_id = u.id 
                    WHERE c.user_id = ?", [$user_id]);
$customer = $stmt->fetch();

// Get recent orders
$stmt = $db->query("SELECT o.*, s.name as store_name, COUNT(oi.id) as item_count
                    FROM orders o 
                    LEFT JOIN stores s ON o.store_id = s.id
                    LEFT JOIN order_items oi ON o.id = oi.order_id
                    WHERE o.customer_id = ? 
                    GROUP BY o.id
                    ORDER BY o.created_at DESC 
                    LIMIT 5", [$customer['id']]);
$recent_orders = $stmt->fetchAll();

// Get statistics
$stmt = $db->query("SELECT 
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_spent,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders
                    FROM orders WHERE customer_id = ?", [$customer['id']]);
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Müştəri Paneli - Alumpro.Az</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/customer-sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-house-door text-primary"></i> 
                        Xoş gəlmisiniz, <?= htmlspecialchars($customer['contact_person']) ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#supportModal">
                                <i class="bi bi-headset"></i> Dəstək
                            </button>
                            <a href="profile.php" class="btn btn-outline-secondary">
                                <i class="bi bi-person-gear"></i> Profil
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="dashboard-card">
                            <div class="d-flex align-items-center">
                                <div class="card-icon bg-gradient-primary">
                                    <i class="bi bi-box"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="text-muted small">Ümumi Sifarişlər</div>
                                    <div class="h4 mb-0"><?= $stats['total_orders'] ?: 0 ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="dashboard-card">
                            <div class="d-flex align-items-center">
                                <div class="card-icon bg-gradient-secondary">
                                    <i class="bi bi-currency-dollar"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="text-muted small">Ümumi Xərc</div>
                                    <div class="h4 mb-0"><?= formatCurrency($stats['total_spent'] ?: 0) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="dashboard-card">
                            <div class="d-flex align-items-center">
                                <div class="card-icon" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="text-muted small">Gözləyən Sifarişlər</div>
                                    <div class="h4 mb-0"><?= $stats['pending_orders'] ?: 0 ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="dashboard-card">
                            <div class="d-flex align-items-center">
                                <div class="card-icon" style="background: linear-gradient(135deg, #28a745, #20c997);">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="text-muted small">Tamamlanmış</div>
                                    <div class="h4 mb-0"><?= $stats['completed_orders'] ?: 0 ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history"></i> Son Sifarişlər
                        </h5>
                        <a href="orders.php" class="btn btn-sm btn-outline-primary">
                            Hamısını gör <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Sifariş №</th>
                                        <th>Tarix</th>
                                        <th>Mağaza</th>
                                        <th>Məhsul sayı</th>
                                        <th>Məbləğ</th>
                                        <th>Status</th>
                                        <th>Əməliyyat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_orders)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <i class="bi bi-inbox display-4 text-muted"></i>
                                                <p class="text-muted mt-2">Hələ sifarişiniz yoxdur</p>
                                                <a href="tel:+994501234567" class="btn btn-primary">
                                                    <i class="bi bi-telephone"></i> Sifariş vermək üçün zəng edin
                                                </a>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($order['order_number']) ?></strong>
                                                </td>
                                                <td><?= date('d.m.Y', strtotime($order['order_date'])) ?></td>
                                                <td><?= htmlspecialchars($order['store_name']) ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?= $order['item_count'] ?> məhsul</span>
                                                </td>
                                                <td>
                                                    <strong><?= formatCurrency($order['total_amount']) ?></strong>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_colors = [
                                                        'pending' => 'warning',
                                                        'in_production' => 'info', 
                                                        'completed' => 'success',
                                                        'cancelled' => 'danger'
                                                    ];
                                                    $status_texts = [
                                                        'pending' => 'Gözləyir',
                                                        'in_production' => 'İstehsalda',
                                                        'completed' => 'Tamamlandı',
                                                        'cancelled' => 'Ləğv edildi'
                                                    ];
                                                    ?>
                                                    <span class="badge bg-<?= $status_colors[$order['status']] ?>">
                                                        <?= $status_texts[$order['status']] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="order-details.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-telephone"></i> Əlaqə
                                </h5>
                            </div>
                            <div class="card-body text-center">
                                <p class="text-muted">Sifariş vermək və ya məlumat almaq üçün</p>
                                <div class="d-grid gap-2">
                                    <a href="tel:+994501234567" class="btn btn-success">
                                        <i class="bi bi-telephone-fill"></i> Mağaza 1
                                    </a>
                                    <a href="tel:+994501234568" class="btn btn-success">
                                        <i class="bi bi-telephone-fill"></i> Mağaza 2
                                    </a>
                                    <a href="https://wa.me/994501234567" target="_blank" class="btn btn-outline-success">
                                        <i class="bi bi-whatsapp"></i> WhatsApp
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-question-circle"></i> Yardım
                                </h5>
                            </div>
                            <div class="card-body text-center">
                                <p class="text-muted">Suallarınız var? Bizə müraciət edin</p>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#supportModal">
                                        <i class="bi bi-chat-dots"></i> Dəstək Çatı
                                    </button>
                                    <a href="mailto:info@alumpro.az" class="btn btn-outline-primary">
                                        <i class="bi bi-envelope"></i> Email Göndər
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Support Modal -->
    <?php include '../includes/support-modal.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/support.js"></script>
</body>
</html>