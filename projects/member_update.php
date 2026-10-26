<?php
// FR-4 Project Membership - Change a member's responsibility (POST action script)
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_role(['admin', 'project_manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('projects/index.php');
}

$project_id     = (int)($_POST['project_id'] ?? 0);
$user_id        = (int)($_POST['user_id'] ?? 0);
$responsibility = trim($_POST['responsibility'] ?? '');

if (!get_project($conn, $project_id) || !can_manage_project($conn, $project_id)) {
    set_flash('danger', 'You are not allowed to manage this project.');
    redirect('projects/index.php');
}

if ($responsibility === '' || strlen($responsibility) > 100) {
    set_flash('danger', 'Responsibility is required (max. 100 characters).');
} else {
    db_run($conn, "UPDATE project_members SET responsibility = ? WHERE project_id = ? AND user_id = ?", 'sii', [$responsibility, $project_id, $user_id]);
    set_flash('success', 'Responsibility updated.');
}

redirect('projects/members.php?project_id=' . $project_id);
