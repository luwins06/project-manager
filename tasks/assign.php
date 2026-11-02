<?php
// FR-6 Task Assignment - Assign / reassign / unassign a task (POST action script)
// FR-10: every real change is written to the activity log
// Allowed: Admin, or the Project Manager who created the project
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_role(['admin', 'project_manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('projects/index.php');
}

$task_id     = (int)($_POST['task_id'] ?? 0);
$assigned_to = $_POST['assigned_to'] ?? '';
$task        = get_task($conn, $task_id);

if (!$task || !can_manage_project($conn, $task['project_id'])) {
    set_flash('danger', 'You are not allowed to assign this task.');
    redirect('projects/index.php');
}

if ($assigned_to === '') {
    db_run($conn, "UPDATE tasks SET assigned_to = NULL WHERE task_id = ?", 'i', [$task_id]);
    if ($task['assigned_to'] !== null) {
        log_activity($conn, $task['project_id'], 'task_assigned', 'unassigned task "' . $task['title'] . '" (was ' . $task['assignee_name'] . ')', $task_id);
    }
    set_flash('success', 'Task is now unassigned.');
} elseif (!is_assignable(get_assignable_members($conn, $task['project_id']), $assigned_to)) {
    set_flash('danger', 'The assigned user must be an active member of this project.');
} else {
    db_run($conn, "UPDATE tasks SET assigned_to = ? WHERE task_id = ?", 'ii', [(int)$assigned_to, $task_id]);
    if ((int)$task['assigned_to'] !== (int)$assigned_to) {   // FR-10 only log a real change
        $new_name = user_full_name($conn, $assigned_to);
        $what = $task['assigned_to'] === null
            ? 'assigned task "' . $task['title'] . '" to ' . $new_name
            : 'reassigned task "' . $task['title'] . '" from ' . $task['assignee_name'] . ' to ' . $new_name;
        log_activity($conn, $task['project_id'], 'task_assigned', $what, $task_id);
    }
    set_flash('success', 'Task assignment updated.');
}

redirect('tasks/view.php?task_id=' . $task_id);
