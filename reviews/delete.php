<?php
// reviews/delete.php - Delete Customer Review
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$review_id  = (int)($_POST['review_id'] ?? 0);
$service_id = (int)($_POST['service_id'] ?? 0);
$user_id    = (int)$_SESSION['user_id'];

$rows = db_select($conn, "SELECT user_id, service_id FROM reviews WHERE review_id = ?", 'i', [$review_id]);
if (!empty($rows)) {
    if ((int)$rows[0]['user_id'] === $user_id || has_role('admin')) {
        db_run($conn, "DELETE FROM reviews WHERE review_id = ?", 'i', [$review_id]);
        set_flash('success', 'Review deleted.');
    }
}

redirect("services/view.php?service_id={$service_id}");
