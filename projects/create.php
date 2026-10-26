<?php
// FR-3 Project Creation (Admin and Project Manager only)
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_role(['admin', 'project_manager']);

$errors = [];
$data = ['project_name' => '', 'description' => '', 'start_date' => date('Y-m-d'), 'deadline' => '', 'status' => 'Planning'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['project_name'] = trim($_POST['project_name'] ?? '');
    $data['description']  = trim($_POST['description'] ?? '');
    $data['start_date']   = $_POST['start_date'] ?? '';
    $data['deadline']     = $_POST['deadline'] ?? '';
    $data['status']       = $_POST['status'] ?? '';

    if ($data['project_name'] === '') {
        $errors[] = 'Project name is required.';
    } elseif (strlen($data['project_name']) > 100) {
        $errors[] = 'Project name must be 100 characters or fewer.';
    }
    if (!valid_date($data['start_date'])) {
        $errors[] = 'Enter a valid start date.';
    }
    if (!valid_date($data['deadline'])) {
        $errors[] = 'Enter a valid deadline.';
    }
    if (valid_date($data['start_date']) && valid_date($data['deadline']) && $data['deadline'] < $data['start_date']) {
        $errors[] = 'Deadline cannot be earlier than the start date.';
    }
    if (!in_array($data['status'], project_statuses(), true)) {
        $errors[] = 'Select a valid status.';
    }

    if (count($errors) === 0) {
        mysqli_begin_transaction($conn);
        db_run(
            $conn,
            "INSERT INTO projects (project_name, description, start_date, deadline, status, created_by) VALUES (?, ?, ?, ?, ?, ?)",
            'sssssi',
            [$data['project_name'], $data['description'], $data['start_date'], $data['deadline'], $data['status'], $_SESSION['user_id']]
        );
        $project_id = mysqli_insert_id($conn);

        // The creator is automatically the first member of the project (FR-4)
        db_run(
            $conn,
            "INSERT INTO project_members (project_id, user_id, responsibility) VALUES (?, ?, 'Project Manager')",
            'ii',
            [$project_id, $_SESSION['user_id']]
        );
        mysqli_commit($conn);

        // FR-10 Activity Log
        log_activity($conn, $project_id, 'project_created', 'created project "' . $data['project_name'] . '"');

        set_flash('success', 'Project created.');
        redirect('projects/view.php?project_id=' . $project_id);
    }
}

include('../includes/header.php');
?>

<div class="form-card">
  <h2>New Project</h2>
  <?php foreach ($errors as $err) { echo "<div class='alert alert-danger'>" . e($err) . "</div>"; } ?>

  <form method="POST" action="create.php">
    <div class="mb-3">
      <label for="project_name">Project Name</label>
      <input type="text" class="form-control" id="project_name" name="project_name" maxlength="100" value="<?php echo e($data['project_name']); ?>" required>
    </div>
    <div class="mb-3">
      <label for="description">Description</label>
      <textarea class="form-control" id="description" name="description" rows="4"><?php echo e($data['description']); ?></textarea>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="start_date">Start Date</label>
        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo e($data['start_date']); ?>" required>
      </div>
      <div class="col-md-6 mb-3">
        <label for="deadline">Deadline</label>
        <input type="date" class="form-control" id="deadline" name="deadline" value="<?php echo e($data['deadline']); ?>" required>
      </div>
    </div>
    <div class="mb-3">
      <label for="status">Status</label>
      <select class="form-select" id="status" name="status">
        <?php foreach (project_statuses() as $s) {
            $sel = ($data['status'] === $s) ? 'selected' : '';
            echo "<option value='" . e($s) . "' $sel>" . e($s) . "</option>";
        } ?>
      </select>
    </div>
    <button type="submit">Create Project</button>
    <a href="index.php" class="button button-secondary">Cancel</a>
  </form>
</div>

<?php include('../includes/footer.php'); ?>
