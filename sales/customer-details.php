<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireSales();

$customer_id = $_GET['id'] ?? 0;
$user_id = SessionManager::getUserId();
$user_role = SessionManager::getUserRole();
$db = new Database();

// Get customer details
$stmt = $db->query("SELECT c.*, u.full_name, u.email, u.phone, u.profile_image, u.created_at as registration_date
                    FROM customers c 
                    LEFT JOIN users u ON c.user_id = u.id 
                    WHERE c.id = ?", [$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    header('Location: customers.php');
    exit;
}

// Get customer orders
$stmt = $db->query("SELECT o.*, s.name as store_name, COUNT(oi.id) as item_count
                    FROM orders o 
                    LEFT JOIN stores s ON o.store_id = s.id
                    LEFT JOIN order_items oi ON o.id = oi.order_id
                    WHERE o.customer_id = ? 
                    GROUP BY o.id
                    ORDER BY o.created_at DESC", [$customer_id]);
$orders = $stmt->fetchAll();

// Get customer statistics
$stmt = $db->query("SELECT 
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_spent,
                    AVG(total_amount) as average_order,
                    MAX(order_date) as last_order_date,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders
                    FROM orders WHERE customer_id = ?", [$customer_id]);
$stats = $stmt->fetch();

// Get most ordered products
$stmt = $db->query("SELECT oi.profile_type, COUNT(*) as order_count, SUM(oi.total_price) as total_sales
                    FROM order_items oi
                    JOIN orders o ON oi.order_id = o.id
                    WHERE o.customer_id = ? AND oi.profile_type IS NOT NULL
                    GROUP BY oi.profile_type
                    ORDER BY order_count DESC
                    LIMIT 5", [$customer_id]);
$favorite_products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Müştəri Təfərrüatı - <?= htmlspecialchars($customer['contact_person']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php if ($user_role === 'admin'): ?>
                <?php include '../includes/admin-sidebar.php'; ?>
            <?php else: ?>
                <?php include '../includes/sales-sidebar.php'; ?>
            <?php endif; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-person text-primary"></i> 
                        <?= htmlspecialchars($customer['contact_person']) ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="customers.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Geri
                            </a>
                            <a href="new-order.php?customer=<?= $customer['id'] ?>" class="btn btn-success">
                                <i class="bi bi-plus-circle"></i> Yeni Sifariş
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Customer Info -->
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <?php if ($customer['profile_image']): ?>
                                    <img src="<?= SITE_URL . '/' . PROFILE_IMAGES_PATH . $customer['profile_image'] ?>" 
                                         class="rounded-circle mb-3" width="120" height="120" 
                                         style="object-fit: cover;" alt="Profile">
                                <?php else: ?>
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto mb-3" 
                                         style="width: 120px; height: 120px;">
                                        <i class="bi bi-person display-4 text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <h4><?= htmlspecialchars($customer['contact_person']) ?></h4>
                                <?php if ($customer['company_name']): ?>
                                    <p class="text-muted"><?= htmlspecialchars($customer['company_name']) ?></p>
                                <?php endif; ?>
                                
                                <div class="d-grid gap-2">
                                    <a href="tel:<?= $customer['phone'] ?>" class="btn btn-success">
                                        <i class="bi bi-telephone"></i> Zəng Et
                                    </a>
                                    <a href="https://wa.me/<?= str_replace(['+', ' '], '', $customer['phone']) ?>" 
                                       target="_blank" class="btn btn-outline-success">
                                        <i class="bi bi-whatsapp"></i> WhatsApp
                                    </a>
                                    <?php if ($customer['email']): ?>
                                        <a href="mailto:<?= $customer['email'] ?>" class="btn btn-outline-primary">
                                            <i class="bi bi-envelope"></i> Email
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Customer Details -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-info-circle"></i> Əlaqə Məlumatları
                                </h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless table-sm">
                                    <tr>
                                        <td><strong>Telefon:</strong></td>
                                        <td><?= htmlspecialchars($customer['phone']) ?></td>
                                    </tr>
                                    <?php if ($customer['email']): ?>
                                        <tr>
                                            <td><strong>Email:</strong></td>
                                            <td><?= htmlspecialchars($customer['email']) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($customer['address']): ?>
                                        <tr>
                                            <td><strong>Ünvan:</strong></td>
                                            <td><?= nl2br(htmlspecialchars($customer['address'])) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td><strong>Qeydiyyat:</strong></td>
                                        <td><?= date('d.m.Y', strtotime($customer['registration_date'])) ?></td>
                                    </tr>
                                </table>
                                
                                <?php if ($customer['notes']): ?>
                                    <hr>
                                    <div>
                                        <strong>Qeydlər:</strong>
                                        <p class="mt-2 mb-0"><?= nl2br(htmlspecialchars($customer['notes'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistics and Orders -->
                    <div class="col-md-8">
                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3 mb-3">
                                <div class="card border-primary">
                                    <div class="card-body text-center">
                                        <i class="bi bi-box text-primary display-6"></i>
                                        <h4 class="mt-2"><?= $stats['total_orders'] ?: 0 ?></h4>
                                        <p class="text-muted mb-0">Ümumi Sifariş</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card border-success">
                                    <div class="card-body text-center">
                                        <i class="bi bi-currency-dollar text-success display-6"></i>
                                        <h4 class="mt-2"><?= formatCurrency($stats['total_spent'] ?: 0) ?></h4>
                                        <p class="text-muted mb-0">Ümumi Xərc</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card border-info">
                                    <div class="card-body text-center">
                                        <i class="bi bi-graph-up text-info display-6"></i>
                                        <h4 class="mt-2"><?= formatCurrency($stats['average_order'] ?: 0) ?></h4>
                                        <p class="text-muted mb-0">Orta Sifariş</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card border-warning">
                                    <div class="card-body text-center">
                                        <i class="bi bi-clock text-warning display-6"></i>
                                        <h4 class="mt-2">
                                            <?php if ($stats['last_order_date']): ?>
                                                <?= date('d.m.Y', strtotime($stats['last_order_date'])) ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </h4>
                                        <p class="text-muted mb-0">Son Sifariş</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Favorite Products -->
                        <?php if (!empty($favorite_products)): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="bi bi-heart"></i> Ən Çox Sifariş Etdiyi Profillər
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php foreach ($favorite_products as $product): ?>
                                            <div class="col-md-6 mb-2">
                                                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                                                    <div>
                                                        <strong><?= htmlspecialchars($product['profile_type']) ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?= $product['order_count'] ?> dəfə</small>
                                                    </div>
                                                    <div class="text-success">
                                                        <strong><?= formatCurrency($product['total_sales']) ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Orders Table -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-list-ul"></i> Sifariş Tarixçəsi
                                </h5>
                                <a href="new-order.php?customer=<?= $customer['id'] ?>" class="btn btn-sm btn-success">
                                    <i class="bi bi-plus"></i> Yeni Sifariş
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
                                                <th>Məhsul Sayı</th>
                                                <th>Məbləğ</th>
                                                <th>Status</th>
                                                <th>Əməliyyat</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($orders)): ?>
                                                <tr>
                                                    <td colspan="7" class="text-center py-4">
                                                        <i class="bi bi-inbox display-4 text-muted"></i>
                                                        <p class="text-muted mt-2">Hələ sifariş yoxdur</p>
                                                        <a href="new-order.php?customer=<?= $customer['id'] ?>" class="btn btn-success">
                                                            <i class="bi bi-plus-circle"></i> İlk Sifarişi Yarat
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($orders as $order): ?>
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
                                                            <div class="btn-group" role="group">
                                                                <a href="order-details.php?id=<?= $order['id'] ?>" 
                                                                   class="btn btn-sm btn-outline-primary" title="Təfərrüat">
                                                                    <i class="bi bi-eye"></i>
                                                                </a>
                                                                <?php if ($order['status'] === 'completed'): ?>
                                                                    <a href="../pdf/order-receipt.php?id=<?= $order['id'] ?>" 
                                                                       class="btn btn-sm btn-outline-secondary" title="PDF" target="_blank">
                                                                        <i class="bi bi-file-pdf"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>