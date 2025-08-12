<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';
require_once '../includes/twilio.php';

SessionManager::requireAdmin();

$db = new Database();
$message = '';
$error = '';

// Handle actions
if ($_POST) {
    if ($_POST['action'] === 'send_bulk_message') {
        try {
            $message_text = trim($_POST['message']);
            $target_customers = $_POST['target_customers'] ?? [];
            
            if (empty($message_text) || empty($target_customers)) {
                throw new Exception('Mesaj və hədəf müştərilər mütləqdir');
            }
            
            $twilio = new TwilioManager();
            $sent_count = 0;
            $failed_count = 0;
            
            foreach ($target_customers as $customer_id) {
                $stmt = $db->query("SELECT contact_person, phone FROM customers WHERE id = ?", [$customer_id]);
                $customer = $stmt->fetch();
                
                if ($customer) {
                    $result = $twilio->sendWhatsAppMessage(
                        $customer['phone'], 
                        $message_text
                    );
                    
                    if ($result['success']) {
                        $sent_count++;
                    } else {
                        $failed_count++;
                    }
                }
            }
            
            $message = "Mesaj göndərildi: $sent_count uğurlu, $failed_count uğursuz";
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($_POST['action'] === 'send_promotional') {
        try {
            $promotion_text = trim($_POST['promotion_text']);
            $customer_filter = $_POST['customer_filter'] ?? 'all';
            
            if (empty($promotion_text)) {
                throw new Exception('Promosyon mətnı mütləqdir');
            }
            
            // Get customers based on filter
            $where_clause = "";
            $params = [];
            
            if ($customer_filter === 'active') {
                $where_clause = "WHERE EXISTS (SELECT 1 FROM orders o WHERE o.customer_id = c.id AND o.order_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH))";
            } elseif ($customer_filter === 'high_value') {
                $where_clause = "WHERE c.total_spent >= 1000";
            }
            
            $stmt = $db->query("SELECT id, contact_person, phone FROM customers c $where_clause", $params);
            $customers = $stmt->fetchAll();
            
            $twilio = new TwilioManager();
            $sent_count = 0;
            
            foreach ($customers as $customer) {
                $result = $twilio->sendPromotionalMessage(
                    $customer['phone'],
                    $customer['contact_person'],
                    $promotion_text
                );
                
                if ($result['success']) {
                    $sent_count++;
                }
                
                // Add delay to avoid rate limiting
                usleep(500000); // 0.5 second delay
            }
            
            $message = "Promosyon mesajı $sent_count müştəriyə göndərildi";
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get WhatsApp statistics
$stmt = $db->query("SELECT 
                    COUNT(*) as total_messages,
                    COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_count,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_count,
                    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_count
                    FROM whatsapp_messages");
$stats = $stmt->fetch();

// Get recent messages
$stmt = $db->query("SELECT * FROM whatsapp_messages ORDER BY created_at DESC LIMIT 20");
$recent_messages = $stmt->fetchAll();

// Get customers for bulk messaging
$stmt = $db->query("SELECT c.*, COUNT(o.id) as order_count 
                    FROM customers c 
                    LEFT JOIN orders o ON c.id = o.customer_id 
                    GROUP BY c.id 
                    ORDER BY c.contact_person");
$customers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp İdarəsi - Alumpro.Az</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin-sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-whatsapp text-success"></i> WhatsApp İdarəsi
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

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <i class="bi bi-chat-dots text-success display-6"></i>
                                <h4 class="mt-2"><?= $stats['total_messages'] ?></h4>
                                <p class="text-muted mb-0">Ümumi Mesaj</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <i class="bi bi-check-circle text-primary display-6"></i>
                                <h4 class="mt-2"><?= $stats['delivered_count'] ?></h4>
                                <p class="text-muted mb-0">Çatdırılan</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-danger">
                            <div class="card-body text-center">
                                <i class="bi bi-x-circle text-danger display-6"></i>
                                <h4 class="mt-2"><?= $stats['failed_count'] ?></h4>
                                <p class="text-muted mb-0">Uğursuz</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-info">
                            <div class="card-body text-center">
                                <i class="bi bi-calendar-day text-info display-6"></i>
                                <h4 class="mt-2"><?= $stats['today_count'] ?></h4>
                                <p class="text-muted mb-0">Bu gün</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-send"></i> Toplu Mesaj Göndər
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="send_bulk_message">
                                    <div class="mb-3">
                                        <label for="message" class="form-label">Mesaj *</label>
                                        <textarea class="form-control" name="message" rows="4" required 
                                                  placeholder="Müştərilərə göndəriləcək mesaj..."></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Hədəf Müştərilər *</label>
                                        <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="select_all">
                                                <label class="form-check-label fw-bold" for="select_all">
                                                    Hamısını seç
                                                </label>
                                            </div>
                                            <hr>
                                            <?php foreach ($customers as $customer): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input customer-checkbox" type="checkbox" 
                                                           name="target_customers[]" value="<?= $customer['id'] ?>" 
                                                           id="customer_<?= $customer['id'] ?>">
                                                    <label class="form-check-label" for="customer_<?= $customer['id'] ?>">
                                                        <?= htmlspecialchars($customer['contact_person']) ?> 
                                                        (<?= $customer['phone'] ?>) - <?= $customer['order_count'] ?> sifariş
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-whatsapp"></i> Mesaj Göndər
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-megaphone"></i> Promosyon Mesajı
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="send_promotional">
                                    <div class="mb-3">
                                        <label for="promotion_text" class="form-label">Promosyon Mətnı *</label>
                                        <textarea class="form-control" name="promotion_text" rows="4" required
                                                  placeholder="Xüsusi təklif və ya promosyon haqqında məlumat..."></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="customer_filter" class="form-label">Hədəf Auditoriya</label>
                                        <select class="form-select" name="customer_filter">
                                            <option value="all">Bütün müştərilər</option>
                                            <option value="active">Aktiv müştərilər (son 6 ay)</option>
                                            <option value="high_value">Yüksək dəyərli müştərilər (>1000 AZN)</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="bi bi-megaphone"></i> Promosyon Göndər
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Messages -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history"></i> Son Mesajlar
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Telefon</th>
                                        <th>Mesaj</th>
                                        <th>Status</th>
                                        <th>Tarix</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_messages as $msg): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($msg['phone_number']) ?></td>
                                            <td>
                                                <?= htmlspecialchars(substr($msg['message'], 0, 100)) ?>
                                                <?= strlen($msg['message']) > 100 ? '...' : '' ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status_colors = [
                                                    'sent' => 'info',
                                                    'delivered' => 'success',
                                                    'read' => 'primary',
                                                    'failed' => 'danger',
                                                    'pending' => 'warning'
                                                ];
                                                $status_texts = [
                                                    'sent' => 'Göndərildi',
                                                    'delivered' => 'Çatdırıldı',
                                                    'read' => 'Oxundu',
                                                    'failed' => 'Uğursuz',
                                                    'pending' => 'Gözləyir'
                                                ];
                                                ?>
                                                <span class="badge bg-<?= $status_colors[$msg['status']] ?>">
                                                    <?= $status_texts[$msg['status']] ?>
                                                </span>
                                            </td>
                                            <td><?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        // Select all functionality
        document.getElementById('select_all').addEventListener('change', function() {
            const customerCheckboxes = document.querySelectorAll('.customer-checkbox');
            customerCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
        
        // Update select all when individual checkboxes change
        document.querySelectorAll('.customer-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.customer-checkbox');
                const checkedBoxes = document.querySelectorAll('.customer-checkbox:checked');
                const selectAllCheckbox = document.getElementById('select_all');
                
                selectAllCheckbox.checked = allCheckboxes.length === checkedBoxes.length;
                selectAllCheckbox.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < allCheckboxes.length;
            });
        });
    </script>
</body>
</html>