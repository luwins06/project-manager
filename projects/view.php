
<?php
session_start();

include('../includes/config.php');
include('../includes/functions.php');

require_login();

$project_id = (int)($_GET['project_id'] ?? 0);
$project = get_project($conn, $project_id);

if (!$project || !can_view_project($conn, $project_id)) {
    set_flash('danger', 'Project not found or you do not have access.');
    redirect('projects/index.php');
}

$can_manage = can_manage_project($conn, $project_id);

/* Update overdue tasks, if the helper exists. */
if (function_exists('update_overdue_tasks')) {
    update_overdue_tasks($conn);
}

/* Fetch task counts. */
$status_counts = [
    'To Do' => 0,
    'In Progress' => 0,
    'Completed' => 0,
    'Failed' => 0
];

$count_rows = db_select(
    $conn,
    "SELECT status, COUNT(*) AS total
     FROM tasks
     WHERE project_id = ?
     GROUP BY status",
    'i',
    [$project_id]
);

foreach ($count_rows as $row) {
    if (isset($status_counts[$row['status']])) {
        $status_counts[$row['status']] = (int)$row['total'];
    }
}

$task_total = array_sum($status_counts);
$completed_tasks = $status_counts['Completed'];

/* Calculate project progress. */
$project_progress = 0;

if ($task_total > 0) {
    $project_progress = (int) round(
        (
            $completed_tasks
            + ($status_counts['In Progress'] * 0.5)
        ) / $task_total * 100
    );
}

/* Automatically complete the project when all tasks are completed. */
if ($task_total > 0 && $completed_tasks === $task_total) {

    if ($project['status'] !== 'Completed') {
        db_run(
            $conn,
            "UPDATE projects
             SET status = 'Completed'
             WHERE project_id = ?",
            'i',
            [$project_id]
        );

        $project['status'] = 'Completed';

        // FR-10: automatic change, so no user is credited
        log_activity($conn, $project_id, 'project_status',
            'Project "' . $project['project_name'] . '" was marked Completed automatically because all tasks are completed.', null, null);
    }

} elseif (
    $task_total > 0 &&
    $completed_tasks < $task_total &&
    $project['status'] === 'Completed'
) {
    /* Reopen the project if a task is no longer completed. */
    $new_status = 'In Progress';

    db_run(
        $conn,
        "UPDATE projects
         SET status = ?
         WHERE project_id = ?",
        'si',
        [$new_status, $project_id]
    );

    $project['status'] = $new_status;

    // FR-10: automatic change, so no user is credited
    log_activity($conn, $project_id, 'project_status',
        'Project "' . $project['project_name'] . '" was reopened (In Progress) because a task is no longer completed.', null, null);
}

/* Calculate deadline warning. */
$today = date('Y-m-d');
$deadline = $project['deadline'];

if ($project['status'] === 'Completed') {
    $deadline_type = 'completed';
    $deadline_label = 'Completed';
    $deadline_message = 'All project tasks are completed.';

} elseif ($deadline < $today) {
    $days_late = (int)floor(
        (strtotime($today) - strtotime($deadline)) / 86400
    );

    $deadline_type = 'failed';
    $deadline_label = 'Deadline Passed';
    $deadline_message = $days_late . ' day(s) overdue.';

} elseif ($deadline === $today) {
    $deadline_type = 'today';
    $deadline_label = 'Due Today';
    $deadline_message = 'The project deadline is today.';

} else {
    $days_remaining = (int)floor(
        (strtotime($deadline) - strtotime($today)) / 86400
    );

    $deadline_type = $days_remaining <= 7 ? 'warning' : 'normal';
    $deadline_label = $days_remaining <= 7 ? 'Deadline Near' : 'On Track';
    $deadline_message = $days_remaining . ' day(s) remaining.';
}

/* Fetch members. */
$members = db_select(
    $conn,
    "SELECT u.first_name, u.last_name, u.email, u.role,
            pm.responsibility, pm.added_at
     FROM project_members pm
     INNER JOIN users u ON u.user_id = pm.user_id
     WHERE pm.project_id = ?
     ORDER BY pm.added_at ASC",
    'i',
    [$project_id]
);

