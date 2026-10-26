<?php
// FR-1 User Management - Activate / deactivate a user (Admin only, POST action script)
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/users.php');
}

$target_id = (int)($_POST['user_id'] ?? 0);

if ($target_id === (int)$_SESSION['user_id']) {
    set_flash('danger', 'You cannot deactivate your own account.');
    redirect('admin/users.php');
}

$rows = db_select($conn, "SELECT status FROM users WHERE user_id = ?", 'i', [$target_id]);
if (count($rows) === 0) {
    set_flash('danger', 'User not found.');
    redirect('admin/users.php');
}

$new_status = ($rows[0]['status'] === 'active') ? 'inactive' : 'active';
db_run($conn, "UPDATE users SET status = ? WHERE user_id = ?", 'si', [$new_status, $target_id]);

set_flash('success', 'User ' . ($new_status === 'active' ? 'activated.' : 'deactivated.'));
redirect('admin/users.php');
