<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid Quotation ID");
}

/* -------------------------------------------------------
   FETCH MAIN QUOTATION
--------------------------------------------------------*/
$qrySql = "
    SELECT q.*,
           c.name AS customer_name,
           co.name AS country_name
    FROM quotations q
    LEFT JOIN customers c ON q.customer_id = c.id
    LEFT JOIN countries co ON q.country_id = co.id
    WHERE q.id = $id
";
$qry = $conn->query($qrySql);
$quotation = $qry->fetch_assoc();
if (!$quotation) die("Quotation not found.");

/* -------------------------------------------------------
   FETCH HOTEL DETAILS
--------------------------------------------------------*/
$hotelsSql = "
    SELECT qh.*,
           ci.name AS city_name,
           ht.name AS hotel_name,
           rc.room_category AS room_name
    FROM quotation_hotels qh
    LEFT JOIN cities ci ON qh.city_id = ci.id
    LEFT JOIN hotels ht ON qh.hotel_id = ht.id
    LEFT JOIN hotel_rooms rc ON qh.room_category_id = rc.id
    WHERE qh.quotation_id = $id
";
$hotelsRes = $conn->query($hotelsSql);

$hotelGroups = [];
while ($h = $hotelsRes->fetch_assoc()) {
    $optionNo = (int)$h['option_no'];
    $hotelGroups[$optionNo][] = $h;
}

/* -------------------------------------------------------
   FETCH TRAVEL PLAN (NOW INCLUDES itinerary_text)
--------------------------------------------------------*/
$travelSql = "
    SELECT qt.*,
           qt.itinerary_text,
           ci.name AS city_name,
           sp.name AS sightseeing_name,
           pp.pickup_name AS pickup_point_name,
           ml.category AS meal_name
    FROM quotation_travels qt
    LEFT JOIN cities ci ON qt.city_id = ci.id
    LEFT JOIN sightseeings sp ON qt.sightseeing_id = sp.id
    LEFT JOIN pickup_points pp ON qt.pickup_point_id = pp.id
    LEFT JOIN meals ml ON qt.meal_id = ml.id
    WHERE qt.quotation_id = $id
    ORDER BY qt.day_no ASC
";

$travel = $conn->query($travelSql);

$hasTravelPlan = ($travel && $travel->num_rows > 0);

/* -------------------------------------------------------
   DECODE COST SUMMARY JSON
--------------------------------------------------------*/
$cost_summary = [];
if (!empty($quotation['cost_summary'])) {
    $tmp = json_decode($quotation['cost_summary'], true);
    if (is_array($tmp)) $cost_summary = $tmp;
}

/* -------------------------------------------------------
   HELPER FUNCTIONS
--------------------------------------------------------*/
function get_travel_activities(mysqli $conn, int $travel_id): string {

    $sql = "
        SELECT sa.activity_name
        FROM quotation_travel_activities qta
        JOIN sightseeing_activities sa
            ON sa.id = qta.activity_id
        WHERE qta.quotation_travel_id = $travel_id
        ORDER BY sa.activity_name
    ";

    $res = $conn->query($sql);

    if (!$res || $res->num_rows === 0) {
        return "—";
    }

    $names = [];
    while ($r = $res->fetch_assoc()) {
        $names[] = $r['activity_name'];
    }

    return implode(', ', $names);
}

function fmt($amount): string {
    return number_format((float)$amount, 2);
}

function get_travel_cars(mysqli $conn, int $travel_id): string {

    $sql = "
        SELECT c.car_name, c.seater
        FROM quotation_travel_cars tc
        JOIN cars c ON c.id = tc.car_id
        WHERE tc.quotation_travel_id = $travel_id
    ";

    $res = $conn->query($sql);

    if (!$res || $res->num_rows === 0) {
        return '—';
    }

    $cars = [];
    while ($r = $res->fetch_assoc()) {
        $cars[] =
            htmlspecialchars($r['car_name']) .
            ' (' . (int)$r['seater'] . ' Seater)';
    }

    // 🔥 JOIN WITH PLUS SIGN
    return implode(' + ', $cars);
}


/* -------------------------------------------------------
   TOTAL COST SUMMARY (FROM quotations TABLE ONLY)
--------------------------------------------------------*/
$summary = [
    'hotel'     => (float)$quotation['hotel_total'],
    'activity'  => (float)$quotation['activity_total'],
    'meal'      => (float)$quotation['meal_total'],
    'transport' => (float)$quotation['transport_total'],
    'guide'     => (float)$quotation['guide_total'],
    'visa'      => (float)$quotation['visa_total'],
    'tip'       => (float)$quotation['tip_total'],
    'grand'     => (float)$quotation['grand_total'],
];

