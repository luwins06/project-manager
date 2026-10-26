<?php
// FR-1 User Management / FR-2 Role-Based Access - Add user (Admin only)
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_role('admin');

$errors = [];
$data = ['first_name' => '', 'last_name' => '', 'email' => '', 'role' => 'member'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['first_name'] = trim($_POST['first_name'] ?? '');
    $data['last_name']  = trim($_POST['last_name'] ?? '');
    $data['email']      = trim($_POST['email'] ?? '');
    $data['role']       = $_POST['role'] ?? '';
    $password           = $_POST['password'] ?? '';
    $confirm            = $_POST['confirm_password'] ?? '';

    if ($data['first_name'] === '' || $data['last_name'] === '') {
        $errors[] = 'First name and last name are required.';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } elseif (count(db_select($conn, "SELECT 1 FROM users WHERE email = ?", 's', [$data['email']])) > 0) {
        $errors[] = 'That email is already registered.';
    }
    if (!array_key_exists($data['role'], role_options())) {
        $errors[] = 'Select a valid role.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $errors[] = 'Password and confirmation do not match.';
    }

    if (count($errors) === 0) {
        db_run(
            $conn,
            "INSERT INTO users (first_name, last_name, email, password, role, status) VALUES (?, ?, ?, ?, ?, 'active')",
            'sssss',
            [$data['first_name'], $data['last_name'], $data['email'], password_hash($password, PASSWORD_DEFAULT), $data['role']]
        );
        set_flash('success', 'User added.');
        redirect('admin/users.php');
    }
}

include('../includes/header.php');
?>

<div class="form-card">
  <h2>Add User</h2>
  <?php foreach ($errors as $err) { echo "<div class='alert alert-danger'>" . e($err) . "</div>"; } ?>

  <form method="POST" action="user_add.php">
    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="first_name">First Name</label>
        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo e($data['first_name']); ?>" required>
      </div>
      <div class="col-md-6 mb-3">
        <label for="last_name">Last Name</label>
        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo e($data['last_name']); ?>" required>
      </div>
    </div>
    <div class="mb-3">
      <label for="email">Email</label>
      <input type="email" class="form-control" id="email" name="email" value="<?php echo e($data['email']); ?>" required>
    </div>
    <div class="mb-3">
      <label for="role">Role</label>
      <select class="form-select" id="role" name="role">
        <?php foreach (role_options() as $value => $label) {
            $sel = ($data['role'] === $value) ? 'selected' : '';
            echo "<option value='" . e($value) . "' $sel>" . e($label) . "</option>";
        } ?>
      </select>
    </div>
    <div class="mb-3">
      <label for="password">Password (min. 8 characters)</label>
      <input type="password" class="form-control" id="password" name="password" required>
    </div>
    <div class="mb-3">
      <label for="confirm_password">Confirm Password</label>
      <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
    </div>
    <button type="submit">Add User</button>
    <a href="users.php" class="button button-secondary">Cancel</a>
  </form>
</div>

<?php include('../includes/footer.php'); ?>
