<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>

<style>
/* NAVBAR ENHANCEMENT */
.navbar-brand {
  letter-spacing: 1px;
}

.navbar-nav .nav-link {
  color: #eaf2ff !important;
  padding: 8px 14px;
  border-radius: 6px;
  margin-right: 4px;
  transition: all 0.2s ease-in-out;
}

.navbar-nav .nav-link:hover {
  background: rgba(255,255,255,0.15);
  color: #fff !important;
}

.navbar-nav .nav-link.active {
  background: #ffffff;
  color: #0d6efd !important;
  font-weight: 700;
}
</style>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
  <div class="container-fluid">

    <a class="navbar-brand fw-bold text-uppercase" href="<?= BASE_URL ?>dashboard.php">
      Explore Vietnam
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#navbarsExample">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarsExample">

      <?php if (!empty($_SESSION['user_id'])): ?>

      <ul class="navbar-nav me-auto mb-2 mb-lg-0 fw-semibold">

        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='customers.php'?'active':'' ?>"
             href="<?= BASE_URL ?>customer/customers.php">
             Customers
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='quotation_new.php'?'active':'' ?>"
             href="<?= BASE_URL ?>quotation/quotation_new.php">
             New Quotation
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='quotations.php'?'active':'' ?>"
             href="<?= BASE_URL ?>quotation/quotations.php">
             All Quotations
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='confirmations_list.php'?'active':'' ?>"
             href="<?= BASE_URL ?>confirmation/confirmations_list.php">
             Confirmation
          </a>
        </li>
        
        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>hotel/hotel_payments.php">Hotel Payment</a>
        </li>
        
        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>agent/agent_payments.php">Agent Payment</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>guide/guide_booking.php">Guide Booking</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>hotel/hotels.php">Hotel List</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>sightseeing/sightseeing_list.php">Sightseeing</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>meal/meal_list.php">Meal</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>car/car_list.php">Car</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>pickup_point/pickup_points.php">Pickup</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>city/add_country_city.php">Country</a>
        </li>

        <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
          <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='users.php'?'active':'' ?>"
               href="<?= BASE_URL ?>customer/users.php">
               Users
            </a>
          </li>
        <?php endif; ?>

      </ul>

      <div class="d-flex align-items-center text-white fw-semibold">
        <span class="me-3">
          <?= htmlspecialchars($_SESSION['name'] ?? '') ?>
          (<?= htmlspecialchars($_SESSION['role'] ?? '') ?>)
        </span>
        <a class="btn btn-sm btn-outline-light" href="<?= BASE_URL ?>logout.php">
          Logout
        </a>
      </div>

      <?php endif; ?>

    </div>
  </div>
</nav>
