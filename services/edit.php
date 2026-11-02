<?php
// services/edit.php - Edit Service & Manage Photos
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');
require_role('admin');

$service_id = (int)($_GET['service_id'] ?? $_POST['service_id'] ?? 0);
$rows = db_select($conn, "SELECT * FROM services WHERE service_id = ?", 'i', [$service_id]);

if (empty($rows)) {
    set_flash('danger', 'Service package not found.');
    redirect('services/index.php');
}
$service = $rows[0];

// Fetch existing gallery photos
$photos = db_select($conn, "SELECT * FROM service_photos WHERE service_id = ?", 'i', [$service_id]);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_name       = trim($_POST['service_name'] ?? '');
    $category           = trim($_POST['category'] ?? '');
    $price              = trim($_POST['price'] ?? '');
    $estimated_duration = trim($_POST['estimated_duration'] ?? '');
    $description        = trim($_POST['description'] ?? '');
    $status             = trim($_POST['status'] ?? 'active');

    // Server-side PHP Validation (no HTML5 validation)
    if ($service_name === '') {
        $errors[] = 'Service name is required.';
    } elseif (mb_strlen($service_name) > 100) {
        $errors[] = 'Service name must not exceed 100 characters.';
    }

    if ($category === '') {
        $errors[] = 'Category is required.';
    }

    if ($price === '') {
        $errors[] = 'Price is required.';
    } elseif (!is_numeric($price) || (float)$price <= 0) {
        $errors[] = 'Price must be a valid positive number.';
    }

    if ($estimated_duration === '') {
        $errors[] = 'Estimated duration is required.';
    }

    if ($description === '') {
        $errors[] = 'Description is required.';
    }

    if (!in_array($status, ['active', 'inactive'], true)) {
        $errors[] = 'Invalid status selected.';
    }

    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $primary_photo_path = $service['primary_photo'];

    // Update primary photo if a new one is uploaded
    if (isset($_FILES['primary_photo']) && $_FILES['primary_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['primary_photo'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Error uploading primary photo.';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_exts, true)) {
                $errors[] = 'Primary photo must be JPG, JPEG, PNG, WEBP, or GIF.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Primary photo must not exceed 5MB.';
            } else {
                $upload_dir = __DIR__ . '/../uploads/services';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0777, true);
                }
                $filename = 'primary_' . uniqid() . '.' . $ext;
                $dest = $upload_dir . '/' . $filename;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $primary_photo_path = 'uploads/services/' . $filename;
                } else {
                    $errors[] = 'Failed to move uploaded primary photo.';
                }
            }
        }
    }

    if (empty($errors)) {
        $sql = "UPDATE services 
                SET service_name = ?, category = ?, price = ?, estimated_duration = ?, primary_photo = ?, description = ?, status = ?
                WHERE service_id = ?";
        db_run($conn, $sql, 'ssdssssi', [
            $service_name,
            $category,
            (float)$price,
            $estimated_duration,
            $primary_photo_path,
            $description,
            $status,
            $service_id
        ]);

        // Process any additional gallery photos uploaded
        if (isset($_FILES['gallery_photos']) && !empty($_FILES['gallery_photos']['name'][0])) {
            $gallery = $_FILES['gallery_photos'];
            $total_files = count($gallery['name']);
            $upload_dir = __DIR__ . '/../uploads/services';

            for ($i = 0; $i < $total_files; $i++) {
                if ($gallery['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($gallery['name'][$i], PATHINFO_EXTENSION));
                    if (in_array($ext, $allowed_exts, true) && $gallery['size'][$i] <= 5 * 1024 * 1024) {
                        $filename = 'gallery_' . $service_id . '_' . uniqid() . '.' . $ext;
                        $dest = $upload_dir . '/' . $filename;
                        if (move_uploaded_file($gallery['tmp_name'][$i], $dest)) {
                            $rel_path = 'uploads/services/' . $filename;
                            db_run($conn, "INSERT INTO service_photos (service_id, filename) VALUES (?, ?)", 'is', [$service_id, $rel_path]);
                        }
                    }
                }
            }
        }

        set_flash('success', 'Service package updated successfully!');
        redirect('services/index.php');
    }

    $service['service_name']       = $service_name;
    $service['category']           = $category;
    $service['price']              = $price;
    $service['estimated_duration'] = $estimated_duration;
    $service['description']        = $description;
    $service['status']             = $status;
}

include('../includes/header.php');
?>

