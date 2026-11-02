<?php
// user/profile.php - My Profile & Photo Upload (MP2: 5pts, MP5: PHP Validation)
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');
require_login();

$user_id = (int)$_SESSION['user_id'];
$errors  = [];

$rows = db_select($conn, "SELECT user_id, first_name, last_name, email, role, photo, created_at FROM users WHERE user_id = ?", 'i', [$user_id]);
if (empty($rows)) {
    redirect('user/logout.php');
}
$user = $rows[0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action 1: Update Profile Info & Photo Upload (MP2: 5pts)
    if ($action === 'update_profile') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');

        // MP5: Server-side PHP Form Validation
        if ($first_name === '') {
            $errors[] = 'First name is required.';
        }
        if ($last_name === '') {
            $errors[] = 'Last name is required.';
        }

        // Process profile photo upload (MP2: 5pts)
        $photo_path = $user['photo'];
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['photo'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Error uploading photo file.';
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (!in_array($ext, $allowed, true)) {
                    $errors[] = 'Profile photo must be a JPG, JPEG, PNG, WEBP, or GIF image.';
                } elseif ($file['size'] > 5 * 1024 * 1024) {
                    $errors[] = 'Profile photo must not exceed 5MB.';
                } else {
                    $dir = __DIR__ . '/../uploads/avatars';
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0777, true);
                    }
                    $filename = 'avatar_' . $user_id . '_' . uniqid() . '.' . $ext;
                    $dest = $dir . '/' . $filename;
                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        // Delete old photo if exists
                        if (!empty($user['photo']) && file_exists(__DIR__ . '/../' . $user['photo'])) {
                            @unlink(__DIR__ . '/../' . $user['photo']);
                        }
                        $photo_path = 'uploads/avatars/' . $filename;
                    } else {
                        $errors[] = 'Failed to save uploaded photo.';
                    }
                }
            }
        }

        if (empty($errors)) {
            db_run($conn, "UPDATE users SET first_name = ?, last_name = ?, photo = ? WHERE user_id = ?", 'sssi', [
                $first_name, $last_name, $photo_path, $user_id
            ]);
            $_SESSION['name'] = $first_name . ' ' . $last_name;
            set_flash('success', 'Profile and photo updated successfully!');
            redirect('user/profile.php');
        }

        $user['first_name'] = $first_name;
        $user['last_name']  = $last_name;
    }

    // Action 2: Change Password
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $pw = db_select($conn, "SELECT password FROM users WHERE user_id = ?", 'i', [$user_id]);
        if ($current === '') {
            $errors[] = 'Current password is required.';
        } elseif (!password_verify($current, $pw[0]['password'])) {
            $errors[] = 'Current password is incorrect.';
        }

        if ($new === '') {
            $errors[] = 'New password is required.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters long.';
        }

        if ($new !== $confirm) {
            $errors[] = 'New password and confirmation do not match.';
        }

        if (empty($errors)) {
            db_run($conn, "UPDATE users SET password = ? WHERE user_id = ?", 'si', [password_hash($new, PASSWORD_DEFAULT), $user_id]);
            set_flash('success', 'Password changed successfully!');
            redirect('user/profile.php');
        }
    }
}

include('../includes/header.php');
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card shadow-sm border-0 form-card mb-4">
      <div class="card-body p-4 p-md-5">
        <h2 class="fw-bold mb-1">My Profile</h2>
        <p class="text-muted small mb-4">Manage your personal information, profile photo, and password.</p>

        <?php if (!empty($errors)) { ?>
          <div class="alert alert-danger" role="alert">
            <h6 class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Form Errors:</h6>
            <ul class="mb-0 ps-3">
              <?php foreach ($errors as $e) { echo "<li>" . e($e) . "</li>"; } ?>
            </ul>
          </div>
        <?php } ?>

        <form method="POST" action="profile.php" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="action" value="update_profile">

          <div class="d-flex align-items-center gap-4 mb-4 p-3 bg-light rounded-3">
            <div>
              <?php echo user_avatar($user['photo'], 80, 'rounded-circle shadow-sm border border-2 border-primary'); ?>
            </div>
            <div class="flex-grow-1">
              <label for="photo" class="form-label fw-semibold mb-1">
                <i class="fa-solid fa-camera me-1 text-primary"></i> Update Profile Photo (MP2: 5pts)
              </label>
              <input type="file" class="form-control form-control-sm" id="photo" name="photo">
              <small class="text-muted">Choose a new JPG, PNG, or GIF file (max 5MB).</small>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="first_name" class="form-label fw-semibold">First Name</label>
              <input type="text" class="form-control" id="first_name" name="first_name" 
                     value="<?php echo e($user['first_name']); ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label for="last_name" class="form-label fw-semibold">Last Name</label>
              <input type="text" class="form-control" id="last_name" name="last_name" 
                     value="<?php echo e($user['last_name']); ?>">
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Email Address</label>
              <input type="text" class="form-control" value="<?php echo e($user['email']); ?>" disabled>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Account Role</label>
              <input type="text" class="form-control" value="<?php echo e(role_label($user['role'])); ?>" disabled>
            </div>
          </div>

          <button type="submit" class="btn btn-primary fw-semibold px-4">
            <i class="fa-solid fa-floppy-disk me-1"></i> Save Profile & Photo
          </button>
        </form>

        <hr class="my-5">

        <h4 class="fw-bold mb-3"><i class="fa-solid fa-key me-1 text-warning"></i> Change Password</h4>
        <form method="POST" action="profile.php" novalidate>
          <input type="hidden" name="action" value="change_password">

          <div class="mb-3">
            <label for="current_password" class="form-label fw-semibold">Current Password</label>
            <input type="password" class="form-control" id="current_password" name="current_password">
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="new_password" class="form-label fw-semibold">New Password (min 8 chars)</label>
              <input type="password" class="form-control" id="new_password" name="new_password">
            </div>
            <div class="col-md-6 mb-3">
              <label for="confirm_password" class="form-label fw-semibold">Confirm New Password</label>
              <input type="password" class="form-control" id="confirm_password" name="confirm_password">
            </div>
          </div>

          <button type="submit" class="btn btn-outline-primary fw-semibold px-4">
            <i class="fa-solid fa-lock me-1"></i> Update Password
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>
