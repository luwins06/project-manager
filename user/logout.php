<?php
// FR-1 User Management - Logout
session_start();
include('../includes/config.php');
include('../includes/functions.php');

session_unset();
session_destroy();

header("Location: {$base_url}/user/login.php");
exit;
