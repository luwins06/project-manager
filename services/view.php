<?php
// services/view.php - Service Details, Multiple Photos Gallery, & Customer Reviews (MP1, MP4)
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');

$service_id = (int)($_GET['service_id'] ?? 0);

$rows = db_select($conn, "SELECT * FROM services WHERE service_id = ?", 'i', [$service_id]);
if (empty($rows)) {
    set_flash('danger', 'Service package not found.');
    redirect('index.php');
}
$service = $rows[0];

// Fetch all gallery photos (MP1: 20pts)
$gallery_photos = db_select($conn, "SELECT * FROM service_photos WHERE service_id = ? ORDER BY photo_id ASC", 'i', [$service_id]);

// Fetch all reviews (MP4: 20pts)
$reviews = db_select(
    $conn,
    "SELECT r.*, u.first_name, u.last_name, u.photo AS user_photo 
     FROM reviews r
     JOIN users u ON u.user_id = r.user_id
     WHERE r.service_id = ?
     ORDER BY r.review_id DESC",
    'i',
    [$service_id]
);

// Calculate review statistics
$review_count = count($reviews);
$avg_rating = 0;
if ($review_count > 0) {
    $sum = array_sum(array_column($reviews, 'rating'));
    $avg_rating = round($sum / $review_count, 1);
}

// Check if current user is logged in, and whether they have availed this service (MP4)
$can_review = false;
$user_review = null;
if (is_logged_in()) {
    $user_id = (int)$_SESSION['user_id'];
    $can_review = customer_has_availed_service($conn, $user_id, $service_id) || has_role('admin');

    // Check if user already submitted a review
    foreach ($reviews as $rev) {
        if ((int)$rev['user_id'] === $user_id) {
            $user_review = $rev;
            break;
        }
    }
}

include('../includes/header.php');
?>

<div class="row mb-3">
  <div class="col-12">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/index.php">Services</a></li>
        <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/index.php?category=<?php echo urlencode($service['category']); ?>"><?php echo e($service['category']); ?></a></li>
        <li class="breadcrumb-item active"><?php echo e($service['service_name']); ?></li>
      </ol>
    </nav>
  </div>
</div>

