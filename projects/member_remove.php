<?php
// FR-4 Project Membership - Remove a member (POST action script)
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_role(['admin', 'project_manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('projects/index.php');
}

$project_id = (int)($_POST['project_id'] ?? 0);
$user_id    = (int)($_POST['user_id'] ?? 0);
$project    = get_project($conn, $project_id);

if (!$project || !can_manage_project($conn, $project_id)) {
    set_flash('danger', 'You are not allowed to manage this project.');
    redirect('projects/index.php');
}

if ($user_id === (int)$project['created_by']) {
    set_flash('danger', 'The project owner cannot be removed.');
} else {
    $removed_name = user_full_name($conn, $user_id);   // FR-10: read before the row is deleted
    // FR-6: the removed member's tasks in this project become unassigned
    db_run($conn, "UPDATE tasks SET assigned_to = NULL WHERE project_id = ? AND assigned_to = ?", 'ii', [$project_id, $user_id]);
    db_run($conn, "DELETE FROM project_members WHERE project_id = ? AND user_id = ?", 'ii', [$project_id, $user_id]);
    log_activity($conn, $project_id, 'member_removed', 'removed ' . $removed_name . ' from the project');   // FR-10
    set_flash('success', 'Member removed. Any tasks assigned to them in this project are now unassigned.');
}

redirect('projects/members.php?project_id=' . $project_id);
