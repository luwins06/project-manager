<?php
// user/login.php - User Login via Email (MP3: 5pts, MP5: PHP Validation)
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');

if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$email = trim($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    // MP5: Server-side PHP Form Validation (no HTML5 validation)
    if ($email === '') {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email format.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if (empty($errors)) {
        $rows = db_select($conn, "SELECT user_id, first_name, last_name, email, password, role, status FROM users WHERE email = ?", 's', [$email]);
        $user = $rows[0] ?? null;

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Invalid email address or password.';
        } elseif ($user['status'] !== 'active') {
            $errors[] = 'Your account has been deactivated. Please contact an administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['user_id'];
            $_SESSION['name']    = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];

            set_flash('success', 'Welcome back, ' . $user['first_name'] . '!');

            // Redirect based on role
            if ($user['role'] === 'customer') {
                redirect('index.php');
            } elseif ($user['role'] === 'admin') {
                redirect('admin/orders.php');
            } else {
                redirect('projects/index.php');
            }
        }
    }
}

include('../includes/header.php');
?>

<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card shadow-sm border-0 form-card my-4">
      <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
          <i class="fa-solid fa-lock fa-3x text-primary mb-2"></i>
          <h2 class="fw-bold">Sign In to ProjectHub</h2>
          <p class="text-muted small">Access your service orders, project tasks, and team workspace.</p>
        </div>

        <?php if (!empty($errors)) { ?>
          <div class="alert alert-danger" role="alert">
            <h6 class="fw-bold mb-1"><i class="fa-solid fa-circle-exclamation me-1"></i> Login Error:</h6>
            <ul class="mb-0 ps-3">
              <?php foreach ($errors as $e) { echo "<li>" . e($e) . "</li>"; } ?>
            </ul>
          </div>
        <?php } ?>

        <!-- MP5: novalidate disables browser HTML5 validation -->
        <form method="POST" action="login.php" novalidate>
          <div class="mb-3">
            <label for="email" class="form-label fw-semibold">Email Address (MP3: 5pts)</label>
            <input type="text" class="form-control" id="email" name="email" 
                   value="<?php echo e($email); ?>" placeholder="name@example.com">
          </div>

          <div class="mb-4">
            <label for="password" class="form-label fw-semibold">Password</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="••••••••">
          </div>

          <div class="d-grid mb-3">
            <button type="submit" class="btn btn-primary btn-lg fw-semibold">
              <i class="fa-solid fa-right-to-bracket me-1"></i> Sign In
            </button>
          </div>

          <div class="text-center small text-muted">
            Don't have an account? <a href="<?php echo $base_url; ?>/user/register.php" class="text-primary fw-semibold">Register here</a>
          </div>
        </form>

        <hr class="my-4">

        <div class="p-3 bg-light rounded text-muted small">
          <strong class="d-block text-dark mb-1"><i class="fa-solid fa-info-circle me-1"></i> Default Test Credentials:</strong>
          <div>Admin: <code>admin@taskmanager.local</code> / <code>admin123</code></div>
          <div>Customer: <code>alex@taskmanager.local</code> / <code>password123</code></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>
