<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("Invalid confirmation ID");

/* =========================
   FETCH CONFIRMATION
========================= */
$stmt = $conn->prepare("
    SELECT 
        cf.*,
        q.travel_date,
        q.departure_date,
        q.adults,
        q.extra_adults,
        q.children,
        q.no_bed_child,
        q.infants,
        q.nights,
        q.days,
        car.car_name,
        car.seater
    FROM confirmations cf
    LEFT JOIN quotations q ON cf.quotation_id = q.id
    LEFT JOIN cars car ON q.car_id = car.id
    WHERE cf.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) die("Confirmation not found");

/* =========================
   CHILD AGES
========================= */
$childAges = [];
$ca = $conn->prepare("
    SELECT child_age 
    FROM confirmation_child_ages 
    WHERE confirmation_id = ?
    ORDER BY id
");
$ca->bind_param("i", $id);
$ca->execute();
$res = $ca->get_result();
while ($r = $res->fetch_assoc()) {
    $childAges[] = $r['child_age'].' Years';
}
$ca->close();
$childAgeText = $childAges ? ' ('.implode(', ', $childAges).')' : '';

/* =========================
   INFANT AGES
========================= */
$infantAges = [];
$ia = $conn->prepare("
    SELECT infant_age 
    FROM confirmation_infant_ages 
    WHERE confirmation_id = ?
    ORDER BY id
");
$ia->bind_param("i", $id);
$ia->execute();
$res = $ia->get_result();
while ($r = $res->fetch_assoc()) {
    $infantAges[] = $r['infant_age'].' Years';
}
$ia->close();
$infantAgeText = $infantAges ? ' ('.implode(', ', $infantAges).')' : '';

/* =========================
   FETCH AGENT DETAILS
========================= */
$agentName = '--';
$createdBy = '--';
$totalCost = 0;

$ag = $conn->prepare("
    SELECT agent_name, created_by, amount, total_quotation_price
    FROM agent_accounts
    WHERE confirmation_no = ?
    ORDER BY id DESC
    LIMIT 1
");
$ag->bind_param("s", $data['confirmation_no']);
$ag->execute();
$res = $ag->get_result();

if ($row = $res->fetch_assoc()) {
    $agentName  = $row['agent_name'] ?? '--';
    $createdBy = $row['created_by'] ?? '--';
    $totalCost = (float)($row['total_quotation_price'] ?? 0);
}
$ag->close();

/* =========================
   HOTELS
========================= */
$hotels = [];
$h = $conn->prepare("
    SELECT city_name, hotel_name, hotel_confirmation_no,
           category AS hotel_category, room_category, stay_nights
    FROM confirmations_hotels
    WHERE confirmation_id = ?
    ORDER BY option_no, id
");
$h->bind_param("i", $id);
$h->execute();
$res = $h->get_result();
while ($r = $res->fetch_assoc()) $hotels[] = $r;
$h->close();

/* =========================
   TRAVELS
========================= */
$travels = [];
$t = $conn->prepare("
    SELECT *
    FROM confirmations_travels
    WHERE confirmation_id = ?
    ORDER BY travel_date, id
");
$t->bind_param("i", $id);
$t->execute();
$res = $t->get_result();
while ($r = $res->fetch_assoc()) $travels[] = $r;
$t->close();

/* =========================
   DERIVED VALUES
========================= */
$totalAdults   = $data['adults'] + $data['extra_adults'];
$totalChildren = $data['children'] + $data['no_bed_child'];
$totalInfants  = $data['infants'];
$totalPerson   = $totalAdults + $totalChildren + $totalInfants;

$transport = $data['car_name']
    ? $data['car_name'].($data['seater'] ? " ({$data['seater']} Seater)" : '')
    : '—';

$duration = ($data['nights'] && $data['days'])
    ? "{$data['nights']} Nights / {$data['days']} Days"
    : '—';

function d($x){ return $x ? date('d F Y', strtotime($x)) : '—'; }

$page_title = "View Confirmation";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>

<div class="container mt-4">

<div class="card shadow-sm">
<div class="card-header bg-success text-white">
    <h5 class="mb-0">Confirmation Letter (View)</h5>
</div>

<div class="card-body">

<h6 class="text-secondary">Guest & Travel Summary</h6>
<table class="table table-bordered table-sm">

<tr>
    <th>Confirmation No</th>
    <td><?= htmlspecialchars($data['confirmation_no']) ?></td>

    <th>Guest Name</th>
    <td><?= htmlspecialchars($data['passenger_name']) ?></td>
</tr>

<tr>
    <th>Agent Name</th>
    <td><?= htmlspecialchars($agentName) ?></td>

    <th>Created By</th>
    <td>
        <span class="badge <?= strtolower($createdBy) === 'admin' ? 'bg-danger' : 'bg-primary' ?>">
            <?= htmlspecialchars($createdBy) ?>
        </span>
    </td>
</tr>

<tr>
    <th>Adults</th>
    <td><?= $totalAdults ?></td>

    <th>Children</th>
    <td>
        <?= $totalChildren ?>
        <?= $childAgeText ?>
    </td>
</tr>

<tr>
    <th>Infants</th>
    <td>
        <?= $totalInfants ?>
        <?= $infantAgeText ?>
    </td>

    <th>Total Pax</th>
    <td><?= $totalPerson ?></td>
</tr>

<tr>
    <th>Travel Date</th>
    <td><?= d($data['travel_date']) ?></td>

    <th>Departure Date</th>
    <td><?= d($data['departure_date']) ?></td>
</tr>

<tr>
    <th>Transport</th>
    <td colspan="3"><?= htmlspecialchars($transport) ?></td>
</tr>

<tr>
    <th>Total Cost</th>
    <td colspan="3">
        <strong>
            <?= number_format($totalCost, 2) ?>
        </strong>
    </td>
</tr>

</table>

<?php if ($hotels): ?>
<h6 class="text-secondary mt-4">Hotel Details</h6>
<table class="table table-bordered table-sm text-center">
<thead class="table-light">
<tr>
    <th>City</th><th>Hotel</th><th>Conf No</th>
    <th>Category</th><th>Room</th><th>Nights</th>
</tr>
</thead>
<tbody>
<?php foreach ($hotels as $h): ?>
<tr>
    <td><?= htmlspecialchars($h['city_name']) ?></td>
    <td><?= htmlspecialchars($h['hotel_name']) ?></td>
    <td><?= htmlspecialchars($h['hotel_confirmation_no'] ?: '-') ?></td>
    <td><?= htmlspecialchars($h['hotel_category'] ?: '-') ?></td>
    <td><?= htmlspecialchars($h['room_category'] ?: '-') ?></td>
    <td><?= (int)$h['stay_nights'] ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>

<h6 class="text-secondary mt-4">Travel Plan</h6>
<table class="table table-bordered table-sm text-center">
<thead class="table-light">
<tr>
    <th>Date</th><th>Flight</th><th>Pickup</th>
    <th>Guide</th><th>Program</th><th>Meal</th>
</tr>
</thead>
<tbody>
<?php foreach ($travels as $t): ?>
<tr>
    <td><?= date('l, d M Y', strtotime($t['travel_date'])) ?></td>
    <td><?= htmlspecialchars($t['flight_name'] ?: '-') ?></td>
    <td><?= htmlspecialchars($t['pickup_point'] ?: '-') ?></td>
    <td><?= htmlspecialchars($t['guide'] ?: 'No') ?></td>
    <td><?= nl2br(htmlspecialchars($t['sightseeing'] ?: '-')) ?></td>
    <td><?= htmlspecialchars($t['meal'] ?: 'N/A') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<div class="mt-4">
    <a href="confirmation_pdf.php?id=<?= $id ?>" class="btn btn-success">Download PDF</a>
    <a href="confirmations_list.php" class="btn btn-secondary">Back</a>
</div>

</div>
</div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>