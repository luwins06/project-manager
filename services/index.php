<?php
// services/index.php - Admin Service Management
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');
require_role('admin');

$services = db_select(
    $conn,
    "SELECT s.*, 
            (SELECT COUNT(*) FROM service_photos sp WHERE sp.service_id = s.service_id) AS photo_count,
            (SELECT COUNT(*) FROM reviews r WHERE r.service_id = s.service_id) AS review_count
     FROM services s
     ORDER BY s.service_id DESC"
);

include('../includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h2 class="fw-bold mb-0">Product / Service Management</h2>
    <p class="text-muted mb-0">Create, update, view, and manage project service offerings and photo galleries.</p>
  </div>
  <a href="<?php echo $base_url; ?>/services/create.php" class="btn btn-primary">
    <i class="fa-solid fa-plus me-1"></i> Add New Service
  </a>
</div>

<div class="table-card card shadow-sm border-0">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3" style="width: 80px;">Image</th>
            <th>Service Name</th>
            <th>Category</th>
            <th>Price</th>
            <th>Duration</th>
            <th>Photos</th>
            <th>Status</th>
            <th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($services)) { ?>
            <tr>
              <td colspan="8" class="text-center py-4 text-muted">No services added yet. Click "Add New Service" to create one.</td>
            </tr>
          <?php } ?>
          <?php foreach ($services as $s) { 
            $img = (!empty($s['primary_photo']) && file_exists(__DIR__ . '/../' . $s['primary_photo']))
              ? $base_url . '/' . $s['primary_photo']
              : 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=150&q=80';
            $status_badge = ($s['status'] === 'active') 
              ? "<span class='badge bg-success-subtle text-success border border-success-subtle'>Active</span>"
              : "<span class='badge bg-secondary-subtle text-secondary border'>Inactive</span>";
          ?>
            <tr>
              <td class="ps-3">
                <img src="<?php echo e($img); ?>" alt="Thumb" class="rounded border" style="width: 60px; height: 45px; object-fit: cover;">
              </td>
              <td>
                <div class="fw-bold"><?php echo e($s['service_name']); ?></div>
                <small class="text-muted"><?php echo e(mb_strimwidth($s['description'] ?? '', 0, 60, '...')); ?></small>
              </td>
              <td><span class="badge bg-light text-dark border"><?php echo e($s['category']); ?></span></td>
              <td class="fw-semibold text-primary">PHP <?php echo number_format($s['price'], 2); ?></td>
              <td><small class="text-muted"><?php echo e($s['estimated_duration']); ?></small></td>
              <td>
                <span class="badge bg-info-subtle text-info border border-info-subtle">
                  <i class="fa-solid fa-images me-1"></i><?php echo (int)$s['photo_count']; ?>
                </span>
              </td>
              <td><?php echo $status_badge; ?></td>
              <td class="text-end pe-3">
                <div class="btn-group btn-group-sm">
                  <a href="<?php echo $base_url; ?>/services/view.php?service_id=<?php echo (int)$s['service_id']; ?>" 
                     class="btn btn-outline-secondary" title="View Public Page">
                    <i class="fa-solid fa-eye"></i>
                  </a>
                  <a href="<?php echo $base_url; ?>/services/edit.php?service_id=<?php echo (int)$s['service_id']; ?>" 
                     class="btn btn-outline-primary" title="Edit Service">
                    <i class="fa-solid fa-pen-to-square"></i>
                  </a>
                  <form method="POST" action="<?php echo $base_url; ?>/services/delete.php" class="d-inline"
                        onsubmit="return confirm('Toggle status for this service?');">
                    <input type="hidden" name="service_id" value="<?php echo (int)$s['service_id']; ?>">
                    <button type="submit" class="btn btn-outline-danger" title="Toggle Status">
                      <i class="fa-solid fa-power-off"></i>
                    </button>
                  </form>
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
