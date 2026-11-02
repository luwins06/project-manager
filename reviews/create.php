<?php
// reviews/create.php - Submit Customer Review
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$service_id  = (int)($_POST['service_id'] ?? 0);
$rating      = (int)($_POST['rating'] ?? 5);
$review_text = trim($_POST['review_text'] ?? '');
$user_id     = (int)$_SESSION['user_id'];

// 1. Verify service exists
$serv = db_select($conn, "SELECT service_id FROM services WHERE service_id = ?", 'i', [$service_id]);
if (empty($serv)) {
    set_flash('danger', 'Service not found.');
    redirect('index.php');
}

// 2. Check if customer actually availed this service
if (!customer_has_availed_service($conn, $user_id, $service_id) && !has_role('admin')) {
    set_flash('danger', 'Only customers who have availed this service can submit a review.');
    redirect("services/view.php?service_id={$service_id}");
}

// 3. Check for existing review (one review per service per user)
$existing = db_select($conn, "SELECT review_id FROM reviews WHERE service_id = ? AND user_id = ?", 'ii', [$service_id, $user_id]);
if (!empty($existing)) {
    set_flash('warning', 'You have already reviewed this service. You can update your existing review below.');
    redirect("services/view.php?service_id={$service_id}");
}

// 4. Validate rating & text in PHP
if ($rating < 1 || $rating > 5) {
    set_flash('danger', 'Please provide a valid rating between 1 and 5 stars.');
    redirect("services/view.php?service_id={$service_id}");
}

if ($review_text === '') {
    set_flash('danger', 'Review comments cannot be empty.');
    redirect("services/view.php?service_id={$service_id}");
}

// 5. Apply Regex bad/foul word masking
$filtered_text = filter_profanity($review_text);

// 6. Insert review using prepared statement
$sql = "INSERT INTO reviews (service_id, user_id, rating, review_text) VALUES (?, ?, ?, ?)";
$ok = db_run($conn, $sql, 'iiis', [$service_id, $user_id, $rating, $filtered_text]);

if ($ok) {
    set_flash('success', 'Thank you! Your review has been published.');
} else {
    set_flash('danger', 'Failed to save review. Please try again.');
}

redirect("services/view.php?service_id={$service_id}");
