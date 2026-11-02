<?php
// FR-12 Search & Filtering - Find tasks or projects by keyword, project, member, status, priority and deadline
// Allowed: any logged-in user, limited to the projects they can view (admin = all)
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_login();

update_overdue_tasks($conn);

/* 1. read GET + validate */
$type       = (($_GET['type'] ?? 'tasks') === 'projects') ? 'projects' : 'tasks';
$q          = trim($_GET['q'] ?? '');
$project_id = (int)($_GET['project_id'] ?? 0);
$member     = (string)($_GET['member'] ?? '');       // '' = anyone, 'none' = unassigned, else a user id
$status     = $_GET['status'] ?? '';
$priority   = $_GET['priority'] ?? '';
$deadline   = $_GET['deadline'] ?? '';
$from       = trim($_GET['from'] ?? '');
$to         = trim($_GET['to'] ?? '');
$errors     = [];

$deadline_options = [
    'overdue' => 'Overdue',
    'today'   => 'Due today',
    'week'    => 'Next 7 days',
    'month'   => 'Next 30 days',
];

$status_list = ($type === 'projects') ? project_statuses() : task_statuses();

if ($status !== '' && !in_array($status, $status_list, true)) {
    $status = '';
}
if ($type === 'projects' || ($priority !== '' && !in_array($priority, task_priorities(), true))) {
    $priority = '';
}
if ($deadline !== '' && !isset($deadline_options[$deadline])) {
    $deadline = '';
}
if ($member !== '' && $member !== 'none' && !ctype_digit($member)) {
    $member = '';
}
if ($from !== '' && !valid_date($from)) {
    $errors[] = 'The "from" date is not valid, so it was ignored.';
    $from = '';
}
if ($to !== '' && !valid_date($to)) {
    $errors[] = 'The "to" date is not valid, so it was ignored.';
    $to = '';
}
if ($from !== '' && $to !== '' && $to < $from) {
    $errors[] = 'The "to" date is earlier than the "from" date, so no results can match.';
}

// Only projects the user can view may be chosen
$projects = get_visible_projects($conn);
$visible_ids = array_map('intval', array_column($projects, 'project_id'));

if ($project_id > 0 && !in_array($project_id, $visible_ids, true)) {
    $project_id = 0;
}
if ($type === 'projects') {
    $project_id = 0;
}

// People that can be picked in the member filter
[$mw, $mt, $mp] = project_scope('pm.project_id');
$member_sql = "SELECT DISTINCT u.user_id, u.first_name, u.last_name
               FROM project_members pm
               INNER JOIN users u ON u.user_id = pm.user_id
               WHERE $mw";

if ($project_id > 0) {
    $member_sql .= ' AND pm.project_id = ?';
    $mt .= 'i';
    $mp[] = $project_id;
}

$people = db_select($conn, $member_sql . ' ORDER BY u.last_name ASC, u.first_name ASC', $mt, $mp);


/* 2. build the query */

// SQL for the quick deadline choices, for a given date column
function deadline_condition($key, $date_col, $status_col)
{
    switch ($key) {
        case 'overdue':
            return "($date_col < CURDATE() AND $status_col <> 'Completed')";
        case 'today':
            return "$date_col = CURDATE()";
        case 'week':
            return "($date_col >= CURDATE() AND $date_col <= DATE_ADD(CURDATE(), INTERVAL 7 DAY))";
        case 'month':
            return "($date_col >= CURDATE() AND $date_col <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))";
    }

    return '1=1';
}

$like = '%' . addcslashes($q, '%_\\') . '%';
$limit = 200;

