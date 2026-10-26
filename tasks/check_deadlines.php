<?php
// FR-8 Deadline Monitoring - Pending tasks grouped by how close their due date is
// Allowed: any logged-in user, limited to the projects they can view (admin = all)
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_login();

// Overdue unfinished tasks are marked Failed before anything is counted
update_overdue_tasks($conn);

/* 1. read GET + validate */
$project_id = (int)($_GET['project_id'] ?? 0);
$bucket     = $_GET['bucket'] ?? 'all';

$buckets = [
    'overdue' => 't.due_date < CURDATE()',
    'today'   => 't.due_date = CURDATE()',
    'week'    => 't.due_date > CURDATE() AND t.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)',
    'later'   => 't.due_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY)',
];

if ($bucket !== 'all' && !isset($buckets[$bucket])) {
    $bucket = 'all';
}

if ($project_id > 0) {
    $project = get_project($conn, $project_id);

    if (!$project || !can_view_project($conn, $project_id)) {
        set_flash('danger', 'Project not found or you are not a member of it.');
        redirect('tasks/check_deadlines.php');
    }

    $scope_sql    = 't.project_id = ?';
    $scope_types  = 'i';
    $scope_params = [$project_id];
} else {
    [$scope_sql, $scope_types, $scope_params] = project_scope('t.project_id');
}

/* 2. queries: counts for the four cards, then the task rows */
$count_row = db_select(
    $conn,
    "SELECT
        COALESCE(SUM({$buckets['overdue']}), 0) AS overdue,
        COALESCE(SUM({$buckets['today']}), 0)   AS today,
        COALESCE(SUM({$buckets['week']}), 0)    AS week,
        COALESCE(SUM({$buckets['later']}), 0)   AS later
     FROM tasks t
     WHERE $scope_sql
       AND t.status <> 'Completed'",
    $scope_types,
    $scope_params
)[0];

$pending_total = (int)$count_row['overdue'] + (int)$count_row['today']
               + (int)$count_row['week'] + (int)$count_row['later'];

$extra = $bucket === 'all' ? '' : ' AND (' . $buckets[$bucket] . ')';

$tasks = db_select(
    $conn,
    "SELECT t.task_id, t.title, t.priority, t.due_date, t.status,
            p.project_id, p.project_name,
            CONCAT(a.first_name, ' ', a.last_name) AS assignee_name
     FROM tasks t
     INNER JOIN projects p ON p.project_id = t.project_id
     LEFT JOIN users a ON a.user_id = t.assigned_to
     WHERE $scope_sql
       AND t.status <> 'Completed'
       $extra
     ORDER BY t.due_date ASC,
              FIELD(t.priority, 'High', 'Medium', 'Low'),
              t.title ASC",
    $scope_types,
    $scope_params
);

$projects = get_visible_projects($conn);

// Link that keeps the selected project while switching the group
function deadline_link($bucket, $project_id)
{
    $q = ['bucket' => $bucket];

    if ($project_id > 0) {
        $q['project_id'] = $project_id;
    }

    return 'check_deadlines.php?' . http_build_query($q);
}

$cards = [
    'overdue' => ['Overdue',        (int)$count_row['overdue']],
    'today'   => ['Due Today',      (int)$count_row['today']],
    'week'    => ['Next 7 Days',    (int)$count_row['week']],
    'later'   => ['Later',          (int)$count_row['later']],
];

include('../includes/header.php');
?>

<div class="table-card">

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h2>Deadline Monitoring</h2>
      <p class="text-muted mb-0">
        Pending tasks, soonest due date first.
        Tasks past their due date are marked Failed automatically.
      </p>
    </div>
    <div class="top-actions mb-0">
      <?php if ($project_id > 0) { ?>
        <a href="index.php?project_id=<?php echo $project_id; ?>" class="button button-secondary">Back to Tasks</a>
      <?php } ?>
    </div>
  </div>

  <form method="GET" action="check_deadlines.php" class="interactive-toolbar">
    <input type="hidden" name="bucket" value="<?php echo e($bucket); ?>">
    <select name="project_id" class="form-select" style="max-width: 320px;" aria-label="Project">
      <option value="0">All my projects</option>
      <?php foreach ($projects as $p) { ?>
        <option value="<?php echo (int)$p['project_id']; ?>" <?php echo $project_id === (int)$p['project_id'] ? 'selected' : ''; ?>>
          <?php echo e($p['project_name']); ?>
        </option>
      <?php } ?>
    </select>
    <button type="submit">Show</button>
    <a href="<?php echo e(deadline_link('all', $project_id)); ?>" class="button button-secondary">
      All pending (<?php echo $pending_total; ?>)
    </a>
  </form>

  <div class="interactive-summary">
    <?php foreach ($cards as $key => $card) { ?>
      <a href="<?php echo e(deadline_link($key, $project_id)); ?>"
         class="summary-card"
         style="text-decoration:none; color:inherit; <?php echo $bucket === $key ? 'outline:3px solid rgba(236,72,153,.25);' : ''; ?>">
        <span class="summary-value"><?php echo $card[1]; ?></span>
        <span class="summary-label"><?php echo e($card[0]); ?></span>
      </a>
    <?php } ?>
  </div>

  <?php if (count($tasks) === 0) { ?>
    <p class="text-center text-muted">
      <?php echo $pending_total === 0
          ? 'No pending tasks. Everything is completed.'
          : 'No pending tasks in this group.'; ?>
    </p>
  <?php } else { ?>
    <div class="table-responsive">
      <table class="table align-middle" width="100%" cellpadding="6" cellspacing="0">
        <thead>
          <tr>
            <th>Task</th>
            <th>Project</th>
            <th>Assigned To</th>
            <th>Priority</th>
            <th>Due</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $b = 0;
          foreach ($tasks as $t) {
              $bg_color = ($b++ % 2 == 1) ? 'odd' : 'even';
              echo '<tr class="' . $bg_color . '">';
              echo '<td><strong>' . e($t['title']) . '</strong></td>';
              echo '<td>' . e($t['project_name']) . '</td>';
              echo '<td>' . ($t['assignee_name'] ? e($t['assignee_name']) : '<span class="text-muted">Unassigned</span>') . '</td>';
              echo '<td>' . priority_badge($t['priority']) . '</td>';
              echo '<td>' . e($t['due_date']) . '</td>';
              echo '<td>' . task_status_badge($t['status']) . '</td>';
              echo '<td><a href="view.php?task_id=' . (int)$t['task_id'] . '" class="button">View</a></td>';
              echo '</tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
  <?php } ?>
</div>

<?php include('../includes/footer.php'); ?>
