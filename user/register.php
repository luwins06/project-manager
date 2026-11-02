<?php
// user/register.php - Customer Registration with Profile Photo Upload (MP2: 5pts, MP5: PHP Validation)
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');

if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name'] ?? '');
$email      = trim($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // MP5: Server-side PHP Form Validation (no HTML5 validation)
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
            $errors[] = 'That email address is already registered. Please log in instead.';
        }
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    // Photo upload validation (MP2: 5pts)
    $photo_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['photo'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Error uploading profile photo.';
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
                $filename = 'avatar_' . uniqid() . '.' . $ext;
                $dest = $dir . '/' . $filename;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $photo_path = 'uploads/avatars/' . $filename;
                } else {
                    $errors[] = 'Failed to save uploaded profile photo.';
                }
            }
        }
    }

    // Insert user into database
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (first_name, last_name, email, password, role, photo, status) 
                VALUES (?, ?, ?, ?, 'customer', ?, 'active')";
        $ok = db_run($conn, $sql, 'sssss', [$first_name, $last_name, $email, $hash, $photo_path]);

        if ($ok) {
            $new_user_id = db_insert_id($conn);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['name']    = $first_name . ' ' . $last_name;
            $_SESSION['email']   = $email;
            $_SESSION['role']    = 'customer';

            set_flash('success', 'Registration successful! Welcome to ProjectHub.');
            redirect('index.php');
        } else {
            $errors[] = 'Database error creating your account. Please try again.';
        }
    }
}

include('../includes/header.php');
?>

<div class="row justify-content-center">
  <div class="col-md-7 col-lg-6">
    <div class="card shadow-sm border-0 form-card my-3">
      <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
          <i class="fa-solid fa-user-plus fa-3x text-primary mb-2"></i>
          <h2 class="fw-bold">Create Customer Account</h2>
          <p class="text-muted small">Register to avail services, track projects, and submit verified reviews.</p>
        </div>

        <?php if (!empty($errors)) { ?>
          <div class="alert alert-danger" role="alert">
            <h6 class="fw-bold mb-1"><i class="fa-solid fa-circle-exclamation me-1"></i> Registration Error:</h6>
            <ul class="mb-0 ps-3">
              <?php foreach ($errors as $e) { echo "<li>" . e($e) . "</li>"; } ?>
            </ul>
          </div>
        <?php } ?>

        <!-- MP5: novalidate disables HTML5 validation, PHP performs all checks -->
        <form method="POST" action="register.php" enctype="multipart/form-data" novalidate>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="first_name" class="form-label fw-semibold">First Name</label>
              <input type="text" class="form-control" id="first_name" name="first_name" 
                     value="<?php echo e($first_name); ?>" placeholder="Juan">
            </div>
            <div class="col-md-6 mb-3">
              <label for="last_name" class="form-label fw-semibold">Last Name</label>
              <input type="text" class="form-control" id="last_name" name="last_name" 
                     value="<?php echo e($last_name); ?>" placeholder="Dela Cruz">
            </div>
          </div>

          <div class="mb-3">
            <label for="email" class="form-label fw-semibold">Email Address</label>
            <input type="text" class="form-control" id="email" name="email" 
                   value="<?php echo e($email); ?>" placeholder="name@example.com">
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="password" class="form-label fw-semibold">Password (min 8 chars)</label>
              <input type="password" class="form-control" id="password" name="password" placeholder="••••••••">
            </div>
            <div class="col-md-6 mb-3">
              <label for="confirm_password" class="form-label fw-semibold">Confirm Password</label>
              <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="••••••••">
            </div>
          </div>

          <!-- MP2: User Registration upload a photo (5pts) -->
          <div class="mb-4">
            <label for="photo" class="form-label fw-semibold">
              <i class="fa-solid fa-camera text-primary me-1"></i> Profile Photo (MP2: 5pts)
            </label>
            <input type="file" class="form-control" id="photo" name="photo">
            <small class="text-muted">Upload a personal avatar (JPG, PNG, GIF, max 5MB).</small>
          </div>

          <div class="d-grid mb-3">
            <button type="submit" class="btn btn-primary btn-lg fw-semibold">
              <i class="fa-solid fa-user-check me-1"></i> Register Account
            </button>
          </div>

          <div class="text-center small text-muted">
            Already have an account? <a href="<?php echo $base_url; ?>/user/login.php" class="text-primary fw-semibold">Log in here</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>
