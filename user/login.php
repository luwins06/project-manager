<?php
// FR-1 User Management - Login
session_start();
include('../includes/config.php');
include('../includes/functions.php');

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $rows = db_select($conn, "SELECT user_id, first_name, last_name, email, password, role, status FROM users WHERE email = ?", 's', [$email]);
    $user = $rows[0] ?? null;

    if (!$user || !password_verify($password, $user['password'])) {
        $error = 'Invalid email or password.';
    } elseif ($user['status'] !== 'active') {
        $error = 'Your account is deactivated. Please contact an administrator.';
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['name']    = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['email']   = $user['email'];
        $_SESSION['role']    = $user['role'];
        redirect('index.php');
    }
}

include('../includes/header.php');
?>

<div class="form-card">
  <h2>Login</h2>
  <?php if ($error !== '') { echo "<div class='alert alert-danger'>" . e($error) . "</div>"; } ?>
  <form method="POST" action="login.php">
    <div class="mb-3">
      <label for="email">Email</label>
      <input type="email" class="form-control" id="email" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>" required>
    </div>
    <div class="mb-3">
      <label for="password">Password</label>
      <input type="password" class="form-control" id="password" name="password" required>
    </div>
    <button type="submit">Login</button>
  </form>
</div>

<?php include('../includes/footer.php'); ?>
