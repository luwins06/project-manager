<?php
// FR-11 Project Dashboard - progress, task counts, overdue tasks, members and recent activity
// Allowed: any logged-in user, limited to the projects they can view (admin = all)
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_login();

// Overdue unfinished tasks are marked Failed before anything is counted
update_overdue_tasks($conn);

/* 1. read GET + validate */
$project_id = (int)($_GET['project_id'] ?? 0);
$project    = null;

if ($project_id > 0) {
    $project = get_project($conn, $project_id);

    if (!$project || !can_view_project($conn, $project_id)) {
        set_flash('danger', 'Project not found or you are not a member of it.');
        redirect('dashboard/index.php');
    }

    $scope_sql    = 't.project_id = ?';
    $scope_types  = 'i';
    $scope_params = [$project_id];
} else {
    [$scope_sql, $scope_types, $scope_params] = project_scope('t.project_id');
}

/* 2. queries */

// Task counts for the selected project (or for all visible projects)
$s = db_select(
    $conn,
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(t.status = 'Completed'), 0)   AS completed,
            COALESCE(SUM(t.status = 'To Do'), 0)       AS todo,
            COALESCE(SUM(t.status = 'In Progress'), 0) AS inprogress,
            COALESCE(SUM(t.status = 'Failed'), 0)      AS failed,
            COALESCE(SUM(t.status <> 'Completed' AND t.due_date < CURDATE()), 0) AS overdue,
            COALESCE(SUM(t.assigned_to IS NULL), 0)    AS unassigned
     FROM tasks t
     WHERE $scope_sql",
    $scope_types,
    $scope_params
)[0];

$total      = (int)$s['total'];
$completed  = (int)$s['completed'];
$todo       = (int)$s['todo'];
$inprogress = (int)$s['inprogress'];
$failed     = (int)$s['failed'];
$overdue    = (int)$s['overdue'];
$unassigned = (int)$s['unassigned'];
$pending    = $todo + $inprogress;

// Same rule as get_project_progress(): Completed 100%, In Progress 50%, others 0%
$progress = $total > 0 ? (int)round(($completed + $inprogress * 0.5) / $total * 100) : 0;

// Overdue tasks (most late first)
$overdue_tasks = db_select(
    $conn,
    "SELECT t.task_id, t.title, t.due_date, t.status,
            p.project_name,
            CONCAT(a.first_name, ' ', a.last_name) AS assignee_name
     FROM tasks t
     INNER JOIN projects p ON p.project_id = t.project_id
     LEFT JOIN users a ON a.user_id = t.assigned_to
     WHERE $scope_sql
       AND t.status <> 'Completed'
       AND t.due_date < CURDATE()
     ORDER BY t.due_date ASC
     LIMIT 10",
    $scope_types,
    $scope_params
);

// Recent activity
if ($project_id > 0) {
    $act_where = 'a.project_id = ?';
    $act_types = 'i';
    $act_params = [$project_id];
} else {
    [$act_where, $act_types, $act_params] = project_scope('a.project_id');
}

$recent = db_select(
    $conn,
    "SELECT a.project_id, a.activity_type, a.description, a.created_at,
            p.project_name,
            CONCAT(u.first_name, ' ', u.last_name) AS actor_name
     FROM activity_log a
     INNER JOIN projects p ON p.project_id = a.project_id
     LEFT JOIN users u ON u.user_id = a.user_id
     WHERE $act_where
     ORDER BY a.created_at DESC, a.activity_id DESC
     LIMIT " . ($project_id > 0 ? 5 : 8),
    $act_types,
    $act_params
);

$members       = [];
$project_cards = [];

if ($project_id > 0) {
    // Assigned members and their workload in this project
    $members = db_select(
        $conn,
        "SELECT u.user_id,
                CONCAT(u.first_name, ' ', u.last_name) AS name,
                pm.responsibility,
                COUNT(t.task_id) AS assigned,
                COALESCE(SUM(t.status = 'Completed'), 0) AS completed,
                COALESCE(SUM(t.status IN ('To Do', 'In Progress')), 0) AS pending,
                COALESCE(SUM(t.status <> 'Completed' AND t.due_date < CURDATE()), 0) AS overdue
         FROM project_members pm
         INNER JOIN users u ON u.user_id = pm.user_id
         LEFT JOIN tasks t
                ON t.project_id = pm.project_id
               AND t.assigned_to = u.user_id
         WHERE pm.project_id = ?
         GROUP BY pm.project_member_id, u.user_id, u.first_name, u.last_name, pm.responsibility
         ORDER BY u.last_name ASC, u.first_name ASC",
        'i',
        [$project_id]
    );
} else {
    // One card per visible project
    [$pw, $pt, $pp] = project_scope('p.project_id');

    $project_cards = db_select(
        $conn,
        "SELECT p.project_id, p.project_name, p.status, p.deadline,
                (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.project_id) AS member_count,
                COUNT(t.task_id) AS total,
                COALESCE(SUM(t.status = 'Completed'), 0)   AS completed,
                COALESCE(SUM(t.status = 'In Progress'), 0) AS inprogress,
                COALESCE(SUM(t.status <> 'Completed' AND t.due_date < CURDATE()), 0) AS overdue
         FROM projects p
         LEFT JOIN tasks t ON t.project_id = p.project_id
         WHERE $pw
         GROUP BY p.project_id, p.project_name, p.status, p.deadline
         ORDER BY p.deadline ASC",
        $pt,
        $pp
    );
}

