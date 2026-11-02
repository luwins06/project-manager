<?php
// FR-3 Project Creation (Admin and Project Manager only)
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_role(['admin', 'project_manager']);

$errors = [];
$data = [
    'order_id'     => $_GET['order_id'] ?? '',
    'project_name' => '',
    'description'  => '',
    'start_date'   => date('Y-m-d'),
    'deadline'     => date('Y-m-d', strtotime('+30 days')),
    'status'       => 'Planning'
];

// Fetch orders for dropdown selection
$available_orders = db_select($conn, "SELECT order_id, customer_name FROM v_order_totals ORDER BY order_id DESC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['order_id']     = trim($_POST['order_id'] ?? '');
    $data['project_name'] = trim($_POST['project_name'] ?? '');
    $data['description']  = trim($_POST['description'] ?? '');
    $data['start_date']   = $_POST['start_date'] ?? '';
    $data['deadline']     = $_POST['deadline'] ?? '';
    $data['status']       = $_POST['status'] ?? '';

    // Server-side PHP validation (no HTML5 validation)
    if ($data['project_name'] === '') {
        $errors[] = 'Project name is required.';
    } elseif (strlen($data['project_name']) > 100) {
        $errors[] = 'Project name must be 100 characters or fewer.';
    }
    if (!valid_date($data['start_date'])) {
        $errors[] = 'Enter a valid start date (YYYY-MM-DD).';
    }
    if (!valid_date($data['deadline'])) {
        $errors[] = 'Enter a valid deadline date (YYYY-MM-DD).';
    }
    if (valid_date($data['start_date']) && valid_date($data['deadline']) && $data['deadline'] < $data['start_date']) {
        $errors[] = 'Deadline cannot be earlier than the start date.';
    }
    if (!in_array($data['status'], project_statuses(), true)) {
        $errors[] = 'Select a valid status.';
    }

    $order_fk = ($data['order_id'] !== '' && is_numeric($data['order_id'])) ? (int)$data['order_id'] : null;

    if (count($errors) === 0) {
        mysqli_begin_transaction($conn);
        $sql = "INSERT INTO projects (order_id, project_name, description, start_date, deadline, status, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        db_run($conn, $sql, 'isssssi', [
            $order_fk,
            $data['project_name'],
            $data['description'],
            $data['start_date'],
            $data['deadline'],
            $data['status'],
            $_SESSION['user_id']
        ]);
        $project_id = mysqli_insert_id($conn);

        // Creator is first member
        db_run(
            $conn,
            "INSERT INTO project_members (project_id, user_id, responsibility) VALUES (?, ?, 'Project Manager')",
            'ii',
            [$project_id, $_SESSION['user_id']]
        );

        // If linked to an order, also add customer as viewer member
        if ($order_fk) {
            $ord_user = db_select($conn, "SELECT user_id FROM orders WHERE order_id = ?", 'i', [$order_fk]);
            if (!empty($ord_user)) {
                db_run(
                    $conn,
                    "INSERT IGNORE INTO project_members (project_id, user_id, responsibility) VALUES (?, ?, 'Client')",
                    'ii',
                    [$project_id, $ord_user[0]['user_id']]
                );
            }
        }

        mysqli_commit($conn);

        // FR-10 Activity Log
        log_activity($conn, $project_id, 'project_created', 'created project "' . $data['project_name'] . '"');

        set_flash('success', 'Project created successfully.');
        redirect('projects/view.php?project_id=' . $project_id);
    }
}

include('../includes/header.php');
?>

<div class="form-card">
  <h2>New Project Workspace</h2>
  <p class="text-muted small">Provision a project workspace manually or link it to an existing customer order.</p>

  <?php if (!empty($errors)) { ?>
    <div class="alert alert-danger" role="alert">
      <ul class="mb-0 ps-3">
        <?php foreach ($errors as $err) { echo "<li>" . e($err) . "</li>"; } ?>
      </ul>
    </div>
  <?php } ?>

  <form method="POST" action="create.php" novalidate>
    <div class="row">
      <div class="col-md-8 mb-3">
        <label for="project_name">Project Name</label>
        <input type="text" class="form-control" id="project_name" name="project_name" value="<?php echo e($data['project_name']); ?>" placeholder="e.g. Website Redesign for Client">
      </div>
      <div class="col-md-4 mb-3">
        <label for="order_id">Associated Customer Order (Optional)</label>
        <select class="form-select" id="order_id" name="order_id">
          <option value="">-- None (Internal Project) --</option>
          <?php foreach ($available_orders as $ao) { 
            $sel = ($data['order_id'] == $ao['order_id']) ? 'selected' : '';
            echo "<option value='" . (int)$ao['order_id'] . "' {$sel}>Order #" . (int)$ao['order_id'] . " - " . e($ao['customer_name']) . "</option>";
          } ?>
        </select>
      </div>
    </div>

    <div class="mb-3">
      <label for="description">Description & Goals</label>
      <textarea class="form-control" id="description" name="description" rows="4" placeholder="Scope of deliverables and kickoff notes..."><?php echo e($data['description']); ?></textarea>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="start_date">Start Date</label>
        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo e($data['start_date']); ?>">
      </div>
      <div class="col-md-6 mb-3">
        <label for="deadline">Deadline</label>
        <input type="date" class="form-control" id="deadline" name="deadline" value="<?php echo e($data['deadline']); ?>">
      </div>
    </div>

    <div class="mb-4">
      <label for="status">Status</label>
      <select class="form-select" id="status" name="status">
        <?php foreach (project_statuses() as $s) {
            $sel = ($data['status'] === $s) ? 'selected' : '';
            echo "<option value='" . e($s) . "' $sel>" . e($s) . "</option>";
        } ?>
      </select>
    </div>

    <button type="submit">Create Project Workspace</button>
    <a href="index.php" class="button button-secondary">Cancel</a>
  </form>
</div>

<?php include('../includes/footer.php'); ?>