include('../includes/header.php');
?>

<style>
.project-panel {
    background: #fff;
    padding: 26px;
    margin: 20px auto;
    border-radius: 20px;
    border-top: 7px solid #a83df0;
    box-shadow: 0 8px 28px rgba(50,35,90,.09);
}

.project-heading {
    text-align: center;
    font-size: 30px;
    font-weight: 900;
    overflow-wrap: anywhere;
    color: #a83df0;
    margin-bottom: 24px;
}

.project-info-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
}

.project-info {
    min-width: 0;
    padding: 16px;
    border-radius: 14px;
    background: #fcfaff;
    border-top: 4px solid #873df5;
    box-shadow: 0 5px 15px rgba(40,30,70,.06);
    overflow-wrap: anywhere;
}

.project-info:nth-child(2) { border-color: #ed3b91; }
.project-info:nth-child(3) { border-color: #ff7900; }
.project-info:nth-child(4) { border-color: #10b99c; }

.project-info-label {
    display: block;
    color: #74788b;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    margin-bottom: 12px;
}

.project-info-value { color: #34345e; }

.project-status-form { margin-top: 12px; }
.project-status-form select { width: 100%; }

.deadline-box {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 13px 16px;
    margin: 18px 0;
    border: 1px solid transparent;
    border-radius: 12px;
}

.deadline-normal { background: #f4f8ff; border-color: #dbe7ff; }
.deadline-warning { background: #fff8e7; border-color: #f5d98b; }
.deadline-today { background: #fff0d9; border-color: #f3b64b; }
.deadline-failed { background: #fff0f0; border-color: #f1b5b5; }
.deadline-completed { background: #edf9f0; border-color: #b7dfc0; }

.project-section-title {
    color: #6326c5;
    font-weight: 900;
    margin: 25px 0 15px;
}

.project-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.project-stat {
    padding: 22px 10px;
    border-radius: 18px;
    color: #fff;
    text-align: center;
}

.project-stat-number {
    display: block;
    font-size: 36px;
    font-weight: 900;
}

.project-stat-label {
    font-size: 13px;
    font-weight: 800;
    text-transform: uppercase;
}

.stat-todo-new { background: linear-gradient(120deg,#ff7515,#ed4099); }
.stat-progress-new { background: linear-gradient(120deg,#159ce8,#7435ed); }
.stat-done-new { background: linear-gradient(120deg,#13b6a2,#1cc56a); }

.project-progress { margin: 20px 0; }
.project-progress-heading {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    color: #6326c5;
    font-size: 13px;
    font-weight: 800;
    margin-bottom: 8px;
}

.progress-track {
    height: 15px;
    overflow: hidden;
    border-radius: 999px;
    background: #eeeaf7;
}

.progress-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg,#13bd93,#31c84b);
}

.project-actions {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 18px;
}

@media(max-width:768px) {
    .project-info-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
    .project-panel { padding: 17px; }
}

@media(max-width:480px) {
    .project-info-grid, .project-stats { grid-template-columns: 1fr; }
    .project-heading { font-size: 24px; }
}
</style>

<div class="table-card project-panel">

    <h2 class="project-heading">
        <?php echo e($project['project_name']); ?>
    </h2>

    <div class="project-info-grid">

        <div class="project-info">
            <span class="project-info-label">Status</span>

            <?php echo status_badge($project['status']); ?>

            <?php if ($can_manage) { ?>
                
<form method="POST" action="update_status.php">
    <input
        type="hidden"
        name="project_id"
        value="<?php echo (int)$project_id; ?>"
    >

    <select name="status" class="form-select" required>
        <?php foreach (project_statuses() as $option) { ?>
            <option
                value="<?php echo e($option); ?>"
                <?php echo $project['status'] === $option ? 'selected' : ''; ?>
            >
                <?php echo e($option); ?>
            </option>
        <?php } ?>
    </select>

    <button type="submit" class="button">Save Status</button>
</form>

            <?php } ?>
        </div>

        <div class="project-info">
            <span class="project-info-label">Start Date</span>
            <div class="project-info-value">
                <?php echo e($project['start_date']); ?>
            </div>
        </div>

        <div class="project-info">
            <span class="project-info-label">Deadline</span>
            <div class="project-info-value">
                <?php echo e($project['deadline']); ?>
            </div>
        </div>

        <div class="project-info">
            <span class="project-info-label">Created By</span>
            <div class="project-info-value">
                <?php echo e($project['owner_name']); ?>
            </div>
        </div>

    </div>

    <div class="deadline-box deadline-<?php echo e($deadline_type); ?>">
        <strong><?php echo e($deadline_label); ?></strong>
        <span><?php echo e($deadline_message); ?></span>
    </div>

    <p>
        <strong>Description</strong><br>
        <?php
        echo !empty($project['description'])
            ? nl2br(e($project['description']))
            : '<span class="text-muted">No description.</span>';
        ?>
    </p>

    <hr>

    <h4 class="project-section-title">Tasks</h4>

    <div class="project-stats">

        <div class="project-stat stat-todo-new">
            <span class="project-stat-number">
                <?php echo $status_counts['To Do']; ?>
            </span>
            <span class="project-stat-label">To Do</span>
        </div>

        <div class="project-stat stat-progress-new">
            <span class="project-stat-number">
                <?php echo $status_counts['In Progress']; ?>
            </span>
            <span class="project-stat-label">In Progress</span>
        </div>

        <div class="project-stat stat-done-new">
            <span class="project-stat-number">
                <?php echo $status_counts['Completed']; ?>
            </span>
            <span class="project-stat-label">Completed</span>
        </div>

    </div>

    <?php if ($status_counts['Failed'] > 0) { ?>
        <p class="text-danger mt-3">
            <strong>Failed tasks:</strong>
            <?php echo $status_counts['Failed']; ?>
        </p>
    <?php } ?>

    <div class="project-progress">

        <div class="project-progress-heading">
            <span>⚑ Project progress</span>
            <span><?php echo $project_progress; ?>% complete</span>
        </div>

        <div
            class="progress-track"
            role="progressbar"
            aria-valuenow="<?php echo $project_progress; ?>"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div
                class="progress-fill"
                style="width:<?php echo $project_progress; ?>%;"
            ></div>
        </div>

        <small class="text-muted">
            Progress is calculated from task statuses.
            Completed = 100%, In Progress = 50%, To Do or Failed = 0%.
        </small>

    </div>

    <div class="project-actions">
        <a
            href="<?php echo e($base_url); ?>/tasks/index.php?project_id=<?php echo $project_id; ?>"
            class="button"
        >View Tasks</a>

        <a
            href="<?php echo e($base_url); ?>/dashboard/index.php?project_id=<?php echo $project_id; ?>"
            class="button button-secondary"
        >Dashboard</a>

        <a
            href="<?php echo e($base_url); ?>/activities/index.php?project_id=<?php echo $project_id; ?>"
            class="button button-secondary"
        >Activity Log</a>

        <?php if ($can_manage) { ?>
            <a
                href="<?php echo e($base_url); ?>/tasks/create.php?project_id=<?php echo $project_id; ?>"
                class="button"
            >+ New Task</a>
        <?php } ?>
    </div>

    <hr>

    <h4 class="project-section-title">
        Members (<?php echo count($members); ?>)
    </h4>

    <?php if ($can_manage) { ?>
        <div class="project-actions">
            <a
                href="<?php echo e($base_url); ?>/projects/members.php?project_id=<?php echo $project_id; ?>"
                class="button"
            >Manage Members</a>
        </div>
    <?php } ?>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>System Role</th>
                    <th>Responsibility</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)) { ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">
                            No project members found.
                        </td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($members as $member) { ?>
                        <tr>
                            <td>
                                <?php echo e(
                                    $member['first_name'] . ' ' .
                                    $member['last_name']
                                ); ?>
                            </td>
                            <td><?php echo e($member['email']); ?></td>
                            <td><?php echo e(role_label($member['role'])); ?></td>
                            <td><?php echo e($member['responsibility']); ?></td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <a
        href="<?php echo e($base_url); ?>/projects/index.php"
        class="button button-secondary"
    >Back to Projects</a>

</div>

<?php include('../includes/footer.php'); ?>
