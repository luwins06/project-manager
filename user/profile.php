<?php
// FR-1 User Management - My Profile (name + password)
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_login();

$user_id = $_SESSION['user_id'];
$errors = [];

$rows = db_select($conn, "SELECT user_id, first_name, last_name, email, role, created_at FROM users WHERE user_id = ?", 'i', [$user_id]);
$user = $rows[0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        if ($first_name === '' || $last_name === '') {
            $errors[] = 'First name and last name are required.';
        } else {
            db_run($conn, "UPDATE users SET first_name = ?, last_name = ? WHERE user_id = ?", 'ssi', [$first_name, $last_name, $user_id]);
            $_SESSION['name'] = $first_name . ' ' . $last_name;
            set_flash('success', 'Profile updated.');
            redirect('user/profile.php');
        }
        $user['first_name'] = $first_name;
        $user['last_name']  = $last_name;
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $pw = db_select($conn, "SELECT password FROM users WHERE user_id = ?", 'i', [$user_id]);
        if (!password_verify($current, $pw[0]['password'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New password and confirmation do not match.';
        } else {
            db_run($conn, "UPDATE users SET password = ? WHERE user_id = ?", 'si', [password_hash($new, PASSWORD_DEFAULT), $user_id]);
            set_flash('success', 'Password changed.');
            redirect('user/profile.php');
        }
    }
}

include('../includes/header.php');
?>

<div class="form-card">
  <h2>My Profile</h2>
  <?php foreach ($errors as $err) { echo "<div class='alert alert-danger'>" . e($err) . "</div>"; } ?>

  <form method="POST" action="profile.php">
    <input type="hidden" name="action" value="update_profile">
    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="first_name">First Name</label>
        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo e($user['first_name']); ?>" required>
      </div>
      <div class="col-md-6 mb-3">
        <label for="last_name">Last Name</label>
        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo e($user['last_name']); ?>" required>
      </div>
    </div>
    <div class="mb-3">
      <label>Email</label>
      <input type="text" class="form-control" value="<?php echo e($user['email']); ?>" disabled>
    </div>
    <div class="mb-3">
      <label>Role</label>
      <input type="text" class="form-control" value="<?php echo e(role_label($user['role'])); ?>" disabled>
    </div>
    <button type="submit">Save Profile</button>
  </form>

  <hr class="my-4">

  <h4>Change Password</h4>
  <form method="POST" action="profile.php">
    <input type="hidden" name="action" value="change_password">
    <div class="mb-3">
      <label for="current_password">Current Password</label>
      <input type="password" class="form-control" id="current_password" name="current_password" required>
    </div>
    <div class="mb-3">
      <label for="new_password">New Password (min. 8 characters)</label>
      <input type="password" class="form-control" id="new_password" name="new_password" required>
    </div>
    <div class="mb-3">
      <label for="confirm_password">Confirm New Password</label>
      <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
    </div>
    <button type="submit">Change Password</button>
  </form>
</div>

<?php include('../includes/footer.php'); ?>
