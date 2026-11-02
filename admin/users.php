<?php
// admin/users.php - User Management (MP2: User CRUD, MP3: Admin Access)
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');
require_role('admin');

$role_filter = trim($_GET['role'] ?? '');

$sql = "SELECT user_id, first_name, last_name, email, role, photo, status, created_at FROM users";
$types = '';
$params = [];

if ($role_filter !== '' && array_key_exists($role_filter, role_options())) {
    $sql .= " WHERE role = ?";
    $types .= 's';
    $params[] = $role_filter;
}
$sql .= " ORDER BY user_id ASC";

$users = db_select($conn, $sql, $types, $params);

include('../includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h2 class="fw-bold mb-0">User Management (MP2)</h2>
    <p class="text-muted mb-0">Manage system users, upload avatars, deactivate accounts, and assign roles.</p>
  </div>
  <a href="<?php echo $base_url; ?>/admin/user_add.php" class="btn btn-primary">
    <i class="fa-solid fa-user-plus me-1"></i> Add New User
  </a>
</div>

<!-- Role Filter Pills -->
<div class="d-flex gap-2 mb-3">
  <span class="text-muted small fw-bold text-uppercase align-self-center me-1">Filter Role:</span>
  <a href="users.php" class="badge text-decoration-none px-3 py-2 rounded-pill <?php echo ($role_filter === '') ? 'bg-primary text-white' : 'bg-light text-dark border'; ?>">All (<?php echo count($users); ?>)</a>
  <?php foreach (role_options() as $r_key => $r_name) {
    $act = ($role_filter === $r_key) ? 'bg-primary text-white' : 'bg-light text-dark border';
    echo "<a href='users.php?role=" . urlencode($r_key) . "' class='badge text-decoration-none px-3 py-2 rounded-pill {$act}'>" . e($r_name) . "</a>";
  } ?>
</div>

<div class="card shadow-sm border-0 table-card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3" style="width: 60px;">Photo</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Role (MP2: 5pts)</th>
            <th>Status (MP2: 5pts)</th>
            <th>Joined Date</th>
            <th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u) { 
            $is_self = ((int)$u['user_id'] === (int)$_SESSION['user_id']);
            $role_badge_colors = [
              'admin'           => 'danger',
              'project_manager' => 'primary',
              'member'          => 'info',
              'customer'        => 'secondary'
            ];
            $rb = $role_badge_colors[$u['role']] ?? 'secondary';
          ?>
            <tr>
              <td class="ps-3">
                <?php echo user_avatar($u['photo'], 40); ?>
              </td>
              <td>
                <div class="fw-bold"><?php echo e($u['first_name'] . ' ' . $u['last_name']); ?> <?php echo $is_self ? '<span class="badge bg-secondary-subtle text-secondary small ms-1">You</span>' : ''; ?></div>
              </td>
              <td><?php echo e($u['email']); ?></td>
              <td>
                <span class="badge bg-<?php echo $rb; ?>-subtle text-<?php echo $rb; ?> border border-<?php echo $rb; ?>-subtle">
                  <?php echo e(role_label($u['role'])); ?>
                </span>
              </td>
              <td>
                <?php if ($u['status'] === 'active') { ?>
                  <span class="badge bg-success-subtle text-success border border-success-subtle">
                    <i class="fa-solid fa-circle-check me-1"></i> Active
                  </span>
                <?php } else { ?>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                    <i class="fa-solid fa-ban me-1"></i> Deactivated
                  </span>
                <?php } ?>
              </td>
              <td><small class="text-muted"><?php echo e(date('M d, Y', strtotime($u['created_at']))); ?></small></td>
              <td class="text-end pe-3">
                <div class="btn-group btn-group-sm">
                  <a href="<?php echo $base_url; ?>/admin/user_edit.php?user_id=<?php echo (int)$u['user_id']; ?>" 
                     class="btn btn-outline-primary" title="Update Role & Details">
                    <i class="fa-solid fa-user-pen"></i>
                  </a>

                  <?php if (!$is_self) { ?>
                    <!-- MP2: Admin can deactivate a user (5pts) -->
                    <form method="POST" action="<?php echo $base_url; ?>/admin/user_toggle.php" class="d-inline"
                          onsubmit="return confirm('<?php echo ($u['status'] === 'active') ? 'Deactivate this user account?' : 'Activate this user account?'; ?>');">
                      <input type="hidden" name="user_id" value="<?php echo (int)$u['user_id']; ?>">
                      <?php if ($u['status'] === 'active') { ?>
                        <button type="submit" class="btn btn-outline-danger" title="Deactivate User">
                          <i class="fa-solid fa-user-slash"></i>
                        </button>
                      <?php } else { ?>
                        <button type="submit" class="btn btn-outline-success" title="Activate User">
                          <i class="fa-solid fa-user-check"></i>
                        </button>
                      <?php } ?>
                    </form>
                  <?php } ?>
                </div>
              </td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>
