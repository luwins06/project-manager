<?php
// includes/functions.php
// Shared helpers. Include AFTER session_start() and config.php on every page.

/* ---------- Output / navigation ---------- */

// Escape any value before printing it in HTML
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Redirect to a path inside the project, e.g. redirect('projects/index.php');
function redirect($path)
{
    global $base_url;
    header("Location: {$base_url}/{$path}");
    exit;
}

// One-time message shown at the top of the next page
function set_flash($type, $message)
{
    $_SESSION['flash'] = [
        'type' => $type,
        'msg'  => $message
    ];
}

function show_flash()
{
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];

        echo "<div class='alert alert-" .
             e($f['type']) .
             "' role='alert'>" .
             e($f['msg']) .
             "</div>";

        unset($_SESSION['flash']);
    }
}


/* ---------- Database (prepared statements) ---------- */

// SELECT: returns an array of associative rows
function db_select($conn, $sql, $types = '', $params = [])
{
    $stmt = mysqli_prepare($conn, $sql);

    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);

    mysqli_stmt_close($stmt);

    return $rows;
}


// INSERT / UPDATE / DELETE
function db_run($conn, $sql, $types = '', $params = [])
{
    $stmt = mysqli_prepare($conn, $sql);

    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    $ok = mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);

    return $ok;
}


/* ---------- FR-2 Role-Based Access ---------- */

function role_options()
{
    return [
        'admin'           => 'Admin',
        'project_manager' => 'Project Manager',
        'member'          => 'Member',
    ];
}

function role_label($role)
{
    $roles = role_options();

    return $roles[$role] ?? $role;
}

function is_logged_in()
{
    return isset($_SESSION['user_id']);
}


// Page guard: any logged-in user
function require_login()
{
    if (!is_logged_in()) {
        redirect('user/login.php');
    }
}


// Page guard: only the listed roles
function require_role($roles)
{
    require_login();

    if (!in_array($_SESSION['role'], (array)$roles, true)) {
        set_flash(
            'danger',
            'You do not have permission to access that page.'
        );

        redirect('index.php');
    }
}


function has_role($roles)
{
    return is_logged_in()
        && in_array($_SESSION['role'], (array)$roles, true);
}


/* ---------- FR-3 / FR-4 Projects ---------- */

function project_statuses()
{
    return [
        'Planning',
        'In Progress',
        'On Hold',
        'Completed',
        'Failed'
    ];
}


function status_badge($status)
{
    $colors = [
        'Planning'    => 'secondary',
        'In Progress' => 'primary',
        'On Hold'     => 'warning',
        'Completed'   => 'success',
        'Failed'      => 'danger',
    ];

    $c = $colors[$status] ?? 'secondary';

    return "<span class='badge text-bg-{$c}'>" .
           e($status) .
           "</span>";
}


// Returns the project row or null
function get_project($conn, $project_id)
{
    $rows = db_select(
        $conn,
        "SELECT p.*,
                CONCAT(u.first_name, ' ', u.last_name) AS owner_name
         FROM projects p
         INNER JOIN users u
             ON u.user_id = p.created_by
         WHERE p.project_id = ?",
        'i',
        [$project_id]
    );

    return $rows[0] ?? null;
}


// Admin sees every project;
// everyone else only projects they belong to
function can_view_project($conn, $project_id)
{
    if (has_role('admin')) {
        return true;
    }

    $rows = db_select(
        $conn,
        "SELECT 1
         FROM project_members
         WHERE project_id = ?
         AND user_id = ?",
        'ii',
        [
            $project_id,
            $_SESSION['user_id']
        ]
    );

    return count($rows) > 0;
}


// Admin, or the Project Manager who created the project
function can_manage_project($conn, $project_id)
{
    if (has_role('admin')) {
        return true;
    }

    if (!has_role('project_manager')) {
        return false;
    }

    $rows = db_select(
        $conn,
        "SELECT 1
         FROM projects
         WHERE project_id = ?
         AND created_by = ?",
        'ii',
        [
            $project_id,
            $_SESSION['user_id']
        ]
    );

    return count($rows) > 0;
}


// Validate a Y-m-d date string
function valid_date($str)
{
    $d = DateTime::createFromFormat('Y-m-d', $str);

    return $d && $d->format('Y-m-d') === $str;
}


