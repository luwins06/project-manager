<?php
// FR-5 / FR-6 / FR-7 / FR-8 - Interactive task board for one project (overdue tasks show as Failed)
session_start();

include('../includes/config.php');
include('../includes/functions.php');

require_login();

update_overdue_tasks($conn);

$project_id = (int)($_GET['project_id'] ?? 0);

$project = get_project($conn, $project_id);

if (!$project || !can_view_project($conn, $project_id)) {

    set_flash(
        'danger',
        'Project not found or you are not a member of it.'
    );

    redirect('projects/index.php');
}

$can_manage =
    can_manage_project($conn, $project_id);

$tasks = db_select(
    $conn,

    "SELECT
        t.task_id,
        t.title,
        t.description,
        t.priority,
        t.start_date,
        t.due_date,
        t.status,
        t.assigned_to,
        CONCAT(a.first_name, ' ', a.last_name)
            AS assignee_name

     FROM tasks t

     LEFT JOIN users a
        ON a.user_id = t.assigned_to

     WHERE t.project_id = ?

     ORDER BY
        FIELD(
            t.status,
            'To Do',
            'In Progress',
            'Completed'
        ),
        t.due_date ASC",

    'i',

    [$project_id]
);

$counts = [
    'To Do' => 0,
    'In Progress' => 0,
    'Completed' => 0,
    'Failed' => 0
];

foreach ($tasks as $t) {

    if (isset($counts[$t['status']])) {
        $counts[$t['status']]++;
    }

}

// FR-8: overdue tasks become 'Failed'. Their pill and board column
// only appear once at least one task has that status.
$board_statuses = ['To Do', 'In Progress', 'Completed'];

if ($counts['Failed'] > 0) {
    $board_statuses[] = 'Failed';
}

include('../includes/header.php');
?>

