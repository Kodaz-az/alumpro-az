<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireLogin();

$order_id = $_GET['id'] ?? 0;
$user_role = SessionManager::getUserRole();
$user_id = SessionManager::getUserId();

$db = new Database();

// Get order details
$stmt = $db->query("SELECT o.*, c.contact_person, c.phone as customer_phone, c.company_name, c.address,
                           u.full_name as sales_person, s.name as store_name, s.phone as store_phone, s.address as store_address
                    FROM orders o 
                    LEFT JOIN customers c ON o.customer_id = c.id
                    LEFT JOIN users u ON o.sales_person_id = u.id
                    LEFT JOIN stores s ON o.store_id = s.id
                    WHERE o.id = ?", [$order_id]);
$order = $stmt->fetch();

if (!$order) {
    die('Sifariş tapılmadı');
}

// Check permissions
if ($user_role === 'customer') {
    // Customer can only view their own orders
    $stmt = $db->query("SELECT user_id FROM customers WHERE id = ?", [$order['customer_id']]);
    $customer = $stmt->fetch();
    if (!$customer || $customer['user_id'] !== $user_id) {
        die('Bu sifarişi görmək icazəniz yoxdur');
    }
} elseif ($user_role === 'sales') {
    // Sales person can view orders from their store
    $stmt = $db->query("SELECT store_id FROM users WHERE id = ?", [$user_id]);
    $user_data = $stmt->fetch();
    if ($user_data['store_id'] && $order['store_id'] !== $user_data['store_id']) {
        die('Bu sifarişi görmək icazəniz yoxdur');
    }
}

// Get order items
$stmt = $db->query("SELECT * FROM order_items WHERE order_id = ? ORDER BY id", [$order_id]);
$order_items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sifariş Qaiməsi - <?= htmlspecialchars($order['order_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/print.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .header-logo { height: 60px; }
        .company-info { background: linear-gradient(135deg, #20B2AA, #4682B4); color: white; }
        .order-details { background: #f8f9fa; }
        .items-table th { background: #e9ecef; }
        .total-section { background: #e3f2fd; border: 2px solid #2196f3; }
        @media print {
            .no-print { display: none !important; }
            body { print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-6">
                <div class="company-info p-3 rounded">
                    <h2 class="fw-bold mb-1">
                        <i class="bi bi-house-door"></i> Alumpro.Az
                    </h2>
                    <p class="mb-1">Aluminum profil və şüşə sistemləri</p>
                    <p class="mb-1"><i class="bi bi-telephone"></i> +994 50 123 45 67</p>
                    <p class="mb-0"><i class="bi bi-envelope"></i> info@alumpro.az</p>
                </div>
            </div>
            <div class="col-6 text-end">
                <h1 class="display-6 fw-bold text-primary">SİFARİŞ QAİMƏSİ</h1>
                <p class="fs-4 fw-bold"><?= htmlspecialchars($order['order_number']) ?></p>
                <p class="text-muted">Tarix: <?= date('d.m.Y', strtotime($order['order_date'])) ?></p>
                <?php if ($order['barcode']): ?>
                    <p class="small font-monospace">Barkod: <?= htmlspecialchars($order['barcode']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Customer and Store Info -->
        <div class="row mb-4">
            <div class="col-6">
                <div class="order-details p-3 rounded">
                    <h5 class="fw-bold mb-3"><i class="bi bi-person"></i> Müştəri Məlumatları</h5>
                    <p class="mb-1"><strong>Ad:</strong> <?= htmlspecialchars($order['contact_person']) ?></p>
                    <p class="mb-1"><strong>Telefon:</strong> <?= htmlspecialchars($order['customer_phone']) ?></p>
                    <?php if ($order['company_name']): ?>
                        <p class="mb-1"><strong>Şirkət:</strong> <?= htmlspecialchars($order['company_name']) ?></p>
                    <?php endif; ?>
                    <?php if ($order['address']): ?>
                        <p class="mb-0"><strong>Ünvan:</strong> <?= htmlspecialchars($order['address']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-6">
                <div class="order-details p-3 rounded">
                    <h5 class="fw-bold mb-3"><i class="bi bi-shop"></i> Mağaza Məlumatları</h5>
                    <p class="mb-1"><strong>Mağaza:</strong> <?= htmlspecialchars($order['store_name']) ?></p>
                    <p class="mb-1"><strong>Satış Meneceri:</strong> <?= htmlspecialchars($order['sales_person']) ?></p>
                    <?php if ($order['store_phone']): ?>
                        <p class="mb-1"><strong>Telefon:</strong> <?= htmlspecialchars($order['store_phone']) ?></p>
                    <?php endif; ?>
                    <?php if ($order['store_address']): ?>
                        <p class="mb-0"><strong>Ünvan:</strong> <?= htmlspecialchars($order['store_address']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="mb-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-list-ul"></i> Sifariş Tərkibi</h5>
            <div class="table-responsive">
                <table class="table table-bordered items-table">
                    <thead>
                        <tr>
                            <th width="5%">№</th>
                            <th width="15%">Tip</th>
                            <th width="20%">Profil Tipi</th>
                            <th width="20%">Şüşə Növü</th>
                            <th width="10%">Hündürlük</th>
                            <th width="10%">En</th>
                            <th width="8%">Say</th>
                            <th width="12%">Məbləğ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $index => $item): ?>
                            <tr>
                                <td class="text-center"><?= $index + 1 ?></td>
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
                                <td class="text-center"><?= $item['height'] ? $item['height'] . ' sm' : '-' ?></td>
                                <td class="text-center"><?= $item['width'] ? $item['width'] . ' sm' : '-' ?></td>
                                <td class="text-center"><?= $item['quantity'] ?></td>
                                <td class="text-end fw-bold"><?= formatCurrency($item['total_price']) ?></td>
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

        <!-- Total Section -->
        <div class="row">
            <div class="col-8">
                <?php if ($order['notes']): ?>
                    <div class="mb-3">
                        <h6 class="fw-bold">Qeydlər:</h6>
                        <p><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="mt-4">
                    <h6 class="fw-bold">Status Məlumatları:</h6>
                    <p class="mb-1">
                        <strong>Status:</strong> 
                        <?php
                        $status_texts = [
                            'pending' => 'Gözləyir',
                            'in_production' => 'İstehsalda',
                            'completed' => 'Tamamlandı',
                            'cancelled' => 'Ləğv edildi'
                        ];
                        echo $status_texts[$order['status']];
                        ?>
                    </p>
                    <?php if ($order['delivery_date']): ?>
                        <p class="mb-1"><strong>Təhvil Tarixi:</strong> <?= date('d.m.Y', strtotime($order['delivery_date'])) ?></p>
                    <?php endif; ?>
                    <?php if ($order['delivered_by']): ?>
                        <p class="mb-1"><strong>Təhvil Verən:</strong> <?= htmlspecialchars($order['delivered_by']) ?></p>
                    <?php endif; ?>
                    <?php if ($order['received_by']): ?>
                        <p class="mb-1"><strong>Təhvil Alan:</strong> <?= htmlspecialchars($order['received_by']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-4">
                <div class="total-section p-4 rounded">
                    <h5 class="fw-bold mb-3 text-center">Qiymət Hesabı</h5>
                    <table class="table table-borderless mb-0">
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
                                <td>Yığma xidməti:</td>
                                <td class="text-end">+<?= formatCurrency($order['assembly_cost']) ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($order['accessories_cost'] > 0): ?>
                            <tr>
                                <td>Əlavə aksesuarlar:</td>
                                <td class="text-end">+<?= formatCurrency($order['accessories_cost']) ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="border-top border-2">
                            <td class="fw-bold fs-5">ÜMUMI MƏBLƏĞ:</td>
                            <td class="text-end fw-bold fs-5"><?= formatCurrency($order['total_amount']) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="row mt-5">
            <div class="col-12 text-center">
                <hr>
                <p class="text-muted small">
                    Bu sənəd sistem tərəfindən avtomatik olaraq yaradılmışdır.<br>
                    <?= date('d.m.Y H:i') ?> tarixində çap edilmişdir.
                </p>
            </div>
        </div>

        <!-- Print Button -->
        <div class="text-center mt-4 no-print">
            <button onclick="window.print()" class="btn btn-primary me-2">
                <i class="bi bi-printer"></i> Çap et
            </button>
            <button onclick="window.close()" class="btn btn-secondary">
                <i class="bi bi-x"></i> Bağla
            </button>
        </div>
    </div>

    <script>
        // Auto print when page loads (optional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>