if ($type === 'tasks') {

    [$where, $types, $params] = project_scope('t.project_id');
    $conds = [$where];

    if ($q !== '') {
        $conds[] = '(t.title LIKE ? OR t.description LIKE ?)';
        $types  .= 'ss';
        array_push($params, $like, $like);
    }
    if ($project_id > 0) {
        $conds[] = 't.project_id = ?';
        $types  .= 'i';
        $params[] = $project_id;
    }
    if ($member === 'none') {
        $conds[] = 't.assigned_to IS NULL';
    } elseif ($member !== '') {
        $conds[] = 't.assigned_to = ?';
        $types  .= 'i';
        $params[] = (int)$member;
    }
    if ($status !== '') {
        $conds[] = 't.status = ?';
        $types  .= 's';
        $params[] = $status;
    }
    if ($priority !== '') {
        $conds[] = 't.priority = ?';
        $types  .= 's';
        $params[] = $priority;
    }
    if ($deadline !== '') {
        $conds[] = deadline_condition($deadline, 't.due_date', 't.status');
    }
    if ($from !== '') {
        $conds[] = 't.due_date >= ?';
        $types  .= 's';
        $params[] = $from;
    }
    if ($to !== '') {
        $conds[] = 't.due_date <= ?';
        $types  .= 's';
        $params[] = $to;
    }

    $results = db_select(
        $conn,
        "SELECT t.task_id, t.title, t.priority, t.due_date, t.status,
                p.project_id, p.project_name,
                CONCAT(a.first_name, ' ', a.last_name) AS assignee_name
         FROM tasks t
         INNER JOIN projects p ON p.project_id = t.project_id
         LEFT JOIN users a ON a.user_id = t.assigned_to
         WHERE " . implode(' AND ', $conds) . "
         ORDER BY t.due_date ASC, FIELD(t.priority, 'High', 'Medium', 'Low'), t.title ASC
         LIMIT $limit",
        $types,
        $params
    );

} else {

    [$where, $types, $params] = project_scope('p.project_id');
    $conds = [$where];

    if ($q !== '') {
        $conds[] = '(p.project_name LIKE ? OR p.description LIKE ?)';
        $types  .= 'ss';
        array_push($params, $like, $like);
    }
    if ($member === 'none') {
        $conds[] = 'NOT EXISTS (SELECT 1 FROM project_members x WHERE x.project_id = p.project_id)';
    } elseif ($member !== '') {
        $conds[] = 'EXISTS (SELECT 1 FROM project_members x WHERE x.project_id = p.project_id AND x.user_id = ?)';
        $types  .= 'i';
        $params[] = (int)$member;
    }
    if ($status !== '') {
        $conds[] = 'p.status = ?';
        $types  .= 's';
        $params[] = $status;
    }
    if ($deadline !== '') {
        $conds[] = deadline_condition($deadline, 'p.deadline', 'p.status');
    }
    if ($from !== '') {
        $conds[] = 'p.deadline >= ?';
        $types  .= 's';
        $params[] = $from;
    }
    if ($to !== '') {
        $conds[] = 'p.deadline <= ?';
        $types  .= 's';
        $params[] = $to;
    }

    $results = db_select(
        $conn,
        "SELECT p.project_id, p.project_name, p.start_date, p.deadline, p.status,
                CONCAT(u.first_name, ' ', u.last_name) AS owner_name,
                (SELECT COUNT(*) FROM project_members pm2 WHERE pm2.project_id = p.project_id) AS member_count
         FROM projects p
         INNER JOIN users u ON u.user_id = p.created_by
         WHERE " . implode(' AND ', $conds) . "
         ORDER BY p.deadline ASC, p.project_name ASC
         LIMIT $limit",
        $types,
        $params
    );
}

include('../includes/header.php');
?>

