<?php
ob_start();

require_once __DIR__ . '/../../config/auth.php';
require_login();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Mpdf\Mpdf;

/* =========================
   VALIDATION
========================= */
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die('Invalid confirmation');

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
        q.visa_total,
        q.tip_total,
        car.car_name AS car_name,
        car.seater AS car_seater
    FROM confirmations cf
    LEFT JOIN quotations q ON cf.quotation_id = q.id
    LEFT JOIN cars car ON q.car_id = car.id
    WHERE cf.id = ?
");



$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$confirmationId = (int)$data['id']; // REAL confirmation ID
$stmt->close();

/* =========================
   FETCH CHILD AGES
========================= */
$childAges = [];

$confirmationId = (int)$data['id'];

$ca = $conn->prepare("
    SELECT child_age
    FROM confirmation_child_ages
    WHERE confirmation_id = ?
    ORDER BY id
");
$ca->bind_param("i", $confirmationId);
$ca->execute();
$res = $ca->get_result();

while ($r = $res->fetch_assoc()) {
    $childAges[] = (int)$r['child_age'];
}
$ca->close();

/* Build age display text */
$childAgeText = '';
if (!empty($childAges)) {
    $childAgeText = ' (' . implode(', ', array_map(fn($a) => $a . ' Years', $childAges)) . ')';
}

/* =========================
   FETCH INFANT AGES
========================= */
$infantAges = [];

$confirmationId = (int)$data['id'];

$ia = $conn->prepare("
    SELECT infant_age
    FROM confirmation_infant_ages
    WHERE confirmation_id = ?
    ORDER BY id
");
$ia->bind_param("i", $confirmationId);
$ia->execute();
$resInfant = $ia->get_result();

while ($r = $resInfant->fetch_assoc()) {
    $infantAges[] = (int)$r['infant_age'];
}
$ia->close();

/* Build infant age display text */
$infantAgeText = '';
if (!empty($infantAges)) {
    $infantAgeText = ' (' . implode(', ', array_map(fn($a) => $a . ' Years', $infantAges)) . ')';
}

/* =========================
   FETCH AGENT NAME
========================= */
$agentName = '--';
$createdByUser = '--';

$ag = $conn->prepare("
    SELECT agent_name, created_by
    FROM agent_accounts
    WHERE confirmation_no = ?
    ORDER BY id DESC
    LIMIT 1
");
$ag->bind_param("s", $data['confirmation_no']);
$ag->execute();
$agRes = $ag->get_result();

if ($row = $agRes->fetch_assoc()) {
    $agentName     = $row['agent_name'];
    $createdByUser = $row['created_by'];
}
$ag->close();

/* =========================
   FETCH HOTELS
========================= */
$hotels = [];
$h = $conn->prepare("
    SELECT 
        city_name,
        hotel_name,
        hotel_confirmation_no,
        category AS hotel_category,
        room_category,
        stay_nights
    FROM confirmations_hotels
    WHERE confirmation_id = ?
    ORDER BY option_no, id
");
$h->bind_param("i", $id);
$h->execute();
$res = $h->get_result();
while ($r = $res->fetch_assoc()) {
    $hotels[] = $r;
}
$h->close();


/* =========================
   FETCH TRAVEL
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

$totalPerson =
    (int)$data['adults'] +
    (int)$data['extra_adults'] +
    (int)$data['children'] +
    (int)$data['no_bed_child']+
    (int)$data['infants'];

$totalAdults  = (int)$data['adults'] + (int)$data['extra_adults'];
$totalChildren = (int)$data['children'] + (int)$data['no_bed_child'];
$totalInfants = (int)$data['infants'];

$guestNumber = $data['passenger_mobile'] ?: '--';

if (!empty($data['car_name'])) {
    $transport = $data['car_name'];
    if (!empty($data['car_seater'])) {
        $transport .= ' (' . $data['car_seater'] . ' Seater)';
    }
} else {
    $transport = '—';
}


$duration = ((int)$data['nights'] > 0 && (int)$data['days'] > 0)
    ? $data['nights'].' Nights / '.$data['days'].' Days'
    : '—';

    $visaTipText = '—';
$includes = [];

$visaTotal = (float)($data['visa_total'] ?? 0);
$tipTotal  = (float)($data['tip_total'] ?? 0);

if ($visaTotal > 0) {
    $includes[] = 'Visa';
}

if ($tipTotal > 0) {
    $includes[] = 'Tip';
}

if (!empty($includes)) {
    $visaTipText = 'Including - ' . implode(', ', $includes);
}

/* =========================
   HELPERS
========================= */
function d($x){ return $x ? date('d F, Y', strtotime($x)) : ''; }

/* === CITY → COLOR (SMART MATCH) === */
function cityColorByName($text)
{
    $text = strtoupper($text);

    if (strpos($text, 'HANOI') !== false) return 'red';
    if (strpos($text, 'HALONG') !== false) return 'red';

    if (strpos($text, 'DANANG') !== false) return 'blue';
    if (strpos($text, 'HOI AN') !== false) return 'blue';
    if (strpos($text, 'BA NA') !== false) return 'navy';

    if (strpos($text, 'HO CHI MINH') !== false || strpos($text, 'HCM') !== false)
        return 'green';

    if (strpos($text, 'SAPA') !== false) return 'red';
    if (strpos($text, 'PHU QUOC') !== false) return 'purple';

    return 'black';
}

/* =========================
   CONDITIONS FOR NOTES
========================= */

/* Breakfast at Hotel → only if hotel exists */
$showBreakfast = !empty($hotels);

/* Local Meal at Cruise → only if meal contains Local Br / Local L / Local D */
$showLocalMeal = false;

foreach ($travels as $t) {
    $meal = strtolower(trim($t['meal'] ?? ''));
    if (in_array($meal, ['local br', 'local l', 'local d'])) {
        $showLocalMeal = true;
        break;
    }
}

/* =========================
   BUILD HTML (GREEN EV-659 STYLE)
========================= */
$html = '
<!DOCTYPE html>
<html>
<head>
<style>
body { font-family: sans-serif; font-size: 10px; color:#000; }

.title {
    text-align:center;
    font-size:14px;
    font-weight:bold;
    margin-bottom:6px;
}

table { width:100%; border-collapse:collapse; }

th, td {
    border:1px solid #000000;
    padding:4px;
    vertical-align:top;
}

.header-green {
    background:#2f6b1f;
    font-weight:bold;
    text-align:center;
}

.header-green th {
    background:#2f6b1f;
    color:#ffffff !important;
}


.sub-green {
    background:#cfe3bf;
    font-weight:bold;
}


.light-green td {
    background: #eaf4e3;
}

.red{color:#c00000;font-weight:bold;}
.darkred{color:#8b0000;font-weight:bold;}
.blue{color:#0047ab;font-weight:bold;}
.teal{color:#008080;font-weight:bold;}
.navy{color:#000080;font-weight:bold;}
.green{color:#008000;font-weight:bold;}
.purple{color:#6a0dad;font-weight:bold;}
.orange{color:#d35400;font-weight:bold;}
.black{color:#000;font-weight:bold;}

.small { font-size:9px; }

.note-table td {
    background:#cfe3bf;
    font-size:9px;
}

.highlight {
    background-color: #fff200 !important; /* Yellow */
    font-weight: bold;
}
.wrap-center {
    text-align: center;
    white-space: pre-line;   /* ✅ respects new lines */
    word-wrap: break-word;
    word-break: break-word;
}

</style>
</head>
<body>

<div class="title">Vietnam Travel Itinerary</div>

<table>
<tr class="light-green">
  <td><b>Confirmation No.:</b> '.$data['confirmation_no'].'</td>
  <td><b>Guest Name:</b> '.$data['passenger_name'].'</td>
  <td><b>Agent Name:</b> '.$agentName.'</td>
  
</tr>
<tr class="light-green">
  <td><b>Adults:</b> '.$totalAdults.'</td>
  <td><b>Children:</b> '.$totalChildren.$childAgeText.'</td>
  <td><b>Infants:</b> '.$totalInfants.$infantAgeText.'</td>
</tr>

<tr class="light-green">
   <td><b>Travel Date:</b> '.d($data['travel_date']).'</td>
  <td><b>Arrival Date:</b> '.d($data['travel_date']).'</td>
  <td><b>Departure Date:</b> '.d($data['departure_date']).'</td>
</tr>

<tr class="light-green">
  <td><b>Transport:</b> '.$transport.'</td>
  <td><b>'.$duration.'</b></td>
  <td><b></b> '.$visaTipText.'</td>
</tr>
</table>';

if (!empty($data['travel_date'])) {
    $hotelStartDate = new DateTime($data['travel_date']);
} else {
    $hotelStartDate = null;
}

if (!empty($hotels)) {

    $html .= '
    <table>
    <tr class="header-green">
      <th>Place</th>
      <th>Hotel Name</th>
      <th>Hotel Conf. No</th>
      <th>Hotel Category</th>
      <th>Room Category</th>
      <th>Period of Stay</th>
    </tr>';

    foreach ($hotels as $h) {

    if ($hotelStartDate instanceof DateTime) {

        // Clone current start date
        $checkInDate = clone $hotelStartDate;
        $formattedDate = $checkInDate->format('d F Y');

        // Move pointer for next hotel
        $hotelStartDate->modify('+' . (int)$h['stay_nights'] . ' days');

    } else {
        $formattedDate = '—';
    }

    $html .= '
    <tr class="light-green">
      <td>'.$h['city_name'].'</td>
      <td>'.$h['hotel_name'].'</td>
      <td>'.($h['hotel_confirmation_no'] ?: '-').'</td>
      <td>'.($h['hotel_category'] ?: '-').'</td>
      <td>'.($h['room_category'] ?: '-').'</td>
      <td>'.$formattedDate.' / Nights '.$h['stay_nights'].'</td>
    </tr>';
}


    $html .= '</table>';
}

$html .= '
<table>
<tr class="header-green">
  <th>Day - Date (Approx)</th>
  <th>Flight Details/Pick-Up Time</th>
  <th>Pick-Up Point</th>
  <th>Guide</th>
  <th>Tour / Transfer Program</th>
  <th>Meal</th>
</tr>';

foreach ($travels as $t) {

    $colorClass = cityColorByName(
        $t['pickup_point'].' '.$t['sightseeing'].' '.$t['flight_name']
    );

    $html .= '
<tr class="light-green">

  <td class="'.$colorClass.' wrap-center">
    <div style="text-align:center;">
      '.date('l, j F, Y', strtotime($t['travel_date'])).'
    </div>
  </td>

  <td class="'.$colorClass.' wrap-center">
    <div style="text-align:center;">
      '.htmlspecialchars($t['flight_name']).'
    </div>
  </td>

  <td class="'.$colorClass.' wrap-center">
    <div style="text-align:center;">
      '.htmlspecialchars($t['pickup_point']).'
    </div>
  </td>

  <td class="'.$colorClass.' wrap-center">
    <div style="text-align:center;">
      '.htmlspecialchars($t['guide'] ?: 'No').'
    </div>
  </td>

  <td class="'.$colorClass.' wrap-center">
    <div style="text-align:center;">
      '.nl2br(htmlspecialchars($t['sightseeing'])).'
    </div>
  </td>

  <td class="'.$colorClass.' wrap-center">
    <div style="text-align:center;">
      '.htmlspecialchars($t['meal'] ?: 'N/A').'
    </div>
  </td>

</tr>';
}

$html .= '
</table>

<table class="note-table">
<tr>
    <td colspan="2" class="small">
        <b>Note :</b>
        Any Airport pickup waiting time 1 Hour from the time of landing of flight.
        Pickup time from hotel for any international flight 4 hour before departure time,
        and 3 hour for domestic local vietnam internal flight.
        If any Flight Delay / Early inform us in advance.
        If any flight early/delay/cancelled due to any reason are not responsible
        for pickup/drop or missed flight due to pickup/drop.
        Extra charges applicable in pickup/drop to/from airport without prior information of above.
    </td>
</tr>

<tr>
    <td width="50%" class="highlight" style="background-color:#fff200;">
        '.($showBreakfast ? 'Breakfast at Hotel.' : '').'
    </td>
    <td width="50%" class="highlight" style="background-color:#fff200;">
        '.($showLocalMeal ? 'Local Meal at Cruise: Lunch, Dinner, Brunch.' : '').'
    </td>
</tr>

<tr>
    <td width="50%" class="small">
        <b>Remark 1 :</b>
        Buy local Sim card at Arrival Airport and send us guest number on our whatsapp number for communication.
        In any airport pickup/drop 1 bag of 20Kg luggage bag per person and 1 hand bag 7 kg per person allowed in car,
        if more bags extra charges pay by guest.
    </td>
    <td width="50%" class="small">
        <b>Remark 2 :</b>
        In city tour Temple/Pagoda/Church/Mauseloum - Full cloths compulsory for Gents/ladies/child. 
        Ladies also put Scarf in pocket if needed wear it. Take swimming costume & extra short cloths in theme park and water park tour, 
        Bring Umbrella in your bag during trip. Compulsory Tip : 3 USD per pax per day.
        Child Below 100 cm Complimentary.
    </td>
</tr>
</table>

<table style="margin-top:10px;">
<tr>
  <td style="border:none; text-align:right; font-size:9px;">
    <b>Created By :</b> '.$createdByUser.'
  </td>
</tr>
</table>

</body>
</html>
';

/* =========================
   GENERATE PDF
========================= */
$mpdf = new Mpdf([
    "format" => "A4",
    "margin_left" => 8,
    "margin_right" => 8,
    "margin_top" => 8,
    "margin_bottom" => 8
]);

$mpdf->WriteHTML($html);
ob_end_clean();
$mpdf->Output("Confirmation_{$data['confirmation_no']}.pdf", "I");
exit;
