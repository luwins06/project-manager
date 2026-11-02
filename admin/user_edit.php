<?php
// admin/user_edit.php - Admin Updates Active User Role & Details
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');
require_role('admin');

$edit_id = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
$rows = db_select($conn, "SELECT user_id, first_name, last_name, email, role, photo, status FROM users WHERE user_id = ?", 'i', [$edit_id]);

if (empty($rows)) {
    set_flash('danger', 'User account not found.');
    redirect('admin/users.php');
}

$user = $rows[0];
$is_self = ((int)$user['user_id'] === (int)$_SESSION['user_id']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $role       = $is_self ? $user['role'] : trim($_POST['role'] ?? '');
    $status     = $is_self ? $user['status'] : trim($_POST['status'] ?? '');
    $password   = $_POST['password'] ?? '';

    // Server-side PHP Validation
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
        $check = db_select($conn, "SELECT 1 FROM users WHERE email = ? AND user_id <> ?", 'si', [$email, $edit_id]);
        if (!empty($check)) {
            $errors[] = 'That email address is already used by another account.';
        }
    }

    if (!array_key_exists($role, role_options())) {
        $errors[] = 'Please select a valid role.';
    }

    if (!in_array($status, ['active', 'inactive'], true)) {
        $errors[] = 'Please select a valid status.';
    }

    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'New password must be at least 8 characters long.';
    }

    // Photo upload
    $photo_path = $user['photo'];
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
                $filename = 'avatar_' . $edit_id . '_' . uniqid() . '.' . $ext;
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
        // Admin updates an active user's role
        $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, photo = ?, status = ? WHERE user_id = ?";
        db_run($conn, $sql, 'ssssssi', [$first_name, $last_name, $email, $role, $photo_path, $status, $edit_id]);

        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            db_run($conn, "UPDATE users SET password = ? WHERE user_id = ?", 'si', [$hash, $edit_id]);
        }

        if ($is_self) {
            $_SESSION['name']  = $first_name . ' ' . $last_name;
            $_SESSION['email'] = $email;
        }

        set_flash('success', "User account for {$first_name} {$last_name} updated successfully.");
        redirect('admin/users.php');
    }

    $user['first_name'] = $first_name;
    $user['last_name']  = $last_name;
    $user['email']      = $email;
    $user['role']       = $role;
    $user['status']     = $status;
}

include('../includes/header.php');
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card shadow-sm border-0 form-card my-3">
      <div class="card-body p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="fw-bold mb-0">Edit User Account</h2>
          <a href="<?php echo $base_url; ?>/admin/users.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Users
          </a>
        </div>
        <p class="text-muted small mb-4">Modify account role, update photo avatar, and manage activation status.</p>

        <?php if (!empty($errors)) { ?>
          <div class="alert alert-danger" role="alert">
            <h6 class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Errors encountered:</h6>
            <ul class="mb-0 ps-3">
              <?php foreach ($errors as $e) { echo "<li>" . e($e) . "</li>"; } ?>
            </ul>
          </div>
        <?php } ?>

        <form method="POST" action="user_edit.php" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">

          <div class="d-flex align-items-center gap-3 mb-4 p-3 bg-light rounded-3">
            <div><?php echo user_avatar($user['photo'], 64); ?></div>
            <div class="flex-grow-1">
              <label for="photo" class="form-label fw-semibold mb-1">
                <i class="fa-solid fa-camera text-primary me-1"></i> Change User Avatar
              </label>
              <input type="file" class="form-control form-control-sm" id="photo" name="photo">
              <small class="text-muted">JPG, PNG, GIF (max 5MB).</small>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="first_name" class="form-label fw-semibold">First Name</label>
              <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo e($user['first_name']); ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label for="last_name" class="form-label fw-semibold">Last Name</label>
              <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo e($user['last_name']); ?>">
            </div>
          </div>

          <div class="mb-3">
            <label for="email" class="form-label fw-semibold">Email Address</label>
            <input type="text" class="form-control" id="email" name="email" value="<?php echo e($user['email']); ?>">
          </div>

          <!-- Admin updates an active user's role -->
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="role" class="form-label fw-semibold">Role</label>
              <select class="form-select" id="role" name="role" <?php echo $is_self ? 'disabled' : ''; ?>>
                <?php foreach (role_options() as $r_val => $r_lbl) {
                  $sel = ($user['role'] === $r_val) ? 'selected' : '';
                  echo "<option value='" . e($r_val) . "' {$sel}>" . e($r_lbl) . "</option>";
                } ?>
              </select>
              <?php if ($is_self) { ?>
                <input type="hidden" name="role" value="<?php echo e($user['role']); ?>">
                <small class="text-muted">You cannot change your own admin role.</small>
              <?php } ?>
            </div>

            <div class="col-md-6 mb-3">
              <label for="status" class="form-label fw-semibold">Status</label>
              <select class="form-select" id="status" name="status" <?php echo $is_self ? 'disabled' : ''; ?>>
                <option value="active" <?php echo ($user['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo ($user['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive (Deactivated)</option>
              </select>
              <?php if ($is_self) { ?>
                <input type="hidden" name="status" value="<?php echo e($user['status']); ?>">
                <small class="text-muted">You cannot deactivate your own account.</small>
              <?php } ?>
            </div>
          </div>

          <div class="mb-4">
            <label for="password" class="form-label fw-semibold">Reset Password (leave blank to keep current)</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="••••••••">
          </div>

          <div class="d-flex justify-content-end gap-2 pt-3 border-top">
            <a href="<?php echo $base_url; ?>/admin/users.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4 fw-semibold">
              <i class="fa-solid fa-user-check me-1"></i> Update User Account
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>