/* ---------- PROJECT PROGRESS ---------- */

/*
 * Project progress is calculated automatically
 * based on the status of its tasks.
 *
 * To Do       = 0%
 * In Progress = 50%
 * Completed   = 100%
 * Failed      = 0%
 */
function get_project_progress($conn, $project_id)
{
    $rows = db_select(
        $conn,
        "SELECT status
         FROM tasks
         WHERE project_id = ?",
        'i',
        [$project_id]
    );

    // No tasks = 0%
    if (count($rows) === 0) {
        return 0;
    }

    $total_progress = 0;

    foreach ($rows as $task) {

        switch ($task['status']) {

            case 'Completed':
                $total_progress += 100;
                break;

            case 'In Progress':
                $total_progress += 50;
                break;

            case 'To Do':
                $total_progress += 0;
                break;

            case 'Failed':
                $total_progress += 0;
                break;
        }
    }

    return round(
        $total_progress / count($rows)
    );
}


/* ---------- TASK PROGRESS ---------- */

/*
 * Returns the progress percentage
 * for an individual task.
 */
function get_task_progress($status)
{
    switch ($status) {

        case 'Completed':
            return 100;

        case 'In Progress':
            return 50;

        case 'Failed':
            return 0;

        case 'To Do':
        default:
            return 0;
    }
}


/* ---------- DEADLINE MONITORING ---------- */

/*
 * Returns information about a project/task deadline.
 *
 * More than 7 days  = On Track
 * 1-7 days          = Deadline Near
 * 0 days            = Due Today
 * Past deadline     = Failed
 * Completed         = Completed
 */
function get_deadline_info($deadline, $status = null)
{
    $today = new DateTime(
        date('Y-m-d')
    );

    $due = new DateTime(
        $deadline
    );

    $difference = (int)$today
        ->diff($due)
        ->format('%r%a');


    // Completed work
    if ($status === 'Completed') {

        return [
            'type'    => 'completed',
            'label'   => 'Completed',
            'message' => 'Completed.',
            'days'    => $difference
        ];
    }


    // Deadline already passed
    if ($difference < 0) {

        return [
            'type'    => 'failed',
            'label'   => 'Failed',
            'message' => 'Deadline passed ' .
                         abs($difference) .
                         ' day(s) ago.',
            'days'    => $difference
        ];
    }


    // Due today
    if ($difference === 0) {

        return [
            'type'    => 'today',
            'label'   => 'Due Today',
            'message' => 'Deadline is today.',
            'days'    => 0
        ];
    }


    // Seven days or less
    if ($difference <= 7) {

        return [
            'type'    => 'warning',
            'label'   => 'Deadline Near',
            'message' => $difference .
                         ' day(s) remaining.',
            'days'    => $difference
        ];
    }


    // More than seven days
    return [
        'type'    => 'normal',
        'label'   => 'On Track',
        'message' => $difference .
                     ' day(s) remaining.',
        'days'    => $difference
    ];
}


/* ---------- AUTOMATIC OVERDUE TASKS ---------- */

/*
 * FR-8: Automatically changes unfinished tasks
 * whose deadline has passed into Failed.
 * Each change is written to the activity log once (FR-10).
 */
function update_overdue_tasks($conn)
{
    $late = db_select(
        $conn,
        "SELECT task_id, project_id, title
         FROM tasks
         WHERE due_date < CURDATE()
         AND status NOT IN ('Completed', 'Failed')"
    );

    foreach ($late as $t) {

        // Read affected rows from the statement itself (before it is closed),
        // so two page loads at the same moment cannot log the same change twice.
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE tasks
             SET status = 'Failed'
             WHERE task_id = ?
             AND status NOT IN ('Completed', 'Failed')"
        );

        $task_id = (int)$t['task_id'];
        mysqli_stmt_bind_param($stmt, 'i', $task_id);
        mysqli_stmt_execute($stmt);
        $changed = mysqli_stmt_affected_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        if ($changed) {
            log_activity(
                $conn,
                (int)$t['project_id'],
                'task_status',
                'Task "' . $t['title'] . '" was marked Failed automatically because its due date passed.',
                $task_id,
                null
            );
        }
    }
}