<div class="table-card interactive-tasks">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">

        <div>

            <h2>
                Tasks:
                <?php echo e($project['project_name']); ?>
            </h2>

            <p class="text-muted mb-0">
                Use List or Kanban view, search tasks,
                and filter by status.
            </p>

        </div>

        <div class="top-actions mb-0">

            <a
                href="../projects/view.php?project_id=<?php echo $project_id; ?>"
                class="button button-secondary">
                Back to Project
            </a>

            <a
                href="check_deadlines.php?project_id=<?php echo $project_id; ?>"
                class="button button-secondary">
                Deadlines
            </a>

            <?php if ($can_manage) { ?>

                <a
                    href="create.php?project_id=<?php echo $project_id; ?>"
                    class="button">
                    + New Task
                </a>

            <?php } ?>

        </div>

    </div>


    <!-- INTERACTIVE TOOLBAR -->

    <div class="interactive-toolbar">

        <div class="search-wrap">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input
                id="taskSearch"
                type="search"
                class="form-control"
                placeholder="Search tasks or assignees..."
                aria-label="Search tasks"
            >

        </div>


        <!-- STATUS FILTERS -->

        <div class="filter-pills">

            <button
                type="button"
                class="filter-pill active"
                data-filter="all">

                All
                (<?php echo count($tasks); ?>)

            </button>

            <?php foreach ($counts as $status => $number) {

                if ($status === 'Failed' && $number === 0) {
                    continue;
                }
            ?>

                <button
                    type="button"
                    class="filter-pill"
                    data-filter="<?php echo e($status); ?>">

                    <?php echo e($status); ?>
                    (<?php echo $number; ?>)

                </button>

            <?php } ?>

        </div>


        <!-- VIEW SWITCH -->

        <div class="view-toggle">

            <button
                type="button"
                class="button active"
                id="taskListBtn"
                title="List view">

                <i class="fa-solid fa-list"></i>

            </button>

            <button
                type="button"
                class="button button-secondary"
                id="taskBoardBtn"
                title="Kanban view">

                <i class="fa-solid fa-table-columns"></i>

            </button>

        </div>

        <span
            class="toolbar-count"
            id="taskCount">
        </span>

    </div>


    <?php if (!$tasks) { ?>

        <p class="text-center text-muted">
            No tasks in this project yet.
        </p>

    <?php } else { ?>


        <!-- LIST VIEW -->

        <div id="taskList">

            <div class="table-responsive">

                <table
                    class="table align-middle"
                    width="100%"
                    cellpadding="6"
                    cellspacing="0"
                >

                    <thead>

                        <tr>

                            <th>Task</th>
                            <th>Assigned To</th>
                            <th>Priority</th>
                            <th>Start</th>
                            <th>Due</th>
                            <th>Status</th>
                            <th>Actions</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($tasks as $i => $t) {

                        $searchText = strtolower(
                            $t['title'] . ' ' .
                            ($t['assignee_name'] ?? '') . ' ' .
                            $t['priority'] . ' ' .
                            $t['status']
                        );

                        $can_update =
                            $can_manage ||
                            (
                                (int)$t['assigned_to'] ===
                                (int)$_SESSION['user_id']
                            );
                    ?>

                        <tr
                            class="task-item"
                            data-status="<?php echo e($t['status']); ?>"
                            data-search="<?php echo e($searchText); ?>"
                        >

                            <td>
                                <strong>
                                    <?php echo e($t['title']); ?>
                                </strong>
                            </td>

                            <td>

                                <?php

                                if ($t['assignee_name']) {

                                    echo e(
                                        $t['assignee_name']
                                    );

                                } else {

                                    echo
                                        '<span class="text-muted">
                                            Unassigned
                                        </span>';
                                }

                                ?>

                            </td>

                            <td>
                                <?php
                                echo priority_badge(
                                    $t['priority']
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo e($t['start_date']);
                                ?>
                            </td>

                            <td>
                                <?php
                                echo e($t['due_date']);
                                ?>
                            </td>

                            <td>

                                <?php if ($can_update) { ?>

                                    <form
                                        method="POST"
                                        action="update_status.php"
                                        class="d-flex gap-1"
                                    >

                                        <input
                                            type="hidden"
                                            name="task_id"
                                            value="<?php
                                                echo (int)$t['task_id'];
                                            ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="return"
                                            value="index"
                                        >

                                        <select
                                            name="status"
                                            class="form-select form-select-sm"
                                        >

                                            <?php foreach (
                                                task_statuses()
                                                as $status
                                            ) { ?>

                                                <option
                                                    value="<?php
                                                        echo e($status);
                                                    ?>"
                                                    <?php
                                                    echo
                                                        $t['status'] ===
                                                        $status
                                                        ? 'selected'
                                                        : '';
                                                    ?>
                                                >

                                                    <?php
                                                    echo e($status);
                                                    ?>

                                                </option>

                                            <?php } ?>

                                        </select>

                                        <button
                                            type="submit">
                                            Save
                                        </button>

                                    </form>

                                <?php } else {

                                    echo task_status_badge(
                                        $t['status']
                                    );

                                } ?>

                            </td>

                            <td>

                                <a
                                    href="view.php?task_id=<?php
                                        echo (int)$t['task_id'];
                                    ?>"
                                    class="button">
                                    View
                                </a>

                            </td>

                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- KANBAN BOARD -->

        <div
            id="taskBoard"
            class="kanban-board<?php echo $counts['Failed'] > 0 ? ' has-failed' : ''; ?>"
            hidden
        >

            <?php foreach ($board_statuses as $status) { ?>

                <section
                    class="kanban-column"
                    data-column="<?php echo e($status); ?>"
                >

                    <h5>

                        <span>
                            <?php echo e($status); ?>
                        </span>

                        <span class="badge text-bg-secondary">
                            <?php echo $counts[$status]; ?>
                        </span>

                    </h5>


                    <div class="kanban-items">

                        <?php foreach ($tasks as $t) {

                            if ($t['status'] !== $status) {
                                continue;
                            }
                        ?>

                            <article
                                class="kanban-card task-board-item"
                                data-status="<?php
                                    echo e($t['status']);
                                ?>"
                                data-search="<?php

                                    echo e(
                                        strtolower(
                                            $t['title'] .
                                            ' ' .
                                            ($t['assignee_name'] ?? '')
                                        )
                                    );

                                ?>"
                            >

                                <h6>
                                    <?php echo e($t['title']); ?>
                                </h6>

                                <?php
                                echo priority_badge(
                                    $t['priority']
                                );
                                ?>

                                <p class="mb-2">

                                    <small>

                                        <?php

                                        if (
                                            $t['assignee_name']
                                        ) {

                                            echo
                                                'Assigned: ' .
                                                e(
                                                    $t[
                                                        'assignee_name'
                                                    ]
                                                );

                                        } else {

                                            echo 'Unassigned';

                                        }

                                        ?>

                                    </small>

                                </p>

                                <small>
                                    Due:
                                    <?php echo e($t['due_date']); ?>
                                </small>

                                <?php echo due_chip($t['due_date'], $t['status']); ?>

                                <div class="mt-2">

                                    <a
                                        href="view.php?task_id=<?php
                                            echo (int)$t['task_id'];
                                        ?>"
                                        class="button">
                                        Open Task
                                    </a>

                                </div>

                            </article>

                        <?php } ?>

                    </div>

                </section>

            <?php } ?>

        </div>


        <!-- NO SEARCH RESULTS -->

        <div
            id="taskEmpty"
            class="empty-state"
            hidden
            style="padding:30px;text-align:center;"
        >
            No matching tasks.
        </div>

    <?php } ?>

</div>


<script>
(function () {

    var root =
        document.querySelector('.interactive-tasks');

    if (!root) return;

    var filter = 'all';
    var q = '';

    var items = [].slice.call(
        root.querySelectorAll('.task-item')
    );

    var boardItems = [].slice.call(
        root.querySelectorAll('.task-board-item')
    );

    var count =
        root.querySelector('#taskCount');

    var empty =
        root.querySelector('#taskEmpty');


    function visible(element) {

        return (
            filter === 'all' ||
            element.dataset.status === filter
        ) &&
        (
            !q ||
            element.dataset.search.indexOf(q) > -1
        );

    }


    function apply() {

        var shown = 0;


        /* LIST */

        items.forEach(function (element) {

            var ok = visible(element);

            element.hidden = !ok;

            if (ok) {
                shown++;
            }

        });


        /* KANBAN */

        boardItems.forEach(function (element) {

            element.hidden =
                !visible(element);

        });


        count.textContent =
            shown +
            ' of ' +
            items.length +
            ' shown';


        if (empty) {

            empty.hidden =
                shown !== 0;

        }

    }


    /* FILTER BUTTONS */

    root.querySelectorAll('.filter-pill')
        .forEach(function (button) {

            button.addEventListener(
                'click',
                function () {

                    filter =
                        button.dataset.filter;


                    root.querySelectorAll(
                        '.filter-pill'
                    ).forEach(function (item) {

                        item.classList.toggle(
                            'active',
                            item === button
                        );

                    });

                    apply();

                }
            );

        });


    /* SEARCH */

    root.querySelector('#taskSearch')
        .addEventListener(
            'input',
            function () {

                q =
                    this.value
                    .toLowerCase()
                    .trim();

                apply();

            }
        );


    var list =
        root.querySelector('#taskList');

    var board =
        root.querySelector('#taskBoard');


    /* LIST VIEW */

    root.querySelector('#taskListBtn')
        .addEventListener(
            'click',
            function () {

                list.hidden = false;
                board.hidden = true;

                this.classList.add('active');

                root.querySelector(
                    '#taskBoardBtn'
                ).classList.remove('active');

            }
        );


    /* KANBAN VIEW */

    root.querySelector('#taskBoardBtn')
        .addEventListener(
            'click',
            function () {

                list.hidden = true;
                board.hidden = false;

                this.classList.add('active');

                root.querySelector(
                    '#taskListBtn'
                ).classList.remove('active');

            }
        );


    apply();

})();
</script>


<?php include('../includes/footer.php'); ?>