<div class="table-card">

  <h2>Search &amp; Filter</h2>

  <div class="top-actions">
    <a href="index.php?type=tasks" class="button<?php echo $type === 'tasks' ? '' : ' button-secondary'; ?>">Tasks</a>
    <a href="index.php?type=projects" class="button<?php echo $type === 'projects' ? '' : ' button-secondary'; ?>">Projects</a>
  </div>

  <?php foreach ($errors as $err) { echo "<div class='alert alert-danger'>" . e($err) . "</div>"; } ?>

  <form method="GET" action="index.php" class="mb-3">
    <input type="hidden" name="type" value="<?php echo e($type); ?>">

    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="q"><?php echo $type === 'tasks' ? 'Task title or description' : 'Project name or description'; ?></label>
        <input type="search" class="form-control" id="q" name="q" value="<?php echo e($q); ?>" placeholder="Type a keyword...">
      </div>

      <?php if ($type === 'tasks') { ?>
        <div class="col-md-3 mb-3">
          <label for="project_id">Project</label>
          <select class="form-select" id="project_id" name="project_id">
            <option value="0">All projects</option>
            <?php foreach ($projects as $p) { ?>
              <option value="<?php echo (int)$p['project_id']; ?>" <?php echo $project_id === (int)$p['project_id'] ? 'selected' : ''; ?>>
                <?php echo e($p['project_name']); ?>
              </option>
            <?php } ?>
          </select>
        </div>
      <?php } ?>

      <div class="col-md-3 mb-3">
        <label for="member"><?php echo $type === 'tasks' ? 'Assigned member' : 'Member'; ?></label>
        <select class="form-select" id="member" name="member">
          <option value="">Anyone</option>
          <?php if ($type === 'tasks') { ?>
            <option value="none" <?php echo $member === 'none' ? 'selected' : ''; ?>>Unassigned</option>
          <?php } ?>
          <?php foreach ($people as $u) { ?>
            <option value="<?php echo (int)$u['user_id']; ?>" <?php echo $member === (string)$u['user_id'] ? 'selected' : ''; ?>>
              <?php echo e($u['first_name'] . ' ' . $u['last_name']); ?>
            </option>
          <?php } ?>
        </select>
      </div>
    </div>

    <div class="row">
      <div class="col-md-3 mb-3">
        <label for="status">Status</label>
        <select class="form-select" id="status" name="status">
          <option value="">Any status</option>
          <?php foreach ($status_list as $s) { ?>
            <option value="<?php echo e($s); ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo e($s); ?></option>
          <?php } ?>
        </select>
      </div>

      <?php if ($type === 'tasks') { ?>
        <div class="col-md-3 mb-3">
          <label for="priority">Priority</label>
          <select class="form-select" id="priority" name="priority">
            <option value="">Any priority</option>
            <?php foreach (task_priorities() as $pr) { ?>
              <option value="<?php echo e($pr); ?>" <?php echo $priority === $pr ? 'selected' : ''; ?>><?php echo e($pr); ?></option>
            <?php } ?>
          </select>
        </div>
      <?php } ?>

      <div class="col-md-2 mb-3">
        <label for="deadline">Deadline</label>
        <select class="form-select" id="deadline" name="deadline">
          <option value="">Any time</option>
          <?php foreach ($deadline_options as $key => $label) { ?>
            <option value="<?php echo e($key); ?>" <?php echo $deadline === $key ? 'selected' : ''; ?>><?php echo e($label); ?></option>
          <?php } ?>
        </select>
      </div>

      <div class="col-md-2 mb-3">
        <label for="from">Due from</label>
        <input type="date" class="form-control" id="from" name="from" value="<?php echo e($from); ?>">
      </div>

      <div class="col-md-2 mb-3">
        <label for="to">Due to</label>
        <input type="date" class="form-control" id="to" name="to" value="<?php echo e($to); ?>">
      </div>
    </div>

    <button type="submit">Search</button>
    <a href="index.php?type=<?php echo e($type); ?>" class="button button-secondary">Reset</a>
  </form>

  <p class="text-muted">
    <?php echo count($results); ?> result<?php echo count($results) === 1 ? '' : 's'; ?>
    <?php echo count($results) >= $limit ? ' (showing the first ' . $limit . ', narrow the filters to see others)' : ''; ?>
  </p>

  <?php if (count($results) === 0) { ?>
    <p class="text-center text-muted">Nothing matches these filters.</p>

  <?php } elseif ($type === 'tasks') { ?>
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
          foreach ($results as $t) {
              $bg_color = ($b++ % 2 == 1) ? 'odd' : 'even';
              echo '<tr class="' . $bg_color . '">';
              echo '<td><strong>' . e($t['title']) . '</strong></td>';
              echo '<td>' . e($t['project_name']) . '</td>';
              echo '<td>' . ($t['assignee_name'] ? e($t['assignee_name']) : '<span class="text-muted">Unassigned</span>') . '</td>';
              echo '<td>' . priority_badge($t['priority']) . '</td>';
              echo '<td>' . e($t['due_date']) . '</td>';
              echo '<td>' . task_status_badge($t['status']) . '</td>';
              echo '<td><a href="../tasks/view.php?task_id=' . (int)$t['task_id'] . '" class="button">View</a></td>';
              echo '</tr>';
          }
          ?>
        </tbody>
      </table>
    </div>

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
            <th>Progress</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $b = 0;
          foreach ($results as $p) {
              $bg_color = ($b++ % 2 == 1) ? 'odd' : 'even';
              echo '<tr class="' . $bg_color . '">';
              echo '<td><strong>' . e($p['project_name']) . '</strong></td>';
              echo '<td>' . e($p['owner_name']) . '</td>';
              echo '<td>' . e($p['start_date']) . '</td>';
              echo '<td>' . e($p['deadline']) . '</td>';
              echo '<td>' . status_badge($p['status']) . '</td>';
              echo '<td>' . (int)$p['member_count'] . '</td>';
              echo '<td>' . (int)get_project_progress($conn, $p['project_id']) . '%</td>';
              echo '<td><a href="../projects/view.php?project_id=' . (int)$p['project_id'] . '" class="button">View</a> ';
              echo '<a href="../tasks/index.php?project_id=' . (int)$p['project_id'] . '" class="button">Tasks</a></td>';
              echo '</tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
  <?php } ?>
</div>

<?php include('../includes/footer.php'); ?>