/* ---------- FR-5 / FR-6 / FR-7 Tasks ---------- */

function task_statuses()
{
    return [
        'To Do',
        'In Progress',
        'Completed',
        'Failed'
    ];
}


function task_priorities()
{
    return [
        'Low',
        'Medium',
        'High'
    ];
}


function task_status_badge($status)
{
    $colors = [
        'To Do'       => 'secondary',
        'In Progress' => 'primary',
        'Completed'   => 'success',
        'Failed'      => 'danger'
    ];

    $c = $colors[$status] ?? 'secondary';

    return "<span class='badge text-bg-{$c}'>" .
           e($status) .
           "</span>";
}


function priority_badge($priority)
{
    $colors = [
        'Low'    => 'secondary',
        'Medium' => 'warning',
        'High'   => 'danger'
    ];

    $c = $colors[$priority] ?? 'secondary';

    return "<span class='badge text-bg-{$c}'>" .
           e($priority) .
           "</span>";
}


// Returns the task row
function get_task($conn, $task_id)
{
    $rows = db_select(
        $conn,
        "SELECT t.*,
                p.project_name,
                CONCAT(a.first_name, ' ', a.last_name) AS assignee_name,
                CONCAT(c.first_name, ' ', c.last_name) AS creator_name
         FROM tasks t
         INNER JOIN projects p
             ON p.project_id = t.project_id
         INNER JOIN users c
             ON c.user_id = t.created_by
         LEFT JOIN users a
             ON a.user_id = t.assigned_to
         WHERE t.task_id = ?",
        'i',
        [$task_id]
    );

    return $rows[0] ?? null;
}


// Active members of a project
function get_assignable_members($conn, $project_id)
{
    return db_select(
        $conn,
        "SELECT u.user_id,
                u.first_name,
                u.last_name,
                pm.responsibility
         FROM project_members pm
         INNER JOIN users u
             ON u.user_id = pm.user_id
         WHERE pm.project_id = ?
         AND u.status = 'active'
         ORDER BY u.last_name ASC,
                  u.first_name ASC",
        'i',
        [$project_id]
    );
}


// True if user is an assignable member
function is_assignable($members, $user_id)
{
    foreach ($members as $m) {

        if (
            (int)$m['user_id']
            ===
            (int)$user_id
        ) {
            return true;
        }
    }

    return false;
}


// FR-7: person assigned to task,
// or whoever can manage project
function can_update_task_status($conn, $task)
{
    return can_manage_project(
        $conn,
        $task['project_id']
    )
    ||
    (
        (int)$task['assigned_to']
        ===
        (int)$_SESSION['user_id']
    );
}


/* ---------- Which projects can the current user see? ---------- */

/*
 * SQL fragment that limits rows to the projects the current user may view
 * (admin = all, everyone else = projects they belong to).
 * $column is the project_id column to test, e.g. 'p.project_id'.
 * Returns [sql, types, params] so callers can add it to their own query.
 */
function project_scope($column = 'p.project_id')
{
    if (has_role('admin')) {
        return ['1=1', '', []];
    }

    return [
        "$column IN (SELECT project_id FROM project_members WHERE user_id = ?)",
        'i',
        [(int)$_SESSION['user_id']]
    ];
}


// Projects the current user may view (for filter drop-downs)
function get_visible_projects($conn)
{
    [$where, $types, $params] = project_scope('p.project_id');

    return db_select(
        $conn,
        "SELECT p.project_id, p.project_name, p.status, p.start_date, p.deadline
         FROM projects p
         WHERE $where
         ORDER BY p.project_name ASC",
        $types,
        $params
    );
}


function user_full_name($conn, $user_id)
{
    $rows = db_select(
        $conn,
        "SELECT CONCAT(first_name, ' ', last_name) AS name
         FROM users
         WHERE user_id = ?",
        'i',
        [(int)$user_id]
    );

    return $rows[0]['name'] ?? 'Unknown user';
}


/* ---------- FR-8 Deadline Monitoring ---------- */

