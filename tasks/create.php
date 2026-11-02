<?php
// FR-5 Task Creation (Admin, or the Project Manager who created the project, MP5)
// FR-10: writes 'task_created' / 'task_assigned' to the activity log
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_role(['admin', 'project_manager']);

$project_id = (int)($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
$project = get_project($conn, $project_id);

if (!$project) {
    set_flash('danger', 'Project not found.');
    redirect('projects/index.php');
}
if (!can_manage_project($conn, $project_id)) {
    set_flash('danger', 'Only the project manager who created this project (or an admin) can create tasks.');
    redirect('projects/view.php?project_id=' . $project_id);
}

$members = get_assignable_members($conn, $project_id);
$errors = [];
$data = [
    'title' => '', 'description' => '', 'assigned_to' => '', 'priority' => 'Medium',
    'start_date' => date('Y-m-d'), 'due_date' => '', 'status' => 'To Do',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['title']       = trim($_POST['title'] ?? '');
    $data['description'] = trim($_POST['description'] ?? '');
    $data['assigned_to'] = $_POST['assigned_to'] ?? '';
    $data['priority']    = $_POST['priority'] ?? '';
    $data['start_date']  = $_POST['start_date'] ?? '';
    $data['due_date']    = $_POST['due_date'] ?? '';
    $data['status']      = $_POST['status'] ?? '';

    // MP5: Server-side PHP validation (no HTML5 validation)
    if ($data['title'] === '') {
        $errors[] = 'Task title is required.';
    } elseif (strlen($data['title']) > 150) {
        $errors[] = 'Task title must be 150 characters or fewer.';
    }
    if ($data['assigned_to'] !== '' && !is_assignable($members, $data['assigned_to'])) {
        $errors[] = 'The assigned user must be an active member of this project.';
    }
    if (!in_array($data['priority'], task_priorities(), true)) {
        $errors[] = 'Select a valid priority.';
    }
    if (!valid_date($data['start_date'])) {
        $errors[] = 'Enter a valid start date (YYYY-MM-DD).';
    }
    if (!valid_date($data['due_date'])) {
        $errors[] = 'Enter a valid due date (YYYY-MM-DD).';
    }
    if (valid_date($data['start_date']) && valid_date($data['due_date']) && $data['due_date'] < $data['start_date']) {
        $errors[] = 'Due date cannot be earlier than the start date.';
    }
    if (!in_array($data['status'], task_statuses(), true)) {
        $errors[] = 'Select a valid status.';
    }

    if (count($errors) === 0) {
        $assigned = ($data['assigned_to'] === '') ? null : (int)$data['assigned_to'];

        db_run(
            $conn,
            "INSERT INTO tasks (project_id, title, description, assigned_to, priority, start_date, due_date, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            'ississssi',
            [
                $project_id,
                $data['title'],
                $data['description'],
                $assigned,
                $data['priority'],
                $data['start_date'],
                $data['due_date'],
                $data['status'],
                $_SESSION['user_id']
            ]
        );

        $task_id = mysqli_insert_id($conn);

        // FR-10: log task creation
        log_activity($conn, $project_id, 'task_created', 'created task "' . $data['title'] . '"', $task_id);

        // FR-10: if someone was assigned at creation time, log that too
        if ($assigned !== null) {
            $name = user_full_name($conn, $assigned);
            log_activity($conn, $project_id, 'task_assigned', 'assigned task "' . $data['title'] . '" to ' . $name, $task_id);
        }

        set_flash('success', 'Task created.');
        redirect('tasks/view.php?task_id=' . $task_id);
    }
}

include('../includes/header.php');
?>

<div class="form-card">
  <h2>New Task</h2>
  <p class="text-muted small">Project: <a href="<?php echo $base_url; ?>/projects/view.php?project_id=<?php echo $project_id; ?>"><?php echo e($project['project_name']); ?></a></p>

  <?php if (!empty($errors)) { ?>
    <div class="alert alert-danger" role="alert">
      <ul class="mb-0 ps-3">
        <?php foreach ($errors as $err) { echo "<li>" . e($err) . "</li>"; } ?>
      </ul>
    </div>
  <?php } ?>

  <form method="POST" action="create.php" novalidate>
    <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">

    <div class="mb-3">
      <label for="title">Task Title</label>
      <input type="text" class="form-control" id="title" name="title" maxlength="150" value="<?php echo e($data['title']); ?>" placeholder="e.g. Design relational schema">
    </div>

    <div class="mb-3">
      <label for="description">Description</label>
      <textarea class="form-control" id="description" name="description" rows="4"><?php echo e($data['description']); ?></textarea>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="assigned_to">Assigned Member</label>
        <select class="form-select" id="assigned_to" name="assigned_to">
          <option value="">-- Unassigned --</option>
          <?php foreach ($members as $m) {
              $sel = ((string)$data['assigned_to'] === (string)$m['user_id']) ? 'selected' : '';
              echo "<option value='" . (int)$m['user_id'] . "' $sel>" . e($m['first_name'] . ' ' . $m['last_name'] . ' (' . $m['responsibility'] . ')') . "</option>";
          } ?>
        </select>
      </div>

      <div class="col-md-6 mb-3">
        <label for="priority">Priority</label>
        <select class="form-select" id="priority" name="priority">
          <?php foreach (task_priorities() as $p) {
              $sel = ($data['priority'] === $p) ? 'selected' : '';
              echo "<option value='" . e($p) . "' $sel>" . e($p) . "</option>";
          } ?>
        </select>
      </div>
    </div>

    <div class="row">
      <div class="col-md-4 mb-3">
        <label for="start_date">Start Date</label>
        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo e($data['start_date']); ?>">
      </div>

      <div class="col-md-4 mb-3">
        <label for="due_date">Due Date</label>
        <input type="date" class="form-control" id="due_date" name="due_date" value="<?php echo e($data['due_date']); ?>">
      </div>

      <div class="col-md-4 mb-3">
        <label for="status">Status</label>
        <select class="form-select" id="status" name="status">
          <?php foreach (task_statuses() as $s) {
              $sel = ($data['status'] === $s) ? 'selected' : '';
              echo "<option value='" . e($s) . "' $sel>" . e($s) . "</option>";
          } ?>
        </select>
      </div>
    </div>

    <button type="submit">Create Task</button>
    <a href="index.php?project_id=<?php echo $project_id; ?>" class="button button-secondary">Cancel</a>
  </form>
</div>

<?php include('../includes/footer.php'); ?>
