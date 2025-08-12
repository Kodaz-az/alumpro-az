<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireAdmin();

$user_id = SessionManager::getUserId();
$db = new Database();

// Get comprehensive statistics
$stats = [];

// Total counts
$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'customer'");
$stats['total_customers'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'sales'");
$stats['total_sales_people'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM orders");
$stats['total_orders'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM products");
$stats['total_products'] = $stmt->fetch()['total'];

// Financial stats
$stmt = $db->query("SELECT SUM(total_amount) as total FROM orders WHERE status = 'completed'");
$stats['total_revenue'] = $stmt->fetch()['total'] ?: 0;

$stmt = $db->query("SELECT SUM(total_amount) as total FROM orders WHERE status = 'completed' AND MONTH(order_date) = MONTH(NOW()) AND YEAR(order_date) = YEAR(NOW())");
$stats['monthly_revenue'] = $stmt->fetch()['total'] ?: 0;

// Today's stats
$stmt = $db->query("SELECT COUNT(*) as total FROM orders WHERE DATE(created_at) = CURDATE()");
$stats['today_orders'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE DATE(created_at) = CURDATE()");
$stats['today_registrations'] = $stmt->fetch()['total'];

// Recent activities
$stmt = $db->query("SELECT o.*, c.contact_person, u.full_name as sales_person, s.name as store_name
                    FROM orders o 
                    LEFT JOIN customers c ON o.customer_id = c.id
                    LEFT JOIN users u ON o.sales_person_id = u.id
                    LEFT JOIN stores s ON o.store_id = s.id
                    ORDER BY o.created_at DESC 
                    LIMIT 10");
$recent_orders = $stmt->fetchAll();

// Top customers
$stmt = $db->query("SELECT c.contact_person, c.phone, COUNT(o.id) as order_count, SUM(o.total_amount) as total_spent
                    FROM customers c 
                    LEFT JOIN orders o ON c.id = o.customer_id
                    GROUP BY c.id 
                    ORDER BY total_spent DESC 
                    LIMIT 5");
$top_customers = $stmt->fetchAll();

// Store performance
$stmt = $db->query("SELECT s.name, COUNT(o.id) as order_count, SUM(o.total_amount) as revenue
                    FROM stores s 
                    LEFT JOIN orders o ON s.id = o.store_id AND MONTH(o.order_date) = MONTH(NOW())
                    GROUP BY s.id 
                    ORDER BY revenue DESC");
$store_performance = $stmt->fetchAll();

// Low stock alerts
$low_stock = Utils::getLowStockProducts($db, 10);
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Alumpro.Az</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/admin-sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-speedometer2 text-primary"></i> Admin Panel
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-outline-secondary" onclick="window.print()">
                                <i class="bi bi-printer"></i> Çap et
                            </button>
                            <a href="reports.php" class="btn btn-outline-primary">
                                <i class="bi bi-graph-up"></i> Hesabatlar
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Key Statistics -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="dashboard-card">
                            <div class="d-flex align-items-center">
                                <div class="card-icon bg-gradient-primary">
                                    <i class="bi bi-people"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="text-muted small">Ümumi Müştərilər</div>
                                    <div class="h4 mb-0"><?= $stats['total_customers'] ?></div>
                                    <small class="text-success">+<?= $stats['today_registrations'] ?> bu gün</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="dashboard-card">
                            <div class="d-flex align-items-center">
                                <div class="card-icon bg-gradient-secondary">
                                    <i class="bi bi-box"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="text-muted small">Ümumi Sifarişlər</div>
                                    <div class="h4 mb-0"><?= $stats['total_orders'] ?></div>
                                    <small class="text-info">+<?= $stats['today_orders'] ?> bu gün</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="dashboard-card">
                            <div class="d-flex align-items-center">
                                <div class="card-icon" style="background: linear-gradient(135deg, #28a745, #20c997);">
                                    <i class="bi bi-currency-dollar"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="text-muted small">Aylıq Gəlir</div>
                                    <div class="h4 mb-0"><?= formatCurrency($stats['monthly_revenue']) ?></div>
                                    <small class="text-success">Bu ay</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="dashboard-card">
                            <div class="d-flex align-items-center">
                                <div class="card-icon" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                                    <i class="bi bi-trophy"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="text-muted small">Ümumi Gəlir</div>
                                    <div class="h4 mb-0"><?= formatCurrency($stats['total_revenue']) ?></div>
                                    <small class="text-warning">Bütün zamanlar</small>
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
                                                <th>Satıcı</th>
                                                <th>Mağaza</th>
                                                <th>Məbləğ</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_orders as $order): ?>
                                                <tr>
                                                    <td>
                                                        <a href="order-details.php?id=<?= $order['id'] ?>" class="text-decoration-none">
                                                            <strong><?= htmlspecialchars($order['order_number']) ?></strong>
                                                        </a>
                                                    </td>
                                                    <td><?= htmlspecialchars($order['contact_person']) ?></td>
                                                    <td><?= htmlspecialchars($order['sales_person']) ?></td>
                                                    <td><?= htmlspecialchars($order['store_name']) ?></td>
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
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions & Alerts -->
                    <div class="col-lg-4 mb-4">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-lightning"></i> Tez Əməliyyatlar
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="users.php?action=add" class="btn btn-success">
                                        <i class="bi bi-person-plus"></i> Yeni İstifadəçi
                                    </a>
                                    <a href="../warehouse/products.php" class="btn btn-outline-primary">
                                        <i class="bi bi-box"></i> Anbar İdarəsi
                                    </a>
                                    <a href="news.php" class="btn btn-outline-info">
                                        <i class="bi bi-newspaper"></i> Xəbərlər
                                    </a>
                                    <a href="settings.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-gear"></i> Sistem Ayarları
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Low Stock Alert -->
                        <?php if (!empty($low_stock)): ?>
                            <div class="card">
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
                                                    <div class="small"><?= htmlspecialchars($item['name']) ?></div>
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

                <!-- Analytics Charts -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-graph-up"></i> Mağaza Performansı (Bu ay)
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="storePerformanceChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-star"></i> Top Müştərilər
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
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        // Store Performance Chart
        const storeCtx = document.getElementById('storePerformanceChart').getContext('2d');
        const storeData = <?= json_encode($store_performance) ?>;
        
        new Chart(storeCtx, {
            type: 'bar',
            data: {
                labels: storeData.map(store => store.name),
                datasets: [{
                    label: 'Gəlir (AZN)',
                    data: storeData.map(store => store.revenue),
                    backgroundColor: [
                        'rgba(32, 178, 170, 0.8)',
                        'rgba(70, 130, 180, 0.8)',
                        'rgba(40, 167, 69, 0.8)'
                    ],
                    borderColor: [
                        'rgba(32, 178, 170, 1)',
                        'rgba(70, 130, 180, 1)',
                        'rgba(40, 167, 69, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toFixed(2) + ' AZN';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>
</html>