<?php
// index.php - Homepage & Service Catalog with Search (Quiz 3: 15pts)
session_start();
require_once(__DIR__ . '/includes/config.php');
require_once(__DIR__ . '/includes/functions.php');

$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

// Fetch distinct categories for filter pills
$cat_rows = db_select($conn, "SELECT DISTINCT category FROM services WHERE status = 'active' ORDER BY category ASC");

// Build search query with prepared statements
$sql = "SELECT s.*, 
               (SELECT COUNT(*) FROM reviews r WHERE r.service_id = s.service_id) AS review_count,
               (SELECT AVG(rating) FROM reviews r WHERE r.service_id = s.service_id) AS avg_rating,
               (SELECT photo_path FROM service_photos sp WHERE sp.service_id = s.service_id LIMIT 1) AS gallery_sample
        FROM services s
        WHERE s.status = 'active'";

$types  = '';
$params = [];

if ($search !== '') {
    $sql .= " AND (s.service_name LIKE ? OR s.description LIKE ? OR s.category LIKE ?)";
    $like = '%' . $search . '%';
    $types .= 'sss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($category !== '' && $category !== 'all') {
    $sql .= " AND s.category = ?";
    $types .= 's';
    $params[] = $category;
}

$sql .= " ORDER BY s.service_id DESC";

$services = db_select($conn, $sql, $types, $params);

include(__DIR__ . '/includes/header.php');
?>

<!-- Hero Banner -->
<div class="p-4 p-md-5 mb-4 rounded-3 text-bg-dark hero-banner" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
  <div class="col-md-9 px-0">
    <span class="badge bg-primary mb-2 px-3 py-2 text-uppercase fw-semibold letter-spacing-1">
      <i class="fa-solid fa-rocket me-1"></i> Agency & Project Services
    </span>
    <h1 class="display-5 fw-bold text-white mb-2">Build, Launch, & Scale Your Projects</h1>
    <p class="lead my-3 text-light-emphasis text-slate-300">
      Browse our specialized project packages. When you avail a service, our system immediately provisions a dedicated project workspace with milestones, assigned engineers, and live progress tracking.
    </p>
  </div>
</div>

<!-- Homepage Search & Filter Section (Quiz 3: 15pts) -->
<div class="card shadow-sm border-0 mb-4 bg-light">
  <div class="card-body p-3 p-md-4">
    <form method="GET" action="index.php" class="row g-2 align-items-center">
      <div class="col-md-7 col-lg-8">
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0 text-muted">
            <i class="fa-solid fa-magnifying-glass"></i>
          </span>
          <input type="text" class="form-control border-start-0" name="search" 
                 placeholder="Search services by keyword, category, or description..." 
                 value="<?php echo e($search); ?>">
        </div>
      </div>
      <div class="col-md-3 col-lg-2">
        <select class="form-select" name="category">
          <option value="all">All Categories</option>
          <?php foreach ($cat_rows as $c) { 
            $sel = ($category === $c['category']) ? 'selected' : '';
            echo "<option value='" . e($c['category']) . "' {$sel}>" . e($c['category']) . "</option>";
          } ?>
        </select>
      </div>
      <div class="col-md-2 col-lg-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary w-100 fw-semibold">
          <i class="fa-solid fa-filter me-1"></i> Search
        </button>
        <?php if ($search !== '' || ($category !== '' && $category !== 'all')) { ?>
          <a href="index.php" class="btn btn-outline-secondary" title="Reset Filters">
            <i class="fa-solid fa-rotate-left"></i>
          </a>
        <?php } ?>
      </div>
    </form>
  </div>
</div>

<!-- Category Quick Pills -->
<div class="d-flex flex-wrap gap-2 mb-4 align-items-center">
  <span class="text-muted small fw-bold text-uppercase me-2"><i class="fa-solid fa-tags me-1"></i> Filter:</span>
  <a href="index.php" class="badge text-decoration-none px-3 py-2 rounded-pill <?php echo ($category === '' || $category === 'all') ? 'bg-primary text-white' : 'bg-light text-dark border'; ?>">All</a>
  <?php foreach ($cat_rows as $c) {
    $active = ($category === $c['category']) ? 'bg-primary text-white' : 'bg-light text-dark border';
    echo "<a href='index.php?category=" . urlencode($c['category']) . "' class='badge text-decoration-none px-3 py-2 rounded-pill {$active}'>" . e($c['category']) . "</a>";
  } ?>
