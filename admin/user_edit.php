<?php
// FR-1 User Management / FR-2 Role-Based Access - Edit user (Admin only)
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_role('admin');

$edit_id = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
$rows = db_select($conn, "SELECT user_id, first_name, last_name, email, role, status FROM users WHERE user_id = ?", 'i', [$edit_id]);
if (count($rows) === 0) {
    set_flash('danger', 'User not found.');
    redirect('admin/users.php');
}
$user = $rows[0];
$is_self = ($user['user_id'] == $_SESSION['user_id']);   // an admin cannot demote or deactivate themselves
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user['first_name'] = trim($_POST['first_name'] ?? '');
    $user['last_name']  = trim($_POST['last_name'] ?? '');
    $user['email']      = trim($_POST['email'] ?? '');
    $role   = $is_self ? $user['role'] : ($_POST['role'] ?? '');
    $status = $is_self ? $user['status'] : ($_POST['status'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($user['first_name'] === '' || $user['last_name'] === '') {
        $errors[] = 'First name and last name are required.';
    }
    if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } elseif (count(db_select($conn, "SELECT 1 FROM users WHERE email = ? AND user_id <> ?", 'si', [$user['email'], $edit_id])) > 0) {
        $errors[] = 'That email is already used by another account.';
    }
    if (!array_key_exists($role, role_options())) {
        $errors[] = 'Select a valid role.';
    }
    if (!in_array($status, ['active', 'inactive'], true)) {
        $errors[] = 'Select a valid status.';
    }
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }

    if (count($errors) === 0) {
        db_run(
            $conn,
            "UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, status = ? WHERE user_id = ?",
            'sssssi',
            [$user['first_name'], $user['last_name'], $user['email'], $role, $status, $edit_id]
        );
        if ($password !== '') {
            db_run($conn, "UPDATE users SET password = ? WHERE user_id = ?", 'si', [password_hash($password, PASSWORD_DEFAULT), $edit_id]);
        }
        if ($is_self) {
            $_SESSION['name']  = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['email'] = $user['email'];
        }
        set_flash('success', 'User updated.');
        redirect('admin/users.php');
    }
    $user['role'] = $role;
    $user['status'] = $status;
}

include('../includes/header.php');
?>

<div class="form-card">
  <h2>Edit User</h2>
  <?php foreach ($errors as $err) { echo "<div class='alert alert-danger'>" . e($err) . "</div>"; } ?>

  <form method="POST" action="user_edit.php">
    <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">
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
      <label for="email">Email</label>
      <input type="email" class="form-control" id="email" name="email" value="<?php echo e($user['email']); ?>" required>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="role">Role</label>
        <select class="form-select" id="role" name="role" <?php echo $is_self ? 'disabled' : ''; ?>>
          <?php foreach (role_options() as $value => $label) {
              $sel = ($user['role'] === $value) ? 'selected' : '';
              echo "<option value='" . e($value) . "' $sel>" . e($label) . "</option>";
          } ?>
        </select>
      </div>
      <div class="col-md-6 mb-3">
        <label for="status">Status</label>
        <select class="form-select" id="status" name="status" <?php echo $is_self ? 'disabled' : ''; ?>>
          <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
          <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
        </select>
      </div>
    </div>
    <?php if ($is_self) { echo "<p class='text-muted small'>You cannot change your own role or status.</p>"; } ?>
    <div class="mb-3">
      <label for="password">Reset Password (leave blank to keep current)</label>
      <input type="password" class="form-control" id="password" name="password">
    </div>
    <button type="submit">Save Changes</button>
    <a href="users.php" class="button button-secondary">Cancel</a>
  </form>
</div>

<?php include('../includes/footer.php'); ?>
