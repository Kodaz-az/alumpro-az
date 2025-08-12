<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireSales();

$order_id = $_GET['id'] ?? 0;
$user_id = SessionManager::getUserId();
$user_role = SessionManager::getUserRole();
$store_id = SessionManager::getStoreId();
$db = new Database();

$message = '';
$error = '';

// Handle order status updates
if ($_POST && $_POST['action'] === 'update_status') {
    try {
        $new_status = $_POST['status'];
        $notes = trim($_POST['notes'] ?? '');
        
        $stmt = $db->query("UPDATE orders SET status = ?, notes = CONCAT(COALESCE(notes, ''), '\n', '[', NOW(), '] Status dəyişdirildi: ', ?, COALESCE(CONCAT(' - ', ?), '')) WHERE id = ?", 
                           [$new_status, $new_status, $notes, $order_id]);
        
        Utils::logActivity($user_id, 'order_status_updated', "Sifariş #$order_id statusu dəyişdirildi: $new_status");
        
        // Send WhatsApp notification
        require_once '../includes/whatsapp_helper.php';
        WhatsAppHelper::notifyOrderStatusChanged($order_id, $new_status);
        
        $message = 'Sifariş statusu yeniləndi və müştəriyə bildirildi';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get order details
$stmt = $db->query("SELECT o.*, c.contact_person, c.phone as customer_phone, c.company_name, c.address, c.email,
                           u.full_name as sales_person, s.name as store_name, s.phone as store_phone, s.address as store_address
                    FROM orders o 
                    LEFT JOIN customers c ON o.customer_id = c.id
                    LEFT JOIN users u ON o.sales_person_id = u.id
                    LEFT JOIN stores s ON o.store_id = s.id
                    WHERE o.id = ?", [$order_id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

// Check permissions (sales can only see orders from their store)
if ($user_role === 'sales' && $store_id && $order['store_id'] !== $store_id) {
    header('Location: orders.php?error=access_denied');
    exit;
}

// Get order items
$stmt = $db->query("SELECT * FROM order_items WHERE order_id = ? ORDER BY id", [$order_id]);
$order_items = $stmt->fetchAll();

// Calculate glass sizes
$glass_reduction = 4; // Default value
$stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'glass_size_reduction'");
$setting = $stmt->fetch();
if ($setting) {
    $glass_reduction = floatval($setting['setting_value']);
}
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sifariş Təfərrüatı - <?= htmlspecialchars($order['order_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .order-header {
            background: linear-gradient(135deg, #20B2AA, #4682B4);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        .status-timeline {
            position: relative;
            padding-left: 2rem;
        }
        .status-timeline::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 1rem;
        }
        .timeline-dot {
            position: absolute;
            left: -2rem;
            top: 0.3rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            border: 2px solid #dee2e6;
            background: white;
        }
        .timeline-dot.active {
            background: #20B2AA;
            border-color: #20B2AA;
        }
        .glass-calculation {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 6px;
            padding: 0.75rem;
            margin-top: 0.5rem;
        }
    </style>
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
                        <i class="bi bi-receipt text-primary"></i> 
                        Sifariş Təfərrüatı
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="orders.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Geri
                            </a>
                            <a href="../pdf/order-receipt.php?id=<?= $order['id'] ?>" 
                               class="btn btn-outline-primary" target="_blank">
                                <i class="bi bi-file-pdf"></i> PDF
                            </a>
                            <a href="../pdf/production-order.php?id=<?= $order['id'] ?>" 
                               class="btn btn-outline-info" target="_blank">
                                <i class="bi bi-gear"></i> İstehsalat
                            </a>
                        </div>
                    </div>
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

                <!-- Order Header -->
                <div class="order-header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h2 class="mb-0"><?= htmlspecialchars($order['order_number']) ?></h2>
                            <p class="mb-0 opacity-75">Sifariş tarixi: <?= date('d.m.Y', strtotime($order['order_date'])) ?></p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <h3 class="mb-0"><?= formatCurrency($order['total_amount']) ?></h3>
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
                            <span class="badge fs-6 bg-<?= $status_colors[$order['status']] ?>">
                                <?= $status_texts[$order['status']] ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Order Details -->
                    <div class="col-lg-8">
                        <!-- Customer and Order Info -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="bi bi-person"></i> Müştəri Məlumatları
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-borderless table-sm">
                                            <tr>
                                                <td><strong>Ad:</strong></td>
                                                <td><?= htmlspecialchars($order['contact_person']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Telefon:</strong></td>
                                                <td>
                                                    <a href="tel:<?= $order['customer_phone'] ?>" class="text-decoration-none">
                                                        <?= $order['customer_phone'] ?>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php if ($order['email']): ?>
                                                <tr>
                                                    <td><strong>Email:</strong></td>
                                                    <td><?= htmlspecialchars($order['email']) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if ($order['company_name']): ?>
                                                <tr>
                                                    <td><strong>Şirkət:</strong></td>
                                                    <td><?= htmlspecialchars($order['company_name']) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if ($order['address']): ?>
                                                <tr>
                                                    <td><strong>Ünvan:</strong></td>
                                                    <td><?= nl2br(htmlspecialchars($order['address'])) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        </table>
                                        
                                        <div class="d-grid gap-2 d-md-block">
                                            <a href="tel:<?= $order['customer_phone'] ?>" class="btn btn-success btn-sm">
                                                <i class="bi bi-telephone"></i> Zəng
                                            </a>
                                            <a href="https://wa.me/<?= str_replace(['+', ' '], '', $order['customer_phone']) ?>" 
                                               target="_blank" class="btn btn-outline-success btn-sm">
                                                <i class="bi bi-whatsapp"></i> WhatsApp
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="bi bi-info-circle"></i> Sifariş Məlumatları
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-borderless table-sm">
                                            <tr>
                                                <td><strong>Nömrə:</strong></td>
                                                <td><?= htmlspecialchars($order['order_number']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Tarix:</strong></td>
                                                <td><?= date('d.m.Y', strtotime($order['order_date'])) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Satış Meneceri:</strong></td>
                                                <td><?= htmlspecialchars($order['sales_person']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Mağaza:</strong></td>
                                                <td><?= htmlspecialchars($order['store_name']) ?></td>
                                            </tr>
                                            <?php if ($order['delivery_date']): ?>
                                                <tr>
                                                    <td><strong>Təhvil Tarixi:</strong></td>
                                                    <td><?= date('d.m.Y', strtotime($order['delivery_date'])) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if ($order['barcode']): ?>
                                                <tr>
                                                    <td><strong>Barkod:</strong></td>
                                                    <td><small class="font-monospace"><?= htmlspecialchars($order['barcode']) ?></small></td>
                                                </tr>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Order Items -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-list-ul"></i> Sifariş Məhsulları
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>№</th>
                                                <th>Tip</th>
                                                <th>Profil</th>
                                                <th>Şüşə</th>
                                                <th>Ölçülər</th>
                                                <th>Say</th>
                                                <th>Qiymət</th>
                                                <th>Məbləğ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($order_items as $index => $item): ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td>
                                                        <?php
                                                        $type_texts = [
                                                            'door' => 'Qapaq',
                                                            'glass' => 'Şüşə',
                                                            'accessory' => 'Aksesuar'
                                                        ];
                                                        echo $type_texts[$item['item_type']] ?? 'Məhsul';
                                                        ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($item['profile_type'] ?: '-') ?></td>
                                                    <td><?= htmlspecialchars($item['glass_type'] ?: '-') ?></td>
                                                    <td>
                                                        <?php if ($item['height'] && $item['width']): ?>
                                                            <?= $item['height'] ?> × <?= $item['width'] ?> sm
                                                            
                                                            <?php if ($item['item_type'] === 'door' && $item['height'] && $item['width']): ?>
                                                                <div class="glass-calculation">
                                                                    <small>
                                                                        <strong>Şüşə:</strong> 
                                                                        <?= max(0, $item['height'] - $glass_reduction) ?> × <?= max(0, $item['width'] - $glass_reduction) ?> sm
                                                                    </small>
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= $item['quantity'] ?></td>
                                                    <td><?= formatCurrency($item['unit_price']) ?></td>
                                                    <td><strong><?= formatCurrency($item['total_price']) ?></strong></td>
                                                </tr>
                                                <?php if ($item['notes']): ?>
                                                    <tr>
                                                        <td></td>
                                                        <td colspan="7" class="small text-muted">
                                                            <i class="bi bi-chat-text"></i> <?= nl2br(htmlspecialchars($item['notes'])) ?>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Order Notes -->
                        <?php if ($order['notes']): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="bi bi-chat-square-text"></i> Qeydlər
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?= nl2br(htmlspecialchars($order['notes'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <!-- Status Management -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-gear"></i> Status İdarəsi
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_status">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Yeni Status</label>
                                        <select class="form-select" name="status" required>
                                            <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Gözləyir</option>
                                            <option value="in_production" <?= $order['status'] === 'in_production' ? 'selected' : '' ?>>İstehsalda</option>
                                            <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Tamamlandı</option>
                                            <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Ləğv edildi</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Əlavə Qeyd</label>
                                        <textarea class="form-control" name="notes" rows="3" 
                                                  placeholder="Status dəyişikliyi haqqında qeyd..."></textarea>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-circle"></i> Status Yenilə
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Order Summary -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-calculator"></i> Məbləğ Hesabı
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
                                            <td class="text-end">+<?= formatCurrency($order['transport_cost']) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($order['assembly_cost'] > 0): ?>
                                        <tr>
                                            <td>Yığma:</td>
                                            <td class="text-end">+<?= formatCurrency($order['assembly_cost']) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($order['accessories_cost'] > 0): ?>
                                        <tr>
                                            <td>Aksesuarlar:</td>
                                            <td class="text-end">+<?= formatCurrency($order['accessories_cost']) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr class="border-top border-2">
                                        <td><strong>ÜMUMI:</strong></td>
                                        <td class="text-end"><strong><?= formatCurrency($order['total_amount']) ?></strong></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Status Timeline -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-clock-history"></i> Status Tarixçəsi
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="status-timeline">
                                    <div class="timeline-item">
                                        <div class="timeline-dot active"></div>
                                        <div class="timeline-content">
                                            <strong>Sifariş Yaradıldı</strong>
                                            <br>
                                            <small class="text-muted"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></small>
                                        </div>
                                    </div>
                                    
                                    <?php if ($order['status'] !== 'pending'): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-dot active"></div>
                                            <div class="timeline-content">
                                                <strong>İstehsalat Başladı</strong>
                                                <br>
                                                <small class="text-muted">Status: İstehsalda</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['status'] === 'completed'): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-dot active"></div>
                                            <div class="timeline-content">
                                                <strong>Sifariş Tamamlandı</strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php if ($order['delivery_date']): ?>
                                                        <?= date('d.m.Y', strtotime($order['delivery_date'])) ?>
                                                    <?php else: ?>
                                                        Təhvil üçün hazır
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['status'] === 'cancelled'): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-dot" style="background: #dc3545; border-color: #dc3545;"></div>
                                            <div class="timeline-content">
                                                <strong>Sifariş Ləğv Edildi</strong>
                                                <br>
                                                <small class="text-muted">Ləğv edildi</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
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