<?php
require_once __DIR__ . '/../config/auth.php';
require_login();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/company.php';

// counts
$users = $conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'] ?? 0;
$customers = $conn->query("SELECT COUNT(*) c FROM customers")->fetch_assoc()['c'] ?? 0;
$quotes = $conn->query("SELECT COUNT(*) c FROM quotations")->fetch_assoc()['c'] ?? 0;
?>

<?php $page_title = 'Dashboard'; include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/nav.php'; ?>

<!-- DASHBOARD STYLES -->
<style>
.dashboard-card {
  transition: all 0.35s ease;
}

.dashboard-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 14px 28px rgba(0,0,0,0.15);
}

/* COMPANY CARD */
.company-card {
  position: relative;
  height: 420px;            /* matches your 2nd image */
  background: #ffffff;
  overflow: hidden;
  padding-bottom: 90px;     /* space for bottom info */
}

/* LOGO CENTER */
.company-logo-wrap {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.company-logo {
  max-width: 1000px;
  max-height: 1220px;
  object-fit: contain;
  transition: transform 0.8s ease;
  opacity: 0.35;
}

.company-card:hover .company-logo {
  transform: scale(1.05) translateY(-6px);
   opacity: 0.95;
}


.company-info {
  position: absolute;
  bottom: 1px;            /* ⬅ move up/down */
  left: 50%;
  transform: translateX(-50%);
  text-align: center;
  font-size: 13px;
  color: #444;
  line-height: 1.5;
}

</style>


<div class="container mt-4">

  <!-- STAT CARDS -->
  <div class="row g-3">

    <!-- CUSTOMERS -->
    <div class="col-md-4">
      <div class="card dashboard-card border-0 shadow-sm">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <h6 class="text-muted mb-1">Customers</h6>
            <div class="display-6 fw-semibold"><?php echo (int)$customers; ?></div>
          </div>
          <div class="text-primary dashboard-icon">
            <i class="bi bi-people-fill"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- QUOTATIONS -->
    <div class="col-md-4">
      <div class="card dashboard-card border-0 shadow-sm">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <h6 class="text-muted mb-1">Quotations</h6>
            <div class="display-6 fw-semibold"><?php echo (int)$quotes; ?></div>
          </div>
          <div class="text-success dashboard-icon">
            <i class="bi bi-file-earmark-text-fill"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- TEAM MEMBERS -->
    <div class="col-md-4">
      <div class="card dashboard-card border-0 shadow-sm">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <h6 class="text-muted mb-1">Team Members</h6>
            <div class="display-6 fw-semibold"><?php echo (int)$users; ?></div>
          </div>
          <div class="text-warning dashboard-icon">
            <i class="bi bi-person-badge-fill"></i>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- COMPANY INFO -->
<div class="mt-4">
  <div class="card shadow-sm border-0 dashboard-card company-card">

    <!-- LOGO CENTER -->
    <div class="company-logo-wrap">
      <img
        src="assets/images/Company.jpeg"
        alt="Company Logo"
        class="company-logo"
      >
    </div>

    <!-- COMPANY INFO BOTTOM -->
    <div class="company-info">
      <div style="white-space:pre-line;">
        <?php echo COMPANY_ADDRESS; ?>
        <br>
        Phone: <?php echo COMPANY_PHONE; ?>
        <br>
        Email: <?php echo COMPANY_EMAIL; ?>
      </div>
    </div>

  </div>
</div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
