<?php
// services/delete.php - Toggle Status or Delete Service (Admin only, POST)
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('services/index.php');
}

$service_id = (int)($_POST['service_id'] ?? 0);
$rows = db_select($conn, "SELECT status FROM services WHERE service_id = ?", 'i', [$service_id]);

if (empty($rows)) {
    set_flash('danger', 'Service package not found.');
    redirect('services/index.php');
}

$new_status = ($rows[0]['status'] === 'active') ? 'inactive' : 'active';
db_run($conn, "UPDATE services SET status = ? WHERE service_id = ?", 'si', [$new_status, $service_id]);

set_flash('success', 'Service status changed to ' . ucfirst($new_status) . '.');
redirect('services/index.php');
