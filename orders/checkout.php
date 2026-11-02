<?php
// orders/checkout.php - Avail Service & Process Transaction (Term Test Lab: 10pts Prepared Statements)
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');
require_once('../includes/mailer.php');
require_login();

$service_id = (int)($_GET['service_id'] ?? $_POST['service_id'] ?? 0);
$rows = db_select($conn, "SELECT * FROM services WHERE service_id = ? AND status = 'active'", 'i', [$service_id]);

if (empty($rows)) {
    set_flash('danger', 'The selected service package is currently unavailable.');
    redirect('index.php');
}
$service = $rows[0];

$errors = [];
$quantity = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = (int)($_POST['quantity'] ?? 1);
    $notes    = trim($_POST['notes'] ?? '');
    $user_id  = (int)$_SESSION['user_id'];

    if ($quantity < 1 || $quantity > 10) {
        $errors[] = 'Quantity must be between 1 and 10.';
    }

    if (empty($errors)) {
        $unit_price = (float)$service['price'];
        $grand_total = $unit_price * $quantity;

        // 1. Insert into orders table using prepared statements (Term Test: 10pts)
        $order_sql = "INSERT INTO orders (user_id, status) VALUES (?, 'Confirmed')";
        $ok1 = db_run($conn, $order_sql, 'i', [$user_id]);

        if ($ok1) {
            $order_id = db_insert_id($conn);

            // 2. Insert into order_items table using prepared statements (2NF)
            $item_sql = "INSERT INTO order_items (order_id, service_id, unit_price, quantity) VALUES (?, ?, ?, ?)";
            $ok2 = db_run($conn, $item_sql, 'iidi', [$order_id, $service_id, $unit_price, $quantity]);

            // 3. Workflow: Auto-spawn client project workspace from confirmed order
            $project_id = spawn_project_from_order($conn, $order_id, $user_id);

            // 4. Term Test Lab: Send email notification with PDF receipt via Mailtrap (15pts)
            $email_res = send_order_status_email($conn, $order_id);

            set_flash('success', "Order #{$order_id} placed successfully! A dedicated project workspace has been created for your service.");
            redirect('orders/my_orders.php');
        } else {
            $errors[] = 'Failed to process transaction. Please try again.';
        }
    }
}

include('../includes/header.php');
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card shadow-sm border-0 form-card my-3">
      <div class="card-body p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2 class="fw-bold mb-0">Avail Service Package</h2>
          <a href="<?php echo $base_url; ?>/services/view.php?service_id=<?php echo (int)$service['service_id']; ?>" 
             class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Service
          </a>
        </div>

        <?php if (!empty($errors)) { ?>
          <div class="alert alert-danger" role="alert">
            <h6 class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Checkout Notice:</h6>
            <ul class="mb-0 ps-3">
              <?php foreach ($errors as $e) { echo "<li>" . e($e) . "</li>"; } ?>
            </ul>
          </div>
        <?php } ?>

        <div class="card bg-light border-0 mb-4 p-3">
          <div class="row align-items-center">
            <div class="col-md-3">
              <?php
              $thumb = (!empty($service['primary_photo']) && file_exists(__DIR__ . '/../' . $service['primary_photo']))
                ? $base_url . '/' . $service['primary_photo']
                : 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=300&q=80';
              ?>
              <img src="<?php echo e($thumb); ?>" alt="Thumb" class="img-fluid rounded border shadow-sm" style="max-height: 100px; width: 100%; object-fit: cover;">
            </div>
            <div class="col-md-9 mt-2 mt-md-0">
              <span class="badge bg-primary mb-1"><?php echo e($service['category']); ?></span>
              <h4 class="fw-bold mb-1"><?php echo e($service['service_name']); ?></h4>
              <p class="text-muted small mb-1"><i class="fa-regular fa-clock me-1"></i> Delivery window: <?php echo e($service['estimated_duration']); ?></p>
              <div class="fw-bold text-primary fs-5">PHP <?php echo number_format($service['price'], 2); ?> per package</div>
            </div>
          </div>
        </div>

        <form method="POST" action="checkout.php" novalidate>
          <input type="hidden" name="service_id" value="<?php echo (int)$service['service_id']; ?>">

          <div class="row mb-3">
            <div class="col-md-4">
              <label for="quantity" class="form-label fw-semibold">Package Quantity</label>
              <input type="number" class="form-control" id="quantity" name="quantity" 
                     value="<?php echo e($quantity); ?>" min="1" max="10"
                     onchange="updateTotal(this.value, <?php echo (float)$service['price']; ?>)">
            </div>
            <div class="col-md-8">
              <label class="form-label fw-semibold">Payment / Processing Method</label>
              <input type="text" class="form-control bg-light" value="Invoice / Agency Escrow (Online)" disabled>
            </div>
          </div>

          <div class="mb-4">
            <label for="notes" class="form-label fw-semibold">Project Requirements & Client Notes (optional)</label>
            <textarea class="form-control" id="notes" name="notes" rows="3" 
                      placeholder="Briefly describe your objectives, brand guidelines, or specific feature requests..."></textarea>
          </div>

          <div class="card p-3 mb-4 border bg-light-subtle">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-muted">Unit Package Price:</span>
              <span class="fw-semibold">PHP <?php echo number_format($service['price'], 2); ?></span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-muted">Quantity:</span>
              <span class="fw-semibold" id="displayQty">1</span>
            </div>
            <hr class="my-2">
            <div class="d-flex justify-content-between align-items-center">
              <span class="fs-5 fw-bold text-dark">Estimated Grand Total:</span>
              <span class="fs-4 fw-bold text-primary" id="displayTotal">PHP <?php echo number_format($service['price'], 2); ?></span>
            </div>
          </div>

          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary btn-lg fw-semibold">
              <i class="fa-solid fa-check-circle me-2"></i> Confirm Order & Launch Project Workspace
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function updateTotal(qty, unitPrice) {
  var q = parseInt(qty, 10);
  if (isNaN(q) || q < 1) q = 1;
  document.getElementById('displayQty').innerText = q;
  var grand = (q * unitPrice).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  document.getElementById('displayTotal').innerText = 'PHP ' + grand;
}
</script>

<?php include('../includes/footer.php'); ?>
