<?php
// FR-4 Project Membership - Add / remove members and assign responsibilities
// Allowed: Admin, or the Project Manager who created the project
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_role(['admin', 'project_manager']);

$project_id = (int)($_GET['project_id'] ?? 0);
$project = get_project($conn, $project_id);

if (!$project) {
    set_flash('danger', 'Project not found.');
    redirect('projects/index.php');
}
if (!can_manage_project($conn, $project_id)) {
    set_flash('danger', 'Only the project manager who created this project (or an admin) can manage its members.');
    redirect('projects/view.php?project_id=' . $project_id);
}

$members = db_select(
    $conn,
    "SELECT u.user_id, u.first_name, u.last_name, u.email, u.status, pm.responsibility
     FROM project_members pm
     INNER JOIN users u ON u.user_id = pm.user_id
     WHERE pm.project_id = ?
     ORDER BY pm.added_at ASC",
    'i',
    [$project_id]
);

// Active users that are not yet in this project
$available = db_select(
    $conn,
    "SELECT user_id, first_name, last_name, email, role
     FROM users
     WHERE status = 'active'
       AND user_id NOT IN (SELECT user_id FROM project_members WHERE project_id = ?)
     ORDER BY last_name ASC, first_name ASC",
    'i',
    [$project_id]
);

include('../includes/header.php');
?>

<div class="table-card">
  <h2>Members: <?php echo e($project['project_name']); ?></h2>

  <div class="table-responsive">
    <table class="table align-middle" width="100%" cellpadding="6" cellspacing="0">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Responsibility</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $b = 0;
        foreach ($members as $m) {
            $bg_color = ($b++ % 2 == 1) ? 'odd' : 'even';
            $is_owner = ($m['user_id'] == $project['created_by']);
            echo '<tr class="' . $bg_color . '">';
            echo '<td>' . e($m['first_name'] . ' ' . $m['last_name'])
                . ($m['status'] === 'inactive' ? ' <span class="badge text-bg-secondary">Inactive</span>' : '')
                . ($is_owner ? ' <span class="badge text-bg-info">Owner</span>' : '') . '</td>';
            echo '<td>' . e($m['email']) . '</td>';
            echo '<td>';
            echo '<form method="POST" action="member_update.php" class="d-flex gap-1">';
            echo '<input type="hidden" name="project_id" value="' . $project_id . '">';
            echo '<input type="hidden" name="user_id" value="' . (int)$m['user_id'] . '">';
            echo '<input type="text" class="form-control form-control-sm" name="responsibility" maxlength="100" value="' . e($m['responsibility']) . '" required>';
            echo '<button type="submit">Save</button>';
            echo '</form>';
            echo '</td>';
            echo '<td>';
            if (!$is_owner) {
                echo '<form method="POST" action="member_remove.php" onsubmit="return confirm(\'Remove this member from the project?\');">';
                echo '<input type="hidden" name="project_id" value="' . $project_id . '">';
                echo '<input type="hidden" name="user_id" value="' . (int)$m['user_id'] . '">';
                echo '<button type="submit" class="button-danger">Remove</button>';
                echo '</form>';
            } else {
                echo '<span class="text-muted small">Cannot remove owner</span>';
            }
            echo '</td>';
            echo '</tr>';
        }
        ?>
      </tbody>
    </table>
  </div>

  <hr>
  <h4>Add Member</h4>
  <?php if (count($available) === 0) { ?>
    <p class="text-muted">All active users are already in this project.</p>
  <?php } else { ?>
    <form method="POST" action="member_add.php">
      <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
      <div class="row">
        <div class="col-md-5 mb-3">
          <label for="user_id">User</label>
          <select class="form-select" id="user_id" name="user_id" required>
            <option value="">-- Select user --</option>
            <?php foreach ($available as $u) {
                echo "<option value='" . (int)$u['user_id'] . "'>" . e($u['first_name'] . ' ' . $u['last_name'] . ' (' . role_label($u['role']) . ')') . "</option>";
            } ?>
          </select>
        </div>
        <div class="col-md-5 mb-3">
          <label for="responsibility">Responsibility</label>
          <input type="text" class="form-control" id="responsibility" name="responsibility" maxlength="100" placeholder="e.g. Developer, Designer, QA" required>
        </div>
        <div class="col-md-2 mb-3 d-flex align-items-end">
          <button type="submit">Add</button>
        </div>
      </div>
    </form>
  <?php } ?>

  <a href="view.php?project_id=<?php echo $project_id; ?>" class="button button-secondary">Back to Project</a>
</div>

<?php include('../includes/footer.php'); ?>
