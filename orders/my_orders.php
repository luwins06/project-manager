<?php
// orders/my_orders.php - Customer Orders & Transaction History (FR-18)
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');
require_login();

$user_id = (int)$_SESSION['user_id'];

// Fetch customer's orders from view
$orders = db_select(
    $conn,
    "SELECT o.*, p.project_id, p.project_name
     FROM v_order_totals o
     LEFT JOIN projects p ON p.order_id = o.order_id
     WHERE o.user_id = ?
     ORDER BY o.order_id DESC",
    'i',
    [$user_id]
);

include('../includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h2 class="fw-bold mb-0">My Service Orders</h2>
    <p class="text-muted mb-0">Review your past transactions, download official PDF receipts, and monitor active projects.</p>
  </div>
  <a href="<?php echo $base_url; ?>/index.php" class="btn btn-outline-primary btn-sm">
    <i class="fa-solid fa-store me-1"></i> Browse More Services
  </a>
</div>

<?php if (empty($orders)) { ?>
  <div class="alert alert-info text-center py-5 shadow-sm">
    <i class="fa-solid fa-receipt fa-3x mb-3 text-info"></i>
    <h5>You haven't ordered any services yet.</h5>
    <p class="text-muted mb-3">Browse our available project packages and services to get started.</p>
    <a href="<?php echo $base_url; ?>/index.php" class="btn btn-primary">Explore Services Catalog</a>
  </div>
<?php } else { ?>
  <div class="card shadow-sm border-0 table-card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Order #</th>
              <th>Date</th>
              <th>Status</th>
              <th>Items</th>
              <th>Grand Total</th>
              <th>Associated Project Workspace</th>
              <th class="text-end pe-3">Receipt & Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $ord) { 
              // Fetch itemized names
              $item_names = db_select($conn, "SELECT service_name FROM v_order_details WHERE order_id = ?", 'i', [$ord['order_id']]);
              $services_summary = implode(', ', array_map(function($i) { return $i['service_name']; }, $item_names));
            ?>
              <tr>
                <td class="ps-3 fw-bold">#<?php echo (int)$ord['order_id']; ?></td>
                <td><small class="text-muted"><?php echo e(date('M d, Y h:i A', strtotime($ord['created_at']))); ?></small></td>
                <td><?php echo order_status_badge($ord['status']); ?></td>
                <td>
                  <span class="badge bg-light text-dark border"><?php echo (int)$ord['item_count']; ?> package(s)</span>
                  <div class="small text-muted"><?php echo e(mb_strimwidth($services_summary, 0, 45, '...')); ?></div>
                </td>
                <td class="fw-bold text-primary">PHP <?php echo number_format($ord['grand_total'], 2); ?></td>
                <td>
                  <?php if (!empty($ord['project_id'])) { ?>
                    <a href="<?php echo $base_url; ?>/projects/view.php?project_id=<?php echo (int)$ord['project_id']; ?>" 
                       class="btn btn-sm btn-outline-success">
                      <i class="fa-solid fa-diagram-project me-1"></i> Track Project
                    </a>
                  <?php } else { ?>
                    <span class="text-muted small fst-italic">Pending Setup</span>
                  <?php } ?>
                </td>
                <td class="text-end pe-3">
                  <a href="<?php echo $base_url; ?>/orders/receipt.php?order_id=<?php echo (int)$ord['order_id']; ?>" 
                     class="btn btn-sm btn-outline-danger" target="_blank" title="Download PDF Receipt">
                    <i class="fa-solid fa-file-pdf me-1"></i> PDF Receipt
                  </a>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php } ?>

<?php include('../includes/footer.php'); ?>
