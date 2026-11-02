-- database.sql
-- ProjectHub: Project Management & Service Availing Platform (ITIM211 1st Term 2026)
-- Complete 2NF Relational Schema with MySQL VIEWS, Sample Services, Users, Orders, and Reviews

CREATE DATABASE IF NOT EXISTS db_taskmanager;
USE db_taskmanager;

-- ---------------------------------------------------------------
-- 1. Users Table (MP2 User CRUD, MP3 Login/Logout)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  user_id     INT AUTO_INCREMENT PRIMARY KEY,
  first_name  VARCHAR(50)  NOT NULL,
  last_name   VARCHAR(50)  NOT NULL,
  email       VARCHAR(100) NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,
  role        ENUM('admin','project_manager','member','customer') NOT NULL DEFAULT 'customer',
  photo       VARCHAR(255) NULL,
  status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default Accounts:
-- Admin: admin@taskmanager.local / admin123
-- PM: pm@taskmanager.local / password123
-- Member: member@taskmanager.local / password123
-- Customer: alex@taskmanager.local / password123
INSERT INTO users (user_id, first_name, last_name, email, password, role, photo, status) VALUES
(1, 'System', 'Admin', 'admin@taskmanager.local', '$2y$10$N.8vlM6bx3bg/mtC/yFrMuyO9zpowYWxzVXaDYXSmEc3JSFGwDwRG', 'admin', NULL, 'active'),
(2, 'Alexis', 'Salinas', 'alex@taskmanager.local', '$2y$10$hd4VySy/f9fAq/aLWRqCLeearg72e7eEIAuNrEh1l9LlKkewXUVk6', 'customer', NULL, 'active'),
(3, 'Louience', 'Tordecilla', 'pm@taskmanager.local', '$2y$10$hd4VySy/f9fAq/aLWRqCLeearg72e7eEIAuNrEh1l9LlKkewXUVk6', 'project_manager', NULL, 'active'),
(4, 'Dev', 'Member', 'member@taskmanager.local', '$2y$10$hd4VySy/f9fAq/aLWRqCLeearg72e7eEIAuNrEh1l9LlKkewXUVk6', 'member', NULL, 'active')
ON DUPLICATE KEY UPDATE first_name=VALUES(first_name);

