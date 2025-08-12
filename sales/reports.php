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

// Date filters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$report_type = $_GET['report_type'] ?? 'sales';

// Build where clause for store filtering
$store_where = "";
$store_params = [];
if ($store_id && $user_role === 'sales') {
    $store_where = "AND o.store_id = ?";
    $store_params[] = $store_id;
}

// Get sales data
$sales_data = [];
if ($report_type === 'sales') {
    $stmt = $db->query("SELECT 
                        DATE(o.order_date) as order_date,
                        COUNT(o.id) as order_count,
                        SUM(o.total_amount) as total_sales,
                        AVG(o.total_amount) as average_order
                        FROM orders o 
                        WHERE o.order_date BETWEEN ? AND ? 
                        AND o.status != 'cancelled'
                        $store_where
                        GROUP BY DATE(o.order_date)
                        ORDER BY order_date", 
                        array_merge([$date_from, $date_to], $store_params));
    $sales_data = $stmt->fetchAll();
}

// Get product data
$product_data = [];
if ($report_type === 'products') {
    $stmt = $db->query("SELECT 
                        oi.profile_type,
                        COUNT(*) as order_count,
                        SUM(oi.quantity) as total_quantity,
                        SUM(oi.total_price) as total_sales
                        FROM order_items oi
                        JOIN orders o ON oi.order_id = o.id
                        WHERE o.order_date BETWEEN ? AND ?
                        AND o.status != 'cancelled'
                        AND oi.profile_type IS NOT NULL
                        $store_where
                        GROUP BY oi.profile_type
                        ORDER BY total_sales DESC", 
                        array_merge([$date_from, $date_to], $store_params));
    $product_data = $stmt->fetchAll();
}

// Get customer data
$customer_data = [];
if ($report_type === 'customers') {
    $stmt = $db->query("SELECT 
                        c.contact_person,
                        c.phone,
                        c.company_name,
                        COUNT(o.id) as order_count,
                        SUM(o.total_amount) as total_spent,
                        MAX(o.order_date) as last_order
                        FROM customers c
                        JOIN orders o ON c.id = o.customer_id
                        WHERE o.order_date BETWEEN ? AND ?
                        AND o.status != 'cancelled'
                        $store_where
                        GROUP BY c.id
                        ORDER BY total_spent DESC", 
                        array_merge([$date_from, $date_to], $store_params));
    $customer_data = $stmt->fetchAll();
}

// Calculate summary statistics
$summary = [];
$stmt = $db->query("SELECT 
                    COUNT(o.id) as total_orders,
                    SUM(o.total_amount) as total_revenue,
                    AVG(o.total_amount) as average_order,
                    COUNT(DISTINCT o.customer_id) as unique_customers
                    FROM orders o 
                    WHERE o.order_date BETWEEN ? AND ? 
                    AND o.status != 'cancelled'
                    $store_where", 
                    array_merge([$date_from, $date_to], $store_params));
