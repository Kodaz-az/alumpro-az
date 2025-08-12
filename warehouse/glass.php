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

// Handle glass actions
if ($_POST) {
    if ($_POST['action'] === 'add_glass') {
        try {
            $name = trim($_POST['name']);
            $type = trim($_POST['type']);
            $color = trim($_POST['color']);
            $height = floatval($_POST['height']);
            $width = floatval($_POST['width']);
            $quantity = intval($_POST['quantity']);
            $purchase_price = floatval($_POST['purchase_price']);
            $selling_price = floatval($_POST['selling_price']);
            $date_added = $_POST['date_added'];
            
            if (empty($name) || $height <= 0 || $width <= 0) {
                throw new Exception('Şüşə adı və ölçüləri mütləqdir');
            }
            
            // Calculate total area
            $total_area = ($height * $width * $quantity) / 10000; // Convert cm² to m²
            
            // Generate glass code
            $code = Utils::generateProductCode('GLASS');
            
            $stmt = $db->query("INSERT INTO glass (code, name, type, color, height, width, quantity, total_area, purchase_price, selling_price, remaining_quantity, remaining_area, date_added) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", 
                [$code, $name, $type, $color, $height, $width, $quantity, $total_area, $purchase_price, $selling_price, $quantity, $total_area, $date_added]);
            
            Utils::logActivity($user_id, 'glass_added', "Şüşə əlavə edildi: $name ($code)");
            
            $message = 'Şüşə uğurla əlavə edildi';
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get glass with filtering
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$color_filter = $_GET['color'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR code LIKE ? OR type LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($type_filter)) {
    $where_conditions[] = "type = ?";
    $params[] = $type_filter;
}

if (!empty($color_filter)) {
    $where_conditions[] = "color = ?";
    $params[] = $color_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_params = $params;
$stmt = $db->query("SELECT COUNT(*) as total FROM glass $where_clause", $count_params);
$total_glass = $stmt->fetch()['total'];
$total_pages = ceil($total_glass / $limit);

// Get glass
$params[] = $limit;
$params[] = $offset;
$stmt = $db->query("SELECT * FROM glass $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?", $params);
$glass_items = $stmt->fetchAll();

// Get glass types for dropdown
$stmt = $db->query("SELECT DISTINCT type FROM glass WHERE type IS NOT NULL AND type != '' ORDER BY type");
$glass_types = $stmt->fetchAll();

// Get glass colors for dropdown
$stmt = $db->query("SELECT DISTINCT color FROM glass WHERE color IS NOT NULL AND color != '' ORDER BY color");
$glass_colors = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şüşə Anbarı - Alumpro.Az</title>
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
                        <i class="bi bi-window text-primary"></i> Şüşə Anbarı
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addGlassModal">
                                <i class="bi bi-plus-circle"></i> Yeni Şüşə
                            </button>
                            <a href="products.php" class="btn btn-outline-primary">
                                <i class="bi bi-box-seam"></i> Profillər
                            </a>
                            <a href="accessories.php" class="btn btn-outline-secondary">
                                <i class="bi bi-tools"></i> Aksesuarlar
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
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="search" 
                                       value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Şüşə adı, kod və ya tip ilə axtarın...">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="type">
                                    <option value="">Bütün tiplər</option>
                                    <?php foreach ($glass_types as $type): ?>
                                        <option value="<?= htmlspecialchars($type['type']) ?>" <?= $type_filter === $type['type'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($type['type']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="color">
                                    <option value="">Bütün rənglər</option>
                                    <?php foreach ($glass_colors as $color): ?>
                                        <option value="<?= htmlspecialchars($color['color']) ?>" <?= $color_filter === $color['color'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($color['color']) ?>
                                        </option>
                                    <?php endforeach; ?>
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

                <!-- Glass Summary -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card border-info">
                            <div class="card-body text-center">
                                <i class="bi bi-window display-4 text-info"></i>
                                <h4 class="mt-2"><?= $total_glass ?></h4>
                                <p class="text-muted">Ümumi Şüşə Növü</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <i class="bi bi-layers display-4 text-success"></i>
                                <?php
                                $stmt = $db->query("SELECT SUM(remaining_quantity) as total_quantity FROM glass");
                                $total_quantity = $stmt->fetch()['total_quantity'] ?: 0;
                                ?>
                                <h4 class="mt-2"><?= $total_quantity ?></h4>
                                <p class="text-muted">Ümumi Ədəd</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <i class="bi bi-aspect-ratio display-4 text-primary"></i>
                                <?php
                                $stmt = $db->query("SELECT SUM(remaining_area) as total_area FROM glass");
                                $total_area = $stmt->fetch()['total_area'] ?: 0;
                                ?>
                                <h4 class="mt-2"><?= number_format($total_area, 2) ?> m²</h4>
                                <p class="text-muted">Ümumi Sahə</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <i class="bi bi-currency-dollar display-4 text-warning"></i>
                                <?php
                                $stmt = $db->query("SELECT SUM(remaining_quantity * purchase_price) as total_value FROM glass");
                                $total_value = $stmt->fetch()['total_value'] ?: 0;
                                ?>
                                <h4 class="mt-2"><?= formatCurrency($total_value) ?></h4>
                                <p class="text-muted">Anbar Dəyəri</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Glass Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            Şüşələr (<?= $total_glass ?> nəticə)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Kod</th>
                                        <th>Şüşə</th>
                                        <th>Tip</th>
                                        <th>Rəng</th>
                                        <th>Ölçülər (sm)</th>
                                        <th>Vahid Sahə</th>
                                        <th>Qalan Ədəd</th>
                                        <th>Qalan Sahə</th>
                                        <th>Qiymət</th>
                                        <th>Əməliyyat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($glass_items)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center py-4">
                                                <i class="bi bi-inbox display-4 text-muted"></i>
                                                <p class="text-muted mt-2">Şüşə tapılmadı</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($glass_items as $glass): ?>
                                            <tr class="<?= $glass['remaining_quantity'] == 0 ? 'table-danger' : ($glass['remaining_quantity'] <= 5 ? 'table-warning' : '') ?>">
                                                <td>
                                                    <small class="font-monospace"><?= htmlspecialchars($glass['code']) ?></small>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($glass['name']) ?></strong>
                                                </td>
                                                <td><?= htmlspecialchars($glass['type'] ?: '-') ?></td>
                                                <td>
                                                    <?php if ($glass['color']): ?>
                                                        <span class="badge" style="background-color: <?= htmlspecialchars($glass['color']) ?>; color: <?= $glass['color'] === '#FFFFFF' || $glass['color'] === 'white' ? 'black' : 'white' ?>;">
                                                            <?= htmlspecialchars($glass['color']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= $glass['height'] ?> × <?= $glass['width'] ?>
                                                    <br>
                                                    <small class="text-muted"><?= number_format(($glass['height'] * $glass['width']) / 10000, 4) ?> m²</small>
                                                </td>
                                                <td>
                                                    <?= number_format(($glass['height'] * $glass['width']) / 10000, 4) ?> m²
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $glass['remaining_quantity'] == 0 ? 'danger' : ($glass['remaining_quantity'] <= 5 ? 'warning' : 'success') ?>">
                                                        <?= $glass['remaining_quantity'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?= number_format($glass['remaining_area'], 2) ?> m²</strong>
                                                </td>
                                                <td>
                                                    <small>
                                                        <strong>Satış:</strong> <?= formatCurrency($glass['selling_price']) ?><br>
                                                        <span class="text-muted">Alış: <?= formatCurrency($glass['purchase_price']) ?></span>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="adjustGlassStock(<?= $glass['id'] ?>, '<?= htmlspecialchars($glass['name']) ?>', <?= $glass['remaining_quantity'] ?>)"
                                                                title="Anbar düzəlişi">
                                                            <i class="bi bi-arrow-up-down"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary" 
                                                                onclick="editGlass(<?= htmlspecialchars(json_encode($glass)) ?>)"
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

    <!-- Add Glass Modal -->
    <div class="modal fade" id="addGlassModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle"></i> Yeni Şüşə Əlavə Et
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_glass">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Şüşə Adı *</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="type" class="form-label">Tip</label>
                                <input type="text" class="form-control" name="type" placeholder="Şəffaf, Mat, Naxışlı və s.">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="color" class="form-label">Rəng</label>
                                <input type="text" class="form-control" name="color" placeholder="Şəffaf, Mavi və s.">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="height" class="form-label">Hündürlük (sm) *</label>
                                <input type="number" class="form-control" name="height" step="0.1" min="0" required onchange="calculateArea()">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="width" class="form-label">En (sm) *</label>
                                <input type="number" class="form-control" name="width" step="0.1" min="0" required onchange="calculateArea()">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>Vahid sahə:</strong> <span id="unit_area">0 m²</span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="quantity" class="form-label">Ədəd *</label>
                                <input type="number" class="form-control" name="quantity" min="0" required onchange="calculateTotalArea()">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Ümumi Sahə</label>
                                <input type="text" class="form-control" id="total_area" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="purchase_price" class="form-label">Alış Qiyməti (m²)</label>
                                <input type="number" class="form-control" name="purchase_price" step="0.01" min="0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="selling_price" class="form-label">Satış Qiyməti (m²)</label>
                                <input type="number" class="form-control" name="selling_price" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="date_added" class="form-label">Əlavə Edilmə Tarixi *</label>
                            <input type="date" class="form-control" name="date_added" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Bağla</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check"></i> Şüşə Əlavə Et
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function calculateArea() {
            const height = parseFloat(document.querySelector('input[name="height"]').value) || 0;
            const width = parseFloat(document.querySelector('input[name="width"]').value) || 0;
            const area = (height * width) / 10000; // Convert cm² to m²
            
            document.getElementById('unit_area').textContent = area.toFixed(4) + ' m²';
            calculateTotalArea();
        }
        
        function calculateTotalArea() {
            const height = parseFloat(document.querySelector('input[name="height"]').value) || 0;
            const width = parseFloat(document.querySelector('input[name="width"]').value) || 0;
            const quantity = parseInt(document.querySelector('input[name="quantity"]').value) || 0;
            const totalArea = (height * width * quantity) / 10000; // Convert cm² to m²
            
            document.getElementById('total_area').value = totalArea.toFixed(2) + ' m²';
        }
        
        function adjustGlassStock(glassId, glassName, currentStock) {
            // Glass stock adjustment functionality
            alert('Şüşə anbar düzəlişi funksiyası tezliklə əlavə ediləcək');
        }
        
        function editGlass(glass) {
            // Glass editing functionality
            alert('Şüşə redaktə funksiyası tezliklə əlavə ediləcək');
        }
    </script>
</body>
</html>