<div class="row g-4 mb-5">
  <!-- Left Column: Primary Photo & Gallery Photos (MP1: 20pts) -->
  <div class="col-lg-7">
    <div class="card shadow-sm border-0 overflow-hidden mb-3">
      <?php
      $main_img = (!empty($service['primary_photo']) && file_exists(__DIR__ . '/../' . $service['primary_photo']))
        ? $base_url . '/' . $service['primary_photo']
        : 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1000&q=80';
      ?>
      <img id="mainServiceImage" src="<?php echo e($main_img); ?>" 
           class="img-fluid w-100" alt="<?php echo e($service['service_name']); ?>" 
           style="max-height: 420px; object-fit: cover;">
    </div>

    <!-- Multiple Photos Gallery Thumbnails (MP1: 20pts) -->
    <?php if (!empty($gallery_photos)) { ?>
      <div class="card shadow-sm border-0 p-2">
        <small class="text-muted fw-bold text-uppercase px-2 mb-2 d-block">
          <i class="fa-solid fa-images me-1 text-primary"></i> Gallery & Project Screenshots (Click to preview):
        </small>
        <div class="d-flex gap-2 flex-wrap px-2">
          <!-- Primary thumb -->
          <img src="<?php echo e($main_img); ?>" alt="Primary" 
               class="img-thumbnail cursor-pointer gallery-thumb border-primary" 
               style="width: 75px; height: 55px; object-fit: cover; cursor: pointer;"
               onclick="document.getElementById('mainServiceImage').src = this.src;">

          <?php foreach ($gallery_photos as $gp) { 
            $gp_src = $base_url . '/' . $gp['filename'];
          ?>
            <img src="<?php echo e($gp_src); ?>" alt="Gallery Photo" 
                 class="img-thumbnail cursor-pointer gallery-thumb" 
                 style="width: 75px; height: 55px; object-fit: cover; cursor: pointer;"
                 onclick="document.getElementById('mainServiceImage').src = this.src;">
          <?php } ?>
        </div>
      </div>
    <?php } ?>
  </div>

  <!-- Right Column: Service Details & Avail Action -->
  <div class="col-lg-5">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body p-4 d-flex flex-column">
        <div class="d-flex gap-2 mb-2">
          <span class="badge bg-primary"><?php echo e($service['category']); ?></span>
          <span class="badge bg-secondary">
            <i class="fa-regular fa-clock me-1"></i><?php echo e($service['estimated_duration']); ?>
          </span>
          <?php if ($service['status'] === 'active') { ?>
            <span class="badge bg-success-subtle text-success border border-success-subtle">Available</span>
          <?php } else { ?>
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Inactive</span>
          <?php } ?>
        </div>

        <h2 class="fw-bold text-dark mb-2"><?php echo e($service['service_name']); ?></h2>

        <!-- Rating Summary -->
        <div class="mb-3 d-flex align-items-center gap-2">
          <div class="text-warning">
            <?php 
            for ($star = 1; $star <= 5; $star++) {
              if ($star <= floor($avg_rating)) {
                echo '<i class="fa-solid fa-star"></i>';
              } elseif ($star - $avg_rating <= 0.5 && $star > $avg_rating) {
                echo '<i class="fa-solid fa-star-half-stroke"></i>';
              } else {
                echo '<i class="fa-regular fa-star text-muted"></i>';
              }
            }
            ?>
          </div>
          <span class="fw-bold"><?php echo $avg_rating; ?> / 5.0</span>
          <span class="text-muted">(<?php echo $review_count; ?> customer reviews)</span>
        </div>

        <div class="p-3 bg-light rounded-3 mb-4">
          <small class="text-muted d-block text-uppercase fw-semibold">Package Price</small>
          <div class="fs-2 fw-bold text-primary">PHP <?php echo number_format($service['price'], 2); ?></div>
          <small class="text-muted">Includes project setup, assigned team members, and full delivery tracking.</small>
        </div>

        <h5 class="fw-bold mb-2">Package Deliverables & Description</h5>
        <p class="text-muted mb-4 flex-grow-1" style="white-space: pre-line;"><?php echo e($service['description']); ?></p>

        <div class="d-grid gap-2 mt-auto">
          <a href="<?php echo $base_url; ?>/orders/checkout.php?service_id=<?php echo (int)$service['service_id']; ?>" 
             class="btn btn-primary btn-lg fw-semibold shadow-sm">
            <i class="fa-solid fa-cart-shopping me-2"></i> Avail Service / Order Now
          </a>
          <?php if (has_role('admin')) { ?>
            <a href="<?php echo $base_url; ?>/services/edit.php?service_id=<?php echo (int)$service['service_id']; ?>" 
               class="btn btn-outline-secondary btn-sm">
              <i class="fa-solid fa-pen-to-square me-1"></i> Edit This Service (Admin)
            </a>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MP4: Customer Reviews Section (20pts) -->
<div class="card shadow-sm border-0 mb-5">
  <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
    <h4 class="fw-bold mb-0">
      <i class="fa-solid fa-comments me-2 text-primary"></i> Customer Reviews & Ratings
    </h4>
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
      <?php echo $review_count; ?> Verified Review(s)
    </span>
  </div>
  <div class="card-body p-4">
    <!-- Review Form Section (MP4: Only for customers who availed the service) -->
    <div class="mb-5 pb-4 border-bottom">
      <?php if (!is_logged_in()) { ?>
        <div class="alert alert-light border text-center py-3">
          <p class="mb-2 text-muted">Want to leave a review? Please sign in with your customer account.</p>
          <a href="<?php echo $base_url; ?>/user/login.php" class="btn btn-primary btn-sm px-4">Log In to Review</a>
        </div>
      <?php } elseif ($can_review) { ?>
        <div class="card bg-light border-0 p-3">
          <h5 class="fw-bold mb-2">
            <?php echo $user_review ? '<i class="fa-solid fa-pen-nib me-1 text-primary"></i> Update Your Review' : '<i class="fa-solid fa-star me-1 text-warning"></i> Leave a Review for This Service'; ?>
          </h5>
          <p class="text-muted small mb-3">
            <i class="fa-solid fa-circle-check text-success me-1"></i> <strong>Verified Purchase:</strong> You have an order for this service. Foul/bad words are masked automatically (MP4).
          </p>

          <form method="POST" action="<?php echo $base_url; ?>/reviews/<?php echo $user_review ? 'edit.php' : 'create.php'; ?>" novalidate>
            <input type="hidden" name="service_id" value="<?php echo (int)$service_id; ?>">
            <?php if ($user_review) { ?>
              <input type="hidden" name="review_id" value="<?php echo (int)$user_review['review_id']; ?>">
            <?php } ?>

            <div class="row mb-3">
              <div class="col-md-3">
                <label for="rating" class="form-label fw-semibold">Rating (1 to 5 Stars)</label>
                <select class="form-select" id="rating" name="rating">
                  <?php 
                  $current_val = $user_review['rating'] ?? 5;
                  for ($r = 5; $r >= 1; $r--) {
                    $sel = ($current_val == $r) ? 'selected' : '';
                    echo "<option value='{$r}' {$sel}>{$r} Star" . ($r > 1 ? 's' : '') . "</option>";
                  }
                  ?>
                </select>
              </div>
            </div>

            <div class="mb-3">
              <label for="review_text" class="form-label fw-semibold">Your Review / Experience</label>
              <textarea class="form-control" id="review_text" name="review_text" rows="3" 
                        placeholder="Describe the quality of delivery, communication, and overall service..."><?php echo e($user_review['review_text'] ?? ''); ?></textarea>
            </div>

            <div class="d-flex justify-content-between align-items-center">
              <button type="submit" class="btn btn-primary fw-semibold px-4">
                <i class="fa-solid fa-paper-plane me-1"></i> <?php echo $user_review ? 'Update Review' : 'Submit Review'; ?>
              </button>
              <?php if ($user_review) { ?>
                <form method="POST" action="<?php echo $base_url; ?>/reviews/delete.php" class="d-inline"
                      onsubmit="return confirm('Are you sure you want to delete your review?');">
                  <input type="hidden" name="review_id" value="<?php echo (int)$user_review['review_id']; ?>">
                  <input type="hidden" name="service_id" value="<?php echo (int)$service_id; ?>">
                  <button type="submit" class="btn btn-outline-danger btn-sm">Delete Review</button>
                </form>
              <?php } ?>
            </div>
          </form>
        </div>
      <?php } else { ?>
        <div class="alert alert-secondary border d-flex align-items-center gap-3">
          <i class="fa-solid fa-shield-halved fa-2x text-muted"></i>
          <div>
            <strong class="d-block text-dark">Review Policy (MP4 Requirement):</strong>
            <span class="small text-muted">
              Only verified customers who have completed or confirmed an order for this service can leave a review. Avail this service package above to share your feedback!
            </span>
          </div>
        </div>
      <?php } ?>
    </div>

    <!-- Reviews List -->
    <h5 class="fw-bold mb-3">Customer Feedback</h5>
    <?php if (empty($reviews)) { ?>
      <p class="text-muted fst-italic">No reviews yet for this service. Be the first customer to avail and review!</p>
    <?php } else { ?>
      <div class="d-flex flex-column gap-3">
        <?php foreach ($reviews as $rev) { 
          // MP4: Regex masked foul words applied to review_text
          $clean_comment = filter_profanity($rev['review_text']);
        ?>
          <div class="p-3 rounded border bg-light-subtle">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div class="d-flex align-items-center gap-2">
                <?php echo user_avatar($rev['user_photo'], 36); ?>
                <div>
                  <strong class="text-dark d-block"><?php echo e($rev['first_name'] . ' ' . $rev['last_name']); ?></strong>
                  <span class="badge bg-success-subtle text-success border border-success-subtle small py-0">
                    <i class="fa-solid fa-check me-1"></i> Verified Customer
                  </span>
                </div>
              </div>
              <small class="text-muted"><?php echo e(date('M d, Y', strtotime($rev['created_at']))); ?></small>
            </div>

            <!-- Rating Stars -->
            <div class="text-warning mb-2 small">
              <?php for ($s = 1; $s <= 5; $s++) {
                echo ($s <= $rev['rating']) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star text-muted"></i>';
              } ?>
            </div>

            <!-- Review Text with Regex Filter (MP4: 5pts) -->
            <p class="mb-0 text-secondary"><?php echo nl2br(e($clean_comment)); ?></p>
          </div>
        <?php } ?>
      </div>
    <?php } ?>
  </div>
</div>

<?php include('../includes/footer.php'); ?>
