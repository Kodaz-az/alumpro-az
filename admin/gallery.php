<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireAdmin();

$db = new Database();
$message = '';
$error = '';

// Handle gallery operations
if ($_POST) {
    if ($_POST['action'] === 'upload_image') {
        try {
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $category = trim($_POST['category']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($title)) {
                throw new Exception('Başlıq mütləqdir');
            }
            
            // Handle image upload
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Şəkil seçilməlidir');
            }
            
            $image = Utils::uploadFile($_FILES['image'], GALLERY_IMAGES_PATH, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            if (!$image) {
                throw new Exception('Şəkil yüklənməsi uğursuz oldu');
            }
            
            $stmt = $db->query("INSERT INTO gallery (title, image, description, category, is_active) VALUES (?, ?, ?, ?, ?)", 
                [$title, $image, $description, $category, $is_active]);
            
            Utils::logActivity(SessionManager::getUserId(), 'gallery_image_added', "Qalereya şəkli əlavə edildi: $title");
            
            $message = 'Şəkil uğurla qalereyaya əlavə edildi';
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($_POST['action'] === 'delete_image') {
        try {
            $image_id = $_POST['image_id'];
            
            // Get image info before deleting
            $stmt = $db->query("SELECT image, title FROM gallery WHERE id = ?", [$image_id]);
            $image_data = $stmt->fetch();
            
            if ($image_data) {
                // Delete from database
                $stmt = $db->query("DELETE FROM gallery WHERE id = ?", [$image_id]);
                
                // Delete file
                $image_path = GALLERY_IMAGES_PATH . $image_data['image'];
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
                
                Utils::logActivity(SessionManager::getUserId(), 'gallery_image_deleted', "Qalereya şəkli silindi: " . $image_data['title']);
                
                $message = 'Şəkil uğurla silindi';
            }
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get gallery images with pagination
$page = $_GET['page'] ?? 1;
$limit = 12;
$offset = ($page - 1) * $limit;
$category_filter = $_GET['category'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($category_filter)) {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_params = $params;
$stmt = $db->query("SELECT COUNT(*) as total FROM gallery $where_clause", $count_params);
$total_images = $stmt->fetch()['total'];
$total_pages = ceil($total_images / $limit);

// Get images
$params[] = $limit;
$params[] = $offset;
$stmt = $db->query("SELECT * FROM gallery $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?", $params);
$gallery_images = $stmt->fetchAll();

// Get categories
$stmt = $db->query("SELECT DISTINCT category FROM gallery WHERE category IS NOT NULL AND category != '' ORDER BY category");
$categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qalereya İdarəsi - Alumpro.Az</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .gallery-image {
            height: 200px;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .gallery-image:hover {
            transform: scale(1.05);
        }
        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            opacity: 0;
            transition: opacity 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .gallery-card:hover .image-overlay {
            opacity: 1;
        }
    </style>
</head>
<body class="bg-light">
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin-sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-images text-primary"></i> Qalereya İdarəsi
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                <i class="bi bi-cloud-upload"></i> Şəkil Yüklə
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

                <!-- Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <select class="form-select" name="category">
                                    <option value="">Bütün kateqoriyalar</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= htmlspecialchars($category['category']) ?>" 
                                                <?= $category_filter === $category['category'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['category']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-filter"></i> Filterlə
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Gallery Grid -->
                <div class="row">
                    <?php foreach ($gallery_images as $image): ?>
                        <div class="col-md-3 mb-4">
                            <div class="card gallery-card h-100">
                                <div class="position-relative">
                                    <img src="<?= SITE_URL . '/' . GALLERY_IMAGES_PATH . $image['image'] ?>" 
                                         class="card-img-top gallery-image" alt="<?= htmlspecialchars($image['title']) ?>"
                                         onclick="showImageModal('<?= SITE_URL . '/' . GALLERY_IMAGES_PATH . $image['image'] ?>', '<?= htmlspecialchars($image['title']) ?>')">
                                    
                                    <div class="image-overlay">
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-light btn-sm" 
                                                    onclick="showImageModal('<?= SITE_URL . '/' . GALLERY_IMAGES_PATH . $image['image'] ?>', '<?= htmlspecialchars($image['title']) ?>')">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" 
                                                    onclick="deleteImage(<?= $image['id'] ?>, '<?= htmlspecialchars($image['title']) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <?php if (!$image['is_active']): ?>
                                        <span class="position-absolute top-0 end-0 badge bg-secondary m-2">Gizli</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-body">
                                    <h6 class="card-title"><?= htmlspecialchars($image['title']) ?></h6>
                                    <?php if ($image['description']): ?>
                                        <p class="card-text small text-muted">
                                            <?= htmlspecialchars(substr($image['description'], 0, 100)) ?>
                                            <?= strlen($image['description']) > 100 ? '...' : '' ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($image['category']): ?>
                                        <span class="badge bg-primary"><?= htmlspecialchars($image['category']) ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-footer small text-muted">
                                    <?= date('d.m.Y', strtotime($image['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($gallery_images)): ?>
                        <div class="col-12 text-center py-5">
                            <i class="bi bi-images display-1 text-muted"></i>
                            <h3 class="text-muted mt-3">Qalereya boşdur</h3>
                            <p class="text-muted">İlk şəklinizi yükləyin</p>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                <i class="bi bi-cloud-upload"></i> Şəkil Yüklə
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Pagination">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-cloud-upload"></i> Şəkil Yüklə
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="action" value="upload_image">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="image" class="form-label">Şəkil *</label>
                            <input type="file" class="form-control" name="image" accept="image/*" required onchange="previewImage(this)">
                            <small class="form-text text-muted">JPG, PNG, GIF, WebP formatlarında</small>
                        </div>
                        
                        <div class="mb-3" id="imagePreview" style="display: none;">
                            <img id="preview" class="img-fluid rounded" style="max-height: 200px;">
                        </div>
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Başlıq *</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Təsvir</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category" class="form-label">Kateqoriya</label>
                            <input type="text" class="form-control" name="category" 
                                   placeholder="Məsələn: Layihələr, Məhsullar və s.">
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Aktiv et (səhifədə göstər)
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Bağla</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-upload"></i> Yüklə
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Image View Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function showImageModal(imageSrc, title) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('imageModalTitle').textContent = title;
            const modal = new bootstrap.Modal(document.getElementById('imageModal'));
            modal.show();
        }
        
        function deleteImage(imageId, title) {
            if (confirm(`"${title}" şəklini silmək istədiyinizdən əminsiniz?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_image">
                    <input type="hidden" name="image_id" value="${imageId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Auto-fill title from filename
        document.querySelector('input[name="image"]').addEventListener('change', function() {
            const titleInput = document.querySelector('input[name="title"]');
            if (!titleInput.value && this.files[0]) {
                const filename = this.files[0].name;
                const nameWithoutExt = filename.substring(0, filename.lastIndexOf('.'));
                titleInput.value = nameWithoutExt.replace(/[_-]/g, ' ');
            }
        });
    </script>
</body>
</html>