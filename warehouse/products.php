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

// Handle product actions
if ($_POST) {
    if ($_POST['action'] === 'add_product') {
        try {
            $name = trim($_POST['name']);
            $category_id = $_POST['category_id'];
            $type = trim($_POST['type']);
            $color = trim($_POST['color']);
            $unit = trim($_POST['unit']);
            $size = trim($_POST['size']);
            $quantity = intval($_POST['quantity']);
            $purchase_price = floatval($_POST['purchase_price']);
            $selling_price = floatval($_POST['selling_price']);
            $date_added = $_POST['date_added'];
            
            if (empty($name) || empty($category_id)) {
                throw new Exception('Məhsul adı və kateqoriya mütləqdir');
            }
            
            // Generate product code
            $code = Utils::generateProductCode('PROF');
            
            $stmt = $db->query("INSERT INTO products (code, name, category_id, type, color, unit, size, quantity, purchase_price, selling_price, remaining_quantity, date_added) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", 
                [$code, $name, $category_id, $type, $color, $unit, $size, $quantity, $purchase_price, $selling_price, $quantity, $date_added]);
            
            Utils::logActivity($user_id, 'product_added', "Məhsul əlavə edildi: $name ($code)");
            
            $message = 'Məhsul uğurla əlavə edildi';
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($_POST['action'] === 'update_stock') {
        try {
            $product_id = $_POST['product_id'];
            $new_quantity = intval($_POST['new_quantity']);
            $adjustment_type = $_POST['adjustment_type']; // add, subtract, set
            
            $stmt = $db->query("SELECT name, remaining_quantity FROM products WHERE id = ?", [$product_id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                throw new Exception('Məhsul tapılmadı');
            }
            
            $old_quantity = $product['remaining_quantity'];
            
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
            
            $stmt = $db->query("UPDATE products SET remaining_quantity = ? WHERE id = ?", [$final_quantity, $product_id]);
            
            Utils::logActivity($user_id, 'stock_updated', "Anbar yeniləndi: {$product['name']} ($old_quantity → $final_quantity)");
            
            $message = 'Anbar məlumatları yeniləndi';
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get products with filtering
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
    $where_conditions[] = "(p.name LIKE ? OR p.code LIKE ? OR p.type LIKE ? OR p.color LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($type_filter)) {
    $where_conditions[] = "p.type = ?";
    $params[] = $type_filter;
}

if ($stock_filter === 'low_stock') {
    $where_conditions[] = "p.remaining_quantity <= 10";
}

if ($stock_filter === 'out_of_stock') {
    $where_conditions[] = "p.remaining_quantity = 0";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_params = $params;
$stmt = $db->query("SELECT COUNT(*) as total FROM products p $where_clause", $count_params);
$total_products = $stmt->fetch()['total'];
$total_pages = ceil($total_products / $limit);

// Get products
$params[] = $limit;
$params[] = $offset;
$stmt = $db->query("SELECT p.*, c.name as category_name FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    $where_clause
                    ORDER BY p.created_at DESC 
                    LIMIT ? OFFSET ?", $params);
$products = $stmt->fetchAll();

// Get categories for dropdown
$stmt = $db->query("SELECT * FROM categories WHERE type = 'profile' ORDER BY name");
$categories = $stmt->fetchAll();

// Get product types for dropdown
$stmt = $db->query("SELECT DISTINCT type FROM products WHERE type IS NOT NULL AND type != '' ORDER BY type");
$product_types = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Məhsul Anbarı - Alumpro.Az</title>
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
                        <i class="bi bi-box-seam text-primary"></i> Məhsul Anbarı
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                <i class="bi bi-plus-circle"></i> Yeni Məhsul
                            </button>
                            <a href="glass.php" class="btn btn-outline-info">
                                <i class="bi bi-window"></i> Şüşələr
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
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" 
                                       value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Məhsul adı, kod, tip və ya rəng ilə axtarın...">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="category">
                                    <option value="">Bütün kateqoriyalar</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" <?= $category_filter == $category['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="type">
                                    <option value="">Bütün tiplər</option>
                                    <?php foreach ($product_types as $type): ?>
                                        <option value="<?= htmlspecialchars($type['type']) ?>" <?= $type_filter === $type['type'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($type['type']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="filter">
                                    <option value="">Bütün məhsullar</option>
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
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <i class="bi bi-box display-4 text-primary"></i>
                                <h4 class="mt-2"><?= $total_products ?></h4>
                                <p class="text-muted">Ümumi Məhsul</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <i class="bi bi-exclamation-triangle display-4 text-warning"></i>
                                <?php
                                $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE remaining_quantity <= 10 AND remaining_quantity > 0");
                                $low_stock_count = $stmt->fetch()['count'];
                                ?>
                                <h4 class="mt-2"><?= $low_stock_count ?></h4>
                                <p class="text-muted">Az Qalan</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-danger">
                            <div class="card-body text-center">
                                <i class="bi bi-x-circle display-4 text-danger"></i>
                                <?php
                                $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE remaining_quantity = 0");
                                $out_of_stock_count = $stmt->fetch()['count'];
                                ?>
                                <h4 class="mt-2"><?= $out_of_stock_count ?></h4>
                                <p class="text-muted">Bitmiş</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <i class="bi bi-currency-dollar display-4 text-success"></i>
                                <?php
                                $stmt = $db->query("SELECT SUM(remaining_quantity * purchase_price) as total_value FROM products");
                                $total_value = $stmt->fetch()['total_value'] ?: 0;
                                ?>
                                <h4 class="mt-2"><?= formatCurrency($total_value) ?></h4>
                                <p class="text-muted">Anbar Dəyəri</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            Məhsullar (<?= $total_products ?> nəticə)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Kod</th>
                                        <th>Məhsul</th>
                                        <th>Kateqoriya</th>
                                        <th>Tip</th>
                                        <th>Rəng</th>
                                        <th>Ölçü</th>
                                        <th>Vahid</th>
                                        <th>Qalan</th>
                                        <th>Satılan</th>
                                        <th>Qiymət</th>
                                        <th>Əməliyyat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($products)): ?>
                                        <tr>
                                            <td colspan="11" class="text-center py-4">
                                                <i class="bi bi-inbox display-4 text-muted"></i>
                                                <p class="text-muted mt-2">Məhsul tapılmadı</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($products as $product): ?>
                                            <tr class="<?= $product['remaining_quantity'] == 0 ? 'table-danger' : ($product['remaining_quantity'] <= 10 ? 'table-warning' : '') ?>">
                                                <td>
                                                    <small class="font-monospace"><?= htmlspecialchars($product['code']) ?></small>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($product['name']) ?></strong>
                                                </td>
                                                <td><?= htmlspecialchars($product['category_name'] ?: '-') ?></td>
                                                <td><?= htmlspecialchars($product['type'] ?: '-') ?></td>
                                                <td>
                                                    <?php if ($product['color']): ?>
                                                        <span class="badge" style="background-color: <?= htmlspecialchars($product['color']) ?>; color: <?= $product['color'] === '#FFFFFF' || $product['color'] === 'white' ? 'black' : 'white' ?>;">
                                                            <?= htmlspecialchars($product['color']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($product['size'] ?: '-') ?></td>
                                                <td><?= htmlspecialchars($product['unit'] ?: '-') ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $product['remaining_quantity'] == 0 ? 'danger' : ($product['remaining_quantity'] <= 10 ? 'warning' : 'success') ?>">
                                                        <?= $product['remaining_quantity'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?= $product['sold_quantity'] ?></span>
                                                </td>
                                                <td>
                                                    <small>
                                                        <strong>Satış:</strong> <?= formatCurrency($product['selling_price']) ?><br>
                                                        <span class="text-muted">Alış: <?= formatCurrency($product['purchase_price']) ?></span>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="adjustStock(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>', <?= $product['remaining_quantity'] ?>)"
                                                                title="Anbar düzəlişi">
                                                            <i class="bi bi-arrow-up-down"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary" 
                                                                onclick="editProduct(<?= htmlspecialchars(json_encode($product)) ?>)"
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

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle"></i> Yeni Məhsul Əlavə Et
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_product">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Məhsul Adı *</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Kateqoriya *</label>
                                <select class="form-select" name="category_id" required>
                                    <option value="">Seçin...</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="type" class="form-label">Tip</label>
                                <input type="text" class="form-control" name="type" placeholder="B.O, Fumo və s.">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="color" class="form-label">Rəng</label>
                                <input type="text" class="form-control" name="color" placeholder="Ağ, Qara və s.">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="size" class="form-label">Ölçü</label>
                                <input type="text" class="form-control" name="size" placeholder="60x40, 80x40 və s.">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="unit" class="form-label">Vahid</label>
                                <select class="form-select" name="unit">
                                    <option value="ədəd">Ədəd</option>
                                    <option value="metr">Metr</option>
                                    <option value="kg">Kiloqram</option>