-- ---------------------------------------------------------------
-- 2. Services / Products Table (MP1 Product/Service CRUD)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS services (
  service_id         INT AUTO_INCREMENT PRIMARY KEY,
  service_name       VARCHAR(100) NOT NULL,
  category           VARCHAR(50)  NOT NULL DEFAULT 'Development',
  price              DECIMAL(10,2) NOT NULL,
  estimated_duration VARCHAR(50)  NOT NULL DEFAULT '2-3 weeks',
  primary_photo      VARCHAR(255) NULL,
  description        TEXT NULL,
  status             ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO services (service_id, service_name, category, price, estimated_duration, primary_photo, description, status) VALUES
(1, 'Full-Stack Web App Development', 'Development', 25000.00, '3-4 weeks', NULL, 'Design and build of a responsive, database-driven web application with clean architecture and admin dashboard.', 'active'),
(2, 'Mobile Application Prototype', 'Development', 60000.00, '5-6 weeks', NULL, 'Cross-platform mobile application development from initial requirements to staging release.', 'active'),
(3, 'Project Management & Architecture Consulting', 'Consulting', 8000.00, '1-2 weeks', NULL, 'Professional IT project consulting: workflow planning, sprint scheduling, and architectural review.', 'active'),
(4, 'UI/UX Brand Design & Prototyping', 'UI/UX Design', 15000.00, '2 weeks', NULL, 'Complete modern UI/UX design system including Figma interactive prototypes and branding assets.', 'active')
ON DUPLICATE KEY UPDATE service_name=VALUES(service_name);

-- ---------------------------------------------------------------
-- 3. Service Photos Gallery (MP1: 20pts Multiple Photos)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS service_photos (
  photo_id      INT AUTO_INCREMENT PRIMARY KEY,
  service_id    INT NOT NULL,
  filename      VARCHAR(255) NOT NULL,
  is_primary    TINYINT(1) NOT NULL DEFAULT 0,
  uploaded_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------
-- 4. Customer Orders / Transactions (Term Test Lab & Unit 1 2NF)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  order_id    INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  status      ENUM('Pending','Confirmed','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- ---------------------------------------------------------------
-- 5. Order Items / Line Items (Term Test Lab & Unit 1 2NF)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
  order_item_id INT AUTO_INCREMENT PRIMARY KEY,
  order_id      INT NOT NULL,
  service_id    INT NOT NULL,
  unit_price    DECIMAL(10,2) NOT NULL,
  quantity      INT NOT NULL DEFAULT 1,
  UNIQUE KEY uq_order_service (order_id, service_id),
  FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES services(service_id)
);

-- Sample Transaction for Customer Alexis
INSERT INTO orders (order_id, user_id, status) VALUES
(1, 2, 'Confirmed')
ON DUPLICATE KEY UPDATE status=VALUES(status);

INSERT INTO order_items (order_id, service_id, unit_price, quantity) VALUES
(1, 1, 25000.00, 1)
ON DUPLICATE KEY UPDATE unit_price=VALUES(unit_price);

-- ---------------------------------------------------------------
-- 6. Projects Table (Fulfillment & Execution of Orders)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS projects (
  project_id    INT AUTO_INCREMENT PRIMARY KEY,
  order_id      INT NULL,
  project_name  VARCHAR(100) NOT NULL,
  description   TEXT,
  start_date    DATE NOT NULL,
  deadline      DATE NOT NULL,
  status        ENUM('Planning','In Progress','On Hold','Completed','Failed') NOT NULL DEFAULT 'Planning',
  created_by    INT NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id)   REFERENCES orders(order_id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- Initial Project tied to Order #1
INSERT INTO projects (project_id, order_id, project_name, description, start_date, deadline, status, created_by) VALUES
(1, 1, 'Client Order #1 - Web App Development', 'Dedicated project workspace generated from Order #1 for client Alexis Salinas.', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'In Progress', 1)
ON DUPLICATE KEY UPDATE project_name=VALUES(project_name);

-- ---------------------------------------------------------------
-- 7. Project Membership (FR-4)
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

INSERT INTO project_members (project_id, user_id, responsibility) VALUES
(1, 1, 'Project Lead'),
(1, 3, 'Project Manager'),
(1, 4, 'Full-Stack Developer'),
(1, 2, 'Client Viewer')
ON DUPLICATE KEY UPDATE responsibility=VALUES(responsibility);

-- ---------------------------------------------------------------
-- 8. Tasks Table (FR-5, FR-6, FR-7, FR-8)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tasks (
  task_id      INT AUTO_INCREMENT PRIMARY KEY,
  project_id   INT NOT NULL,
  title        VARCHAR(150) NOT NULL,
  description  TEXT,
  assigned_to  INT NULL,
  priority     ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
  start_date   DATE NOT NULL,
  due_date     DATE NOT NULL,
  status       ENUM('To Do','In Progress','Completed','Failed') NOT NULL DEFAULT 'To Do',
  created_by   INT NOT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id)  REFERENCES projects(project_id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_to) REFERENCES users(user_id),
  FOREIGN KEY (created_by)  REFERENCES users(user_id)
);

INSERT INTO tasks (task_id, project_id, title, description, assigned_to, priority, start_date, due_date, status, created_by) VALUES
(1, 1, 'Requirement Gathering & Client Specifications', 'Review client requirements and finalize deliverable specifications.', 3, 'High', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'Completed', 1),
(2, 1, 'Database Schema & Architecture Design', 'Prepare 2NF database schema and MySQL views.', 4, 'High', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'In Progress', 1),
(3, 1, 'Frontend UI & Responsive Views', 'Implement Bootstrap 5 components and interactive dashboards.', 4, 'Medium', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 'To Do', 1)
ON DUPLICATE KEY UPDATE title=VALUES(title);

-- ---------------------------------------------------------------
-- 9. Comments Table (FR-9)
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

INSERT INTO comments (task_id, user_id, comment_text) VALUES
(1, 3, 'Client requirements verified and approved.'),
(2, 4, 'MySQL views and prepared statements implemented.')
ON DUPLICATE KEY UPDATE comment_text=VALUES(comment_text);

-- ---------------------------------------------------------------
-- 10. Activity Log (FR-10)
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

INSERT INTO activity_log (project_id, task_id, user_id, activity_type, description) VALUES
(1, NULL, 1, 'project_created', 'Project workspace created automatically from Order #1.');

-- ---------------------------------------------------------------
-- 11. Customer Reviews Table (MP4: 20pts)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
  review_id     INT AUTO_INCREMENT PRIMARY KEY,
  service_id    INT NOT NULL,
  user_id       INT NOT NULL,
  rating        TINYINT NOT NULL,
  review_text   TEXT NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_service_user_review (service_id, user_id),
  FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)    REFERENCES users(user_id)
);

INSERT INTO reviews (service_id, user_id, rating, review_text) VALUES
(1, 2, 5, 'Exceptional web development service! The team launched our portal ahead of schedule and the project tracking made communication effortless.')
ON DUPLICATE KEY UPDATE review_text=VALUES(review_text);

-- ---------------------------------------------------------------
-- 12. MySQL VIEWS for Order Transaction Details (Unit 1: 20pts)
-- ---------------------------------------------------------------
CREATE OR REPLACE VIEW v_order_details AS
SELECT 
    o.order_id,
    o.status,
    o.created_at,
    o.updated_at,
    u.user_id,
    CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
    u.email AS customer_email,
    s.service_id,
    s.service_name,
    s.category,
    oi.unit_price,
    oi.quantity,
    (oi.unit_price * oi.quantity) AS subtotal
FROM orders o
JOIN users u ON u.user_id = o.user_id
JOIN order_items oi ON oi.order_id = o.order_id
JOIN services s ON s.service_id = oi.service_id;

CREATE OR REPLACE VIEW v_order_totals AS
SELECT 
    order_id,
    status,
    created_at,
    user_id,
    customer_name,
    customer_email,
    COUNT(*) AS item_count,
    SUM(subtotal) AS grand_total
FROM v_order_details
GROUP BY order_id, status, created_at, user_id, customer_name, customer_email;
