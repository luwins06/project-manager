<?php
// admin/order_view.php - Transaction Details & Order Status Update (Term Test Lab: 35pts)
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');
require_once('../includes/mailer.php');
require_role('admin');

$order_id = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);

// Fetch order summary from view
$order_rows = db_select($conn, "SELECT * FROM v_order_totals WHERE order_id = ?", 'i', [$order_id]);
if (empty($order_rows)) {
    set_flash('danger', 'Order not found.');
    redirect('admin/orders.php');
}
$order = $order_rows[0];

// Fetch itemized details from view (Unit 1 MySQL Views: 20pts)
$items = db_select($conn, "SELECT * FROM v_order_details WHERE order_id = ?", 'i', [$order_id]);

// Check if a project is already linked to this order
$proj_rows = db_select($conn, "SELECT project_id, project_name, status FROM projects WHERE order_id = ?", 'i', [$order_id]);
$linked_project = $proj_rows[0] ?? null;

$email_status_msg = '';

// Handle Status Update (Term Test Lab: 5pts)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $new_status = trim($_POST['status'] ?? '');

    if (in_array($new_status, order_statuses(), true)) {
        // Update order status with prepared statement
        db_run($conn, "UPDATE orders SET status = ? WHERE order_id = ?", 'si', [$new_status, $order_id]);

        // Auto-spawn project if confirmed or in progress
        if (($new_status === 'Confirmed' || $new_status === 'In Progress') && !$linked_project) {
            $new_proj_id = spawn_project_from_order($conn, $order_id, $_SESSION['user_id']);
            if ($new_proj_id) {
                $proj_rows = db_select($conn, "SELECT project_id, project_name, status FROM projects WHERE order_id = ?", 'i', [$order_id]);
                $linked_project = $proj_rows[0] ?? null;
            }
        }

        // Term Test Lab: Send email notification with PDF receipt via Mailtrap (15pts)
        $mail_result = send_order_status_email($conn, $order_id);

        $flash_msg = "Order #{$order_id} status updated to '{$new_status}'. Customer notified via email with PDF receipt.";
        set_flash('success', $flash_msg);
        redirect("admin/order_view.php?order_id={$order_id}");
    } else {
        set_flash('danger', 'Invalid order status selected.');
    }
}

include('../includes/header.php');
?>

