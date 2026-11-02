<?php
// admin/orders.php - Admin View All Orders
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');
require_role('admin');

$status_filter = trim($_GET['status'] ?? '');

$sql = "SELECT * FROM v_order_totals";
$types = '';
$params = [];

if ($status_filter !== '' && in_array($status_filter, order_statuses(), true)) {
    $sql .= " WHERE status = ?";
    $types .= 's';
    $params[] = $status_filter;
}
$sql .= " ORDER BY order_id DESC";

$orders = db_select($conn, $sql, $types, $params);

include('../includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h2 class="fw-bold mb-0">Customer Orders & Transactions</h2>
    <p class="text-muted mb-0">View all customer orders, itemized transaction details, and update fulfillment statuses.</p>
  </div>
</div>

<!-- Status Filters -->
<div class="d-flex gap-2 mb-3 align-items-center flex-wrap">
  <span class="text-muted small fw-bold text-uppercase me-1">Status Filter:</span>
  <a href="orders.php" class="badge text-decoration-none px-3 py-2 rounded-pill <?php echo ($status_filter === '') ? 'bg-primary text-white' : 'bg-light text-dark border'; ?>">All (<?php echo count($orders); ?>)</a>
  <?php foreach (order_statuses() as $st) {
    $act = ($status_filter === $st) ? 'bg-primary text-white' : 'bg-light text-dark border';
    echo "<a href='orders.php?status=" . urlencode($st) . "' class='badge text-decoration-none px-3 py-2 rounded-pill {$act}'>" . e($st) . "</a>";
  } ?>
</div>

<div class="card shadow-sm border-0 table-card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Order #</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Email</th>
            <th>Items</th>
            <th>Grand Total</th>
            <th>Status</th>
            <th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)) { ?>
            <tr>
              <td colspan="8" class="text-center py-4 text-muted">No orders found matching the filter.</td>
            </tr>
          <?php } ?>
          <?php foreach ($orders as $o) { ?>
            <tr>
              <td class="ps-3 fw-bold">#<?php echo (int)$o['order_id']; ?></td>
              <td><small class="text-muted"><?php echo e(date('M d, Y h:i A', strtotime($o['created_at']))); ?></small></td>
              <td><span class="fw-semibold text-dark"><?php echo e($o['customer_name']); ?></span></td>
              <td><small class="text-muted"><?php echo e($o['customer_email']); ?></small></td>
              <td><span class="badge bg-light text-dark border"><?php echo (int)$o['item_count']; ?> item(s)</span></td>
              <td class="fw-bold text-primary">PHP <?php echo number_format($o['grand_total'], 2); ?></td>
              <td><?php echo order_status_badge($o['status']); ?></td>
              <td class="text-end pe-3">
                <div class="btn-group btn-group-sm">
                  <a href="<?php echo $base_url; ?>/admin/order_view.php?order_id=<?php echo (int)$o['order_id']; ?>" 
                     class="btn btn-primary" title="View & Manage Order">
                    <i class="fa-solid fa-pen-to-square me-1"></i> Manage
                  </a>
                  <a href="<?php echo $base_url; ?>/orders/receipt.php?order_id=<?php echo (int)$o['order_id']; ?>" 
                     class="btn btn-outline-danger" target="_blank" title="Download PDF Receipt">
                    <i class="fa-solid fa-file-pdf"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>
