<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

/* =========================
   VALIDATE QUOTATION
========================= */
$quotation_id = (int)($_GET['quotation_id'] ?? 0);
if ($quotation_id <= 0) {
    die("Invalid quotation");
}

$childCount = 0;

$resChild = $conn->query("
    SELECT (children + no_bed_child) AS total_children
    FROM quotations
    WHERE id = $quotation_id
");

if ($r = $resChild->fetch_assoc()) {
    $childCount = (int)$r['total_children'];
}

$infantCount = 0;

$resInfant = $conn->query("
    SELECT infants
    FROM quotations
    WHERE id = $quotation_id
");

if ($r = $resInfant->fetch_assoc()) {
    $infantCount = (int)$r['infants'];
}

/* =========================
   FETCH HOTEL OPTIONS
========================= */
$options = [];

$res = $conn->query("
    SELECT 
        qh.option_no,
        qh.stay_nights,
        qh.rooms,
        qh.price,
        qh.base_price,
        qh.extra_adult_price,
        qh.child_price,

        qh.category AS hotel_category,           -- ✅ hotel category
        rr.room_category AS room_category,       -- ✅ room category

        h.name AS hotel_name,
        c.name AS city_name
    FROM quotation_hotels qh
    LEFT JOIN hotels h ON qh.hotel_id = h.id
    LEFT JOIN cities c ON qh.city_id = c.id
    LEFT JOIN hotel_rooms rr ON qh.room_category_id = rr.id
    WHERE qh.quotation_id = $quotation_id
    ORDER BY qh.option_no, qh.id
");


while ($row = $res->fetch_assoc()) {
    $options[$row['option_no']][] = $row;
}

$optionCount = count($options);

/* =========================
   FETCH TRAVEL PLAN (SIGHTSEEINGS NAME ONLY)
========================= */
$travels = [];

$resTravel = $conn->query("
SELECT 
    qt.day_no,
    qt.day_date,
    qt.guide,
    c.name AS city_name,
    pp.pickup_name AS pickup_point,
    m.category AS meal,
    ss.name AS sightseeing_name,

    CASE 
        WHEN qtc.id IS NULL THEN 'no'
        ELSE 'yes'
    END AS car

FROM quotation_travels qt

LEFT JOIN cities c 
       ON qt.city_id = c.id

LEFT JOIN pickup_points pp 
       ON qt.pickup_point_id = pp.id

LEFT JOIN meals m 
       ON qt.meal_id = m.id

LEFT JOIN sightseeings ss 
       ON qt.sightseeing_id = ss.id

/* CORRECT JOIN */
LEFT JOIN quotation_travel_cars qtc 
       ON qtc.quotation_travel_id = qt.id

WHERE qt.quotation_id = $quotation_id

ORDER BY qt.day_date ASC, qt.day_no ASC
");
while ($row = $resTravel->fetch_assoc()) {
    $travels[] = $row;
}

?>

<?php $page_title = 'Add Confirmation'; include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/nav.php'; ?>
<style>
/* ===== Travel Plan -> Table Look ===== */

.travel-card {
  border: 1px solid #cfd4da !important;
  border-left-width: 4px !important;
}

.travel-card .card-body {
  padding: 0;
}

/* Day header */
.travel-card h6 {
  margin: 0;
  padding: 10px 12px;
  font-weight: 600;
  background: #f8f9fa;
  border-bottom: 1px solid #cfd4da;
}

/* Row behaves like table row */
.travel-row {
  margin: 0;
}

/* Each column behaves like table cell */
.travel-row > div {
  border-right: 1px solid #dee2e6;
  border-bottom: 1px solid #dee2e6;
  padding: 8px 10px;
}

/* Last column border fix */
.travel-row > div:nth-child(3n),
.travel-row > div:last-child {
  border-right: none;
}

/* Labels become table headers */
.travel-row .form-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #000;
  margin-bottom: 4px;
}

/* Inputs look like plain text (table cell) */
.travel-row .form-control {
  border: none;
  padding: 0;
  font-size: 13px;
  background: transparent;
  box-shadow: none;
}

/* Editable inputs underline only */
.travel-row input:not([readonly]) {
  border-bottom: 1px dotted #999;
  border-radius: 0;
}

/* Time input compact */
.travel-row input[type="time"] {
  padding: 0;
}

/* Remove spacing caused by Bootstrap */
.travel-row .col-md-4,
.travel-row .col-md-5,
.travel-row .col-md-6,
.travel-row .col-md-3 {
  margin-bottom: 0;
}

</style>

<div class="container mt-4">

  <div class="card shadow-sm">
    <div class="card-header">
      <h5 class="mb-0">Generate Confirmation Letter</h5>
    </div>

    <div class="card-body">

      <form method="POST" action="save_confirmation.php">

        <input type="hidden" name="quotation_id" value="<?= $quotation_id ?>">

        <!-- ================= Passenger Details ================= -->
        <h6>Passenger Details</h6>
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Passenger Name</label>
            <input type="text" name="passenger_name" class="form-control" required>
          </div>
        </div>

        <?php if ($childCount > 0): ?>
<h6 class="mt-4">Child Age Details</h6>
<div class="row">
  <?php for ($i = 0; $i < $childCount; $i++): ?>
    <div class="col-md-2 mb-2">
      <label class="form-label">Child <?= $i + 1 ?> Age</label>
      <input type="number"
             name="child_ages[]"
             class="form-control"
             min="2"
             max="17"
             required>
    </div>
  <?php endfor; ?>
</div>
<?php endif; ?>


<?php if ($infantCount > 0): ?>
<h6 class="mt-4">Infant Age Details</h6>
<div class="row">
  <?php for ($i = 0; $i < $infantCount; $i++): ?>
    <div class="col-md-2 mb-2">
      <label class="form-label">Infant <?= $i + 1 ?> Age</label>
      <input type="number"
             name="infant_ages[]"
             class="form-control"
             min="0"
             max="3"
             required>
    </div>
  <?php endfor; ?>
</div>
<?php endif; ?>


        <!-- ================= Hotel Details ================= -->
        <h5 class="mb-3">Hotel Details</h5>

<?php if (!empty($options)): ?>

  <!-- ===== EXISTING HOTEL OPTIONS (UNCHANGED) ===== -->
  <?php foreach ($options as $optionNo => $rows): ?>

<div class="mb-4">

  <!-- OPTION HEADER -->
  <div class="d-flex align-items-center bg-light rounded px-3 py-2 border-start border-4 border-primary">
    <input class="form-check-input me-2"
           type="radio"
           name="hotel_option"
           value="<?= $optionNo ?>"
           required>
    <strong>Option <?= $optionNo ?></strong>
  </div>

  <!-- HOTEL TABLE -->
  <div class="table-responsive mt-2">
    <table class="table table-bordered table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th>City</th>
          <th>Hotel</th>
          <th>Hotel Conf. No</th>
          <th>Hotel Category</th>
          <th>Room Category</th>
          <th class="text-center">Nights</th>
          <th class="text-center">Rooms</th>
          <th class="text-center">Payment Due Date</th>
          <th class="text-center">Payment Amount</th>
        </tr>
      </thead>

      <tbody>
        <?php foreach ($rows as $i => $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['city_name']) ?></td>
          <td>
  <input type="text"
         class="form-control form-control-sm"
         name="hotels[<?= $optionNo ?>][<?= $i ?>][hotel_name]"
         value="<?= htmlspecialchars($r['hotel_name']) ?>"
         required>
</td>

          <!-- Hotel Confirmation No -->
          <td>
            <input type="text"
                   class="form-control form-control-sm"
                   name="hotels[<?= $optionNo ?>][<?= $i ?>][hotel_confirmation_no]"
                   placeholder="Hotel Conf No">
          </td>

          <!-- Hotel Category -->
          <td>
            <input type="text"
                   class="form-control form-control-sm"
                   name="hotels[<?= $optionNo ?>][<?= $i ?>][hotel_category]"
                   value="<?= htmlspecialchars($r['hotel_category']) ?>"
                   required>
          </td>

          <!-- Room Category -->
          <td>
            <input type="text"
                   class="form-control form-control-sm"
                   name="hotels[<?= $optionNo ?>][<?= $i ?>][room_category]"
                   value="<?= htmlspecialchars($r['room_category']) ?>"
                   required>
          </td>

          <td class="text-center">
  <input type="number"
         class="form-control form-control-sm text-center"
         name="hotels[<?= $optionNo ?>][<?= $i ?>][stay_nights]"
         value="<?= (int)$r['stay_nights'] ?>"
         min="1"
         required>
</td>

<td class="text-center">
  <input type="number"
         class="form-control form-control-sm text-center"
         name="hotels[<?= $optionNo ?>][<?= $i ?>][rooms]"
         value="<?= (int)$r['rooms'] ?>"
         min="1"
         required>
</td>

<td>
  <input type="date"
         class="form-control form-control-sm"
         name="hotels[<?= $optionNo ?>][<?= $i ?>][due_date]">
</td>
<td>
  <input type="number"
         step="0.01"
         min="0"
         class="form-control form-control-sm hotel-payment"
         name="hotels[<?= $optionNo ?>][<?= $i ?>][payment_amount]"
         placeholder="Amount">
</td>
          <!-- HIDDEN REQUIRED FIELDS -->
          <input type="hidden" name="hotels[<?= $optionNo ?>][<?= $i ?>][city_name]"
                 value="<?= htmlspecialchars($r['city_name']) ?>">

          <input type="hidden" name="hotels[<?= $optionNo ?>][<?= $i ?>][hotel_name]"
                 value="<?= htmlspecialchars($r['hotel_name']) ?>">

        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<?php endforeach; ?>

<?php else: ?>

<!-- ===== MANUAL HOTEL ENTRY ===== -->
<div class="alert alert-info">
  No hotel found in quotation. Please add hotel details manually.
</div>

<div class="table-responsive">
  <table class="table table-bordered table-sm align-middle" id="manualHotelTable">
    <thead class="table-light">
      <tr>
        <th>City</th>
        <th>Hotel Name</th>
        <th>Hotel Conf. No</th>
        <th>Hotel Category</th>
        <th>Room Category</th>
        <th class="text-center">Nights</th>
        <th class="text-center">Action</th>
      </tr>
    </thead>

    <tbody>
      <tr>
        <td>
          <input type="text" name="manual_hotels[0][city]"
                 class="form-control form-control-sm">
        </td>

        <td>
          <input type="text" name="manual_hotels[0][hotel_name]"
                 class="form-control form-control-sm">
        </td>

        <td>
  <input type="text"
         name="manual_hotels[0][hotel_confirmation_no]"
         class="form-control form-control-sm"
         placeholder="Hotel Conf No">
</td>

<td>
  <input type="text"
         name="manual_hotels[0][hotel_category]"
         class="form-control form-control-sm">
</td>

<td>
  <input type="text"
         name="manual_hotels[0][room_category]"
         class="form-control form-control-sm">
</td>

    <td class="text-center">
          <input type="number" name="manual_hotels[0][stay_nights]"
                 class="form-control form-control-sm" min="1">
        </td>

        <td class="text-center">
          <button type="button" class="btn btn-sm btn-outline-danger remove-row" disabled>
            ×
          </button>
        </td>
      </tr>
    </tbody>
  </table>
</div>

<button type="button"
        class="btn btn-sm btn-outline-primary mt-2"
        id="addHotelRow">
  + Add More Rows
</button>

<input type="hidden" name="hotel_option" value="manual">


<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {

  const tableBody = document.querySelector('#manualHotelTable tbody');
  const addBtn = document.getElementById('addHotelRow');

  addBtn.addEventListener('click', function () {
    const rowCount = tableBody.rows.length;
    const newRow = tableBody.rows[0].cloneNode(true);

    newRow.querySelectorAll('input').forEach(input => {
      input.value = '';
      input.name = input.name.replace(/\[\d+\]/, '[' + rowCount + ']');
    });

    newRow.querySelector('.remove-row').disabled = false;

    tableBody.appendChild(newRow);
  });

  tableBody.addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-row')) {
      e.target.closest('tr').remove();
      reindexRows();
    }
  });

  function reindexRows() {
    [...tableBody.rows].forEach((row, index) => {
      row.querySelectorAll('input').forEach(input => {
        input.name = input.name.replace(/\[\d+\]/, '[' + index + ']');
      });
      row.querySelector('.remove-row').disabled = index === 0;
    });
  }

});
</script>


