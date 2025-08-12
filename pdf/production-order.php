<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireSales();

$order_id = $_GET['id'] ?? 0;
$user_role = SessionManager::getUserRole();
$user_id = SessionManager::getUserId();

$db = new Database();

// Get order details
$stmt = $db->query("SELECT o.*, c.contact_person, c.phone as customer_phone, c.company_name,
                           u.full_name as sales_person, s.name as store_name, s.phone as store_phone
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
if ($user_role === 'sales') {
    $stmt = $db->query("SELECT store_id FROM users WHERE id = ?", [$user_id]);
    $user_data = $stmt->fetch();
    if ($user_data['store_id'] && $order['store_id'] !== $user_data['store_id']) {
        die('Bu sifarişi görmək icazəniz yoxdur');
    }
}

// Get order items (only doors for production)
$stmt = $db->query("SELECT * FROM order_items WHERE order_id = ? AND item_type = 'door' ORDER BY id", [$order_id]);
$order_items = $stmt->fetchAll();

// Get glass reduction setting
$stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'glass_size_reduction'");
$glass_reduction = $stmt->fetch()['setting_value'] ?? 4;
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İstehsalat Qaiməsi - <?= htmlspecialchars($order['order_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/print.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .production-header { background: #fff3cd; border: 2px solid #ffc107; padding: 15px; text-align: center; font-weight: bold; margin-bottom: 20px; }
        .customer-section { background: #f8f9fa; padding: 15px; margin-bottom: 15px; border: 1px solid #dee2e6; }
        .door-section { border: 2px solid #007bff; margin-bottom: 20px; padding: 15px; }
        .door-header { background: #007bff; color: white; padding: 10px; margin: -15px -15px 15px -15px; font-weight: bold; }
        .glass-section { background: #e7f3ff; border: 1px solid #b3d9ff; padding: 10px; margin: 10px 0; }
        .measurements-table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        .measurements-table td, .measurements-table th { border: 1px solid #000; padding: 8px; text-align: center; }
        .measurements-table th { background: #e9ecef; font-weight: bold; }
        .notes-section { border: 1px dashed #6c757d; padding: 10px; margin: 10px 0; min-height: 60px; }
        .worker-section { border: 2px solid #28a745; padding: 15px; margin-top: 30px; }
        .signature-line { border-bottom: 1px solid #000; width: 200px; margin: 10px 0; display: inline-block; }
        .quality-section { border: 3px solid #dc3545; padding: 15px; margin-top: 20px; text-align: center; font-weight: bold; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
    <div class="container-fluid p-3">
        <!-- Header -->
        <div class="row mb-3">
            <div class="col-3">
                <div style="border: 1px solid #000; padding: 10px; text-align: center;">
                    <strong><?= date('d.m.Y', strtotime($order['order_date'])) ?></strong>
                </div>
            </div>
            <div class="col-6">
                <div class="production-header">
                    <h2 class="mb-0">İSTEHSALAT QAİMƏSİ</h2>
                </div>
            </div>
            <div class="col-3">
                <div style="border: 1px solid #000; padding: 10px; text-align: center;">
                    <strong>№ <?= substr($order['order_number'], -4) ?></strong>
                </div>
            </div>
        </div>

        <!-- Customer Info -->
        <div class="customer-section">
            <div class="row">
                <div class="col-6">
                    <strong>Müştəri:</strong> <?= htmlspecialchars($order['contact_person']) ?><br>
                    <strong>Qeyd:</strong> ________________________________
                </div>
                <div class="col-6">
                    <strong>Profil:</strong> ________________________________<br>
                    <strong>Mağaza:</strong> <?= htmlspecialchars($order['store_name']) ?>
                </div>
            </div>
        </div>

        <!-- Production Items -->
        <?php 
        $door_counter = 1;
        foreach ($order_items as $item): 
            // Calculate glass dimensions
            $glass_height = max(0, $item['height'] - $glass_reduction);
            $glass_width = max(0, $item['width'] - $glass_reduction);
        ?>
            <div class="door-section">
                <div class="door-header">
                    Qapaq #<?= $door_counter ?> - <?= htmlspecialchars($item['profile_type']) ?>
                </div>
                
                <!-- Door measurements table -->
                <table class="measurements-table">
                    <thead>
                        <tr>
                            <th>Tip</th>
                            <th>Hündürlük</th>
                            <th>x</th>
                            <th>En</th>
                            <th>Ədəd</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><?= htmlspecialchars($item['profile_type']) ?></strong></td>
                            <td><?= $item['height'] ?></td>
                            <td>x</td>
                            <td><?= $item['width'] ?></td>
                            <td><?= $item['quantity'] ?></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Production notes section -->
                <div class="notes-section">
                    <strong>İstehsalat qeydləri:</strong><br>
                    <div style="min-height: 50px; margin-top: 5px;">
                        <?= $item['notes'] ? nl2br(htmlspecialchars($item['notes'])) : '' ?>
                    </div>
                </div>

                <!-- Glass calculation -->
                <div class="glass-section">
                    <strong><?= $item['height'] ?>,<?= substr($item['height'] - floor($item['height']), 1) ?> də şüşə yeri:</strong> 
                    yuxarıdan <strong><?= $glass_reduction/2 ?></strong>, 
                    60 və aşağıdan <strong><?= $glass_reduction/2 ?></strong>, 
                    <strong><?= $glass_reduction/2 ?></strong><br>
                    
                    <div class="mt-2">
                        <strong>Şüşə ölçüləri:</strong> <?= $glass_height ?> x <?= $glass_width ?>
                    </div>
                </div>

                <!-- Additional production rows for glass -->
                <?php if ($door_counter < count($order_items)): ?>
                    <div style="margin-top: 30px; page-break-inside: avoid;">
                        <div style="border-bottom: 1px solid #000; padding-bottom: 10px; margin-bottom: 15px;">
                            <strong><?= htmlspecialchars($order['contact_person']) ?></strong>
                        </div>
                        
                        <table class="measurements-table">
                            <thead>
                                <tr>
                                    <th>Tip</th>
                                    <th>Hündürlük</th>
                                    <th>x</th>
                                    <th>En</th>
                                    <th>Ədəd</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Şüşə</strong></td>
                                    <td><?= $glass_height ?></td>
                                    <td>x</td>
                                    <td><?= $glass_width ?></td>
                                    <td><?= $item['quantity'] ?></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <!-- Glass production notes -->
                        <div class="notes-section">
                            <strong>Şüşə üçün qeydlər:</strong><br>
                            <div style="min-height: 40px; margin-top: 5px;">
                                Tip: <?= htmlspecialchars($item['glass_type']) ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php $door_counter++; ?>
        <?php endforeach; ?>

        <!-- Worker and Quality Section -->
        <div class="worker-section">
            <div class="row">
                <div class="col-6">
                    <strong>Sifarişi icra edən:</strong><br>
                    <div class="signature-line"></div>
                    <div style="text-align: center; margin-top: 5px;">İmza</div>
                </div>
                <div class="col-6">
                    <strong>Sifarişi təhvil verən:</strong><br>
                    <div class="signature-line"></div>
                    <div style="text-align: center; margin-top: 5px;">İmza</div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-6">
                    <strong>Sifarişi təhvil alan:</strong><br>
                    <div class="signature-line"></div>
                    <div style="text-align: center; margin-top: 5px;">İmza</div>
                </div>
                <div class="col-6">
                    <strong>Təhvil verilmə vaxtı:</strong><br>
                    <div class="signature-line"></div>
                    <div style="text-align: center; margin-top: 5px;">Tarix və vaxt</div>
                </div>
            </div>
        </div>

        <!-- Quality Check Section -->
        <div class="quality-section">
            <div class="row align-items-center">
                <div class="col-8">
                    <strong>"Detalları yoxladım, heç bir problem yoxdur, detalları qəbul edirəm"</strong>
                </div>
                <div class="col-4">
                    <div class="signature-line"></div>
                    <div style="text-align: center; margin-top: 5px;">İmza</div>
                </div>
            </div>
        </div>

        <!-- Print Info -->
        <div class="text-center mt-4 print-footer">
            <small>
                Sifariş nömrəsi: <?= htmlspecialchars($order['order_number']) ?> | 
                Çap tarixi: <?= date('d.m.Y H:i') ?> | 
                Satış meneceri: <?= htmlspecialchars($order['sales_person']) ?>
                <?php if ($order['barcode']): ?>
                    | Barkod: <?= htmlspecialchars($order['barcode']) ?>
                <?php endif; ?>
            </small>
        </div>

        <!-- Print Controls -->
        <div class="text-center mt-4 no-print">
            <button onclick="window.print()" class="btn btn-primary me-2">
                <i class="bi bi-printer"></i> Çap et
            </button>
            <button onclick="sendToProduction()" class="btn btn-success me-2">
                <i class="bi bi-whatsapp"></i> İstehsalata göndər
            </button>
            <button onclick="window.close()" class="btn btn-secondary">
                <i class="bi bi-x"></i> Bağla
            </button>
        </div>
    </div>

    <script>
        function sendToProduction() {
            if (confirm('Bu qaiməni istehsalata WhatsApp vasitəsilə göndərmək istəyirsiniz?')) {
                const orderNumber = '<?= htmlspecialchars($order['order_number']) ?>';
                const message = `İstehsalat qaiməsi: ${orderNumber}\n\nQaime hazırdır və çap edildi.\n\nAlumpro.Az`;
                const whatsappUrl = `https://wa.me/994501234567?text=${encodeURIComponent(message)}`;
                window.open(whatsappUrl, '_blank');
            }
        }

        // Auto print option
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>