$summary = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hesabatlar - Alumpro.Az</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <i class="bi bi-graph-up text-primary"></i> Hesabatlar
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button onclick="exportReport()" class="btn btn-outline-success">
                                <i class="bi bi-download"></i> Excel Export
                            </button>
                            <button onclick="printReport()" class="btn btn-outline-secondary">
                                <i class="bi bi-printer"></i> Çap
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">Başlanğıc Tarix</label>
                                <input type="date" class="form-control" name="date_from" 
                                       value="<?= htmlspecialchars($date_from) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">Son Tarix</label>
                                <input type="date" class="form-control" name="date_to" 
                                       value="<?= htmlspecialchars($date_to) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="report_type" class="form-label">Hesabat Növü</label>
                                <select class="form-select" name="report_type">
                                    <option value="sales" <?= $report_type === 'sales' ? 'selected' : '' ?>>Satış Hesabatı</option>
                                    <option value="products" <?= $report_type === 'products' ? 'selected' : '' ?>>Məhsul Hesabatı</option>
                                    <option value="customers" <?= $report_type === 'customers' ? 'selected' : '' ?>>Müştəri Hesabatı</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Hesabat Yarat
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <i class="bi bi-box text-primary display-6"></i>
                                <h4 class="mt-2"><?= $summary['total_orders'] ?: 0 ?></h4>
                                <p class="text-muted mb-0">Ümumi Sifariş</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <i class="bi bi-currency-dollar text-success display-6"></i>
                                <h4 class="mt-2"><?= formatCurrency($summary['total_revenue'] ?: 0) ?></h4>
                                <p class="text-muted mb-0">Ümumi Gəlir</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-info">
                            <div class="card-body text-center">
                                <i class="bi bi-graph-up text-info display-6"></i>
                                <h4 class="mt-2"><?= formatCurrency($summary['average_order'] ?: 0) ?></h4>
                                <p class="text-muted mb-0">Orta Sifariş</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <i class="bi bi-people text-warning display-6"></i>
                                <h4 class="mt-2"><?= $summary['unique_customers'] ?: 0 ?></h4>
                                <p class="text-muted mb-0">Unikal Müştəri</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chart Section -->
                <?php if ($report_type === 'sales' && !empty($sales_data)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-bar-chart"></i> Satış Qrafiki
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="salesChart" height="100"></canvas>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Data Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-table"></i> 
                            <?php
                            $report_titles = [
                                'sales' => 'Gündəlik Satış Məlumatları',
                                'products' => 'Məhsul Satış Məlumatları',
                                'customers' => 'Müştəri Satış Məlumatları'
                            ];
                            echo $report_titles[$report_type];
                            ?>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <?php if ($report_type === 'sales'): ?>
                                <table class="table table-striped mb-0" id="reportTable">
                                    <thead>
                                        <tr>
                                            <th>Tarix</th>
                                            <th>Sifariş Sayı</th>
                                            <th>Ümumi Satış</th>
                                            <th>Orta Sifariş</th>
                                        </tr>
                                    </thead>
                                                                        <tbody>
                                        <?php foreach ($sales_data as $row): ?>
                                            <tr>
                                                <td><?= date('d.m.Y', strtotime($row['order_date'])) ?></td>
                                                <td><?= $row['order_count'] ?></td>
                                                <td><?= formatCurrency($row['total_sales']) ?></td>
                                                <td><?= formatCurrency($row['average_order']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                            <?php elseif ($report_type === 'products'): ?>
                                <table class="table table-striped mb-0" id="reportTable">
                                    <thead>
                                        <tr>
                                            <th>Profil Tipi</th>
                                            <th>Sifariş Sayı</th>
                                            <th>Ümumi Miqdar</th>
                                            <th>Ümumi Satış</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($product_data as $row): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['profile_type']) ?></td>
                                                <td><?= $row['order_count'] ?></td>
                                                <td><?= $row['total_quantity'] ?></td>
                                                <td><?= formatCurrency($row['total_sales']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                            <?php elseif ($report_type === 'customers'): ?>
                                <table class="table table-striped mb-0" id="reportTable">
                                    <thead>
                                        <tr>
                                            <th>Müştəri</th>
                                            <th>Telefon</th>
                                            <th>Şirkət</th>
                                            <th>Sifariş Sayı</th>
                                            <th>Ümumi Xərc</th>
                                            <th>Son Sifariş</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($customer_data as $row): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['contact_person']) ?></td>
                                                <td><?= htmlspecialchars($row['phone']) ?></td>
                                                <td><?= htmlspecialchars($row['company_name'] ?: '-') ?></td>
                                                <td><?= $row['order_count'] ?></td>
                                                <td><?= formatCurrency($row['total_spent']) ?></td>
                                                <td><?= date('d.m.Y', strtotime($row['last_order'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        // Sales chart
        <?php if ($report_type === 'sales' && !empty($sales_data)): ?>
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesData = <?= json_encode($sales_data) ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: salesData.map(item => {
                    const date = new Date(item.order_date);
                    return date.toLocaleDateString('az-AZ');
                }),
                datasets: [{
                    label: 'Gündəlik Satış (AZN)',
                    data: salesData.map(item => parseFloat(item.total_sales)),
                    borderColor: 'rgb(32, 178, 170)',
                    backgroundColor: 'rgba(32, 178, 170, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString() + ' AZN';
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
        function exportReport() {
            const table = document.getElementById('reportTable');
            const csv = tableToCSV(table);
            downloadCSV(csv, 'hesabat_<?= date('Y-m-d') ?>.csv');
        }
        
        function tableToCSV(table) {
            const rows = table.querySelectorAll('tr');
            const csv = [];
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td, th');
                const rowData = Array.from(cells).map(cell => {
                    return '"' + cell.textContent.trim().replace(/"/g, '""') + '"';
                });
                csv.push(rowData.join(','));
            });
            
            return csv.join('\n');
        }
        
        function downloadCSV(csv, filename) {
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }
        
        function printReport() {
            window.print();
        }
    </script>
</body>
</html>
                                    