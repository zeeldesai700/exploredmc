  <?php
  error_reporting(0);
  ini_set('display_errors', 0);
  ob_start();


  require_once __DIR__ . '/../../config/db.php';
  require_once __DIR__ . '/../../vendor/autoload.php';


  use Mpdf\Mpdf;

  /* -------------------------------------------------------
    GET ID
  --------------------------------------------------------*/
  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) die("Invalid Quotation ID");

  $qRes = $conn->query("
      SELECT
          activity_total,
          meal_total,
          transport_total,
          guide_total,
          visa_total,
          tip_total,

          activity_per_adult,
          activity_per_extra_adult,
          activity_per_child,
          activity_per_child_no_bed,

          meal_per_adult,
          meal_per_extra_adult,
          meal_per_child,
          meal_per_child_no_bed,

          transport_per_adult,
          transport_per_extra_adult,
          transport_per_child,
          transport_per_child_no_bed,

          guide_per_adult,
          guide_per_extra_adult,
          guide_per_child,
          guide_per_child_no_bed,

          visa_per_adult,
          visa_per_extra_adult,
          visa_per_child,
          visa_per_child_no_bed,

          tip_per_adult,
          tip_per_extra_adult,
          tip_per_child,
          tip_per_child_no_bed
      FROM quotations
      WHERE id = $id
      LIMIT 1
  ") or die($conn->error);

  $q = $qRes->fetch_assoc();
  if (!$q) die("Quotation not found");


  /* -------------------------------------------------------
    FETCH QUOTATION
  --------------------------------------------------------*/
  $q = $conn->query("
  SELECT q.*, c.name customer_name, co.name country_name
  FROM quotations q
  LEFT JOIN customers c ON q.customer_id = c.id
  LEFT JOIN countries co ON q.country_id = co.id
  WHERE q.id = $id
  ")->fetch_assoc();

  if (!$q) die("Quotation not found");

 function get_travel_cars_pdf(mysqli $conn, int $travel_id): string {
    $sql = "
        SELECT c.car_name, c.seater
        FROM quotation_travel_cars tc
        JOIN cars c ON c.id = tc.car_id
        WHERE tc.quotation_travel_id = $travel_id
    ";
    $res = $conn->query($sql);
    if (!$res || $res->num_rows === 0) return '—';

    $cars = [];
    while ($r = $res->fetch_assoc()) {
        $cars[] = htmlspecialchars($r['car_name']).' ('.$r['seater'].' Seater)';
    }
    return implode(' + ', $cars); // 🔥 plus sign
}

function get_travel_car_count(mysqli $conn, int $travel_id): int {
    $res = $conn->query("
        SELECT COUNT(*) cnt
        FROM quotation_travel_cars
        WHERE quotation_travel_id = $travel_id
    ");
    $row = $res ? $res->fetch_assoc() : ['cnt' => 0];
    return (int)$row['cnt'];
}

  /* -------------------------------------------------------
    PREPARE VISA / TIP TEXT (✔ FIXED POSITION)
  --------------------------------------------------------*/
  $visaText = '';
  $tipText  = '';

  if (!empty($q['visa_total']) && (float)$q['visa_total'] > 0) {
      $visaText = 'VISA:'.$q['country_name'];
  }

  if (!empty($q['tip_total']) && (float)$q['tip_total'] > 0) {
      $tipText = 'Tip Included';
  }

  $otherCostTotal =
      $q['activity_total'] +
      $q['meal_total'] +
      $q['transport_total'] +
      $q['guide_total'] +
      $q['visa_total'] +
      $q['tip_total'];

  $otherPerAdult =
      $q['activity_per_adult'] +
      $q['meal_per_adult'] +
      $q['transport_per_adult'] +
      $q['guide_per_adult'] +
      $q['visa_per_adult'] +
      $q['tip_per_adult'];

  $otherPerExtraAdult =
      $q['activity_per_extra_adult'] +
      $q['meal_per_extra_adult'] +
      $q['transport_per_extra_adult'] +
      $q['guide_per_extra_adult'] +
      $q['visa_per_extra_adult'] +
      $q['tip_per_extra_adult'];

  $otherPerChild =
      $q['activity_per_child'] +
      $q['meal_per_child'] +
      $q['transport_per_child'] +
      $q['guide_per_child'] +
      $q['visa_per_child'] +
      $q['tip_per_child'];

  $otherPerNoBed =
      $q['activity_per_child_no_bed'] +
      $q['meal_per_child_no_bed'] +
      $q['transport_per_child_no_bed'] +
      $q['guide_per_child_no_bed'] +
      $q['visa_per_child_no_bed'] +
      $q['tip_per_child_no_bed'];

  /* -------------------------------------------------------
    FETCH HOTELS (WITH NAMES)
  --------------------------------------------------------*/
  $hotelsRes = $conn->query("
SELECT 
  qh.id,
  qh.quotation_id,
  qh.option_no,               -- 🔥 ADD THIS
  qh.city_id,
  qh.category AS option_category,
  qh.hotel_id,
  qh.room_category_id,
  qh.stay_nights,
  qh.price,
  qh.rooms,
  qh.base_price,
  qh.extra_adult_price,
  qh.child_price,
  qh.nobed_price,

  ci.name          AS city_name,
  ht.name          AS hotel_name,
  ht.category      AS hotel_star,
  rc.room_category AS room_name

FROM quotation_hotels qh
LEFT JOIN cities ci      ON qh.city_id = ci.id
LEFT JOIN hotels ht      ON qh.hotel_id = ht.id
LEFT JOIN hotel_rooms rc ON qh.room_category_id = rc.id
WHERE qh.quotation_id = $id
ORDER BY qh.option_no, qh.id
") or die($conn->error);

  $hotelGroups = [];

while ($row = $hotelsRes->fetch_assoc()) {
    $optionNo = (int)$row['option_no'];   // 🔥 REAL OPTION
    $hotelGroups[$optionNo][] = $row;
}



  /* -------------------------------------------------------
    FETCH TRAVELS (ACTIVITY + TRANSFER + ITINERARY)
  --------------------------------------------------------*/
  $travels = $conn->query("
SELECT 
    qt.*,
    ci.name AS city_name,
    sp.name AS sightseeing_name,
    c.car_name,
    c.seater,
    ml.category AS meal_name      -- ✅ CORRECT COLUMN
FROM quotation_travels qt
LEFT JOIN cities ci ON qt.city_id = ci.id   
LEFT JOIN sightseeings sp ON qt.sightseeing_id = sp.id
LEFT JOIN cars c ON qt.car_id = c.id
LEFT JOIN meals ml ON qt.meal_id = ml.id   -- ✅ SAME AS VIEW
WHERE qt.quotation_id = $id
ORDER BY qt.day_no ASC
") or die($conn->error);



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
          return '';
      }

      $names = [];
      while ($r = $res->fetch_assoc()) {
          $names[] = $r['activity_name'];
      }

      return implode(', ', $names);
  }

  function showCostRow(string $label, $amount): string
  {
      if (empty($amount) || (float)$amount <= 0) {
          return '';
      }

      return '
      <tr>
        <td style="padding:4px 0;">'.$label.'</td>
        <td style="padding:4px 0;text-align:center;">
        Per Person USD '.number_format($amount, 0).' /-
        </td>
      </tr>';
  }

  $adult_total = (int)$q['adults'] + (int)$q['extra_adults'];
  $child_total = (int)$q['children'] + (int)$q['no_bed_child'];
  $infant_total = (int)($q['infants'] ?? 0);

  $room_total = (int)$q['rooms'];

$rooms_html = 'DBL : '.$room_total;


  if (!empty($q['extra_adults']) && $q['extra_adults'] > 0) {
      $rooms_html .= '<br>Adult Extra Bed : '.$q['extra_adults'];
  }

  if (!empty($q['children']) && $q['children'] > 0) {
      $rooms_html .= '<br>CWB : '.$q['children'];
  }

  if (!empty($q['no_bed_child']) && $q['no_bed_child'] > 0) {
      $rooms_html .= '<br>CNB : '.$q['no_bed_child'];
  }

  /* -------------------------------------------------------
    INIT mPDF
  --------------------------------------------------------*/
  $mpdf = new Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_top' => 14,
    'margin_bottom' => 18,
    'margin_left' => 12,
    'margin_right' => 12,
    'default_font' => 'dejavusans'
  ]);

  /* FOOTER */
  $mpdf->SetHTMLFooter('
  <div style="text-align:center;font-size:9px;color:#666;">
  Page {PAGENO} of {nbpg}
  </div>
  ');

  /* BACKGROUND LETTERHEAD */
  $mpdf->SetDefaultBodyCSS('background', "url('../assets/letterhead_bg.png')");
  $mpdf->SetDefaultBodyCSS('background-image-resize', 6);

  /* -------------------------------------------------------
    CSS
  --------------------------------------------------------*/
  $css = '
  body{font-size:11px;}
  table{width:100%;border-collapse:collapse;}
  th,td{border:1px solid #999;padding:6px;vertical-align:top;}
  th{background:#e6e6e6;}
  .center{text-align:center;}
  .right{text-align:right;}
  .bold{font-weight:bold;}

  .section-title{
    background:#f3f1cf;
    border:1px solid #999;
    padding:6px;
    font-weight:bold;
    text-align:center;
    margin-top:10px;
  }

  .note{
    background:#4f79a7;
    color:#fff;
    padding:6px;
    font-weight:bold;
    margin-top:6px;
  }

  .no-break{page-break-inside:avoid;}
  .page-break{page-break-before:always;}
  ';

  /* -------------------------------------------------------
    HTML START
  --------------------------------------------------------*/
  $html = '<style>'.$css.'</style>

  <!-- HEADER -->
  <div class="center">
    <h2>Quotation</h2>
    <h4>'.$q['nights'].' Night '.$q['days'].' Days '.$q['country_name'].' Package</h4>
    <img src="../assets/logo.png" width="150">
  </div>

  <table>
  <tr>
  <td class="bold">'.htmlspecialchars($q['customer_name']).'</td>
  <td class="right">
  Quotation No: '.$q['quotation_no'].'<br>
  Quotation Date: '.date('d M Y',strtotime($q['created_at'])).'<br>
  Destination: '.$q['country_name'].'
  </td>
  </tr>
  </table>

  <table>
  <tr>
    <th>Travel Date</th>
    <th>Adult</th>
    <th>Child</th>
    <th>Infant</th>
  </tr>

  <tr>
    <td style="text-align:center;">
      '.date('d M Y', strtotime($q['travel_date'])).'
      <strong> To </strong>
      '.date('d M Y', strtotime($q['departure_date'])).'
    </td>

    <td style="text-align:center;">'.$adult_total.'</td>
    <td style="text-align:center;">'.$child_total.'</td>
    <td style="text-align:center;">'.$infant_total.'</td>
  </tr>
  </table>



  <div class="note">THE COSTS PROVIDED ARE BASED ON A PER PERSON BASIS IN [USD]</div>
  ';

  /* ---------------- VISA / TIP BLOCK (✔ CORRECT) ---------------- */
  if ($visaText || $tipText) {
      $html .= '
      <div style="border:1px solid #999;padding:6px;margin:8px 0;">
        <table style="width:100%;border:none;">
          <tr>';
      if ($visaText) $html .= '<td class="bold">'.$visaText.'</td>';
      if ($tipText)  $html .= '<td class="bold">'.$tipText.'</td>';
      $html .= '</tr></table>
      </div>';
  }

  $otherPerAdult =
      $q['activity_per_adult'] +
      $q['meal_per_adult'] +
      $q['transport_per_adult'] +
      $q['guide_per_adult'] +
      $q['visa_per_adult'] +
      $q['tip_per_adult'];

  $otherPerExtraAdult =
      $q['activity_per_extra_adult'] +
      $q['meal_per_extra_adult'] +
      $q['transport_per_extra_adult'] +
      $q['guide_per_extra_adult'] +
      $q['visa_per_extra_adult'] +
      $q['tip_per_extra_adult'];

  $otherPerChild =
      $q['activity_per_child'] +
      $q['meal_per_child'] +
      $q['transport_per_child'] +
      $q['guide_per_child'] +
      $q['visa_per_child'] +
      $q['tip_per_child'];

  $otherPerNoBed =
      $q['activity_per_child_no_bed'] +
      $q['meal_per_child_no_bed'] +
      $q['transport_per_child_no_bed'] +
      $q['guide_per_child_no_bed'] +
      $q['visa_per_child_no_bed'] +
      $q['tip_per_child_no_bed'];

  $otherCostTotal = (
      $q['activity_total'] +
      $q['meal_total'] +
      $q['transport_total'] +
      $q['guide_total'] +
      $q['visa_total'] +
      $q['tip_total'] +
      $q['extra_total']
  );

/* =======================================================
   HOTEL OPTIONS + MULTI-HOTEL CALCULATION (FINAL FIX)
======================================================= */

$adults      = (int)$q['adults'];
$extraAdults = (int)$q['extra_adults'];
$children    = (int)$q['children'];
$noBed       = (int)$q['no_bed_child'];

foreach ($hotelGroups as $optionNo => $hotels) {

    if (empty($hotels)) continue;

    /* ================= OPTION TITLE ================= */
    $html .= '
    <div class="section-title">
      HOTEL OPTION '.$optionNo.'
    </div>';

    /* ================= HOTEL TABLE ================= */
    $html .= '
    <div class="section-title">HOTEL</div>

    <table>
      <tr>
        <th style="width:25%">HOTEL NAME</th>
        <th style="width:15%">DESTINATION</th>
        <th style="width:20%">ROOM TYPE</th>
        <th style="width:8%">MEAL<br>PLAN</th>
        <th style="width:10%">ROOMS</th>
        <th style="width:22%">STAY</th>
      </tr>';

    $startDate = strtotime($q['travel_date']);

    foreach ($hotels as $row) {

        $from = date('d M Y', $startDate);
        $to   = date('d M Y', strtotime("+{$row['stay_nights']} days", $startDate));

        $html .= '
        <tr>
          <td>
            <strong>'.htmlspecialchars($row['hotel_name']).'</strong><br>
            <span style="font-size:10px;color:#555;">'.$row['hotel_star'].'</span>
          </td>

          <td class="center">
            '.htmlspecialchars($row['city_name']).'<br>
            <span style="font-size:10px;">'.$row['stay_nights'].' Night</span>
          </td>

          <td>'.htmlspecialchars($row['room_name']).'</td>
          <td class="center">BB</td>
          <td class="center">'.$rooms_html.'</td>
          <td class="center">'.$from.' To '.$to.'</td>
        </tr>';

        $startDate = strtotime($to);
    }

    $html .= '</table><br>';

 /* ================= OPTION-WISE HOTEL COST (FINAL FIX) ================= */

/* ================= INIT TOTALS ================= */
$hotelOptionTotal   = 0;

$perAdultHotelSum   = 0;
$perExtraAdultSum   = 0;
$perChildHotelSum   = 0;
$perNoBedHotelSum   = 0;

/* ================= CALCULATE ALL HOTELS ================= */
foreach ($hotels as $h) {

    $nights = (int)$h['stay_nights'];

    // ✅ OPTION HOTEL TOTAL (FINAL ROOM PRICE)
    $hotelOptionTotal += (float)$h['price'];

    // ✅ PER PERSON (SAME AS VIEW PAGE)
    $perAdultHotelSum += (float)$h['base_price'];

    $perExtraAdultSum += (float)$h['extra_adult_price'] * $nights;
    $perChildHotelSum += (float)$h['child_price'] * $nights;
    $perNoBedHotelSum += (float)$h['nobed_price'] * $nights;

    // per-person accumulation
    $perAdultHotelSum   += $perAdultHotel;
    $perExtraAdultSum   += $perExtraAdultHotel;
    $perChildHotelSum   += $perChildHotel;
    $perNoBedHotelSum   += $perNoBedHotel;

    // total accumulation
    $hotelOptionTotal +=
        ($perAdultHotel      * $adults) +
        ($perExtraAdultHotel * $extraAdults) +
        ($perChildHotel      * $children) +
        ($perNoBedHotel      * $noBed);
}


    /* ================= FINAL PER PERSON ================= */
    $perAdult      = $otherPerAdult      + $perAdultHotelSum;
    $perExtraAdult = $otherPerExtraAdult + $perExtraAdultSum;
    $perChild      = $otherPerChild      + $perChildHotelSum;
    $perNoBed      = $otherPerNoBed      + $perNoBedHotelSum;

    /* ================= FINAL TOTAL ================= */
    $finalTotal = $otherCostTotal + $hotelOptionTotal;

    /* ================= COST SUMMARY ================= */
    $html .= "<table style='width:45%;float:right;font-size:11px;'>";

    if ($perAdult > 0) {
        $html .= "<tr><td>Per Adult</td><td align='right'>USD ".number_format($perAdult,2)."</td></tr>";
    }

    if ($perExtraAdult > 0) {
        $html .= "<tr><td>Per Extra Adult</td><td align='right'>USD ".number_format($perExtraAdult,2)."</td></tr>";
    }

    if ($perChild > 0) {
        $html .= "<tr><td>Per Child With Bed</td><td align='right'>USD ".number_format($perChild,2)."</td></tr>";
    }

    if ($perNoBed > 0) {
        $html .= "<tr><td>Per Child No Bed</td><td align='right'>USD ".number_format($perNoBed,2)."</td></tr>";
    }

    $html .= "
    <tr style='font-weight:bold;'>
      <td>TOTAL PACKAGE COST</td>
      <td align='right'>USD ".number_format($finalTotal,0)."</td>
    </tr>
    </table>

    <div style='clear:both'></div><br>";
}

/* =======================================================
   COST SUMMARY (NO HOTEL CASE)
======================================================= */
if (empty($hotelGroups)) {

    // total = ONLY other costs
    $finalTotal = $otherCostTotal;

    // optional heading (recommended)
    $html .= "<div class='section-title'>COST SUMMARY</div>";

    $html .= "<table style='width:45%;float:right;font-size:11px;'>";

    if ($otherPerAdult > 0) {
        $html .= "
        <tr>
          <td>Per Adult</td>
          <td align='right'>USD ".number_format($otherPerAdult, 2)."</td>
        </tr>";
    }

    if ($otherPerExtraAdult > 0) {
        $html .= "
        <tr>
          <td>Per Extra Adult</td>
          <td align='right'>USD ".number_format($otherPerExtraAdult, 2)."</td>
        </tr>";
    }

    if ($otherPerChild > 0) {
        $html .= "
        <tr>
          <td>Per Child With Bed</td>
          <td align='right'>USD ".number_format($otherPerChild, 2)."</td>
        </tr>";
    }

    if ($otherPerNoBed > 0) {
        $html .= "
        <tr>
          <td>Per Child No Bed</td>
          <td align='right'>USD ".number_format($otherPerNoBed, 2)."</td>
        </tr>";
    }

    // TOTAL (always show)
    $html .= "
    <tr style='font-weight:bold'>
      <td>TOTAL PACKAGE COST</td>
      <td align='right'>USD ".number_format($finalTotal, 0)."</td>
    </tr>
    </table>

    <div style='clear:both'></div><br>";
}


  $html .= '
<div class="section-title">SIGHTSEEING / ACTIVITIES</div>
<table>
<tr>
  <th>ACTIVITY</th>
  <th>TYPE</th>
  <th>VEHICLE</th>
  <th>NO. OF VEHICLE</th>
  <th>DATE</th>
  <th>GUIDE COST</th>
</tr>';

mysqli_data_seek($travels, 0);
while ($t = $travels->fetch_assoc()) {

    $activityNames = trim(get_travel_activities($conn, (int)$t['id']));
    if ($activityNames === '') continue;

    $vehicle      = get_travel_cars_pdf($conn, (int)$t['id']);
    $vehicleCount = get_travel_car_count($conn, (int)$t['id']);

    $html .= '
    <tr>
      <td>'.htmlspecialchars($activityNames).'</td>
      <td class="center">PVT</td>
      <td>'.$vehicle.'</td>
      <td class="center">'.($vehicleCount ?: '—').'</td>
      <td class="center">'.(
            $t['day_date']
            ? date('d M Y', strtotime($t['day_date']))
            : '—'
        ).'</td>
      <td class="center">'.($t['guide'] === 'Yes' ? 'Yes' : 'No').'</td>
    </tr>';
}

$html .= '</table>';

$html .= '
<div class="section-title">TRANSFER</div>
<table>
<tr>
  <th>TRANSFER</th>
  <th>TYPE</th>
  <th>VEHICLE</th>
  <th>NO. OF VEHICLE</th>
  <th>MEAL</th>
</tr>';

mysqli_data_seek($travels, 0);
while ($t = $travels->fetch_assoc()) {

    if (empty($t['sightseeing_name']) && empty($t['car_id'])) continue;

    $vehicle      = get_travel_cars_pdf($conn, (int)$t['id']);
    $vehicleCount = get_travel_car_count($conn, (int)$t['id']);

    $mealText = (!empty($t['meal_id']) && !empty($t['meal_name']))
        ? 'Yes<br>(' . htmlspecialchars($t['meal_name']) . ')'
        : 'No';

    $html .= '
    <tr>
      <td>'.htmlspecialchars($t['sightseeing_name'] ?: '—').'</td>
      <td class="center">PVT</td>
      <td>'.$vehicle.'</td>
      <td class="center">'.($vehicleCount ?: '—').'</td>
      <td class="center">'.$mealText.'</td>
    </tr>';
}

$html .= '</table>';

$html .= '
  <!-- DAY WISE ITINERARY (AFTER TRANSFERS) -->
  <div class="section-title">DAY WISE ITINERARY</div>';

  mysqli_data_seek($travels,0);
  while($t = $travels->fetch_assoc()){
  $html .= '
  <div class="no-break" style="border:1px solid #999;padding:8px;margin-bottom:6px;">
  '.nl2br($t['itinerary_text']).'
  </div>';
  }

  $html .= '
  <div class="section-title">INCLUSION</div>
  <ul>
    <li>Transportation with air-conditioned as program PVT.</li>
    <li>Tour arrival and departure airport transfers.</li>
    <li>Accommodation base on a twin-share basis.</li>
    <li>Breakfast (check-in time: 14:00 / Check-out time: 12:00).</li>
    <li>English Speaking Guide as per mentioned above itinerary.</li>
    <li>All entrance fee, meals as mentioned in program.</li>
    <li>Mineral water: 2 bottles per person per day.</li>
  </ul>

  <div class="section-title">EXCLUSION</div>
<ul>
  <li>Guide available where mentioned; extra guide charges apply for additional days.</li>
  <li>International airfare to and from Vietnam, and internal domestic airfare.</li>
  <li>Beverages and other meals not indicated in the program.</li>
  <li>Early check-in and late check-out at all hotels.</li>
  <li>Travel insurance of all kinds and personal expenses (laundry, telephone, shopping, etc.).</li>
  <li>Gratuities (approximately USD 3 per person per day).</li>
  <li>Any additional expenses caused by circumstances beyond our control, such as natural calamities (typhoons, floods), flight delays, rescheduling or cancellations, accidents, medical evacuations, riots, strikes, etc.</li>
  <li>
    Surcharge for peak seasons:New Year (01–05 Jan),Lunar New Year (13 Feb–22 Feb),Southern Liberation Day (29 Apr–03 May),National Day (30 Aug–03 Sep),
    Christmas (20–31 Dec).
  </li>
</ul>

  <div class="section-title">IMPORTANT NOTES</div>
  <ul>
  <li>Price based on twin/triple sharing room per person. Hotel availability subject to confirmation.</li>
  <li>Prices quoted in USD and subject to change without prior notice.</li>
  <li>This quotation does not imply or constitute availability.</li>
  <li>If listed hotel is unavailable, similar category hotel will be provided.</li>
  <li>Compulsory Gala Dinner applicable on Christmas Eve & New Year Eve.</li>
  <li>Airfare is same for children (2 years & above) and adults.</li>
  <li>TIP is compulsory for driver & guide: USD 3 per pax per day.</li>
  <li>In any airport pickup /drop 1 bag of 20Kg luggage bag per person and 1 backpack 7 kg per person allowed in car, if more bags extra charges pay by guest.</li>
  <li><b>Child below 100 cm complimentary.</b></li>
  </ul>

  <p>
  You are looking to book a hotel in specific areas to avoid additional transfer rates. Here are the recommended areas for 
  each city:
  </p>

  <ul>
  <li><b>Hanoi:</b> Old Quarter Area</li>
  <li><b>Sapa:</b> Sapa Town</li>
  <li><b>Halong Bay:</b> Tuan Chau Marina</li>
  <li><b>Danang:</b> My Khe Beach</li>
  <li><b>Ho Chi Minh City:</b> District 1 & District 3</li>
  <li><b>Phu Quoc:</b> Duong Dong Town</li>
  </ul>

  <p>
  Booking a hotel within these areas will help minimize transfer costs. If you book a hotel outside of these areas, 
  additional transfer rates may apply.
  </p>
  <b>Phu Quoc:Kiss Of the Sea Show Tuesday Closed....</b>

  <!-- CANCELLATION (LAST PAGE) -->
  <div class="section-title">CANCELLATION POLICY</div>
  <ul>
  <li>All cancellation must be made in writing </li>
  <li>Any cancellation after confirmation of booking : 10% charge</li>
  <li>Any cancellation between 29Days – 15 Days prior to arrival date: 75% of tour fare charge </li>
  <li>Any cancellation less than 14s Days: 100% of tour fare charge</li>
  </ul>
  ';

  /* -------------------------------------------------------
    OUTPUT
  --------------------------------------------------------*/
  $mpdf->WriteHTML($html);
  $pdfPath = __DIR__ . "../pdf/Quotation_{$q['quotation_no']}.pdf";

  /* Ensure folder exists */
  if (!is_dir(__DIR__.'../pdf')) {
      mkdir(__DIR__.'../pdf', 0777, true);
  }

  /* SAVE FILE */
  $mpdf->Output($pdfPath, \Mpdf\Output\Destination::FILE);

  /* ALSO SHOW IN BROWSER */
  $mpdf->Output(
    "Quotation_{$q['quotation_no']}.pdf",
    \Mpdf\Output\Destination::INLINE
  );
  exit;
