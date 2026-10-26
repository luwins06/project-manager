<?php
// FR-4 Project Membership - Add a member (POST action script)
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

$target = db_select($conn, "SELECT status FROM users WHERE user_id = ?", 'i', [$user_id]);
$exists = db_select($conn, "SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?", 'ii', [$project_id, $user_id]);

if (count($target) === 0 || $target[0]['status'] !== 'active') {
    set_flash('danger', 'Select an active user.');
} elseif ($responsibility === '' || strlen($responsibility) > 100) {
    set_flash('danger', 'Responsibility is required (max. 100 characters).');
} elseif (count($exists) > 0) {
    set_flash('warning', 'That user is already a member of this project.');
} else {
    db_run($conn, "INSERT INTO project_members (project_id, user_id, responsibility) VALUES (?, ?, ?)", 'iis', [$project_id, $user_id, $responsibility]);
    log_activity($conn, $project_id, 'member_added', 'added ' . user_full_name($conn, $user_id) . ' to the project as ' . $responsibility);   // FR-10
    set_flash('success', 'Member added.');
}

redirect('projects/members.php?project_id=' . $project_id);