// Small coloured pill (same look as the JS due-date chips) for places
// without a table, e.g. kanban cards.
function due_chip($due_date, $status = null)
{
    if ($status === 'Completed') {
        return '';
    }

    $info = get_deadline_info($due_date, $status);
    $days = (int)$info['days'];

    switch ($info['type']) {
        case 'failed':
            $cls = 'due-over';
            $txt = 'Overdue ' . abs($days) . 'd';
            break;
        case 'today':
            $cls = 'due-soon';
            $txt = 'Due today';
            break;
        case 'warning':
            $cls = 'due-soon';
            $txt = $days === 1 ? 'Due tomorrow' : 'in ' . $days . ' days';
            break;
        default:
            $cls = 'due-ok';
            $txt = 'in ' . $days . ' days';
    }

    return "<span class='due-chip {$cls}'>" . e($txt) . "</span>";
}


/* ---------- FR-10 Activity Log ---------- */

function activity_types()
{
    return [
        'project_created' => ['label' => 'Project created',  'icon' => 'fa-folder-plus',        'color' => 'primary'],
        'project_status'  => ['label' => 'Project status',   'icon' => 'fa-flag',               'color' => 'info'],
        'member_added'    => ['label' => 'Member added',     'icon' => 'fa-user-plus',          'color' => 'success'],
        'member_removed'  => ['label' => 'Member removed',   'icon' => 'fa-user-minus',         'color' => 'secondary'],
        'task_created'    => ['label' => 'Task created',     'icon' => 'fa-square-plus',        'color' => 'primary'],
        'task_assigned'   => ['label' => 'Task assignment',  'icon' => 'fa-user-check',         'color' => 'warning'],
        'task_status'     => ['label' => 'Task status',      'icon' => 'fa-arrows-rotate',      'color' => 'info'],
        'comment_added'   => ['label' => 'Comment',          'icon' => 'fa-comment',            'color' => 'success'],
    ];
}


/*
 * Record one activity. Call it right after the change succeeds.
 *   $description  what happened, without the person's name
 *                 (the name is joined in when the log is shown)
 *   $user_id      false = the logged-in user, null = the system
 */
function log_activity($conn, $project_id, $type, $description, $task_id = null, $user_id = false)
{
    if (!isset(activity_types()[$type])) {
        return false;
    }

    if ($user_id === false) {
        $user_id = $_SESSION['user_id'] ?? null;
    }

    if (function_exists('mb_substr')) {
        $description = mb_substr($description, 0, 255);
    } else {
        $description = substr($description, 0, 255);
    }

    return db_run(
        $conn,
        "INSERT INTO activity_log
            (project_id, task_id, user_id, activity_type, description)
         VALUES (?, ?, ?, ?, ?)",
        'iiiss',
        [
            (int)$project_id,
            $task_id === null ? null : (int)$task_id,
            $user_id === null ? null : (int)$user_id,
            $type,
            $description
        ]
    );
}


// Prints a list of activity rows (needs: description, activity_type,
// actor_name, created_at, and project_name when $show_project is true)
function render_activity_list($rows, $show_project = false)
{
    $types = activity_types();

    if (count($rows) === 0) {
        echo '<p class="text-muted mb-0">No activity recorded yet.</p>';
        return;
    }

    echo '<ul class="list-group list-group-flush">';

    foreach ($rows as $r) {

        $t     = $types[$r['activity_type']] ?? ['label' => $r['activity_type'], 'icon' => 'fa-circle', 'color' => 'secondary'];
        $actor = $r['actor_name'] ? $r['actor_name'] : 'System';

        echo '<li class="list-group-item d-flex gap-3 align-items-start bg-transparent px-0">';
        echo '<span class="badge text-bg-' . e($t['color']) . ' mt-1" title="' . e($t['label']) . '">'
           . '<i class="fa-solid ' . e($t['icon']) . '"></i></span>';
        echo '<div class="flex-grow-1">';
        echo '<strong>' . e($actor) . '</strong> ' . e($r['description']);

        if ($show_project && !empty($r['project_name'])) {
            echo ' <span class="text-muted">in</span> '
               . '<a href="' . e($GLOBALS['base_url']) . '/projects/view.php?project_id=' . (int)$r['project_id'] . '">'
               . e($r['project_name']) . '</a>';
        }

        echo '<br><small class="text-muted">' . e($r['created_at']) . '</small>';
        echo '</div></li>';
    }

    echo '</ul>';
}


/* ---------- FR-9 Comments ---------- */

function comment_max_length()
{
    return 1000;
}