<div class="row justify-content-center">
  <div class="col-lg-9">
    <div class="card shadow-sm border-0 form-card">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="fw-bold mb-0">Edit Service Package</h2>
          <a href="<?php echo $base_url; ?>/services/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to List
          </a>
        </div>

        <?php if (!empty($errors)) { ?>
          <div class="alert alert-danger" role="alert">
            <h6 class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Errors encountered:</h6>
            <ul class="mb-0 ps-3">
              <?php foreach ($errors as $e) { echo "<li>" . e($e) . "</li>"; } ?>
            </ul>
          </div>
        <?php } ?>

        <form method="POST" action="edit.php" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="service_id" value="<?php echo (int)$service['service_id']; ?>">

          <div class="row">
            <div class="col-md-8 mb-3">
              <label for="service_name" class="form-label fw-semibold">Service Name</label>
              <input type="text" class="form-control" id="service_name" name="service_name" 
                     value="<?php echo e($service['service_name']); ?>">
            </div>
            <div class="col-md-4 mb-3">
              <label for="status" class="form-label fw-semibold">Status</label>
              <select class="form-select" id="status" name="status">
                <option value="active" <?php echo ($service['status'] === 'active') ? 'selected' : ''; ?>>Active (Visible)</option>
                <option value="inactive" <?php echo ($service['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive (Hidden)</option>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="category" class="form-label fw-semibold">Category</label>
              <select class="form-select" id="category" name="category">
                <option value="Development" <?php echo ($service['category'] === 'Development') ? 'selected' : ''; ?>>Development</option>
                <option value="UI/UX Design" <?php echo ($service['category'] === 'UI/UX Design') ? 'selected' : ''; ?>>UI/UX Design</option>
                <option value="Consulting" <?php echo ($service['category'] === 'Consulting') ? 'selected' : ''; ?>>Consulting</option>
                <option value="Database" <?php echo ($service['category'] === 'Database') ? 'selected' : ''; ?>>Database</option>
                <option value="Quality Assurance" <?php echo ($service['category'] === 'Quality Assurance') ? 'selected' : ''; ?>>Quality Assurance</option>
                <option value="Marketing & SEO" <?php echo ($service['category'] === 'Marketing & SEO') ? 'selected' : ''; ?>>Marketing & SEO</option>
              </select>
            </div>

            <div class="col-md-4 mb-3">
              <label for="price" class="form-label fw-semibold">Base Price (PHP)</label>
              <input type="text" class="form-control" id="price" name="price" 
                     value="<?php echo e($service['price']); ?>">
            </div>

            <div class="col-md-4 mb-3">
              <label for="estimated_duration" class="form-label fw-semibold">Estimated Duration</label>
              <input type="text" class="form-control" id="estimated_duration" name="estimated_duration" 
                     value="<?php echo e($service['estimated_duration']); ?>">
            </div>
          </div>

          <div class="mb-3">
            <label for="description" class="form-label fw-semibold">Description</label>
            <textarea class="form-control" id="description" name="description" rows="4"><?php echo e($service['description']); ?></textarea>
          </div>

          <!-- Primary Photo Section -->
          <div class="card mb-4 border bg-light">
            <div class="card-body">
              <h5 class="fw-bold mb-2"><i class="fa-solid fa-image text-primary me-1"></i> Primary Photo</h5>
              <div class="d-flex align-items-center gap-3">
                <?php if (!empty($service['primary_photo']) && file_exists(__DIR__ . '/../' . $service['primary_photo'])) { ?>
                  <img src="<?php echo e($base_url . '/' . $service['primary_photo']); ?>" alt="Current Primary" 
                       class="rounded border" style="width: 100px; height: 75px; object-fit: cover;">
                <?php } ?>
                <div class="flex-grow-1">
                  <label for="primary_photo" class="form-label small text-muted mb-1">Replace Primary Photo (optional):</label>
                  <input type="file" class="form-control form-control-sm" id="primary_photo" name="primary_photo">
                </div>
              </div>
            </div>
          </div>

          <!-- Existing Gallery Photos & Upload More -->
          <div class="card mb-4 border bg-light">
            <div class="card-body">
              <h5 class="fw-bold mb-2"><i class="fa-solid fa-images text-success me-1"></i> Gallery Photos (<?php echo count($photos); ?>)</h5>
              
              <?php if (!empty($photos)) { ?>
                <div class="row g-2 mb-3">
                  <?php foreach ($photos as $p) { ?>
                    <div class="col-6 col-md-3 col-lg-2 position-relative text-center">
                      <img src="<?php echo e($base_url . '/' . $p['filename']); ?>" alt="Gallery" 
                           class="img-thumbnail w-100" style="height: 90px; object-fit: cover;">
                      <a href="<?php echo $base_url; ?>/services/delete_photo.php?photo_id=<?php echo (int)$p['photo_id']; ?>&service_id=<?php echo (int)$service_id; ?>" 
                         class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1 py-0 px-1"
                         onclick="return confirm('Delete this gallery photo?');" title="Delete Photo">
                        <i class="fa-solid fa-xmark"></i>
                      </a>
                    </div>
                  <?php } ?>
                </div>
              <?php } else { ?>
                <p class="text-muted small mb-2">No additional gallery photos uploaded yet.</p>
              <?php } ?>

              <label for="gallery_photos" class="form-label small text-muted mb-1">Upload More Gallery Photos:</label>
              <input type="file" class="form-control form-control-sm" id="gallery_photos" name="gallery_photos[]" multiple>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 pt-3 border-top">
            <a href="<?php echo $base_url; ?>/services/index.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4 fw-semibold">
              <i class="fa-solid fa-floppy-disk me-1"></i> Update Service
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>
