<?php
// admin/user_add.php - Admin Add User with Photo & Role
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');
require_role('admin');

$errors = [];
$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name'] ?? '');
$email      = trim($_POST['email'] ?? '');
$role       = trim($_POST['role'] ?? 'member');
$password   = $_POST['password'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Server-side PHP Form Validation (no HTML5 validation)
    if ($first_name === '') {
        $errors[] = 'First name is required.';
    }
    if ($last_name === '') {
        $errors[] = 'Last name is required.';
    }
    if ($email === '') {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $check = db_select($conn, "SELECT 1 FROM users WHERE email = ?", 's', [$email]);
        if (!empty($check)) {
            $errors[] = 'That email address is already in use.';
        }
    }

    if (!array_key_exists($role, role_options())) {
        $errors[] = 'Please select a valid role.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    // Photo upload
    $photo_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['photo'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Error uploading photo file.';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (!in_array($ext, $allowed, true)) {
                $errors[] = 'Photo must be JPG, JPEG, PNG, WEBP, or GIF.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Photo must not exceed 5MB.';
            } else {
                $dir = __DIR__ . '/../uploads/avatars';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }
                $filename = 'avatar_' . uniqid() . '.' . $ext;
                $dest = $dir . '/' . $filename;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $photo_path = 'uploads/avatars/' . $filename;
                } else {
                    $errors[] = 'Failed to move uploaded photo file.';
                }
            }
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (first_name, last_name, email, password, role, photo, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'active')";
        $ok = db_run($conn, $sql, 'ssssss', [$first_name, $last_name, $email, $hash, $role, $photo_path]);

        if ($ok) {
            set_flash('success', "User account for {$first_name} created successfully!");
            redirect('admin/users.php');
        } else {
            $errors[] = 'Database error creating user account.';
        }
    }
}

include('../includes/header.php');
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card shadow-sm border-0 form-card my-3">
      <div class="card-body p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="fw-bold mb-0">Add New User</h2>
          <a href="<?php echo $base_url; ?>/admin/users.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Users
          </a>
        </div>
        <p class="text-muted small mb-4">Create account credentials, upload profile photo, and assign administrative or client roles.</p>

        <?php if (!empty($errors)) { ?>
          <div class="alert alert-danger" role="alert">
            <h6 class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Form Errors:</h6>
            <ul class="mb-0 ps-3">
              <?php foreach ($errors as $e) { echo "<li>" . e($e) . "</li>"; } ?>
            </ul>
          </div>
        <?php } ?>

        <form method="POST" action="user_add.php" enctype="multipart/form-data" novalidate>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="first_name" class="form-label fw-semibold">First Name</label>
              <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo e($first_name); ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label for="last_name" class="form-label fw-semibold">Last Name</label>
              <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo e($last_name); ?>">
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="email" class="form-label fw-semibold">Email Address</label>
              <input type="text" class="form-control" id="email" name="email" value="<?php echo e($email); ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label for="role" class="form-label fw-semibold">System Role</label>
              <select class="form-select" id="role" name="role">
                <?php foreach (role_options() as $r_val => $r_lbl) {
                  $sel = ($role === $r_val) ? 'selected' : '';
                  echo "<option value='" . e($r_val) . "' {$sel}>" . e($r_lbl) . "</option>";
                } ?>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label for="password" class="form-label fw-semibold">Initial Password (min 8 characters)</label>
            <input type="password" class="form-control" id="password" name="password">
          </div>

          <div class="mb-4">
            <label for="photo" class="form-label fw-semibold">
              <i class="fa-solid fa-camera text-primary me-1"></i> Profile Photo
            </label>
            <input type="file" class="form-control" id="photo" name="photo">
            <small class="text-muted">JPG, PNG, WEBP, or GIF (max 5MB).</small>
          </div>

          <div class="d-flex justify-content-end gap-2 pt-3 border-top">
            <a href="<?php echo $base_url; ?>/admin/users.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4 fw-semibold">
              <i class="fa-solid fa-user-plus me-1"></i> Create User Account
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>
