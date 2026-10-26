
<?php
session_start();

include('../includes/config.php');
include('../includes/functions.php');

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('projects/index.php');
}

$project_id = (int)($_POST['project_id'] ?? 0);
$status = trim($_POST['status'] ?? '');

$project = get_project($conn, $project_id);

if (!$project || !can_view_project($conn, $project_id)) {
    set_flash('danger', 'Project not found or access denied.');
    redirect('projects/index.php');
}

if (!can_manage_project($conn, $project_id)) {
    set_flash('danger', 'You cannot change this project status.');
    redirect('projects/view.php?project_id=' . $project_id);
}

if (!in_array($status, project_statuses(), true)) {
    set_flash('danger', 'Invalid project status.');
    redirect('projects/view.php?project_id=' . $project_id);
}

db_run(
    $conn,
    "UPDATE projects SET status = ? WHERE project_id = ?",
    'si',
    [$status, $project_id]
);

// FR-10 Activity Log (only when the status really changed)
if ($project['status'] !== $status) {
    log_activity(
        $conn,
        $project_id,
        'project_status',
        'changed the status of project "' . $project['project_name'] . '" from ' . $project['status'] . ' to ' . $status
    );
}

set_flash('success', 'Project status updated successfully.');

redirect('projects/view.php?project_id=' . $project_id);
