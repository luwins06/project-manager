<?php
// FR-5 / FR-6 / FR-7 - Task details, reassignment and status update
// FR-9 - Comments on the task (any member who can view the project)
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_login();

$task_id = (int)($_GET['task_id'] ?? 0);
$task = get_task($conn, $task_id);

if (!$task || !can_view_project($conn, $task['project_id'])) {
    set_flash('danger', 'Task not found or you are not a member of its project.');
    redirect('projects/index.php');
}

$can_manage = can_manage_project($conn, $task['project_id']);
$can_update = can_update_task_status($conn, $task);
$members = $can_manage ? get_assignable_members($conn, $task['project_id']) : [];

// FR-9 Comments (oldest first, so the form sits under the conversation)
$comments = db_select(
    $conn,
    "SELECT c.comment_id, c.comment_text, c.created_at,
            CONCAT(u.first_name, ' ', u.last_name) AS author,
            u.role
     FROM comments c
     INNER JOIN users u ON u.user_id = c.user_id
     WHERE c.task_id = ?
     ORDER BY c.created_at ASC, c.comment_id ASC",
    'i',
    [$task_id]
);

include('../includes/header.php');
?>

<div class="table-card">
  <h2><?php echo e($task['title']); ?></h2>
  <p class="text-center text-muted">
    Project: <a href="index.php?project_id=<?php echo (int)$task['project_id']; ?>"><?php echo e($task['project_name']); ?></a>
  </p>

  <div class="row mb-3">
    <div class="col-md-3"><strong>Status</strong><br><?php echo task_status_badge($task['status']); ?></div>
    <div class="col-md-3"><strong>Priority</strong><br><?php echo priority_badge($task['priority']); ?></div>
    <div class="col-md-3"><strong>Start Date</strong><br><?php echo e($task['start_date']); ?></div>
    <div class="col-md-3"><strong>Due Date</strong><br><?php echo e($task['due_date']); ?></div>
  </div>
  <div class="row mb-3">
    <div class="col-md-6"><strong>Assigned To</strong><br><?php echo $task['assignee_name'] ? e($task['assignee_name']) : '<span class="text-muted">Unassigned</span>'; ?></div>
    <div class="col-md-6"><strong>Created By</strong><br><?php echo e($task['creator_name']); ?> on <?php echo e(substr($task['created_at'], 0, 10)); ?></div>
  </div>
  <p><strong>Description</strong><br><?php echo $task['description'] !== '' ? nl2br(e($task['description'])) : '<span class="text-muted">No description.</span>'; ?></p>

  <?php if ($can_update) { ?>
    <hr>
    <h4>Update Status</h4>
    <form method="POST" action="update_status.php" class="d-flex gap-2 mb-3" style="max-width: 400px;">
      <input type="hidden" name="task_id" value="<?php echo (int)$task['task_id']; ?>">
      <input type="hidden" name="return" value="view">
      <select name="status" class="form-select">
        <?php foreach (task_statuses() as $s) {
            $sel = ($task['status'] === $s) ? 'selected' : '';
            echo "<option value='" . e($s) . "' $sel>" . e($s) . "</option>";
        } ?>
      </select>
      <button type="submit">Save</button>
    </form>
  <?php } ?>

  <?php if ($can_manage) { ?>
    <hr>
    <h4>Assign Task</h4>
    <form method="POST" action="assign.php" class="d-flex gap-2 mb-3" style="max-width: 500px;">
      <input type="hidden" name="task_id" value="<?php echo (int)$task['task_id']; ?>">
      <select name="assigned_to" class="form-select">
        <option value="">-- Unassigned --</option>
        <?php foreach ($members as $m) {
            $sel = ((int)$task['assigned_to'] === (int)$m['user_id']) ? 'selected' : '';
            echo "<option value='" . (int)$m['user_id'] . "' $sel>" . e($m['first_name'] . ' ' . $m['last_name'] . ' (' . $m['responsibility'] . ')') . "</option>";
        } ?>
      </select>
      <button type="submit">Assign</button>
    </form>
  <?php } ?>

  <hr id="comments">
  <h4>Comments (<?php echo count($comments); ?>)</h4>

  <?php if (count($comments) === 0) { ?>
    <p class="text-muted">No comments yet. Ask a question or post an update below.</p>
  <?php } ?>

  <?php foreach ($comments as $c) { ?>
    <div class="border rounded p-3 mb-2 bg-body-tertiary">
      <div class="d-flex flex-wrap justify-content-between gap-1">
        <strong><?php echo e($c['author']); ?> <small class="text-muted">(<?php echo e(role_label($c['role'])); ?>)</small></strong>
        <small class="text-muted"><?php echo e($c['created_at']); ?></small>
      </div>
      <div class="mt-1"><?php echo nl2br(e($c['comment_text'])); ?></div>
    </div>
  <?php } ?>

  <form method="POST" action="../comments/add.php" class="mt-3 mb-3">
    <input type="hidden" name="task_id" value="<?php echo (int)$task['task_id']; ?>">
    <label for="comment_text">Add a comment</label>
    <textarea class="form-control mb-2" id="comment_text" name="comment_text" rows="3"
      maxlength="<?php echo comment_max_length(); ?>" placeholder="Write a question or an update..." required></textarea>
    <button type="submit">Post Comment</button>
  </form>

  <a href="index.php?project_id=<?php echo (int)$task['project_id']; ?>" class="button button-secondary">Back to Tasks</a>
</div>

<?php include('../includes/footer.php'); ?>
