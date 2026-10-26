<?php
// FR-1 User Management / FR-2 Role-Based Access - User list (Admin only)
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_role('admin');

$users = db_select($conn, "SELECT user_id, first_name, last_name, email, role, status, created_at FROM users ORDER BY status ASC, last_name ASC, first_name ASC");

include('../includes/header.php');
?>

<div class="table-card">
  <h2>Manage Users</h2>
  <div class="top-actions">
    <a href="user_add.php" class="button">+ Add User</a>
  </div>

  <div class="table-responsive">
    <table class="table align-middle" width="100%" cellpadding="6" cellspacing="0">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $b = 0;
        foreach ($users as $u) {
            $bg_color = ($b++ % 2 == 1) ? 'odd' : 'even';
            $is_self = ($u['user_id'] == $_SESSION['user_id']);
            echo '<tr class="' . $bg_color . '">';
            echo '<td>' . e($u['first_name'] . ' ' . $u['last_name']) . ($is_self ? ' <small class="text-muted">(you)</small>' : '') . '</td>';
            echo '<td>' . e($u['email']) . '</td>';
            echo '<td>' . e(role_label($u['role'])) . '</td>';
            echo '<td>' . ($u['status'] === 'active'
                ? '<span class="badge text-bg-success">Active</span>'
                : '<span class="badge text-bg-secondary">Inactive</span>') . '</td>';
            echo '<td>';
            echo '<a href="user_edit.php?user_id=' . (int)$u['user_id'] . '" class="button">Edit</a> ';
            if (!$is_self) {
                $new_label = ($u['status'] === 'active') ? 'Deactivate' : 'Activate';
                $btn_class = ($u['status'] === 'active') ? 'button-danger' : '';
                echo '<form method="POST" action="user_toggle.php" onsubmit="return confirm(\'' . $new_label . ' this user?\');">';
                echo '<input type="hidden" name="user_id" value="' . (int)$u['user_id'] . '">';
                echo '<button type="submit" class="' . $btn_class . '">' . $new_label . '</button>';
                echo '</form>';
            }
            echo '</td>';
            echo '</tr>';
        }
        ?>
      </tbody>
    </table>
  </div>
</div>

<?php include('../includes/footer.php'); ?>
