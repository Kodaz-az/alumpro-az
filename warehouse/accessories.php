<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireSales();

$user_id = SessionManager::getUserId();
$user_role = SessionManager::getUserRole();
$db = new Database();

// Check warehouse access
if ($user_role === 'sales') {
    $stmt = $db->query("SELECT warehouse_access FROM users WHERE id = ?", [$user_id]);
    $user_data = $stmt->fetch();
    if (!$user_data['warehouse_access']) {
        header('Location: ../sales/dashboard.php?error=access_denied');
        exit;
    }
}

$message = '';
$error = '';

// Handle accessory actions
if ($_POST) {
    if ($_POST['action'] === 'add_accessory') {
        try {
            $name = trim($_POST['name']);
            $category = trim($_POST['category']);
            $type = trim($_POST['type']);
            $color = trim($_POST['color']);
            $unit = trim($_POST['unit']);
            $quantity = intval($_POST['quantity']);
            $purchase_price = floatval($_POST['purchase_price']);
            $selling_price = floatval($_POST['selling_price']);
            $date_added = $_POST['date_added'];
            
            if (empty($name) || empty($category)) {
                throw new Exception('Aksesuar adı və kateqoriya mütləqdir');
            }
            
            // Generate accessory code
            $code = Utils::generateProductCode('ACC');
            
            $stmt = $db->query("INSERT INTO accessories (code, name, category, type, color, unit, quantity, purchase_price, selling_price, remaining_quantity, date_added) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", 
                [$code, $name, $category, $type, $color, $unit, $quantity, $purchase_price, $selling_price, $quantity, $date_added]);
            
            Utils::logActivity($user_id, 'accessory_added', "Aksesuar əlavə edildi: $name ($code)");
            
            $message = 'Aksesuar uğurla əlavə edildi';
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($_POST['action'] === 'update_stock') {
        try {
            $accessory_id = $_POST['accessory_id'];
            $new_quantity = intval($_POST['new_quantity']);
            $adjustment_type = $_POST['adjustment_type'];
            
            $stmt = $db->query("SELECT name, remaining_quantity FROM accessories WHERE id = ?", [$accessory_id]);
            $accessory = $stmt->fetch();
            
            if (!$accessory) {
                throw new Exception('Aksesuar tapılmadı');
            }
            
            $old_quantity = $accessory['remaining_quantity'];
            
            switch ($adjustment_type) {
                case 'add':
                    $final_quantity = $old_quantity + $new_quantity;
                    break;
                case 'subtract':
                    $final_quantity = max(0, $old_quantity - $new_quantity);
                    break;
                case 'set':
                    $final_quantity = $new_quantity;
                    break;
                default:
                    throw new Exception('Düzəliş növü düzgün deyil');
            }
            
            $stmt = $db->query("UPDATE accessories SET remaining_quantity = ? WHERE id = ?", [$final_quantity, $accessory_id]);
            
            Utils::logActivity($user_id, 'accessory_stock_updated', "Aksesuar anbarı yeniləndi: {$accessory['name']} ($old_quantity → $final_quantity)");
            
            $message = 'Anbar məlumatları yeniləndi';
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get accessories with filtering
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$type_filter = $_GET['type'] ?? '';
$stock_filter = $_GET['filter'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR code LIKE ? OR type LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
}

if (!empty($type_filter)) {
    $where_conditions[] = "type = ?";
    $params[] = $type_filter;
}

if ($stock_filter === 'low_stock') {
    $where_conditions[] = "remaining_quantity <= 10";
}

if ($stock_filter === 'out_of_stock') {
    $where_conditions[] = "remaining_quantity = 0";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_params = $params;
$stmt = $db->query("SELECT COUNT(*) as total FROM accessories $where_clause", $count_params);
$total_accessories = $stmt->fetch()['total'];
$total_pages = ceil($total_accessories / $limit);

// Get accessories
$params[] = $limit;
$params[] = $offset;
$stmt = $db->query("SELECT * FROM accessories $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?", $params);
$accessories = $stmt->fetchAll();

// Get categories for dropdown
$stmt = $db->query("SELECT DISTINCT category FROM accessories WHERE category IS NOT NULL AND category != '' ORDER BY category");
$categories = $stmt->fetchAll();

// Get types for dropdown
$stmt = $db->query("SELECT DISTINCT type FROM accessories WHERE type IS NOT NULL AND type != '' ORDER BY type");
$types = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aksesuar Anbarı - Alumpro.Az</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
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
                        <i class="bi bi-tools text-primary"></i> Aksesuar Anbarı
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addAccessoryModal">
                                <i class="bi bi-plus-circle"></i> Yeni Aksesuar
                            </button>
                            <a href="products.php" class="btn btn-outline-primary">
                                <i class="bi bi-box-seam"></i> Profillər
                            </a>
                            <a href="glass.php" class="btn btn-outline-info">
                                <i class="bi bi-window"></i> Şüşələr
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

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" 
                                       value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Aksesuar adı, kod və ya tip ilə axtarın...">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="category">
                                    <option value="">Bütün kateqoriyalar</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= htmlspecialchars($category['category']) ?>" <?= $category_filter === $category['category'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['category']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="type">
                                    <option value="">Bütün tiplər</option>
                                    <?php foreach ($types as $type): ?>
                                        <option value="<?= htmlspecialchars($type['type']) ?>" <?= $type_filter === $type['type'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($type['type']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="filter">
                                    <option value="">Bütün aksesuarlar</option>
                                    <option value="low_stock" <?= $stock_filter === 'low_stock' ? 'selected' : '' ?>>Az qalan</option>
                                    <option value="out_of_stock" <?= $stock_filter === 'out_of_stock' ? 'selected' : '' ?>>Bitmiş</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Axtar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Stock Summary -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <i class="bi bi-tools display-4 text-success"></i>
                                <h4 class="mt-2"><?= $total_accessories ?></h4>
                                <p class="text-muted">Ümumi Aksesuar</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-info">
                            <div class="card-body text-center">
                                <i class="bi bi-stack display-4 text-info"></i>
                                <?php
                                $stmt = $db->query("SELECT SUM(remaining_quantity) as total_quantity FROM accessories");
                                $total_quantity = $stmt->fetch()['total_quantity'] ?: 0;
                                ?>
                                <h4 class="mt-2"><?= $total_quantity ?></h4>
                                <p class="text-muted">Ümumi Say</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <i class="bi bi-exclamation-triangle display-4 text-warning"></i>
                                <?php
                                $stmt = $db->query("SELECT COUNT(*) as count FROM accessories WHERE remaining_quantity <= 10 AND remaining_quantity > 0");
                                $low_stock_count = $stmt->fetch()['count'];
                                ?>
                                <h4 class="mt-2"><?= $low_stock_count ?></h4>
                                <p class="text-muted">Az Qalan</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <i class="bi bi-currency-dollar display-4 text-primary"></i>
                                <?php
                                $stmt = $db->query("SELECT SUM(remaining_quantity * purchase_price) as total_value FROM accessories");
                                $total_value = $stmt->fetch()['total_value'] ?: 0;
                                ?>
                                <h4 class="mt-2"><?= formatCurrency($total_value) ?></h4>
                                <p class="text-muted">Anbar Dəyəri</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Accessories Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            Aksesuarlar (<?= $total_accessories ?> nəticə)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Kod</th>
                                        <th>Aksesuar</th>
                                        <th>Kateqoriya</th>
                                        <th>Tip</th>
                                        <th>Rəng</th>
                                        <th>Vahid</th>
                                        <th>Qalan</th>
                                        <th>Satılan</th>
                                        <th>Qiymət</th>
                                        <th>Əməliyyat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($accessories)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center py-4">
                                                <i class="bi bi-inbox display-4 text-muted"></i>
                                                <p class="text-muted mt-2">Aksesuar tapılmadı</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($accessories as $accessory): ?>
                                            <tr class="<?= $accessory['remaining_quantity'] == 0 ? 'table-danger' : ($accessory['remaining_quantity'] <= 10 ? 'table-warning' : '') ?>">
                                                <td>
                                                    <small class="font-monospace"><?= htmlspecialchars($accessory['code']) ?></small>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($accessory['name']) ?></strong>
                                                </td>
                                                <td><?= htmlspecialchars($accessory['category'] ?: '-') ?></td>
                                                <td><?= htmlspecialchars($accessory['type'] ?: '-') ?></td>
                                                <td>
                                                    <?php if ($accessory['color']): ?>
                                                        <span class="badge" style="background-color: <?= htmlspecialchars($accessory['color']) ?>; color: <?= $accessory['color'] === '#FFFFFF' || $accessory['color'] === 'white' ? 'black' : 'white' ?>;">
                                                            <?= htmlspecialchars($accessory['color']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($accessory['unit'] ?: '-') ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $accessory['remaining_quantity'] == 0 ? 'danger' : ($accessory['remaining_quantity'] <= 10 ? 'warning' : 'success') ?>">
                                                        <?= $accessory['remaining_quantity'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?= $accessory['sold_quantity'] ?></span>
                                                </td>
                                                <td>
                                                    <small>
                                                        <strong>Satış:</strong> <?= formatCurrency($accessory['selling_price']) ?><br>
                                                        <span class="text-muted">Alış: <?= formatCurrency($accessory['purchase_price']) ?></span>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="adjustAccessoryStock(<?= $accessory['id'] ?>, '<?= htmlspecialchars($accessory['name']) ?>', <?= $accessory['remaining_quantity'] ?>)"
                                                                title="Anbar düzəlişi">
                                                            <i class="bi bi-arrow-up-down"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary" 
                                                                onclick="editAccessory(<?= htmlspecialchars(json_encode($accessory)) ?>)"
                                                                title="Redaktə et">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="card-footer">
                            <nav aria-label="Pagination">
                                <ul class="pagination mb-0 justify-content-center">
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Accessory Modal -->
    <div class="modal fade" id="addAccessoryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle"></i> Yeni Aksesuar Əlavə Et
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_accessory">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Aksesuar Adı *</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Kateqoriya *</label>
                                <select class="form-select" name="category" required>
                                    <option value="">Seçin...</option>
                                    <option value="Qoltuq">Qoltuq</option>
                                    <option value="Vida">Vida</option>
                                    <option value="Mıknatıs">Mıknatıs</option>
                                    <option value="Qapı Çəkəsi">Qapı Çəkəsi</option>
                                    <option value="Kilit">Kilit</option>
                                    <option value="Menteşe">Menteşe</option>
                                    <option value="Rezin">Rezin</option>
                                    <option value="Digər">Digər</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="type" class="form-label">Tip</label>
                                <input type="text" class="form-control" name="type" placeholder="Ölçü, model və s.">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="color" class="form-label">Rəng</label>
                                <input type="text" class="form-control" name="color" placeholder="Ağ, Qara və s.">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="unit" class="form-label">Vahid</label>
                                <select class="form-select" name="unit">
                                    <option value="ədəd">Ədəd</option>
                                    <option value="dəst">Dəst</option>
                                    <option value="kg">Kiloqram</option>
                                    <option value="metr">Metr</option>
                                    <option value="kom">Komplet</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="quantity" class="form-label">Say *</label>
                                <input type="number" class="form-control" name="quantity" min="0" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="purchase_price" class="form-label">Alış Qiyməti</label>
                                <input type="number" class="form-control" name="purchase_price" step="0.01" min="0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="selling_price" class="form-label">Satış Qiyməti</label>
                                <input type="number" class="form-control" name="selling_price" step="0.01" min="0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="date_added" class="form-label">Tarix *</label>
                                <input type="date" class="form-control" name="date_added" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Bağla</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check"></i> Aksesuar Əlavə Et
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Stock Adjustment Modal -->
    <div class="modal fade" id="stockAdjustModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-gradient-secondary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-arrow-up-down"></i> Anbar Düzəlişi
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="stockAdjustForm">
                    <input type="hidden" name="action" value="update_stock">
                    <input type="hidden" name="accessory_id" id="adjust_accessory_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Aksesuar</label>
                            <input type="text" class="form-control" id="adjust_accessory_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Hazırki Say</label>
                            <input type="text" class="form-control" id="adjust_current_stock" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="adjustment_type" class="form-label">Düzəliş Növü *</label>
                            <select class="form-select" name="adjustment_type" id="adjustment_type" required>
                                <option value="add">Əlavə et (+)</option>
                                <option value="subtract">Çıxar (-)</option>
                                <option value="set">Təyin et (=)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="new_quantity" class="form-label">Say *</label>
                            <input type="number" class="form-control" name="new_quantity" id="new_quantity" min="0" required>
                        </div>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <span id="adjustment_preview"></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Bağla</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check"></i> Düzəlişi Tətbiq Et
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function adjustAccessoryStock(accessoryId, accessoryName, currentStock) {
            document.getElementById('adjust_accessory_id').value = accessoryId;
            document.getElementById('adjust_accessory_name').value = accessoryName;
            document.getElementById('adjust_current_stock').value = currentStock;
            document.getElementById('new_quantity').value = '';
            document.getElementById('adjustment_preview').textContent = '';
            
            const modal = new bootstrap.Modal(document.getElementById('stockAdjustModal'));
            modal.show();
        }
        
        function updateAdjustmentPreview() {
            const currentStock = parseInt(document.getElementById('adjust_current_stock').value) || 0;
            const adjustmentType = document.getElementById('adjustment_type').value;
            const newQuantity = parseInt(document.getElementById('new_quantity').value) || 0;
            const previewElement = document.getElementById('adjustment_preview');
            
            let finalQuantity;
            let message;
            
            switch (adjustmentType) {
                case 'add':
                    finalQuantity = currentStock + newQuantity;
                    message = `${currentStock} + ${newQuantity} = ${finalQuantity}`;
                    break;
                case 'subtract':
                    finalQuantity = Math.max(0, currentStock - newQuantity);
                    message = `${currentStock} - ${newQuantity} = ${finalQuantity}`;
                    break;
                case 'set':
                    finalQuantity = newQuantity;
                    message = `Yeni say: ${finalQuantity}`;
                    break;
                default:
                    message = '';
            }
            
            previewElement.textContent = message;
        }
        
        document.getElementById('adjustment_type').addEventListener('change', updateAdjustmentPreview);
        document.getElementById('new_quantity').addEventListener('input', updateAdjustmentPreview);
        
        function editAccessory(accessory) {
            alert('Aksesuar redaktə funksiyası tezliklə əlavə ediləcək');
        }
    </script>
</body>
</html>