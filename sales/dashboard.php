<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireSales();

$user_id = SessionManager::getUserId();
$user_role = SessionManager::getUserRole();
$store_id = SessionManager::getStoreId();

$db = new Database();

// Get user info
$stmt = $db->query("SELECT u.*, s.name as store_name FROM users u 
                    LEFT JOIN stores s ON u.store_id = s.id 
                    WHERE u.id = ?", [$user_id]);
$user = $stmt->fetch();

// Get statistics
$stats = Utils::getStats($db, $user_role, $store_id);

// Get top customers
$top_customers = Utils::getTopCustomers($db, 5, $store_id);

// Get top products
$top_products = Utils::getTopProducts($db, 5, $store_id);

// Get low stock products
$low_stock = Utils::getLowStockProducts($db, 10);

// Get recent orders
$where_clause = $store_id ? "WHERE o.store_id = ?" : "";
$params = $store_id ? [$store_id] : [];
$stmt = $db->query("SELECT o.*, c.contact_person, c.phone as customer_phone
                    FROM orders o 
                    LEFT JOIN customers c ON o.customer_id = c.id
                    $where_clause
                    ORDER BY o.created_at DESC 
                    LIMIT 10", $params);
$recent_orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satış Paneli - Alumpro.Az</title>
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
            <?php include '../includes/sales-sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-speedometer2 text-primary"></i> 
                        Satış Paneli
                        <?php if ($user['store_name']): ?>
                            <small class="text-muted">- <?= htmlspecialchars($user['store_name']) ?></small>
                        <?php endif; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="new-order.php" class="btn btn-success">
                                <i class="bi bi-plus-circle"></i> Yeni Sifariş
                            </a>
                            <a href="customers.php" class="btn btn-outline-primary">
                                <i class="bi bi-people"></i> Müştərilər
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
                                    <i class="bi bi-calendar-day"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="text-muted small">Bu gün</div>
                                    <div class="h4 mb-0"><?= $stats['today_orders'] ?? 0 ?></div>
                                    <small class="text-muted">sifariş</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="dashboard-card">
                            <div class="d-flex align-items-center">
                                <div class="card-icon bg-gradient-secondary">
                                    <i class="bi bi-calendar-week"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="text-muted small">Bu həftə</div>
                                    <div class="h4 mb-0"><?= $stats['week_orders'] ?? 0 ?></div>
                                    <small class="text-muted">sifariş</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="dashboard-card">
                            <div class="d-flex align-items-center">
                                <div class="card-icon" style="background: linear-gradient(135deg, #28a745, #20c997);">
                                    <i class="bi bi-calendar-month"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="text-muted small">Bu ay</div>
                                    <div class="h4 mb-0"><?= $stats['month_orders'] ?? 0 ?></div>
                                    <small class="text-muted">sifariş</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="dashboard-card">
                            <div class="d-flex align-items-center">
                                <div class="card-icon" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                                    <i class="bi bi-currency-dollar"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="text-muted small">Aylıq satış</div>
                                    <div class="h4 mb-0"><?= formatCurrency($stats['month_sales'] ?? 0) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Orders -->
                    <div class="col-lg-8 mb-4">
                        <div class="card">
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
                                                <th>Müştəri</th>
                                                <th>Məbləğ</th>
                                                <th>Status</th>
                                                <th>Tarix</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recent_orders)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center py-4">
                                                        <i class="bi bi-inbox display-6 text-muted"></i>
                                                        <p class="text-muted mt-2">Hələ sifariş yoxdur</p>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($recent_orders as $order): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?= htmlspecialchars($order['order_number']) ?></strong>
                                                        </td>
                                                        <td>
                                                            <?= htmlspecialchars($order['contact_person']) ?>
                                                            <?php if ($order['customer_phone']): ?>
                                                                <br>
                                                                <small class="text-muted"><?= $order['customer_phone'] ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= formatCurrency($order['total_amount']) ?></td>
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
                                                        <td><?= date('d.m.Y', strtotime($order['order_date'])) ?></td>
                                                        <td>
                                                            <a href="order-details.php?id=<?= $order['id'] ?>" 
                                                               class="btn btn-sm btn-outline-primary">
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
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-lightning"></i> Tez Əməliyyatlar
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="new-order.php" class="btn btn-success">
                                        <i class="bi bi-plus-circle"></i> Yeni Sifariş
                                    </a>
                                    <a href="customers.php?action=add" class="btn btn-outline-primary">
                                        <i class="bi bi-person-plus"></i> Yeni Müştəri
                                    </a>
                                    <?php if ($user['warehouse_access']): ?>
                                        <a href="../warehouse/products.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-box"></i> Anbar
                                        </a>
                                    <?php endif; ?>
                                    <a href="reports.php" class="btn btn-outline-info">
                                        <i class="bi bi-graph-up"></i> Hesabatlar
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Low Stock Alert -->
                        <?php if (!empty($low_stock) && $user['warehouse_access']): ?>
                            <div class="card mt-3">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0">
                                        <i class="bi bi-exclamation-triangle"></i> Az Qalan Məhsullar
                                    </h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="list-group list-group-flush">
                                        <?php foreach (array_slice($low_stock, 0, 5) as $item): ?>
                                            <div class="list-group-item d-flex justify-content-between">
                                                <div>
                                                    <small class="text-muted"><?= ucfirst($item['type']) ?></small>
                                                    <div><?= htmlspecialchars($item['name']) ?></div>
                                                </div>
                                                <span class="badge bg-warning"><?= $item['stock'] ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (count($low_stock) > 5): ?>
                                        <div class="card-footer text-center">
                                            <a href="../warehouse/products.php?filter=low_stock" class="text-decoration-none">
                                                Daha çox... (<?= count($low_stock) - 5 ?>)
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Customers and Products -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-star"></i> Ən Çox Sifariş Verən Müştərilər
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($top_customers)): ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-people display-6 text-muted"></i>
                                        <p class="text-muted mt-2">Məlumat yoxdur</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($top_customers as $customer): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?= htmlspecialchars($customer['contact_person']) ?></h6>
                                                    <small><?= $customer['order_count'] ?> sifariş</small>
                                                </div>
                                                <p class="mb-1">
                                                    <small class="text-muted"><?= $customer['phone'] ?></small>
                                                </p>
                                                <small class="text-success"><?= formatCurrency($customer['total_spent']) ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-trophy"></i> Ən Çox Satılan Profillər
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($top_products)): ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-box display-6 text-muted"></i>
                                        <p class="text-muted mt-2">Məlumat yoxdur</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($top_products as $product): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?= htmlspecialchars($product['profile_type']) ?></h6>
                                                    <small><?= $product['order_count'] ?> sifariş</small>
                                                </div>
                                                <small class="text-success"><?= formatCurrency($product['total_sales']) ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/sales.js"></script>
</body>
</html>