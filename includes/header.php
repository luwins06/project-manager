<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$cur_user_id = $_SESSION['user_id'] ?? null;
$cur_role    = $_SESSION['role'] ?? null;
$cur_photo   = null;

if ($cur_user_id && isset($conn)) {
    $u_rows = db_select($conn, "SELECT photo FROM users WHERE user_id = ?", 'i', [$cur_user_id]);
    $cur_photo = $u_rows[0]['photo'] ?? null;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
    integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link href="<?php echo $base_url; ?>/includes/style/style.css" rel="stylesheet" type="text/css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous">
  </script>
  <title>ProjectHub - Project & Service Management</title>
</head>

<body>
  <nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom shadow-sm">
    <div class="container-fluid">
      <a class="navbar-brand fw-bold text-primary d-flex align-items-center gap-2" href="<?php echo $base_url; ?>/index.php">
        <i class="fa-solid fa-cubes-stacked"></i> ProjectHub
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
        data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false"
        aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item">
            <a class="nav-link" href="<?php echo $base_url; ?>/index.php"><i class="fa-solid fa-store me-1"></i> Services</a>
          </li>

          <?php if ($cur_user_id) { ?>
            <?php if ($cur_role === 'customer') { ?>
              <li class="nav-item">
                <a class="nav-link" href="<?php echo $base_url; ?>/orders/my_orders.php"><i class="fa-solid fa-receipt me-1"></i> My Orders</a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="<?php echo $base_url; ?>/projects/index.php"><i class="fa-solid fa-diagram-project me-1"></i> My Projects</a>
              </li>
            <?php } ?>

            <?php if (in_array($cur_role, ['admin', 'project_manager', 'member'], true)) { ?>
              <li class="nav-item">
                <a class="nav-link" href="<?php echo $base_url; ?>/projects/index.php"><i class="fa-solid fa-folder me-1"></i> Projects</a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="<?php echo $base_url; ?>/tasks/index.php"><i class="fa-solid fa-list-check me-1"></i> Tasks</a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="<?php echo $base_url; ?>/dashboard/index.php"><i class="fa-solid fa-chart-line me-1"></i> Dashboard</a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="<?php echo $base_url; ?>/tasks/check_deadlines.php"><i class="fa-solid fa-clock me-1"></i> Deadlines</a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="<?php echo $base_url; ?>/activities/index.php"><i class="fa-solid fa-clock-rotate-left me-1"></i> Activity</a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="<?php echo $base_url; ?>/search/index.php"><i class="fa-solid fa-magnifying-glass me-1"></i> Search</a>
              </li>
            <?php } ?>

            <?php if ($cur_role === 'admin') { ?>
              <li class="nav-item">
                <a class="nav-link" href="<?php echo $base_url; ?>/services/index.php"><i class="fa-solid fa-gear me-1"></i> Manage Services</a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="<?php echo $base_url; ?>/admin/orders.php"><i class="fa-solid fa-file-invoice-dollar me-1"></i> Orders</a>
              </li>
              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="fa-solid fa-user-shield me-1"></i> Admin
                </a>
                <ul class="dropdown-menu">
                  <li><a class="dropdown-item" href="<?php echo $base_url; ?>/admin/users.php"><i class="fa-solid fa-users-gear me-2"></i> Manage Users</a></li>
                  <li><a class="dropdown-item" href="<?php echo $base_url; ?>/services/create.php"><i class="fa-solid fa-plus me-2"></i> Add Service</a></li>
                </ul>
              </li>
            <?php } ?>
          <?php } ?>
        </ul>

        <ul class="navbar-nav ms-auto align-items-center">
          <?php if ($cur_user_id) { 
            $nav_email = htmlspecialchars($_SESSION['email'], ENT_QUOTES, 'UTF-8');
            $nav_name  = htmlspecialchars($_SESSION['name'] ?? 'User', ENT_QUOTES, 'UTF-8');
          ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?php echo user_avatar($cur_photo, 32); ?>
                <span class="d-none d-sm-inline"><?php echo $nav_name; ?></span>
                <span class="badge bg-secondary-subtle text-secondary border"><?php echo e(role_label($cur_role)); ?></span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li class="dropdown-header">Signed in as<br><strong><?php echo $nav_email; ?></strong></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?php echo $base_url; ?>/user/profile.php"><i class="fa-solid fa-id-card me-2"></i> My Profile</a></li>
                <?php if ($cur_role === 'customer') { ?>
                  <li><a class="dropdown-item" href="<?php echo $base_url; ?>/orders/my_orders.php"><i class="fa-solid fa-receipt me-2"></i> My Orders</a></li>
                <?php } ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?php echo $base_url; ?>/user/logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Logout</a></li>
              </ul>
            </li>
          <?php } else { ?>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo $base_url; ?>/user/login.php"><i class="fa-solid fa-right-to-bracket me-1"></i> Login</a>
            </li>
            <li class="nav-item ms-lg-2">
              <a class="btn btn-primary btn-sm px-3" href="<?php echo $base_url; ?>/user/register.php"><i class="fa-solid fa-user-plus me-1"></i> Register</a>
            </li>
          <?php } ?>
        </ul>
      </div>
    </div>
  </nav>
  <div class="container page-content py-4">
    <?php show_flash(); ?>