<!-- ================= Travel Plan ================= -->
<h5 class="mb-3 mt-4">Travel Plan</h5>

<?php if (!empty($travels)): ?>

<div class="table-responsive">
  <table class="table table-bordered table-sm align-middle text-center">

    <thead class="table-light">
      <tr>
        <th>Date</th>
        <th>Flight Name/Pickup Time</th>
        <th>Pickup Point</th>
        <th>Sightseeing</th>
        <th>Meal</th>
        <th>Guide</th>
      </tr>
    </thead>

    <tbody>
      <?php foreach ($travels as $i => $t): ?>
      <tr>

        <!-- Date -->
        <td>
<?= !empty($t['day_date']) ? date('d M Y', strtotime($t['day_date'])) : '—' ?>

<input type="hidden"
       name="travel[<?= $i ?>][day_date]"
       value="<?= htmlspecialchars($t['day_date'] ?? '') ?>">

<input type="hidden"
       name="travel[<?= $i ?>][city_name]"
       value="<?= htmlspecialchars($t['city_name'] ?? '') ?>">

<input type="hidden"
       name="travel[<?= $i ?>][car]"
       value="<?= strtolower(trim($t['car'] ?? 'no')) ?>">
       </td>
        <!-- Flight Name (Editable) -->
        <td>
          <input type="text"
                 name="travel[<?= $i ?>][flight_name]"
                 value="<?= htmlspecialchars($t['flight_name'] ?? '') ?>"
                 class="form-control form-control-sm"
                 placeholder="Enter flight name">
        </td>
        <!-- Pickup Point -->
       <td>
  <input type="text"
         name="travel[<?= $i ?>][pickup_point]"
         value="<?= htmlspecialchars($t['pickup_point'] ?? '') ?>"
         class="form-control form-control-sm"
         placeholder="Enter pickup point">
