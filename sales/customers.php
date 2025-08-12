<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireSales();

$user_id = SessionManager::getUserId();
$store_id = SessionManager::getStoreId();
$db = new Database();

// Pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Search
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.contact_person LIKE ? OR c.phone LIKE ? OR c.company_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_params = $params;
$stmt = $db->query("SELECT COUNT(*) as total FROM customers c $where_clause", $count_params);
$total_customers = $stmt->fetch()['total'];
$total_pages = ceil($total_customers / $limit);

// Get customers
$params[] = $limit;
$params[] = $offset;
$stmt = $db->query("SELECT c.*, 
                           COUNT(o.id) as total_orders, 
                           COALESCE(SUM(o.total_amount), 0) as total_spent,
                           MAX(o.order_date) as last_order_date
                    FROM customers c 
                    LEFT JOIN orders o ON c.id = o.customer_id
                    $where_clause
                    GROUP BY c.id
                    ORDER BY c.contact_person
                    LIMIT ? OFFSET ?", $params);
$customers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Müştərilər - Alumpro.Az</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
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
                        <i class="bi bi-people text-primary"></i> Müştərilər
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                                <i class="bi bi-person-plus"></i> Yeni Müştəri
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Search -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="search" 
                                       value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Ad, telefon və ya şirkət adı ilə axtarın...">
                            </div>
                            <div class="col-md-4">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Axtar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Customers Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            Müştərilər (<?= $total_customers ?> nəticə)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Müştəri</th>
                                        <th>Əlaqə</th>
                                        <th>Şirkət</th>
                                        <th>Sifarişlər</th>
                                        <th>Ümumi Xərc</th>
                                        <th>Son Sifariş</th>
                                        <th>Əməliyyat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($customers)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <i class="bi bi-inbox display-4 text-muted"></i>
                                                <p class="text-muted mt-2">Müştəri tapılmadı</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($customers as $customer): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($customer['contact_person']) ?></strong>
                                                </td>
                                                <td>
                                                    <a href="tel:<?= $customer['phone'] ?>" class="text-decoration-none">
                                                        <i class="bi bi-telephone text-success"></i> <?= $customer['phone'] ?>
                                                    </a>
                                                    <?php if ($customer['email']): ?>
                                                        <br>
                                                        <a href="mailto:<?= $customer['email'] ?>" class="text-decoration-none text-muted">
                                                            <i class="bi bi-envelope"></i> <?= $customer['email'] ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($customer['company_name'] ?: '-') ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?= $customer['total_orders'] ?></span>
                                                </td>
                                                <td>
                                                    <strong class="text-success"><?= formatCurrency($customer['total_spent']) ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($customer['last_order_date']): ?>
                                                        <?= date('d.m.Y', strtotime($customer['last_order_date'])) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="new-order.php?customer=<?= $customer['id'] ?>" 
                                                           class="btn btn-sm btn-success" title="Yeni Sifariş">
                                                            <i class="bi bi-plus-circle"></i>
                                                        </a>
                                                        <a href="customer-details.php?id=<?= $customer['id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary" title="Təfərrüat">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="tel:<?= $customer['phone'] ?>" 
                                                           class="btn btn-sm btn-outline-success" title="Zəng">
                                                            <i class="bi bi-telephone"></i>
                                                        </a>
                                                        <a href="https://wa.me/<?= str_replace(['+', ' '], '', $customer['phone']) ?>" 
                                                           target="_blank" class="btn btn-sm btn-outline-success" title="WhatsApp">
                                                            <i class="bi bi-whatsapp"></i>
                                                        </a>
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

    <!-- New Customer Modal -->
    <div class="modal fade" id="newCustomerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-person-plus"></i> Yeni Müştəri Əlavə Et
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="newCustomerForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_customer_name" class="form-label">Ad və Soyad *</label>
                                <input type="text" class="form-control" id="new_customer_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="new_customer_phone" class="form-label">Telefon *</label>
                                <input type="tel" class="form-control" id="new_customer_phone" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_customer_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="new_customer_email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="new_customer_company" class="form-label">Şirkət Adı</label>
                                <input type="text" class="form-control" id="new_customer_company">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="new_customer_address" class="form-label">Ünvan</label>
                            <textarea class="form-control" id="new_customer_address" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Bağla</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check"></i> Müştəri Əlavə Et
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        // New customer form
        document.getElementById('newCustomerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'add_customer');
            formData.append('contact_person', document.getElementById('new_customer_name').value);
            formData.append('phone', document.getElementById('new_customer_phone').value);
            formData.append('email', document.getElementById('new_customer_email').value);
            formData.append('company_name', document.getElementById('new_customer_company').value);
            formData.append('address', document.getElementById('new_customer_address').value);
            
            fetch('../api/customers.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal and reset form
                    const modal = bootstrap.Modal.getInstance(document.getElementById('newCustomerModal'));
                    modal.hide();
                    document.getElementById('newCustomerForm').reset();
                    
                    // Reload page to show new customer
                    window.location.reload();
                } else {
                    alert('Xəta: ' + data.message);
                }
            })
            .catch(error => {
                alert('Xəta baş verdi: ' + error.message);
            });
        });
        
        // Phone number formatting
        document.getElementById('new_customer_phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.startsWith('994')) {
                value = '+' + value;
            } else if (value.startsWith('0')) {
                value = '+994' + value.substring(1);
            } else if (!value.startsWith('+994')) {
                value = '+994' + value;
            }
            e.target.value = value;
        });
    </script>
</body>
</html>