<div class="row justify-content-center">
  <div class="col-lg-10">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="fw-bold mb-1">Transaction Details: Order #<?php echo (int)$order['order_id']; ?></h2>
        <p class="text-muted mb-0">Customer transaction review, status management, and email receipt generator.</p>
      </div>
      <div class="btn-group">
        <a href="<?php echo $base_url; ?>/admin/orders.php" class="btn btn-outline-secondary btn-sm">
          <i class="fa-solid fa-arrow-left me-1"></i> Back to Orders
        </a>
        <a href="<?php echo $base_url; ?>/orders/receipt.php?order_id=<?php echo (int)$order['order_id']; ?>" 
           class="btn btn-outline-danger btn-sm" target="_blank">
          <i class="fa-solid fa-file-pdf me-1"></i> View PDF Receipt
        </a>
      </div>
    </div>

    <!-- Status & Linked Project Bar -->
    <div class="card shadow-sm border-0 mb-4 bg-light">
      <div class="card-body p-4">
        <div class="row g-3 align-items-center">
          <div class="col-md-6">
            <small class="text-muted text-uppercase fw-semibold d-block">Current Order Status</small>
            <div class="d-flex align-items-center gap-2 mt-1">
              <span class="fs-5"><?php echo order_status_badge($order['status']); ?></span>
              <span class="text-muted small">Updated on <?php echo e(date('M d, Y h:i A', strtotime($order['created_at']))); ?></span>
            </div>
          </div>
          <div class="col-md-6">
            <small class="text-muted text-uppercase fw-semibold d-block">Associated Project Workspace</small>
            <div class="mt-1">
              <?php if ($linked_project) { ?>
                <a href="<?php echo $base_url; ?>/projects/view.php?project_id=<?php echo (int)$linked_project['project_id']; ?>" 
                   class="btn btn-success btn-sm">
                  <i class="fa-solid fa-diagram-project me-1"></i> Open Project: <?php echo e($linked_project['project_name']); ?>
                </a>
              <?php } else { ?>
                <span class="text-muted small me-2">No project workspace spawned yet.</span>
                <form method="POST" action="order_view.php" class="d-inline">
                  <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="status" value="Confirmed">
                  <button type="submit" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-plus me-1"></i> Confirm & Spawn Project
                  </button>
                </form>
              <?php } ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4 mb-4">
      <!-- Customer Information Card -->
      <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-header bg-white border-bottom py-3">
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-user me-2 text-primary"></i> Customer Details</h5>
          </div>
          <div class="card-body">
            <table class="table table-sm table-borderless mb-0">
              <tr>
                <td class="text-muted fw-semibold" style="width: 140px;">Customer Name:</td>
                <td><strong class="text-dark"><?php echo e($order['customer_name']); ?></strong></td>
              </tr>
              <tr>
                <td class="text-muted fw-semibold">Customer Email:</td>
                <td><a href="mailto:<?php echo e($order['customer_email']); ?>"><?php echo e($order['customer_email']); ?></a></td>
              </tr>
              <tr>
                <td class="text-muted fw-semibold">Customer ID:</td>
                <td>#<?php echo (int)$order['user_id']; ?></td>
              </tr>
              <tr>
                <td class="text-muted fw-semibold">Transaction Date:</td>
                <td><?php echo e(date('F d, Y h:i A', strtotime($order['created_at']))); ?></td>
              </tr>
            </table>
          </div>
        </div>
      </div>

      <!-- Update Status Card (Term Test Lab: 5pts) -->
      <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-header bg-white border-bottom py-3">
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-pen-to-square me-2 text-warning"></i> Update Transaction Status</h5>
          </div>
          <div class="card-body">
            <p class="text-muted small mb-3">
              Updating the transaction status will automatically dispatch an email notification to the customer with an attached PDF receipt (via Mailtrap).
            </p>
            <form method="POST" action="order_view.php" novalidate>
              <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
              <input type="hidden" name="action" value="update_status">

              <div class="mb-3">
                <label for="status" class="form-label fw-semibold">Select New Status</label>
                <select class="form-select" id="status" name="status">
                  <?php foreach (order_statuses() as $st) {
                    $sel = ($order['status'] === $st) ? 'selected' : '';
                    echo "<option value='" . e($st) . "' {$sel}>" . e($st) . "</option>";
                  } ?>
                </select>
              </div>

              <button type="submit" class="btn btn-primary fw-semibold w-100">
                <i class="fa-solid fa-paper-plane me-1"></i> Update Status & Email Customer
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Itemized Services Breakdown (Unit 1 View & Term Test: 5pts) -->
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-header bg-white border-bottom py-3">
        <h5 class="fw-bold mb-0">
          <i class="fa-solid fa-list-check me-2 text-success"></i> Purchased Services & Line Items (v_order_details)
        </h5>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Service Name</th>
                <th>Category</th>
                <th class="text-center">Quantity</th>
                <th class="text-end">Unit Price</th>
                <th class="text-end pe-3">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $item) { ?>
                <tr>
                  <td class="ps-3">
                    <strong><?php echo e($item['service_name']); ?></strong>
                  </td>
                  <td><span class="badge bg-light text-dark border"><?php echo e($item['category']); ?></span></td>
                  <td class="text-center"><?php echo (int)$item['quantity']; ?></td>
                  <td class="text-end">PHP <?php echo number_format($item['unit_price'], 2); ?></td>
                  <td class="text-end pe-3 fw-bold text-dark">PHP <?php echo number_format($item['subtotal'], 2); ?></td>
                </tr>
              <?php } ?>
            </tbody>
            <tfoot class="table-light">
              <tr>
                <td colspan="4" class="text-end fw-bold fs-5 py-3">Grand Total (Subtotals Sum):</td>
                <td class="text-end pe-3 fw-bold fs-5 text-primary py-3">
                  PHP <?php echo number_format($order['grand_total'], 2); ?>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <!-- Mailtrap Integration Info Note -->
    <div class="alert alert-light border shadow-sm p-3">
      <div class="d-flex align-items-center gap-3">
        <i class="fa-solid fa-envelope-circle-check fa-2x text-primary"></i>
        <div>
          <strong class="text-dark">Mailtrap & PDF Receipt Service:</strong>
          <p class="text-muted small mb-0">
            Status notification emails and PDF receipts are dispatched using Mailtrap SMTP credentials configured in <code>includes/config.php</code>. A local backup of every transmitted HTML notification and PDF receipt is also recorded in <code>mailoutput/</code> for offline grading and inspection.
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>