</td>

        <!-- Sightseeing -->
        <td>
  <input type="text"
         name="travel[<?= $i ?>][sightseeing_name]"
         value="<?= htmlspecialchars($t['sightseeing_name'] ?? '') ?>"
         class="form-control form-control-sm"
         placeholder="Enter sightseeing">
</td>

        <!-- Meal -->
        <td>
          <?= htmlspecialchars($t['meal'] ?? '—') ?>
          <input type="hidden"
                 name="travel[<?= $i ?>][meal]"
                 value="<?= htmlspecialchars($t['meal'] ?? '') ?>">
        </td>

        <!-- Guide -->
      <td>
<?php 
  $guide_val = strtolower(trim($t['guide'] ?? 'yes')); // default yes
?>
<select name="travel[<?= $i ?>][guide]"
        class="form-select form-select-sm">
  <option value="yes" <?= $guide_val == 'yes' ? 'selected' : '' ?>>Yes</option>
  <option value="no" <?= $guide_val == 'no' ? 'selected' : '' ?>>No</option>
</select>
</td>

      </tr>
      <?php endforeach; ?>
    </tbody>

  </table>
</div>

<?php else: ?>
  <div class="alert alert-warning">
    No travel plan found for this quotation.
  </div>
<?php endif; ?>

<!-- ================= Total Quotation Price ================= -->
<h5 class="mt-4">Quotation Summary</h5>

<div class="row mb-3">
  <div class="col-md-4">
    <label class="form-label fw-bold">Total Quotation Price</label>
    <input type="number"
           step="0.01"
           min="0"
           name="total_quotation_price"
           id="totalQuotationPrice"
           class="form-control"
           placeholder="Enter total quotation amount"
           required>
  </div>
</div>
        <!-- ================= Actions ================= -->
        <div class="text-end mt-4">
          <button class="btn btn-primary">
            Save & Generate Letter
          </button>
          <a href="../quotation/quotations.php" class="btn btn-secondary">
            Cancel
          </a>
        </div>

      </form>

    </div>
  </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
