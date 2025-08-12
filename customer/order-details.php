<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireRole('customer');

$user_id = SessionManager::getUserId();
$order_id = $_GET['id'] ?? 0;

$db = new Database();

// Get customer info
$stmt = $db->query("SELECT * FROM customers WHERE user_id = ?", [$user_id]);
$customer = $stmt->fetch();

// Get order details
$stmt = $db->query("SELECT o.*, s.name as store_name, s.phone as store_phone, 
                           u.full_name as sales_person, u.phone as sales_phone
                    FROM orders o 
                    LEFT JOIN stores s ON o.store_id = s.id
                    LEFT JOIN users u ON o.sales_person_id = u.id
                    WHERE o.id = ? AND o.customer_id = ?", [$order_id, $customer['id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

// Get order items
$stmt = $db->query("SELECT oi.*, a.name as accessory_name
                    FROM order_items oi
                    LEFT JOIN accessories a ON oi.accessory_id = a.id
                    WHERE oi.order_id = ?
                    ORDER BY oi.id", [$order_id]);
$order_items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sifariş Təfərrüatı - Alumpro.Az</title>
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
                        <i class="bi bi-receipt text-primary"></i> 
                        Sifariş Təfərrüatı: <?= htmlspecialchars($order['order_number']) ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="orders.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Geri
                            </a>
                            <?php if ($order['status'] === 'completed'): ?>
                                <a href="../pdf/order-receipt.php?id=<?= $order['id'] ?>" 
                                   class="btn btn-outline-primary" target="_blank">
                                    <i class="bi bi-file-pdf"></i> PDF
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Order Info -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-info-circle"></i> Sifariş Məlumatları
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Sifariş №:</strong></td>
                                                <td><?= htmlspecialchars($order['order_number']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Tarix:</strong></td>
                                                <td><?= date('d.m.Y', strtotime($order['order_date'])) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Mağaza:</strong></td>
                                                <td><?= htmlspecialchars($order['store_name']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Satış Meneceri:</strong></td>
                                                <td>
                                                    <?= htmlspecialchars($order['sales_person']) ?>
                                                    <?php if ($order['sales_phone']): ?>
                                                        <br>
                                                        <a href="tel:<?= $order['sales_phone'] ?>" class="text-success">
                                                            <i class="bi bi-telephone"></i> <?= $order['sales_phone'] ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Status:</strong></td>
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
                                                    <span class="badge bg-<?= $status_colors[$order['status']] ?> fs-6">
                                                        <?= $status_texts[$order['status']] ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php if ($order['delivery_date']): ?>
                                                <tr>
                                                    <td><strong>Təhvil Tarixi:</strong></td>
                                                    <td><?= date('d.m.Y', strtotime($order['delivery_date'])) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if ($order['delivered_by']): ?>
                                                <tr>
                                                    <td><strong>Təhvil Verən:</strong></td>
                                                    <td><?= htmlspecialchars($order['delivered_by']) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if ($order['received_by']): ?>
                                                <tr>
                                                    <td><strong>Təhvil Alan:</strong></td>
                                                    <td><?= htmlspecialchars($order['received_by']) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                </div>
                                
                                <?php if ($order['notes']): ?>
                                    <hr>
                                    <div>
                                        <strong>Qeydlər:</strong>
                                        <p class="mt-2 mb-0"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-calculator"></i> Qiymət Hesabı
                                </h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <td>Ara məbləğ:</td>
                                        <td class="text-end"><?= formatCurrency($order['subtotal']) ?></td>
                                    </tr>
                                    <?php if ($order['discount'] > 0): ?>
                                        <tr>
                                            <td>Endirim:</td>
                                            <td class="text-end text-success">-<?= formatCurrency($order['discount']) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($order['transport_cost'] > 0): ?>
                                        <tr>
                                            <td>Nəqliyyat:</td>
                                            <td class="text-end"><?= formatCurrency($order['transport_cost']) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($order['assembly_cost'] > 0): ?>
                                        <tr>
                                            <td>Yığma xidməti:</td>
                                            <td class="text-end"><?= formatCurrency($order['assembly_cost']) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($order['accessories_cost'] > 0): ?>
                                        <tr>
                                            <td>Əlavə aksesuarlar:</td>
                                            <td class="text-end"><?= formatCurrency($order['accessories_cost']) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr class="border-top">
                                        <td><strong>Ümumi məbləğ:</strong></td>
                                        <td class="text-end"><strong><?= formatCurrency($order['total_amount']) ?></strong></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Contact -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-telephone"></i> Əlaqə
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <?php if ($order['store_phone']): ?>
                                        <a href="tel:<?= $order['store_phone'] ?>" class="btn btn-success">
                                            <i class="bi bi-telephone-fill"></i> Mağaza
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($order['sales_phone']): ?>
                                        <a href="tel:<?= $order['sales_phone'] ?>" class="btn btn-outline-success">
                                            <i class="bi bi-person"></i> Satış Meneceri
                                        </a>
                                        <a href="https://wa.me/<?= str_replace(['+', ' '], '', $order['sales_phone']) ?>" 
                                           target="_blank" class="btn btn-outline-success">
                                            <i class="bi bi-whatsapp"></i> WhatsApp
                                        </a>
                                    <?php endif; ?>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#supportModal">
                                        <i class="bi bi-chat-dots"></i> Dəstək
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-list-ul"></i> Sifariş Tərkibi
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Növ</th>
                                        <th>Təfərrüat</th>
                                        <th>Ölçülər</th>
                                        <th>Say</th>
                                        <th>Vahid Qiyməti</th>
                                        <th>Məbləğ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                $type_icons = [
                                                    'door' => 'bi-door-open',
                                                    'glass' => 'bi-window',
                                                    'accessory' => 'bi-tools'
                                                ];
                                                $type_texts = [
                                                    'door' => 'Qapaq',
                                                    'glass' => 'Şüşə',
                                                    'accessory' => 'Aksesuar'
                                                ];
                                                ?>
                                                <i class="bi <?= $type_icons[$item['item_type']] ?? 'bi-box' ?> text-primary"></i>
                                                <?= $type_texts[$item['item_type']] ?? 'Məhsul' ?>
                                            </td>
                                            <td>
                                                <?php if ($item['item_type'] === 'door'): ?>
                                                    <strong>Profil:</strong> <?= htmlspecialchars($item['profile_type']) ?><br>
                                                    <strong>Şüşə:</strong> <?= htmlspecialchars($item['glass_type']) ?>
                                                <?php elseif ($item['item_type'] === 'glass'): ?>
                                                    <strong>Növ:</strong> <?= htmlspecialchars($item['glass_type']) ?>
                                                <?php elseif ($item['item_type'] === 'accessory'): ?>
                                                    <strong>Aksesuar:</strong> <?= htmlspecialchars($item['accessory_name']) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($item['height'] && $item['width']): ?>
                                                    <?= $item['height'] ?> x <?= $item['width'] ?> sm
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?= $item['quantity'] ?></span>
                                            </td>
                                            <td><?= formatCurrency($item['unit_price']) ?></td>
                                            <td><strong><?= formatCurrency($item['total_price']) ?></strong></td>
                                        </tr>
                                        <?php if ($item['notes']): ?>
                                            <tr>
                                                <td colspan="6" class="border-0 pt-0">
                                                    <small class="text-muted">
                                                        <i class="bi bi-chat-text"></i> 
                                                        <?= nl2br(htmlspecialchars($item['notes'])) ?>
                                                    </small>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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