/* -------------------------------------------------------
   PAGE UI
--------------------------------------------------------*/
$page_title = 'Quotation Details';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>

<style>
.q-view-header {
  background: linear-gradient(90deg,#123A7A 0%, #2F7BED 100%);
  color: #fff; padding: 18px; border-radius: 8px;
  margin-bottom: 18px;
}
.q-section { margin-bottom: 18px; border-radius: 8px; }
.q-card { padding: 16px; background: #fff; border: 1px solid #e6ebf3; border-radius: 8px; }
.table-compact td, .table-compact th { padding: .5rem .6rem; font-size: .95rem; }
.activity-list { text-align:left; max-width:100%; display:inline-block; }
</style>

<div class="container mt-4">

  <!-- HEADER -->
  <div class="q-view-header">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0">Quotation: <?= htmlspecialchars($quotation['quotation_no']) ?></h3>
        <small><?= htmlspecialchars($quotation['customer_name']) ?> • <?= htmlspecialchars($quotation['country_name']) ?></small>
      </div>
      <div class="text-end">
        <strong>Grand Total</strong>
        <div style="font-size:1.25rem;"><?= "$ " . fmt($quotation['grand_total']) ?></div>
      </div>
    </div>
  </div>

  <!-- BASIC INFO -->
  <div class="q-section q-card">
    <h5>Basic Information</h5>
    <table class="table table-bordered table-sm table-compact">
      <tbody>
        <tr>
          <th>Quotation No</th><td><?= $quotation['quotation_no'] ?></td>
          <th>Quotation Date</th><td><?= $quotation['created_at'] ?></td>
        </tr>
        <tr>
          <th>Customer</th><td><?= $quotation['customer_name'] ?></td>
          <th>Status</th><td><?= $quotation['status'] ?? "—" ?></td>
        </tr>
        <tr>
          <th>Travel Date</th><td><?= $quotation['travel_date'] ?></td>
          <th>Departure Date</th><td><?= $quotation['departure_date'] ?></td>
        </tr>
        <tr>
  <th>Adults</th><td><?= $quotation['adults'] ?></td>
  <th>Extra Adults</th><td><?= $quotation['extra_adults'] ?></td>
  <th>Children</th><td><?= $quotation['children'] ?></td>
</tr>
<tr>
  <th>Infants</th><td><?= $quotation['infants'] ?? 0 ?></td>
  <th>No Bed Child</th><td><?= $quotation['no_bed_child'] ?></td>
  <th>Rooms</th><td><?= $quotation['rooms'] ?></td>
</tr>
        <tr>
          <th>Days</th><td><?= $quotation['days'] ?></td>
          <th>Nights</th><td><?= $quotation['nights'] ?></td>
        </tr>
      </tbody>
    </table>
  </div>

<!-- HOTEL DETAILS -->
<div class="q-section q-card">
  <h5>Hotel Details</h5>

<?php foreach ($hotelGroups as $option => $hotels): ?>
  <?php if (empty($hotels)) continue; ?>

  <?php
$optionHotelTotal = 0;
$perAdultSum = 0;
$perExtraAdultSum = 0;
$perChildSum = 0;
$perNoBedSum = 0;

foreach ($hotels as $h) {

    $nights = (int)$h['stay_nights'];

    // option-wise total
    $optionHotelTotal += (float)$h['price'];

    // option-wise per person
    $perAdultSum      += (float)$h['base_price'];
    $perExtraAdultSum += (float)$h['extra_adult_price'] * $nights;
    $perChildSum      += (float)$h['child_price'] * $nights;
    $perNoBedSum      += (float)$h['nobed_price'] * $nights;
}
?>

  <!-- OPTION HEADER -->
  <div class="mb-2 p-2 rounded"
       style="background:#f3f6ff;border-left:4px solid #2F7BED;">
    <strong>Option <?= $option ?> :</strong>
    <span class="float-end fw-bold">
      Hotel Total :
      <?= $quotation['currency'] ?> <?= fmt($optionHotelTotal) ?>
    </span>
  </div>

  <!-- HOTEL LIST -->
  <table class="table table-striped table-sm table-compact text-center mb-2">
    <thead class="table-light">
      <tr>
        <th>City</th>
        <th>Hotel</th>
        <th>Room</th>
        <th>Nights</th>
        <th>Rooms</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($hotels as $h): ?>
      <tr>
        <td><?= htmlspecialchars($h['city_name']) ?></td>
        <td><?= htmlspecialchars($h['hotel_name']) ?></td>
        <td><?= htmlspecialchars($h['room_name']) ?></td>
        <td><?= (int)$h['stay_nights'] ?></td>
        <td><?= (int)$h['rooms'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- PER PERSON (OPTION-WISE) -->
<table class="table table-bordered table-sm w-50 ms-auto mb-4">
  <tbody>

    <?php if ($perAdultSum > 0): ?>
    <tr>
      <td>Per Adult</td>
      <td class="text-end">
        <?= $quotation['currency'] ?> <?= fmt($perAdultSum) ?>
      </td>
    </tr>
    <?php endif; ?>

    <?php if ($perExtraAdultSum > 0): ?>
    <tr>
      <td>Per Extra Adult</td>
      <td class="text-end">
        <?= $quotation['currency'] ?> <?= fmt($perExtraAdultSum) ?>
      </td>
    </tr>
    <?php endif; ?>

    <?php if ($perChildSum > 0): ?>
    <tr>
      <td>Per Child With Bed</td>
      <td class="text-end">
        <?= $quotation['currency'] ?> <?= fmt($perChildSum) ?>
      </td>
    </tr>
    <?php endif; ?>

    <?php if ($perNoBedSum > 0): ?>
    <tr>
      <td>Per Child No Bed</td>
      <td class="text-end">
        <?= $quotation['currency'] ?> <?= fmt($perNoBedSum) ?>
      </td>
    </tr>
    <?php endif; ?>

  </tbody>
</table>


<?php endforeach; ?>
</div>

  <!-- TRAVEL PLAN -->
<?php if ($hasTravelPlan): ?>
<div class="q-section q-card">
  <h5>Travel Plan</h5>

  <table class="table table-bordered table-sm table-compact text-center">
    <thead class="table-light">
      <tr>
        <th>Day</th>
        <th>Date</th>
        <th>City</th>
        <th>Pickup Point</th>
        <th>Transfer</th>
        <th>Activities</th>
        <th>Car Rent</th>
        <th>Meal</th>
        <th>Guide</th>
      </tr>
    </thead>

    <tbody>
<?php
mysqli_data_seek($travel, 0);
while ($t = $travel->fetch_assoc()):

    $hasCars = false;
$chk = $conn->query("
    SELECT 1 FROM quotation_travel_cars
    WHERE quotation_travel_id = {$t['id']}
    LIMIT 1
");
$hasCars = ($chk && $chk->num_rows > 0);

if (
    empty($t['sightseeing_id']) &&
    !$hasCars &&
    empty($t['pickup_point_id']) &&
    empty($t['meal_id']) &&
    empty(trim($t['itinerary_text']))
) {
    continue;
}

    $actNames = get_travel_activities($conn, (int)$t['id']);
?>
<tr>
    <td><?= $t['day_no'] ?></td>
    <td><?= $t['day_date'] ?></td>
    <td><?= htmlspecialchars($t['city_name']) ?></td>
    <td><?= htmlspecialchars($t['pickup_point_name']) ?: '—' ?></td>
    <td><?= htmlspecialchars($t['sightseeing_name']) ?: '—' ?></td>
    <td class="text-start"><?= $actNames ?: '—' ?></td>
    <td class="text-start">
    <?= get_travel_cars($conn, (int)$t['id']); ?>
</td>
    <td><?= $t['meal_name'] ?: '—' ?></td>
    <td><?= $t['guide'] ?: '—' ?></td>
</tr>
<?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- DAY-WISE ITINERARY -->
<?php if ($hasTravelPlan): ?>
<div class="q-section q-card">
  <h5>Day-wise Itinerary</h5>

<?php
mysqli_data_seek($travel, 0);
$found = false;

while ($it = $travel->fetch_assoc()):

    if (empty(trim($it['itinerary_text']))) {
        continue; // 🔥 skip empty itinerary
    }

    $found = true;
    $day   = $it['day_no'];
    $date  = $it['day_date'] ? date("d-m-Y", strtotime($it['day_date'])) : "";
?>
  <div style="padding:14px; margin-bottom:15px; border-left:4px solid #2F7BED;">
    <div style="font-size:14px; line-height:1.6;">
      <?= nl2br(htmlspecialchars($it['itinerary_text'])) ?>
    </div>
  </div>
<?php endwhile; ?>

<?php if (!$found): ?>
  <p class="text-muted">No itinerary available.</p>
<?php endif; ?>

</div>
<?php endif; ?>

 <!-- COST SUMMARY -->
<div class="q-section q-card">
  <h5>Total Cost Summary</h5>

  <table class="table table-bordered table-sm text-center">
    <thead class="table-dark text-white">
      <tr>
        <th>Category</th>
        <th>Total</th>
        <th>Per Adult</th>
        <th>Per Extra Adult</th>
        <th>Per Child with Bed</th>
        <th>Per Child No Bed</th>
      </tr>
    </thead>
    <tbody>

      <!-- HOTEL -->
      <tr>
        <td>Hotel</td>
        <td><?= fmt($summary['hotel']) ?></td>
        <td><?= fmt($quotation['hotel_per_adult']) ?></td>
        <td><?= fmt($quotation['hotel_per_extra_adult']) ?></td>
        <td><?= fmt($quotation['hotel_per_child']) ?></td>
        <td><?= fmt($quotation['hotel_per_child_no_bed']) ?></td>
      </tr>

      <!-- ACTIVITY -->
      <tr>
        <td>Activity</td>
        <td><?= fmt($summary['activity']) ?></td>
        <td><?= fmt($quotation['activity_per_adult']) ?></td>
        <td><?= fmt($quotation['activity_per_extra_adult']) ?></td>
        <td><?= fmt($quotation['activity_per_child']) ?></td>
        <td><?= fmt($quotation['activity_per_child_no_bed']) ?></td>
      </tr>

      <!-- MEAL -->
      <tr>
        <td>Meal</td>
        <td><?= fmt($summary['meal']) ?></td>
        <td><?= fmt($quotation['meal_per_adult']) ?></td>
        <td><?= fmt($quotation['meal_per_extra_adult']) ?></td>
        <td><?= fmt($quotation['meal_per_child']) ?></td>
        <td><?= fmt($quotation['meal_per_child_no_bed']) ?></td>
      </tr>

      <!-- TRANSPORT -->
      <tr>
        <td>Transport</td>
        <td><?= fmt($summary['transport']) ?></td>
        <td><?= fmt($quotation['transport_per_adult']) ?></td>
        <td><?= fmt($quotation['transport_per_extra_adult']) ?></td>
        <td><?= fmt($quotation['transport_per_child']) ?></td>
        <td><?= fmt($quotation['transport_per_child_no_bed']) ?></td>
      </tr>

      <!-- GUIDE -->
      <tr>
        <td>Guide</td>
        <td><?= fmt($summary['guide']) ?></td>
        <td><?= fmt($quotation['guide_per_adult']) ?></td>
        <td><?= fmt($quotation['guide_per_extra_adult']) ?></td>
        <td><?= fmt($quotation['guide_per_child']) ?></td>
        <td><?= fmt($quotation['guide_per_child_no_bed']) ?></td>
      </tr>

      <!-- VISA -->
      <tr>
        <td>Visa Fee</td>
        <td><?= fmt($summary['visa']) ?></td>
        <td><?= fmt($quotation['visa_per_adult']) ?></td>
        <td><?= fmt($quotation['visa_per_extra_adult']) ?></td>
        <td><?= fmt($quotation['visa_per_child']) ?></td>
        <td><?= fmt($quotation['visa_per_child_no_bed']) ?></td>
      </tr>

      <!-- TIP -->
      <tr>
        <td>Tip Amount</td>
        <td><?= fmt($summary['tip']) ?></td>
        <td><?= fmt($quotation['tip_per_adult']) ?></td>
        <td><?= fmt($quotation['tip_per_extra_adult']) ?></td>
        <td><?= fmt($quotation['tip_per_child']) ?></td>
        <td><?= fmt($quotation['tip_per_child_no_bed']) ?></td>
      </tr>

      <!-- GRAND TOTAL -->
      <tr class="table-primary fw-bold">
        <td>Grand Total</td>
        <td><?= fmt($summary['grand']) ?></td>
        <td><?= fmt($quotation['grand_per_adult']) ?></td>
        <td><?= fmt($quotation['grand_per_extra_adult']) ?></td>
        <td><?= fmt($quotation['grand_per_child']) ?></td>
        <td><?= fmt($quotation['grand_per_child_no_bed']) ?></td>
      </tr>

    </tbody>
  </table>
</div>

  <div class="d-flex gap-2">
    <a href="quotation_pdf.php?id=<?= $id ?>" class="btn btn-outline-primary">Download PDF</a>
    <a href="quotation_word.php?id=<?= $id ?>" class="btn btn-outline-primary">Download WORD</a>
    <a href="quotations.php" class="btn btn-outline-secondary">Back</a>
  </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
