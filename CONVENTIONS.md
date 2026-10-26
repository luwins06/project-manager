# Project/Task Management System: Conventions

Follow this file for every FR delivered after FR-4. It is based on the `itim211-2b-shop` guide (plain PHP + procedural `mysqli`, Bootstrap 5 via CDN, `includes/` folder, one folder per module).

## 1. Folder layout

```
itim211-2b-taskmanager/
  index.php                 entry point (redirects)
  database.sql              ALL tables; each FR appends its own block
  includes/
    config.php              DB connection + $base_url
    functions.php           shared helpers (e(), redirect(), db_select(), db_run(), role/permission checks)
    header.php              <head>, Bootstrap navbar, flash messages, opens <div class="container page-content">
    footer.php              closes container, footer note, </body></html>
    style/style.css         shared styles (same palette/fonts as the guide)
  user/                     login, logout, profile             (FR-1)
  admin/                    user management, admin-only pages  (FR-1, FR-2)
  projects/                 projects + members                 (FR-3, FR-4)
  tasks/                    task list, create, view, assign, update status, check_deadlines (FR-5 to FR-8)
  comments/                 add.php, posts a task comment; comments are listed on tasks/view.php (FR-9)
  activities/               index.php, the activity log; writes happen through log_activity() (FR-10)
  dashboard/                index.php, project / overview dashboard (FR-11)
  search/                   index.php, task and project search with filters (FR-12)
```

New module = new folder named after the feature, plural noun (`tasks/`, `comments/`).

## 2. Page template (every page follows this order)

```php
<?php
// FR-n Feature Name - what this page does (who may access it)
session_start();
include('../includes/config.php');
include('../includes/functions.php');
require_login();                  // or require_role('admin') / require_role(['admin','project_manager'])

// 1. read GET/POST + validate
// 2. run queries / handle form submit (redirect() after successful POST)

include('../includes/header.php');   // output starts here, so no redirect() below this line
?>

<div class="form-card"> or <div class="table-card"> ... </div>

<?php include('../includes/footer.php'); ?>
```

Rules:
- Session, config, functions, access guard, POST handling, then header. Redirects must happen before the header is included.
- Pages that only process a form (no HTML) are "action scripts", e.g. `member_add.php`, `user_toggle.php`. They accept POST only, check permission, run the query, `set_flash()`, then `redirect()`.
- File names: `snake_case.php`. Lists = `index.php` / `users.php`, forms = `create.php` / `user_add.php` / `user_edit.php`, details = `view.php`.
- The first comment line names the FR: `// FR-5 Task Creation - ...`.

## 3. Database

- Database: `db_taskmanager`. Each table has `<singular>_id INT AUTO_INCREMENT PRIMARY KEY`.
- Foreign keys are declared. Use `ON DELETE CASCADE` only for child rows that make no sense without the parent (e.g. `project_members`).
- Status/role fields are `ENUM`; the allowed values also live in a helper function in `functions.php` (`project_statuses()`, `role_options()`) so forms and validation share one list.
- Every FR adds a labelled block to the bottom of `database.sql`: `-- FR-n Name`.
- Always use `db_select()` / `db_run()` (prepared statements). Never put `$_GET`/`$_POST` values directly in SQL.

## 4. Roles and access (FR-2)

Session keys: `$_SESSION['user_id']`, `['name']`, `['email']`, `['role']`, where role is `admin`, `project_manager` or `member`.

| Area | Admin | Project Manager | Member |
|---|---|---|---|
| Manage users (`admin/`) | yes | no | no |
| Own profile / password | yes | yes | yes |
| Create project | yes | yes | no |
| View project | all projects | projects they belong to | projects they belong to |
| Manage project members | all projects | only projects they created | no |
| View tasks of a project | all projects | projects they belong to | projects they belong to |
| Create / assign tasks | all projects | only projects they created | no |
| Update task status | all tasks | tasks in projects they created, or tasks assigned to them | only tasks assigned to them |
| Comment on a task (FR-9) | all projects | projects they belong to | projects they belong to |
| Dashboard, deadlines, activity log, search (FR-8, 10, 11, 12) | all projects | projects they belong to | projects they belong to |

`project_scope('<alias>.project_id')` in `functions.php` returns the SQL that limits any query to the projects the current user may see; use it for every cross-project list.

Use `require_role()` for page-level access and `can_view_project()` / `can_manage_project()` for project-level checks. Add new permission helpers to `functions.php`; do not repeat role logic inside pages.

## 5. UI conventions

- Bootstrap 5.3.2 classes for layout and forms (`form-control`, `form-select`, `row/col-md-*`, `badge`, `alert`).
- Wrap content in `.form-card` (forms) or `.table-card` (lists/details). Heading is an `<h2>` inside the card.
- Tables: `table-responsive` wrapper, header columns, rows alternate `class="even"` / `class="odd"` (like the guide's cart table).
- Buttons: `<button type="submit">` or `<a class="button">`; destructive = `button-danger`; cancel/back = `button-secondary`.
- Feedback: `set_flash('success'|'danger'|'warning'|'info', 'message')` then `redirect()`. Validation errors on the same page are shown as `alert alert-danger` above the form.
- Escape everything printed with `e()`. Confirm destructive actions with `onsubmit="return confirm('...');"`.
- Add a link for each new page to the navbar in `includes/header.php`, shown according to role.

## 6. Format of each FR delivery

Every response that delivers FR(s) uses this structure:

1. One-sentence summary of what was added.
2. **Files** — new and changed files (path + purpose).
3. **Database** — the SQL block appended to `database.sql`.
4. **Access rules** — who can do what (use the table in section 4 if it changes).
5. **How to test** — numbered steps with the expected result.
6. **Assumptions / not yet included** — anything deferred to a later FR.

## 7. Setup (XAMPP)

1. Copy the folder to `htdocs/itim211-2b-taskmanager`.
2. Import `database.sql` in phpMyAdmin.
3. Open `http://localhost/itim211-2b-taskmanager/` and log in with `admin@taskmanager.local` / `admin123`.

## 8. Overdue tasks and the activity log (FR-8, FR-10)

- `update_overdue_tasks($conn)` marks unfinished tasks past their due date as `Failed`. Call it at the top of any page that counts or lists tasks. `Failed` must exist in the `tasks.status` and `projects.status` ENUMs (see the FR-8 block in `database.sql`).
- "Overdue" in counts means `due_date < CURDATE()` and status is not `Completed`.
- After every successful change call `log_activity($conn, $project_id, $type, $description, $task_id)`. The description is the action without the person's name, for example `changed the status of task "X" from To Do to In Progress`; the name is joined in when the log is shown. Pass `null` as the last argument for changes made by the system. New types go in `activity_types()` and in the `activity_type` ENUM.