$projects = get_visible_projects($conn);

include('../includes/header.php');
?>

<div class="table-card">

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h2>Dashboard<?php echo $project ? ': ' . e($project['project_name']) : ''; ?></h2>
      <p class="text-muted mb-0">
        <?php echo $project
            ? 'Progress, tasks, deadlines and members of this project.'
            : 'Overview of every project you can access.'; ?>
      </p>
    </div>
    <?php if ($project) { ?>
      <div class="top-actions mb-0">
        <a href="../projects/view.php?project_id=<?php echo $project_id; ?>" class="button button-secondary">Project</a>
        <a href="../tasks/index.php?project_id=<?php echo $project_id; ?>" class="button">Tasks</a>
      </div>
    <?php } ?>
  </div>

  <form method="GET" action="index.php" class="interactive-toolbar">
    <select name="project_id" class="form-select" style="max-width: 320px;" aria-label="Project">
      <option value="0">All my projects</option>
      <?php foreach ($projects as $p) { ?>
        <option value="<?php echo (int)$p['project_id']; ?>" <?php echo $project_id === (int)$p['project_id'] ? 'selected' : ''; ?>>
          <?php echo e($p['project_name']); ?>
        </option>
      <?php } ?>
    </select>
    <button type="submit">Show</button>
  </form>

  <?php if ($project) { ?>
    <div class="row mb-3">
      <div class="col-md-3"><strong>Status</strong><br><?php echo status_badge($project['status']); ?></div>
      <div class="col-md-3"><strong>Start Date</strong><br><?php echo e($project['start_date']); ?></div>
      <div class="col-md-3"><strong>Deadline</strong><br><?php echo e($project['deadline']); ?></div>
      <div class="col-md-3"><strong>Created By</strong><br><?php echo e($project['owner_name']); ?></div>
    </div>
  <?php } ?>

  <!-- TASK TOTALS -->
  <div class="interactive-summary">
    <div class="summary-card">
      <span class="summary-value"><?php echo $total; ?></span>
      <span class="summary-label">Total Tasks</span>
    </div>
    <div class="summary-card">
      <span class="summary-value"><?php echo $completed; ?></span>
      <span class="summary-label">Completed</span>
    </div>
    <div class="summary-card">
      <span class="summary-value"><?php echo $pending; ?></span>
      <span class="summary-label">Pending</span>
    </div>
    <div class="summary-card">
      <span class="summary-value"><?php echo $overdue; ?></span>
      <span class="summary-label">Overdue</span>
    </div>
  </div>

  <!-- PROGRESS -->
  <div class="project-progress-box">
    <div class="d-flex justify-content-between">
      <strong><?php echo $project ? 'Project progress' : 'Overall progress'; ?></strong>
      <strong><?php echo $progress; ?>% complete</strong>
    </div>
    <div class="mini-progress" role="progressbar" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
      <span style="width: <?php echo $progress; ?>%;"></span>
    </div>
    <small class="text-muted">
      Calculated from task status: Completed = 100%, In Progress = 50%, To Do or Failed = 0%.
    </small>
  </div>

  <!-- STATUS BREAKDOWN -->
  <div class="d-flex flex-wrap gap-3 mb-3">
    <span><?php echo task_status_badge('To Do'); ?> <strong><?php echo $todo; ?></strong></span>
    <span><?php echo task_status_badge('In Progress'); ?> <strong><?php echo $inprogress; ?></strong></span>
    <span><?php echo task_status_badge('Completed'); ?> <strong><?php echo $completed; ?></strong></span>
    <span><?php echo task_status_badge('Failed'); ?> <strong><?php echo $failed; ?></strong></span>
    <span class="text-muted">Unassigned tasks: <strong><?php echo $unassigned; ?></strong></span>
  </div>

  <?php if ($project) { ?>

    <!-- ASSIGNED MEMBERS -->
    <hr>
    <h4>Assigned Members (<?php echo count($members); ?>)</h4>
    <?php if (count($members) === 0) { ?>
      <p class="text-muted">This project has no members yet.</p>
    <?php } else { ?>
      <div class="table-responsive">
        <table class="table align-middle" width="100%" cellpadding="6" cellspacing="0">
          <thead>
            <tr>
              <th>Member</th>
              <th>Responsibility</th>
              <th>Assigned</th>
              <th>Completed</th>
              <th>Pending</th>
              <th>Overdue</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $b = 0;
            foreach ($members as $m) {
                $bg_color = ($b++ % 2 == 1) ? 'odd' : 'even';
                echo '<tr class="' . $bg_color . '">';
                echo '<td>' . e($m['name']) . '</td>';
                echo '<td>' . e($m['responsibility']) . '</td>';
                echo '<td>' . (int)$m['assigned'] . '</td>';
                echo '<td>' . (int)$m['completed'] . '</td>';
                echo '<td>' . (int)$m['pending'] . '</td>';
                echo '<td>' . (int)$m['overdue'] . '</td>';
                echo '</tr>';
            }
            ?>
          </tbody>
        </table>
      </div>
    <?php } ?>

  <?php } else { ?>

    <!-- PROJECT CARDS -->
    <hr>
    <h4>Projects (<?php echo count($project_cards); ?>)</h4>
    <?php if (count($project_cards) === 0) { ?>
      <p class="text-muted">No projects to show yet.</p>
    <?php } else { ?>
      <div class="project-grid mb-3">
        <?php foreach ($project_cards as $c) {
            $c_total = (int)$c['total'];
            $c_pct = $c_total > 0
                ? (int)round(((int)$c['completed'] + (int)$c['inprogress'] * 0.5) / $c_total * 100)
                : 0;
        ?>
          <div class="project-card">
            <h4><?php echo e($c['project_name']); ?></h4>
            <?php echo status_badge($c['status']); ?>
            <div class="project-meta">
              <span><strong>Deadline:</strong> <?php echo e($c['deadline']); ?></span>
              <span><strong>Members:</strong> <?php echo (int)$c['member_count']; ?></span>
              <span><strong>Tasks:</strong> <?php echo (int)$c['completed']; ?> of <?php echo $c_total; ?> completed</span>
              <span><strong>Overdue:</strong> <?php echo (int)$c['overdue']; ?></span>
            </div>
            <div class="mini-progress"><span style="width: <?php echo $c_pct; ?>%;"></span></div>
            <small class="text-muted"><?php echo $c_pct; ?>% complete</small>
            <div class="project-actions mt-2">
              <a href="index.php?project_id=<?php echo (int)$c['project_id']; ?>" class="button">Dashboard</a>
              <a href="../tasks/index.php?project_id=<?php echo (int)$c['project_id']; ?>" class="button button-secondary">Tasks</a>
            </div>
          </div>
        <?php } ?>
      </div>
    <?php } ?>

  <?php } ?>

  <!-- OVERDUE TASKS -->
  <hr>
  <h4>Overdue Tasks</h4>
  <?php if (count($overdue_tasks) === 0) { ?>
    <p class="text-muted">No overdue tasks.</p>
  <?php } else { ?>
    <div class="table-responsive">
      <table class="table align-middle" width="100%" cellpadding="6" cellspacing="0">
        <thead>
          <tr>
            <th>Task</th>
            <?php if (!$project) { ?><th>Project</th><?php } ?>
            <th>Assigned To</th>
            <th>Due</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $b = 0;
          foreach ($overdue_tasks as $t) {
              $bg_color = ($b++ % 2 == 1) ? 'odd' : 'even';
              echo '<tr class="' . $bg_color . '">';
              echo '<td><strong>' . e($t['title']) . '</strong></td>';
              if (!$project) {
                  echo '<td>' . e($t['project_name']) . '</td>';
              }
              echo '<td>' . ($t['assignee_name'] ? e($t['assignee_name']) : '<span class="text-muted">Unassigned</span>') . '</td>';
              echo '<td>' . e($t['due_date']) . '</td>';
              echo '<td>' . task_status_badge($t['status']) . '</td>';
              echo '<td><a href="../tasks/view.php?task_id=' . (int)$t['task_id'] . '" class="button">View</a></td>';
              echo '</tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
  <?php } ?>

  <!-- RECENT ACTIVITY -->
  <hr>
  <div class="d-flex justify-content-between align-items-center">
    <h4 class="mb-0">Recent Activity</h4>
    <a href="../activities/index.php<?php echo $project_id > 0 ? '?project_id=' . $project_id : ''; ?>" class="button button-secondary">Full Log</a>
  </div>
  <div class="mt-2">
    <?php render_activity_list($recent, $project_id === 0); ?>
  </div>

</div>

<?php include('../includes/footer.php'); ?>
