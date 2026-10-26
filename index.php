<?php
session_start();
include('./includes/config.php');
include('./includes/functions.php');

// Entry point: send visitors to the right place
if (!is_logged_in()) {
    redirect('user/login.php');
}
redirect('projects/index.php');
