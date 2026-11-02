<?php
// reviews/edit.php - Update Customer Review
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$review_id   = (int)($_POST['review_id'] ?? 0);
$service_id  = (int)($_POST['service_id'] ?? 0);
$rating      = (int)($_POST['rating'] ?? 5);
$review_text = trim($_POST['review_text'] ?? '');
$user_id     = (int)$_SESSION['user_id'];

// Verify review exists and belongs to current user (or admin)
$rows = db_select($conn, "SELECT user_id, service_id FROM reviews WHERE review_id = ?", 'i', [$review_id]);
if (empty($rows)) {
    set_flash('danger', 'Review not found.');
    redirect("services/view.php?service_id={$service_id}");
}

$rev = $rows[0];
if ((int)$rev['user_id'] !== $user_id && !has_role('admin')) {
    set_flash('danger', 'You do not have permission to edit this review.');
    redirect("services/view.php?service_id={$service_id}");
}

// PHP Form Validation
if ($rating < 1 || $rating > 5) {
    set_flash('danger', 'Please provide a valid rating between 1 and 5 stars.');
    redirect("services/view.php?service_id={$service_id}");
}

if ($review_text === '') {
    set_flash('danger', 'Review text cannot be empty.');
    redirect("services/view.php?service_id={$service_id}");
}

// Regex bad/foul word masking
$filtered_text = filter_profanity($review_text);

// Update review using prepared statement
$sql = "UPDATE reviews SET rating = ?, review_text = ? WHERE review_id = ?";
$ok = db_run($conn, $sql, 'isi', [$rating, $filtered_text, $review_id]);

if ($ok) {
    set_flash('success', 'Your review has been updated successfully!');
} else {
    set_flash('danger', 'Failed to update review.');
}

redirect("services/view.php?service_id={$service_id}");
