<?php
// FR-3 Project Creation - Project list (admin: all projects, others: projects they belong to)
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_login();

$base_sql = "SELECT p.project_id, p.project_name, p.start_date, p.deadline, p.status,
                    CONCAT(u.first_name, ' ', u.last_name) AS owner_name,
                    (SELECT COUNT(*) FROM project_members pm2 WHERE pm2.project_id = p.project_id) AS member_count
             FROM projects p
             INNER JOIN users u ON u.user_id = p.created_by";

if (has_role('admin')) {
    $projects = db_select($conn, $base_sql . " ORDER BY p.deadline ASC");
} else {
    $projects = db_select(
        $conn,
        $base_sql . " INNER JOIN project_members pm ON pm.project_id = p.project_id AND pm.user_id = ?
                      ORDER BY p.deadline ASC",
        'i',
        [$_SESSION['user_id']]
    );
}

include('../includes/header.php');
?>

<div class="table-card">
  <h2>Projects</h2>
  <?php if (has_role(['admin', 'project_manager'])) { ?>
    <div class="top-actions">
      <a href="create.php" class="button">+ New Project</a>
    </div>
  <?php } ?>

  <?php if (count($projects) === 0) { ?>
    <p class="text-center text-muted">No projects to show yet.</p>
  <?php } else { ?>
    <div class="table-responsive">
      <table class="table align-middle" width="100%" cellpadding="6" cellspacing="0">
        <thead>
          <tr>
            <th>Project</th>
            <th>Manager</th>
            <th>Start</th>
            <th>Deadline</th>
            <th>Status</th>
            <th>Members</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $b = 0;
          foreach ($projects as $p) {
              $bg_color = ($b++ % 2 == 1) ? 'odd' : 'even';
              echo '<tr class="' . $bg_color . '">';
              echo '<td>' . e($p['project_name']) . '</td>';
              echo '<td>' . e($p['owner_name']) . '</td>';
              echo '<td>' . e($p['start_date']) . '</td>';
              echo '<td>' . e($p['deadline']) . '</td>';
              echo '<td>' . status_badge($p['status']) . '</td>';
              echo '<td>' . (int)$p['member_count'] . '</td>';
              echo '<td><a href="view.php?project_id=' . (int)$p['project_id'] . '" class="button">View</a> ';
              echo '<a href="../tasks/index.php?project_id=' . (int)$p['project_id'] . '" class="button">Tasks</a> ';
              echo '<a href="../dashboard/index.php?project_id=' . (int)$p['project_id'] . '" class="button">Dashboard</a></td>';
              echo '</tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
  <?php } ?>
</div>

<?php include('../includes/footer.php'); ?>
