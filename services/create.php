<?php
// services/create.php - Add Service with Single & Multiple Photos
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');
require_role('admin');

$errors = [];
$service_name       = trim($_POST['service_name'] ?? '');
$category           = trim($_POST['category'] ?? '');
$price              = trim($_POST['price'] ?? '');
$estimated_duration = trim($_POST['estimated_duration'] ?? '');
$description        = trim($_POST['description'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $errors[] = 'Estimated duration is required (e.g. "2-3 weeks").';
    }

    if ($description === '') {
        $errors[] = 'Description is required.';
    }

    // Process primary photo
    $primary_photo_path = null;
    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (isset($_FILES['primary_photo']) && $_FILES['primary_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['primary_photo'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Error uploading primary photo (code ' . $file['error'] . ').';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_exts, true)) {
                $errors[] = 'Primary photo must be a JPG, JPEG, PNG, WEBP, or GIF image.';
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
                    $errors[] = 'Failed to save uploaded primary photo.';
                }
            }
        }
    }

    // If no errors, insert into services table
    if (empty($errors)) {
        $sql = "INSERT INTO services (service_name, category, price, estimated_duration, primary_photo, description, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'active')";
        $ok = db_run($conn, $sql, 'ssdsss', [
            $service_name,
            $category,
            (float)$price,
            $estimated_duration,
            $primary_photo_path,
            $description
        ]);

        if ($ok) {
            $service_id = db_insert_id($conn);

            // Process multiple gallery photos
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

            set_flash('success', 'Service package created successfully!');
            redirect('services/index.php');
        } else {
            $errors[] = 'Database error creating service package.';
        }
    }
}

include('../includes/header.php');
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card shadow-sm border-0 form-card">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="fw-bold mb-0">Add New Service Package</h2>
          <a href="<?php echo $base_url; ?>/services/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to List
          </a>
        </div>
        <p class="text-muted small mb-4">
          Provide complete service details and upload primary/gallery photos. Form validation is processed by PHP.
        </p>

        <?php if (!empty($errors)) { ?>
          <div class="alert alert-danger" role="alert">
            <h6 class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Please fix the following errors:</h6>
            <ul class="mb-0 ps-3">
              <?php foreach ($errors as $e) { echo "<li>" . e($e) . "</li>"; } ?>
            </ul>
          </div>
        <?php } ?>

        <!-- novalidate prevents HTML5 validation -->
        <form method="POST" action="create.php" enctype="multipart/form-data" novalidate>
          <div class="mb-3">
            <label for="service_name" class="form-label fw-semibold">Service Name</label>
            <input type="text" class="form-control" id="service_name" name="service_name" 
                   value="<?php echo e($service_name); ?>" placeholder="e.g. Full-Stack Web App Development">
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="category" class="form-label fw-semibold">Category</label>
              <select class="form-select" id="category" name="category">
                <option value="">-- Select Category --</option>
                <option value="Development" <?php echo ($category === 'Development') ? 'selected' : ''; ?>>Development</option>
                <option value="UI/UX Design" <?php echo ($category === 'UI/UX Design') ? 'selected' : ''; ?>>UI/UX Design</option>
                <option value="Consulting" <?php echo ($category === 'Consulting') ? 'selected' : ''; ?>>Consulting</option>
                <option value="Database" <?php echo ($category === 'Database') ? 'selected' : ''; ?>>Database</option>
                <option value="Quality Assurance" <?php echo ($category === 'Quality Assurance') ? 'selected' : ''; ?>>Quality Assurance</option>
                <option value="Marketing & SEO" <?php echo ($category === 'Marketing & SEO') ? 'selected' : ''; ?>>Marketing & SEO</option>
              </select>
            </div>

            <div class="col-md-4 mb-3">
              <label for="price" class="form-label fw-semibold">Base Price (PHP)</label>
              <input type="text" class="form-control" id="price" name="price" 
                     value="<?php echo e($price); ?>" placeholder="e.g. 25000">
            </div>

            <div class="col-md-4 mb-3">
              <label for="estimated_duration" class="form-label fw-semibold">Estimated Duration</label>
              <input type="text" class="form-control" id="estimated_duration" name="estimated_duration" 
                     value="<?php echo e($estimated_duration); ?>" placeholder="e.g. 3-4 weeks">
            </div>
          </div>

          <div class="mb-3">
            <label for="description" class="form-label fw-semibold">Detailed Description & Scope of Work</label>
            <textarea class="form-control" id="description" name="description" rows="4" 
                      placeholder="Outline the deliverables, technologies, and milestones included..."><?php echo e($description); ?></textarea>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="primary_photo" class="form-label fw-semibold">
                <i class="fa-solid fa-image me-1 text-primary"></i> Primary Featured Photo
              </label>
              <input type="file" class="form-control" id="primary_photo" name="primary_photo">
              <small class="text-muted">Displayed as the main cover image on cards and catalog.</small>
            </div>

            <div class="col-md-6 mb-3">
              <label for="gallery_photos" class="form-label fw-semibold">
                <i class="fa-solid fa-images me-1 text-success"></i> Multiple Gallery Photos
              </label>
              <input type="file" class="form-control" id="gallery_photos" name="gallery_photos[]" multiple>
              <small class="text-muted">Hold Ctrl to select multiple screenshots or portfolio images.</small>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <a href="<?php echo $base_url; ?>/services/index.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4 fw-semibold">
              <i class="fa-solid fa-cloud-arrow-up me-1"></i> Save & Publish Service
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>