</div>

<!-- Header / Counter / Admin Action -->
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="fw-bold mb-0">Available Services</h3>
    <small class="text-muted">Showing <?php echo count($services); ?> service package(s) available</small>
  </div>
  <?php if (has_role('admin')) { ?>
    <a href="<?php echo $base_url; ?>/services/create.php" class="btn btn-success btn-sm">
      <i class="fa-solid fa-circle-plus me-1"></i> Add New Service
    </a>
  <?php } ?>
</div>

<!-- Services Grid (MP1 View & Quiz 3 Results) -->
<?php if (empty($services)) { ?>
  <div class="alert alert-info text-center py-5 shadow-sm">
    <i class="fa-solid fa-circle-info fa-3x mb-3 text-info"></i>
    <h5>No services found matching your search.</h5>
    <p class="text-muted mb-3">Try different keywords or view all available service packages.</p>
    <a href="index.php" class="btn btn-outline-primary">Browse All Services</a>
  </div>
<?php } else { ?>
  <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
    <?php foreach ($services as $s) { 
      // Primary photo fallback
      $img_src = (!empty($s['primary_photo']) && file_exists(__DIR__ . '/' . $s['primary_photo']))
        ? $base_url . '/' . $s['primary_photo']
        : 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=600&q=80';
      
      $avg = round((float)($s['avg_rating'] ?? 0), 1);
      $rev_count = (int)($s['review_count'] ?? 0);
    ?>
      <div class="col">
        <div class="card h-100 shadow-sm border-0 service-card hover-lift">
          <div class="position-relative">
            <img src="<?php echo e($img_src); ?>" class="card-img-top" alt="<?php echo e($s['service_name']); ?>" style="height: 200px; object-fit: cover;">
            <span class="badge bg-dark bg-opacity-75 position-absolute top-0 start-0 m-2 px-2 py-1">
              <?php echo e($s['category']); ?>
            </span>
            <span class="badge bg-primary position-absolute top-0 end-0 m-2 px-2 py-1">
              <i class="fa-regular fa-clock me-1"></i><?php echo e($s['estimated_duration']); ?>
            </span>
          </div>
          <div class="card-body d-flex flex-column">
            <h5 class="card-title fw-bold text-dark mb-1"><?php echo e($s['service_name']); ?></h5>
            
            <!-- Rating Preview -->
            <div class="mb-2 text-warning small">
              <?php 
              for ($star = 1; $star <= 5; $star++) {
                if ($star <= floor($avg)) {
                  echo '<i class="fa-solid fa-star"></i>';
                } elseif ($star - $avg <= 0.5 && $star > $avg) {
                  echo '<i class="fa-solid fa-star-half-stroke"></i>';
                } else {
                  echo '<i class="fa-regular fa-star text-muted"></i>';
                }
              }
              ?>
              <span class="text-muted ms-1">(<?php echo $avg > 0 ? $avg : '0'; ?> / <?php echo $rev_count; ?> reviews)</span>
            </div>

            <p class="card-text text-muted small flex-grow-1">
              <?php echo e(mb_strimwidth($s['description'] ?? '', 0, 115, '...')); ?>
            </p>

            <div class="pt-3 border-top mt-auto d-flex justify-content-between align-items-center">
              <div>
                <small class="text-muted d-block">Starting at</small>
                <span class="fs-5 fw-bold text-primary">PHP <?php echo number_format($s['price'], 2); ?></span>
              </div>
              <div class="btn-group">
                <a href="<?php echo $base_url; ?>/services/view.php?service_id=<?php echo (int)$s['service_id']; ?>" 
                   class="btn btn-outline-primary btn-sm px-3">
                  Details
                </a>
                <a href="<?php echo $base_url; ?>/orders/checkout.php?service_id=<?php echo (int)$s['service_id']; ?>" 
                   class="btn btn-primary btn-sm px-3">
                  Avail
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php } ?>
  </div>
<?php } ?>

<?php include(__DIR__ . '/includes/footer.php'); ?>
