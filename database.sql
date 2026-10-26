-- database.sql
-- Project/Task Management System (ITIM211-2B)
-- Import this file in phpMyAdmin or run: mysql -u root < database.sql
-- Each FR block below is appended in order; later FRs add their tables at the bottom.

CREATE DATABASE IF NOT EXISTS db_taskmanager;
USE db_taskmanager;

-- ---------------------------------------------------------------
-- FR-1 User Management / FR-2 Role-Based Access
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  user_id     INT AUTO_INCREMENT PRIMARY KEY,
  first_name  VARCHAR(50)  NOT NULL,
  last_name   VARCHAR(50)  NOT NULL,
  email       VARCHAR(100) NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,                       -- password_hash() output
  role        ENUM('admin','project_manager','member') NOT NULL DEFAULT 'member',
  status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin account  ->  admin@taskmanager.local / admin123  (change after first login)
INSERT INTO users (first_name, last_name, email, password, role, status) VALUES
('System', 'Admin', 'admin@taskmanager.local',
 '$2y$10$N.8vlM6bx3bg/mtC/yFrMuyO9zpowYWxzVXaDYXSmEc3JSFGwDwRG', 'admin', 'active');

-- ---------------------------------------------------------------
-- FR-3 Project Creation
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS projects (
  project_id    INT AUTO_INCREMENT PRIMARY KEY,
  project_name  VARCHAR(100) NOT NULL,
  description   TEXT,
  start_date    DATE NOT NULL,
  deadline      DATE NOT NULL,
  status        ENUM('Planning','In Progress','On Hold','Completed') NOT NULL DEFAULT 'Planning',
  created_by    INT NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- ---------------------------------------------------------------
-- FR-4 Project Membership
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS project_members (
  project_member_id INT AUTO_INCREMENT PRIMARY KEY,
  project_id        INT NOT NULL,
  user_id           INT NOT NULL,
  responsibility    VARCHAR(100) NOT NULL DEFAULT 'Member',
  added_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_project_user (project_id, user_id),
  FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)    REFERENCES users(user_id)
);

-- ---------------------------------------------------------------
-- FR-5 Task Creation / FR-6 Task Assignment / FR-7 Task Status Tracking
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tasks (
  task_id      INT AUTO_INCREMENT PRIMARY KEY,
  project_id   INT NOT NULL,
  title        VARCHAR(150) NOT NULL,
  description  TEXT,
  assigned_to  INT NULL,                                    -- FR-6: NULL = unassigned
  priority     ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
  start_date   DATE NOT NULL,
  due_date     DATE NOT NULL,
  status       ENUM('To Do','In Progress','Completed') NOT NULL DEFAULT 'To Do',   -- FR-7
  created_by   INT NOT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id)  REFERENCES projects(project_id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_to) REFERENCES users(user_id),
  FOREIGN KEY (created_by)  REFERENCES users(user_id)
);

-- ---------------------------------------------------------------
-- FR-8 Deadline Monitoring
-- Unfinished tasks whose due date has passed are marked 'Failed' automatically
-- (update_overdue_tasks() in includes/functions.php), so 'Failed' must be an allowed value.
-- These ALTERs are safe to run more than once.
-- ---------------------------------------------------------------
ALTER TABLE tasks
  MODIFY status ENUM('To Do','In Progress','Completed','Failed') NOT NULL DEFAULT 'To Do';

ALTER TABLE projects
  MODIFY status ENUM('Planning','In Progress','On Hold','Completed','Failed') NOT NULL DEFAULT 'Planning';

-- ---------------------------------------------------------------
-- FR-9 Comments
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS comments (
  comment_id    INT AUTO_INCREMENT PRIMARY KEY,
  task_id       INT NOT NULL,
  user_id       INT NOT NULL,
  comment_text  TEXT NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- ---------------------------------------------------------------
-- FR-10 Activity Log
-- user_id is NULL when the system made the change (e.g. automatic overdue status)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_log (
  activity_id    INT AUTO_INCREMENT PRIMARY KEY,
  project_id     INT NOT NULL,
  task_id        INT NULL,
  user_id        INT NULL,
  activity_type  ENUM('project_created','project_status','member_added','member_removed',
                      'task_created','task_assigned','task_status','comment_added') NOT NULL,
  description    VARCHAR(255) NOT NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
  FOREIGN KEY (task_id)    REFERENCES tasks(task_id) ON DELETE SET NULL,
  FOREIGN KEY (user_id)    REFERENCES users(user_id)
);

-- FR-11 Project Dashboard and FR-12 Search & Filtering only read the tables above (no new tables).
