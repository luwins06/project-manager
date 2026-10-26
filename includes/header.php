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
  <title>Task Manager</title>
</head>

<body>
  <nav class="navbar navbar-expand-lg bg-body-tertiary">
    <div class="container-fluid">
      <a class="navbar-brand" href="<?php echo $base_url; ?>/index.php">Task Manager</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
        data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false"
        aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <?php if (isset($_SESSION['user_id'])) { ?>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo $base_url; ?>/projects/index.php">Projects</a>
            </li>
            <?php if (in_array($_SESSION['role'], ['admin', 'project_manager'], true)) { ?>
              <li class="nav-item">
                <a class="nav-link" href="<?php echo $base_url; ?>/projects/create.php">New Project</a>
              </li>
            <?php } ?>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo $base_url; ?>/dashboard/index.php">Dashboard</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo $base_url; ?>/tasks/check_deadlines.php">Deadlines</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo $base_url; ?>/activities/index.php">Activity</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo $base_url; ?>/search/index.php">Search</a>
            </li>
            <?php if ($_SESSION['role'] === 'admin') { ?>
              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                  aria-expanded="false">
                  Admin
                </a>
                <ul class="dropdown-menu">
                  <li><a class="dropdown-item" href="<?php echo $base_url; ?>/admin/users.php">Manage Users</a></li>
                </ul>
              </li>
            <?php } ?>
          <?php } ?>
        </ul>
        <?php
        if (isset($_SESSION['user_id'])) {
          $nav_email = htmlspecialchars($_SESSION['email'], ENT_QUOTES, 'UTF-8');
          echo "<ul class='navbar-nav ms-auto'>
                  <li class='nav-item dropdown'>
                    <a class='nav-link dropdown-toggle' href='#' role='button' data-bs-toggle='dropdown' aria-expanded='false'>{$nav_email}</a>
                    <ul class='dropdown-menu dropdown-menu-end'>
                      <li><a class='dropdown-item' href='{$base_url}/user/profile.php'>My Profile</a></li>
                      <li><hr class='dropdown-divider'></li>
                      <li><a class='dropdown-item' href='{$base_url}/user/logout.php'>Logout</a></li>
                    </ul>
                  </li>
                </ul>";
        }
        ?>
      </div>
    </div>
  </nav>
  <div class="container page-content">
    <?php show_flash(); ?>