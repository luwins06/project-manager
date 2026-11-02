# ProjectHub: Conventions & Architectural Guide
### ITIM211 Information Management (1st Term 2026)

## 1. Folder Structure

```
itim211-2b-taskmanager/
  index.php                 Homepage & Service Catalog with Search (Quiz 3)
  README.md                 Complete project overview & test guide
  CONVENTIONS.md            Coding standards, FRs, and architectural rules
  database.sql              Full 2NF database schema, views, and starter data
  mailoutput/               Local archival backup of HTML notifications and PDF receipts
  uploads/
    services/               Primary and gallery service photos (MP1)
    avatars/                User profile photos (MP2)
  includes/
    config.php              Database credentials, $base_url, Mailtrap SMTP settings
    functions.php           Prepared statement wrappers, role checks, profanity regex filter, project auto-spawner
    header.php              Responsive navbar, role-based menus, user profile avatar
    footer.php              Global footer markup and scripts
    mailer.php              Mailtrap SMTP delivery with attached PDF receipt
    pdf.php                 Pure PHP valid PDF receipt generator (ReceiptPDF)
    style/style.css         Theme stylesheet (cards, tables, badges, animations)
  services/
    index.php               Admin Product/Service CRUD list (MP1)
    create.php              Add service with primary & multiple gallery photos (MP1, MP5)
    edit.php                Edit service, update photos, manage status (MP1, MP5)
    delete.php              Deactivate / activate service (MP1)
    delete_photo.php        Remove single photo from gallery (MP1)
    view.php                Public details page, photo gallery carousel, verified customer reviews (MP1, MP4)
  user/
    register.php            Public customer registration with photo upload (MP2, MP5)
    login.php               Email authentication and session regeneration (MP3, MP5)
    logout.php              Session termination and redirection
    profile.php             View profile, update details and avatar photo, change password (MP2, MP5)
  admin/
    users.php               User management list, role filters (MP2)
    user_add.php            Admin create user with role & photo (MP2, MP5)
    user_edit.php           Admin update active user role & details (MP2, MP5)
    user_toggle.php         Admin deactivate / activate user (MP2)
    orders.php              Admin view all orders (Term Test Lab)
    order_view.php          Transaction details, update status, trigger Mailtrap email & PDF receipt (Term Test Lab)
  orders/
    checkout.php            Avail service package with prepared statements (Term Test Lab)
    my_orders.php           Customer transaction history, download PDF receipt, track project (FR-18)
    receipt.php             Stream / download PDF receipt directly in browser (Term Test Lab)
  reviews/
    create.php              Submit review (verified customer check + regex profanity mask) (MP4)
    edit.php                Update customer review (MP4)
    delete.php              Delete review (MP4)
  projects/
    index.php               Projects list (admin, PM, member, customer viewer)
    create.php              Create project (linked to order or internal) (FR-3, MP5)
    view.php                Project workspace, tasks, members, progress bar, order badge
    members.php             Manage project members roster (FR-4)
    member_add.php          Add member to project (FR-4)
    member_remove.php       Remove member from project (FR-4)
    member_update.php       Update member responsibility (FR-4)
    update_status.php       Update project status (FR-3)
  tasks/
    index.php               Task list, kanban board, filter by status / priority (FR-5 to FR-7)
    create.php              Create task with assignees & deadlines (FR-5, MP5)
    view.php                Task details, status updates, comments thread (FR-7, FR-9)
    assign.php              Reassign task to project member (FR-6)
    update_status.php       Update task status (FR-7)
    check_deadlines.php     Deadline monitoring, overdue alerts, automatic failed status (FR-8)
  comments/
    add.php                 Post comment to a task (FR-9)
  activities/
    index.php               System activity log (FR-10)
  dashboard/
    index.php               Executive metrics, progress bars, overdue alerts (FR-11)
  search/
    index.php               Internal project & task search filter (FR-12)
```

---

## 2. Core Functional Requirements (FR-1 to FR-18)

### I. User & Service Management
* **FR-1 User Management:** Allow users to register with photo avatars, update profile details, and change passwords. Administrators can manage, edit, and deactivate user accounts.
* **FR-2 Role-Based Access:** Enforce granular permissions across Admin, Project Manager, Member, and Customer roles.
* **FR-3 Service Management:** Allow administrators to create, read, update, and deactivate project services, including names, descriptions, categories, base pricing, and photos.
* **FR-4 Service Catalog:** Display available services with single and multiple gallery photos, categories, pricing, and duration to the public.

### II. Orders & Transactions
* **FR-5 Order Placement:** Allow authenticated customers to select and avail one or more services.
* **FR-6 Transaction Management:** Record transactions using prepared statements, preserving purchase-time unit prices in 2NF order line items.
* **FR-7 Order Status Management:** Allow administrators to review all customer orders and update transaction fulfillment statuses.
* **FR-8 Transaction Notifications:** Dispatch email notifications via Mailtrap when order status changes, containing itemized services, subtotals, grand total, and an attached PDF receipt.

### III. Project & Task Management (Fulfillment)
* **FR-9 Automated Project Creation:** Provision a dedicated project workspace automatically when a customer's order is confirmed.
* **FR-10 Project Membership:** Assign project leads, team members, and client viewers to projects.
* **FR-11 Task Management:** Allow project leads to create, assign, update, and manage project tasks with priorities and deadlines.
* **FR-12 Deadline Monitoring:** Monitor task deadlines and automatically mark overdue tasks as `Failed`.
* **FR-13 Task Collaboration:** Allow project members and managers to communicate via task comments.
* **FR-14 Activity Logging:** Automatically record system activities (order creation, project status, assignments, comments).

### IV. Reviews & Dashboards
* **FR-15 Verified Customer Reviews:** Allow customers who availed a service to submit and edit ratings and reviews on the service detail page.
* **FR-16 Profanity RegEx Masking:** Apply server-side PHP regular expressions to filter and mask foul/bad words in reviews.
* **FR-17 Project Dashboard:** Display overall project progress, task breakdown, completed/overdue metrics, and member rosters.
* **FR-18 Homepage Search:** Provide real-time keyword and category search on the public homepage.
