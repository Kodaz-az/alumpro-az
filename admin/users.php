<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireAdmin();

$db = new Database();
$message = '';
$error = '';

// Handle user actions
if ($_POST) {
    if ($_POST['action'] === 'add_user') {
        try {
            $username = trim($_POST['username']);
            $phone = Utils::formatPhone(trim($_POST['phone']));
            $email = trim($_POST['email']);
            $full_name = trim($_POST['full_name']);
            $role = $_POST['role'];
            $store_id = $_POST['store_id'] ?: null;
            $warehouse_access = isset($_POST['warehouse_access']) ? 1 : 0;
            $password = $_POST['password'];
            
            if (empty($username) || empty($phone) || empty($full_name) || empty($password)) {
                throw new Exception('Bütün məcburi sahələr doldurulmalıdır');
            }
            
            // Check if username or phone exists
            $stmt = $db->query("SELECT id FROM users WHERE username = ? OR phone = ?", [$username, $phone]);
            if ($stmt->fetch()) {
                throw new Exception('Bu istifadəçi adı və ya telefon nömrəsi artıq mövcuddur');
            }
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->query("INSERT INTO users (username, phone, email, password, full_name, role, store_id, warehouse_access, is_verified, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'active')", 
                [$username, $phone, $email, $hashed_password, $full_name, $role, $store_id, $warehouse_access]);
            
            $message = 'İstifadəçi uğurla əlavə edildi';
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($_POST['action'] === 'update_user') {
        try {
            $user_id = $_POST['user_id'];
            $email = trim($_POST['email']);
            $full_name = trim($_POST['full_name']);
            $role = $_POST['role'];
            $store_id = $_POST['store_id'] ?: null;
            $warehouse_access = isset($_POST['warehouse_access']) ? 1 : 0;
            $status = $_POST['status'];
            
            $stmt = $db->query("UPDATE users SET email = ?, full_name = ?, role = ?, store_id = ?, warehouse_access = ?, status = ? WHERE id = ?", 
                [$email, $full_name, $role, $store_id, $warehouse_access, $status, $user_id]);
            
            // Update password if provided
            if (!empty($_POST['new_password'])) {
                $hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $db->query("UPDATE users SET password = ? WHERE id = ?", [$hashed_password, $user_id]);
            }
            
            $message = 'İstifadəçi məlumatları yeniləndi';
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get users with pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(full_name LIKE ? OR username LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_params = $params;
$stmt = $db->query("SELECT COUNT(*) as total FROM users $where_clause", $count_params);
$total_users = $stmt->fetch()['total'];
$total_pages = ceil($total_users / $limit);

// Get users
$params[] = $limit;
$params[] = $offset;
$stmt = $db->query("SELECT u.*, s.name as store_name FROM users u 
                    LEFT JOIN stores s ON u.store_id = s.id 
                    $where_clause
                    ORDER BY u.created_at DESC 
                    LIMIT ? OFFSET ?", $params);
$users = $stmt->fetchAll();

// Get stores for dropdown
$stmt = $db->query("SELECT * FROM stores ORDER BY name");
$stores = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İstifadəçi İdarəsi - Alumpro.Az</title>
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
            <?php include '../includes/admin-sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-people text-primary"></i> İstifadəçi İdarəsi
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="bi bi-person-plus"></i> Yeni İstifadəçi
                            </button>
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
                                       placeholder="Ad, istifadəçi adı, telefon və ya email ilə axtarın...">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="role">
                                    <option value="">Bütün rollar</option>
                                    <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="sales" <?= $role_filter === 'sales' ? 'selected' : '' ?>>Satış Meneceri</option>
                                    <option value="customer" <?= $role_filter === 'customer' ? 'selected' : '' ?>>Müştəri</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Axtar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            İstifadəçilər (<?= $total_users ?> nəticə)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Ad</th>
                                        <th>İstifadəçi adı</th>
                                        <th>Telefon</th>
                                        <th>Email</th>
                                        <th>Rol</th>
                                        <th>Mağaza</th>
                                        <th>Status</th>
                                        <th>Qeydiyyat</th>
                                        <th>Əməliyyat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">
                                                <i class="bi bi-inbox display-4 text-muted"></i>
                                                <p class="text-muted mt-2">İstifadəçi tapılmadı</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($user['profile_image']): ?>
                                                            <img src="<?= SITE_URL . '/' . PROFILE_IMAGES_PATH . $user['profile_image'] ?>" 
                                                                 class="rounded-circle me-2" width="32" height="32" 
                                                                 style="object-fit: cover;" alt="Profile">
                                                        <?php else: ?>
                                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-2" 
                                                                 style="width: 32px; height: 32px;">
                                                                <i class="bi bi-person text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($user['username']) ?></td>
                                                <td>
                                                    <a href="tel:<?= $user['phone'] ?>" class="text-decoration-none">
                                                        <?= $user['phone'] ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php if ($user['email']): ?>
                                                        <a href="mailto:<?= $user['email'] ?>" class="text-decoration-none">
                                                            <?= htmlspecialchars($user['email']) ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $role_colors = [
                                                        'admin' => 'danger',
                                                        'sales' => 'primary',
                                                        'customer' => 'success'
                                                    ];
                                                    $role_texts = [
                                                        'admin' => 'Admin',
                                                        'sales' => 'Satış',
                                                        'customer' => 'Müştəri'
                                                    ];
                                                    ?>
                                                    <span class="badge bg-<?= $role_colors[$user['role']] ?>">
                                                        <?= $role_texts[$user['role']] ?>
                                                    </span>
                                                    <?php if ($user['warehouse_access'] && $user['role'] === 'sales'): ?>
                                                        <br><small class="text-info">Anbar Girişi</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($user['store_name'] ?: '-') ?>
                                                </td>
                                                <td>
                                                    <?php if ($user['status'] === 'active'): ?>
                                                        <span class="badge bg-success">Aktiv</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Qeyri-aktiv</span>
                                                    <?php endif; ?>
                                                    <?php if (!$user['is_verified']): ?>
                                                        <br><small class="text-warning">Təsdiqlənməyib</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= date('d.m.Y', strtotime($user['created_at'])) ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <?php if ($user['id'] !== SessionManager::getUserId()): ?>
                                                            <button class="btn btn-sm btn-outline-danger" 
                                                                    onclick="toggleUserStatus(<?= $user['id'] ?>, '<?= $user['status'] ?>')">
                                                                <i class="bi bi-<?= $user['status'] === 'active' ? 'x' : 'check' ?>-circle"></i>
                                                            </button>
                                                        <?php endif; ?>
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

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-person-plus"></i> Yeni İstifadəçi Əlavə Et
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_user">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">İstifadəçi adı *</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Telefon *</label>
                                <input type="tel" class="form-control" name="phone" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Ad və Soyad *</label>
                                <input type="text" class="form-control" name="full_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Rol *</label>
                                <select class="form-select" name="role" required onchange="toggleStoreField(this)">
                                    <option value="">Seçin...</option>
                                    <option value="admin">Admin</option>
                                    <option value="sales">Satış Meneceri</option>
                                    <option value="customer">Müştəri</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3" id="storeField" style="display: none;">
                                <label for="store_id" class="form-label">Mağaza</label>
                                <select class="form-select" name="store_id">
                                    <option value="">Seçin...</option>
                                    <?php foreach ($stores as $store): ?>
                                        <option value="<?= $store['id'] ?>"><?= htmlspecialchars($store['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Şifrə *</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="warehouse_access" id="warehouse_access">
                                    <label class="form-check-label" for="warehouse_access">
                                        Anbar girişi icazəsi
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Bağla</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check"></i> İstifadəçi Əlavə Et
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-gradient-secondary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil"></i> İstifadəçi Redaktə Et
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editUserForm">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">İstifadəçi adı</label>
                                <input type="text" class="form-control" id="edit_username" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Telefon</label>
                                <input type="tel" class="form-control" id="edit_phone" readonly>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_full_name" class="form-label">Ad və Soyad *</label>
                                <input type="text" class="form-control" name="full_name" id="edit_full_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_email" class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="edit_email">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="edit_role" class="form-label">Rol *</label>
                                <select class="form-select" name="role" id="edit_role" required onchange="toggleEditStoreField(this)">
                                    <option value="admin">Admin</option>
                                    <option value="sales">Satış Meneceri</option>
                                    <option value="customer">Müştəri</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3" id="editStoreField">
                                <label for="edit_store_id" class="form-label">Mağaza</label>
                                <select class="form-select" name="store_id" id="edit_store_id">
                                    <option value="">Seçin...</option>
                                    <?php foreach ($stores as $store): ?>
                                        <option value="<?= $store['id'] ?>"><?= htmlspecialchars($store['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_status" class="form-label">Status *</label>
                                <select class="form-select" name="status" id="edit_status" required>
                                    <option value="active">Aktiv</option>
                                    <option value="inactive">Qeyri-aktiv</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_new_password" class="form-label">Yeni Şifrə</label>
                                <input type="password" class="form-control" name="new_password" id="edit_new_password">
                                <small class="form-text text-muted">Boş buraxın əgər dəyişmək istəmirsinizsə</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="warehouse_access" id="edit_warehouse_access">
                                    <label class="form-check-label" for="edit_warehouse_access">
                                        Anbar girişi icazəsi
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Bağla</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check"></i> Dəyişiklikləri Saxla
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function toggleStoreField(select) {
            const storeField = document.getElementById('storeField');
            if (select.value === 'sales') {
                storeField.style.display = 'block';
            } else {
                storeField.style.display = 'none';
            }
        }
        
        function toggleEditStoreField(select) {
            const storeField = document.getElementById('editStoreField');
            if (select.value === 'sales') {
                storeField.style.display = 'block';
            } else {
                storeField.style.display = 'none';
            }
        }
        
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_phone').value = user.phone;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_store_id').value = user.store_id || '';
            document.getElementById('edit_status').value = user.status;
            document.getElementById('edit_warehouse_access').checked = user.warehouse_access == 1;
            
            toggleEditStoreField(document.getElementById('edit_role'));
            
            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        }
        
        function toggleUserStatus(userId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = newStatus === 'active' ? 'aktivləşdirmək' : 'deaktivləşdirmək';
            
            if (confirm(`Bu istifadəçini ${action} istəyirsiniz?`)) {
                const formData = new FormData();
                formData.append('action', 'update_user');
                formData.append('user_id', userId);
                formData.append('status', newStatus);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                }).then(() => {
                    window.location.reload();
                });
            }
        }
        
        // Phone number formatting
        document.querySelector('input[name="phone"]').addEventListener('input', function(e) {
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