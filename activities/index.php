<?php
// FR-10 Activity Log - Who did what in a project (or in all projects the user can see)
// Allowed: any logged-in user, limited to the projects they can view (admin = all)
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_login();

update_overdue_tasks($conn);   // so automatic "Failed" changes are in the log

/* 1. read GET + validate */
$project_id = (int)($_GET['project_id'] ?? 0);
$type       = $_GET['type'] ?? '';
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 25;

if ($type !== '' && !isset(activity_types()[$type])) {
    $type = '';
}

if ($project_id > 0) {
    $project = get_project($conn, $project_id);

    if (!$project || !can_view_project($conn, $project_id)) {
        set_flash('danger', 'Project not found or you are not a member of it.');
        redirect('activities/index.php');
    }

    $where  = 'a.project_id = ?';
    $types  = 'i';
    $params = [$project_id];
} else {
    [$where, $types, $params] = project_scope('a.project_id');
}

if ($type !== '') {
    $where  .= ' AND a.activity_type = ?';
    $types  .= 's';
    $params[] = $type;
}

/* 2. queries */
$total = (int)db_select(
    $conn,
    "SELECT COUNT(*) AS n FROM activity_log a WHERE $where",
    $types,
    $params
)[0]['n'];

$pages = max(1, (int)ceil($total / $per_page));
$page  = min($page, $pages);
$offset = ($page - 1) * $per_page;

$rows = db_select(
    $conn,
    "SELECT a.activity_id, a.project_id, a.task_id, a.activity_type, a.description, a.created_at,
            p.project_name,
            CONCAT(u.first_name, ' ', u.last_name) AS actor_name
     FROM activity_log a
     INNER JOIN projects p ON p.project_id = a.project_id
     LEFT JOIN users u ON u.user_id = a.user_id
     WHERE $where
     ORDER BY a.created_at DESC, a.activity_id DESC
     LIMIT $per_page OFFSET $offset",
    $types,
    $params
);

$projects = get_visible_projects($conn);

function activity_page_link($page, $project_id, $type)
{
    $q = ['page' => $page];

    if ($project_id > 0) {
        $q['project_id'] = $project_id;
    }
    if ($type !== '') {
        $q['type'] = $type;
    }

    return 'index.php?' . http_build_query($q);
}

include('../includes/header.php');
?>

<div class="table-card">

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h2>Activity Log<?php echo $project_id > 0 ? ': ' . e($project['project_name']) : ''; ?></h2>
      <p class="text-muted mb-0">
        Project, task, assignment, status and comment activity, newest first.
      </p>
    </div>
    <div class="top-actions mb-0">
      <?php if ($project_id > 0) { ?>
        <a href="../projects/view.php?project_id=<?php echo $project_id; ?>" class="button button-secondary">Back to Project</a>
      <?php } ?>
    </div>
  </div>

  <form method="GET" action="index.php" class="interactive-toolbar">
    <select name="project_id" class="form-select" style="max-width: 280px;" aria-label="Project">
      <option value="0">All my projects</option>
      <?php foreach ($projects as $p) { ?>
        <option value="<?php echo (int)$p['project_id']; ?>" <?php echo $project_id === (int)$p['project_id'] ? 'selected' : ''; ?>>
          <?php echo e($p['project_name']); ?>
        </option>
      <?php } ?>
    </select>

    <select name="type" class="form-select" style="max-width: 220px;" aria-label="Activity type">
      <option value="">All activity</option>
      <?php foreach (activity_types() as $key => $t) { ?>
        <option value="<?php echo e($key); ?>" <?php echo $type === $key ? 'selected' : ''; ?>>
          <?php echo e($t['label']); ?>
        </option>
      <?php } ?>
    </select>

    <button type="submit">Filter</button>
    <a href="index.php" class="button button-secondary">Reset</a>
    <span class="toolbar-count"><?php echo $total; ?> entr<?php echo $total === 1 ? 'y' : 'ies'; ?></span>
  </form>

  <?php render_activity_list($rows, $project_id === 0); ?>

  <?php if ($pages > 1) { ?>
    <div class="d-flex justify-content-between align-items-center mt-3">
      <?php if ($page > 1) { ?>
        <a href="<?php echo e(activity_page_link($page - 1, $project_id, $type)); ?>" class="button button-secondary">&laquo; Newer</a>
      <?php } else { ?>
        <span></span>
      <?php } ?>

      <span class="text-muted">Page <?php echo $page; ?> of <?php echo $pages; ?></span>

      <?php if ($page < $pages) { ?>
        <a href="<?php echo e(activity_page_link($page + 1, $project_id, $type)); ?>" class="button button-secondary">Older &raquo;</a>
      <?php } else { ?>
        <span></span>
      <?php } ?>
    </div>
  <?php } ?>
</div>

<?php include('../includes/footer.php'); ?>
