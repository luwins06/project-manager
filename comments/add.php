<?php
// FR-9 Comments - Add a comment to a task (POST action script)
// Allowed: anyone who can view the task's project (its members, or an admin)
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('projects/index.php');
}

$task_id = (int)($_POST['task_id'] ?? 0);
$text    = trim($_POST['comment_text'] ?? '');
$task    = get_task($conn, $task_id);

if (!$task || !can_view_project($conn, $task['project_id'])) {
    set_flash('danger', 'Task not found or you are not a member of its project.');
    redirect('projects/index.php');
}

$length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);

if ($text === '') {
    set_flash('danger', 'Write something before posting a comment.');
} elseif ($length > comment_max_length()) {
    set_flash('danger', 'Comments can be at most ' . comment_max_length() . ' characters.');
} else {
    db_run(
        $conn,
        "INSERT INTO comments (task_id, user_id, comment_text) VALUES (?, ?, ?)",
        'iis',
        [$task_id, $_SESSION['user_id'], $text]
    );

    // FR-10 Activity Log (first 60 characters as a preview)
    $preview = function_exists('mb_substr') ? mb_substr($text, 0, 60) : substr($text, 0, 60);
    $preview = preg_replace('/\s+/', ' ', $preview) . ($length > 60 ? '...' : '');
    log_activity($conn, $task['project_id'], 'comment_added',
        'commented on task "' . $task['title'] . '": ' . $preview, $task_id);

    set_flash('success', 'Comment posted.');
}

redirect('tasks/view.php?task_id=' . $task_id . '#comments');
