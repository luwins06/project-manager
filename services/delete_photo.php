<?php
// services/delete_photo.php - Delete single gallery photo (Admin only)
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');
require_role('admin');

$photo_id   = (int)($_GET['photo_id'] ?? 0);
$service_id = (int)($_GET['service_id'] ?? 0);

$rows = db_select($conn, "SELECT filename FROM service_photos WHERE photo_id = ?", 'i', [$photo_id]);
if (!empty($rows)) {
    $path = __DIR__ . '/../' . $rows[0]['filename'];
    if (file_exists($path)) {
        @unlink($path);
    }
    db_run($conn, "DELETE FROM service_photos WHERE photo_id = ?", 'i', [$photo_id]);
    set_flash('success', 'Gallery photo removed.');
}

redirect('services/edit.php?service_id=' . $service_id);
