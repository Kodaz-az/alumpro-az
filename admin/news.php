<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireAdmin();

$db = new Database();
$message = '';
$error = '';

// Handle news operations
if ($_POST) {
    if ($_POST['action'] === 'add_news') {
        try {
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($title) || empty($content)) {
                throw new Exception('Başlıq və məzmun mütləqdir');
            }
            
            // Handle image upload
            $image = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $image = Utils::uploadFile($_FILES['image'], GALLERY_IMAGES_PATH, ['jpg', 'jpeg', 'png', 'gif']);
                if (!$image) {
                    throw new Exception('Şəkil yüklənməsi uğursuz oldu');
                }
            }
            
            $stmt = $db->query("INSERT INTO news (title, content, image, is_active) VALUES (?, ?, ?, ?)", 
                [$title, $content, $image, $is_active]);
            
            $message = 'Xəbər uğurla əlavə edildi';
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($_POST['action'] === 'toggle_status') {
        try {
            $news_id = $_POST['news_id'];
            $new_status = $_POST['status'] === 'active' ? 1 : 0;
            
            $stmt = $db->query("UPDATE news SET is_active = ? WHERE id = ?", [$new_status, $news_id]);
            
            $message = 'Xəbər statusu yeniləndi';
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get news with pagination
$page = $_GET['page'] ?? 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$stmt = $db->query("SELECT COUNT(*) as total FROM news");
$total_news = $stmt->fetch()['total'];
$total_pages = ceil($total_news / $limit);

$stmt = $db->query("SELECT * FROM news ORDER BY created_at DESC LIMIT ? OFFSET ?", [$limit, $offset]);
$news_items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xəbər İdarəsi - Alumpro.Az</title>
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
                        <i class="bi bi-newspaper text-primary"></i> Xəbər İdarəsi
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addNewsModal">
                                <i class="bi bi-plus-circle"></i> Yeni Xəbər
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

                <!-- News List -->
                <div class="row">
                    <?php foreach ($news_items as $news): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <?php if ($news['image']): ?>
                                    <img src="<?= SITE_URL . '/' . GALLERY_IMAGES_PATH . $news['image'] ?>" 
                                         class="card-img-top" style="height: 200px; object-fit: cover;" alt="News">
                                <?php endif; ?>
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($news['title']) ?></h5>
                                    <p class="card-text">
                                        <?= nl2br(htmlspecialchars(substr($news['content'], 0, 150))) ?>
                                        <?= strlen($news['content']) > 150 ? '...' : '' ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-<?= $news['is_active'] ? 'success' : 'secondary' ?>">
                                            <?= $news['is_active'] ? 'Aktiv' : 'Qeyri-aktiv' ?>
                                        </span>
                                        <small class="text-muted"><?= date('d.m.Y', strtotime($news['created_at'])) ?></small>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div class="btn-group w-100" role="group">
                                        <button class="btn btn-outline-primary btn-sm" onclick="editNews(<?= htmlspecialchars(json_encode($news)) ?>)">
                                            <i class="bi bi-pencil"></i> Redaktə
                                        </button>
                                        <button class="btn btn-outline-<?= $news['is_active'] ? 'warning' : 'success' ?> btn-sm" 
                                                onclick="toggleNewsStatus(<?= $news['id'] ?>, '<?= $news['is_active'] ? 'inactive' : 'active' ?>')">
                                            <i class="bi bi-<?= $news['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                            <?= $news['is_active'] ? 'Gizlət' : 'Göstər' ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Pagination">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Add News Modal -->
    <div class="modal fade" id="addNewsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle"></i> Yeni Xəbər Əlavə Et
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_news">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">Başlıq *</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="content" class="form-label">Məzmun *</label>
                            <textarea class="form-control" name="content" rows="6" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="image" class="form-label">Şəkil</label>
                            <input type="file" class="form-control" name="image" accept="image/*">
                            <small class="form-text text-muted">JPG, PNG formatında</small>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Aktiv et
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Bağla</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check"></i> Xəbər Əlavə Et
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function toggleNewsStatus(newsId, status) {
            const action = status === 'active' ? 'aktiv' : 'qeyri-aktiv';
            if (confirm(`Bu xəbəri ${action} etmək istəyirsiniz?`)) {