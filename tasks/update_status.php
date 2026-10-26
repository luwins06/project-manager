<?php
// FR-7 Task Status Tracking - Change a task's status (POST action script)
// FR-10: every real change is written to the activity log
// Allowed: the assigned member, the project's manager, or an admin
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('projects/index.php');
}

$task_id = (int)($_POST['task_id'] ?? 0);
$status  = $_POST['status'] ?? '';
$return  = $_POST['return'] ?? 'view';
$task    = get_task($conn, $task_id);

if (!$task || !can_update_task_status($conn, $task)) {
    set_flash('danger', 'You are not allowed to update this task.');
    redirect('projects/index.php');
}

if (!in_array($status, task_statuses(), true)) {
    set_flash('danger', 'Select a valid status.');
} else {
    db_run($conn, "UPDATE tasks SET status = ? WHERE task_id = ?", 'si', [$status, $task_id]);
    if ($task['status'] !== $status) {   // FR-10 only log a real change
        log_activity($conn, $task['project_id'], 'task_status',
            'changed the status of task "' . $task['title'] . '" from ' . $task['status'] . ' to ' . $status, $task_id);
    }
    set_flash('success', 'Task status updated to ' . $status . '.');
}

if ($return === 'index') {
    redirect('tasks/index.php?project_id=' . (int)$task['project_id']);
}
redirect('tasks/view.php?task_id=' . $task_id);
