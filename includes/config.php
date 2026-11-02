<?php
// includes/config.php - Database and System Configuration

$db_host     = "localhost";
$db_username = "root";
$db_passwd   = "";
$db_name     = "db_taskmanager";

$conn = mysqli_connect($db_host, $db_username, $db_passwd);
if (!$conn) {
    die("Could not connect to database server: " . mysqli_connect_error());
}

if (!mysqli_select_db($conn, $db_name)) {
    die("Could not select database {$db_name}: " . mysqli_error($conn));
}
mysqli_set_charset($conn, "utf8mb4");

// Base URL: folder path under web root
$base_url = "/itim211-2b-taskmanager";

// Mailtrap SMTP Configuration
// Sign up free at https://mailtrap.io/ to view your inbox credentials
define('MAILTRAP_HOST', 'sandbox.smtp.mailtrap.io');
define('MAILTRAP_PORT', 2525);
define('MAILTRAP_USER', 'mailtrap_user_placeholder');
define('MAILTRAP_PASS', 'mailtrap_pass_placeholder');
define('MAIL_FROM', 'orders@projecthub.local');
define('MAIL_FROM_NAME', 'ProjectHub Service Platform');
