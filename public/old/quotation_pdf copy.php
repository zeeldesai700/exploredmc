<?php
// quotation_pdf.php - Final TCPDF template (Exact sample design) using Option C (Travel Date Range as subtitle)
// Place this file to replace your existing quotation_pdf.php
// Requires: config/db.php, config/company.php, tcpdf/tcpdf.php
// Logo detection includes the uploaded image at /mnt/data/Untitled.png

ob_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/company.php';
require_once __DIR__ . '/../tcpdf/tcpdf.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo "Invalid quotation id";
    exit;
}

/* -------------------------
   Fetch main quotation
--------------------------*/
$stmt = $conn->prepare("
    SELECT q.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email, c.address AS customer_address,
           co.name AS country_name
    FROM quotations q
    LEFT JOIN customers c ON q.customer_id = c.id
    LEFT JOIN countries co ON q.country_id = co.id
    WHERE q.id = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$quotation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quotation) {
    http_response_code(404);
    echo "Quotation not found";
    exit;
}

/* -------------------------
   Fetch hotels
--------------------------*/
$hotels = [];
$hstmt = $conn->prepare("
    SELECT qh.*, ci.name AS city_name, ht.name AS hotel_name, rr.room_category AS room_name
    FROM quotation_hotels qh
    LEFT JOIN cities ci ON qh.city_id = ci.id
    LEFT JOIN hotels ht ON qh.hotel_id = ht.id
    LEFT JOIN hotel_rooms rr ON qh.room_category_id = rr.id
    WHERE qh.quotation_id = ?
    ORDER BY qh.id ASC
");
$hstmt->bind_param('i', $id);
$hstmt->execute();
$res = $hstmt->get_result();
while ($r = $res->fetch_assoc()) $hotels[] = $r;
$hstmt->close();

/* -------------------------
   Fetch travel plan (daywise)
--------------------------*/
$travel = [];
$tstmt = $conn->prepare("
    SELECT qt.*, ci.name AS city_name, sp.name AS sightseeing_name
    FROM quotation_travels qt
    LEFT JOIN cities ci ON qt.city_id = ci.id
    LEFT JOIN sightseeings sp ON qt.sightseeing_id = sp.id
    WHERE qt.quotation_id = ?
    ORDER BY qt.day_no ASC
");
$tstmt->bind_param('i', $id);
$tstmt->execute();
$res = $tstmt->get_result();
while ($r = $res->fetch_assoc()) $travel[] = $r;
$tstmt->close();

/* -------------------------
   Helpers
--------------------------*/
function find_column_name($conn, $table, array $candidates) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $res = $conn->query("SHOW COLUMNS FROM `{$table}`");
    if (!$res) return null;
    $cols = [];
    while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
    foreach ($candidates as $c) if (in_array($c, $cols, true)) return $c;
    return null;
}

function get_table_label($conn, $table, $id, $colCandidates) {
    if (!$id) return '';
    $col = find_column_name($conn, $table, $colCandidates);
    if (!$col) return '';
    $q = $conn->prepare("SELECT `$col` AS val FROM `$table` WHERE id = ? LIMIT 1");
    if (!$q) return '';
    $q->bind_param('i', $id);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();
    $q->close();
    return $r ? ($r['val'] ?? '') : '';
}

function get_activity_names($conn, $idsStr) {
    $idsStr = trim((string)$idsStr);
    if ($idsStr === '') return '';
    $parts = array_filter(array_map('trim', explode(',', $idsStr)), function($v){ return $v !== ''; });
    $ids = array_map('intval', $parts);
    $ids = array_filter($ids, function($v){ return $v>0; });
    if (count($ids)===0) return $idsStr;
    $in = implode(',', $ids);
    $activity_col = find_column_name($conn, 'sightseeing_activities', ['activity_name','name','title']) ?: 'activity_name';
    $sql = "SELECT `$activity_col` AS nm FROM sightseeing_activities WHERE id IN ($in)";
    $res = $conn->query($sql);
    if (!$res) return $idsStr;
    $names = [];
    while ($r = $res->fetch_assoc()) $names[] = $r['nm'];
    return $names ? implode(', ', $names) : $idsStr;
}

function fmt($v){ return number_format((float)$v, 2); }

function generate_day_paragraph($row, $activities) {
    $parts = [];
    $parts[] = "Breakfast at the hotel.";
    if (!empty($row['sightseeing_name'])) {
        $pickupTxt = !empty($row['pickup_time']) ? " Pickup at {$row['pickup_time']}." : "";
        $parts[] = "Our driver will pick you up from the hotel{$pickupTxt} Proceed for {$row['sightseeing_name']} and enjoy the sightseeing.";
        if (!empty($activities)) $parts[] = "Activities for the day: {$activities}.";
    } else {
        $parts[] = "Day free for leisure or optional activities.";
        if (!empty($activities)) $parts[] = "Optional activities: {$activities}.";
    }
    if (!empty($row['meal_name'])) $parts[] = "Meal: {$row['meal_name']} will be provided as per plan.";
    if (!empty($row['guide']) && strtoupper($row['guide']) === 'YES') $parts[] = "Local guide will accompany as required.";
    $city = !empty($row['city_name']) ? $row['city_name'] : "hotel";
    $parts[] = "Overnight at {$city}.";
    return implode("<br>", $parts);
}

/* -------------------------
   Design variables & logo detection
--------------------------*/
$blue = '#1A76C4';
$gold = '#E4D59D';
$lightGrey = '#F2F2F2';
$company_name = defined('COMPANY_NAME') ? COMPANY_NAME : 'Explore Vietnam';
$company_address = defined('COMPANY_ADDRESS') ? COMPANY_ADDRESS : "3st Floor, Dreem Ries,\nSola, Gujarat 380060";
$company_contact = (defined('COMPANY_PHONE')? COMPANY_PHONE : '+91-9033233085') . ' | ' . (defined('COMPANY_EMAIL')? COMPANY_EMAIL : 'info@hk-tours.example');
$currency = htmlspecialchars($quotation['currency'] ?? 'INR');

if (file_exists(__DIR__.'public/logo.png')) {
    $html .= '<div class="logo-wrap">
                <img src="public/logo.png" style="height:70px;">
              </div>';
} else {
    $html .= '<div style="font-size:22px; font-weight:bold; color:#1A76C4;">'.htmlspecialchars($company_name).'</div>';
}


/* -------------------------
   Build travel date range (Option C)
   Priority:
   1) If quotation has travel_date and departure_date fields -> use them
   2) Else if travel rows exist -> use first and last day_date
   3) Else fallback to quotation['travel_date']
--------------------------*/
function safe_format_date($d) {
    if (!$d) return '';
    $t = strtotime($d);
    if ($t === false) return $d;
    return date('d M Y', $t);
}

$from = '';
$to = '';
if (!empty($quotation['travel_date']) && !empty($quotation['departure_date'])) {
    $from = safe_format_date($quotation['travel_date']);
    $to   = safe_format_date($quotation['departure_date']);
} elseif (!empty($travel)) {
    // collect day_date values
    $dates = array_filter(array_map(function($r){ return $r['day_date'] ?? $r['date'] ?? ''; }, $travel));
    if (!empty($dates)) {
        $first = reset($dates);
        $last  = end($dates);
        $from = safe_format_date($first);
        $to   = safe_format_date($last);
    } else {
        $from = safe_format_date($quotation['travel_date'] ?? '');
        $to = safe_format_date($quotation['departure_date'] ?? '');
    }
} else {
    $from = safe_format_date($quotation['travel_date'] ?? '');
    $to = safe_format_date($quotation['departure_date'] ?? '');
}

$dateRangeTitle = trim($from . ($to ? " To {$to}" : ''));

/* -------------------------
   Build HTML (matching sample design)
--------------------------*/
$html = '
<style>
body { font-family: DejaVu Sans, sans-serif; color:#222; font-size:11px; }
.header-box { border:1px solid #d0d0d0; border-radius:6px; overflow:hidden; }
.header-top { background:'.$blue.'; color:#fff; padding:10px; text-align:center; font-size:20px; font-weight:700; }
.header-sub { text-align:center; font-size:14px; margin-top:6px; color:#222; }
.header-content { padding:10px 12px; }
.left-col { width:55%; display:inline-block; vertical-align:top; }
.right-col { width:42%; display:inline-block; vertical-align:top; text-align:right; }
.meta-table td { padding:2px 6px; vertical-align:top; }
.bar { background:'.$gold.'; padding:6px 8px; border-radius:4px; font-weight:700; margin-bottom:8px; display:inline-block; }
.section-box { border:1px solid #d9d9d9; border-radius:6px; padding:8px; margin-bottom:10px; }
.tbl { width:100%; border-collapse:collapse; font-size:11px; }
.tbl th, .tbl td { border:1px solid #e7e7e7; padding:6px; vertical-align:top; }
.tbl th { background:'.$lightGrey.'; font-weight:700; }
.section-title { background:'.$blue.'; color:#fff; padding:8px; font-weight:700; border-radius:4px; margin-bottom:8px; font-size:12px; }
.itinerary-day { background:#fbfdff; border-left:4px solid #2f7bed; padding:10px; margin-bottom:10px; border-radius:4px; }
.small-muted { color:#666; font-size:10px; }
.note { font-size:10px; color:#444; }
.logo-wrap { text-align:center; }
.logo-wrap img { max-height:72px; }
</style>

<div class="header-box">
  <div class="header-top">Quotation</div>
  <div class="header-sub">'.htmlspecialchars($dateRangeTitle).'</div>
  <div class="header-content">
    <div style="width:100%; display:block;">
      <div class="left-col">';

if ($logoPath) {
    $html .= '<div class="logo-wrap"><img src="'.htmlspecialchars($logoPath).'" alt="logo"></div>';
} else {
    $html .= '<div style="font-size:20px; font-weight:700; color:'.$blue.';">'.htmlspecialchars($company_name).'</div>
              <div class="small-muted">'.nl2br(htmlspecialchars($company_address)).'</div>
              <div class="small-muted" style="margin-top:6px;">'.htmlspecialchars($company_contact).'</div>';
}

$html .= '
      </div>
      <div class="right-col">
        <table class="meta-table" style="width:100%;">';
$html .= '<tr><td><strong>Quotation No:</strong></td><td class="small-muted">'.htmlspecialchars($quotation['quotation_no'] ?? '-').'</td></tr>';
$html .= '<tr><td><strong>Quotation Date:</strong></td><td class="small-muted">'.htmlspecialchars(substr($quotation['created_at'] ?? date('Y-m-d'),0,10)).'</td></tr>';
$html .= '<tr><td><strong>Destination:</strong></td><td class="small-muted">'.htmlspecialchars($quotation['country_name'] ?? '-').'</td></tr>';
$html .= '</table>
      </div>
    </div>

    <div style="margin-top:12px; border-top:1px dashed #cfcfcf; padding-top:8px;">
      <table width="100%" cellpadding="6">
        <tr>
          <td><strong>Travel Date</strong><br><span class="small-muted">'.htmlspecialchars($quotation['travel_date'] ?? $dateRangeTitle) .'</span></td>
          <td><strong>No. of Adult</strong><br><span class="small-muted">'.((int)($quotation['adults'] ?? 0)).'</span></td>
          <td><strong>No. of Child</strong><br><span class="small-muted">'.((int)($quotation['children'] ?? 0)).'</span></td>
          <td><strong>No. of Infant</strong><br><span class="small-muted">'.((int)($quotation['infant'] ?? 0)).'</span></td>
        </tr>
      </table>
    </div>

    <div style="margin-top:10px;">
      <div class="bar">THE COSTS PROVIDED ARE BASED ON A PER PERSON BASIS IN ['.htmlspecialchars($currency).']</div>
    </div>
  </div>
</div>

<br>
';

/* -------------------------
   PACKAGE INFORMATION (hotels)
--------------------------*/
$html .= '<div class="section-box">
  <div style="background:'.$gold.'; padding:8px; font-weight:700; border-radius:4px; margin-bottom:8px;">PACKAGE INFORMATION</div>
  <table class="tbl" width="100%" cellpadding="6" cellspacing="0">
    <thead>
      <tr>
        <th>HOTEL NAME</th>
        <th>DESTINATION</th>
        <th>ROOM TYPE</th>
        <th>MEAL PLAN</th>
        <th>ROOMS</th>
        <th>STAY</th>
      </tr>
    </thead>
    <tbody>';
if ($hotels) {
    foreach ($hotels as $h) {
        $html .= '<tr>
            <td>'.htmlspecialchars($h['hotel_name'] ?? '-').'</td>
            <td>'.htmlspecialchars($h['city_name'] ?? '-').'</td>
            <td>'.htmlspecialchars($h['room_name'] ?? '-').'</td>
            <td>'.htmlspecialchars($h['meal_name'] ?? ($h['meal_plan'] ?? 'BB')).'</td>
            <td>'.htmlspecialchars($h['rooms'] ?? ($h['room_count'] ?? '-')).'</td>
            <td>'.htmlspecialchars(!empty($h['stay_nights']) ? ($h['stay_nights'] . ' Night') : ($h['stay_dates'] ?? '-')).'</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="6" class="small-muted">No hotels added.</td></tr>';
}
$html .= '
    </tbody>
  </table>
</div>';

/* -------------------------
   Activities / VISA / Sightseeing
--------------------------*/
$html .= '
<div class="section-box">
  <div class="section-title">ACTIVITIES INCLUDED</div>
  <div class="small-muted">1. Tip Included</div>
  <br>
  <div style="background:'.$blue.'; color:#fff; padding:6px; font-weight:700; border-radius:4px;">VISA</div>
  <div class="small-muted" style="margin-top:6px;">1. Vietnam Visa</div>
  <br><br>

  <div style="background:'.$blue.'; color:#fff; padding:6px; font-weight:700; border-radius:4px; margin-bottom:8px;">SIGHTSEEING / ACTIVITY</div>
  <table class="tbl" width="100%" cellpadding="6" cellspacing="0">
    <thead>
      <tr>
        <th>SIGHTSEEING / ACTIVITY</th>
        <th>TYPE</th>
        <th>VEHICLE</th>
        <th>NO. OF VEHICLE</th>
        <th>DATE</th>
        <th>GUIDE</th>
      </tr>
    </thead>
    <tbody>';
if ($travel) {
    foreach ($travel as $s) {
        $html .= '<tr>
            <td>'.htmlspecialchars($s['sightseeing_name'] ?? ($s['activity'] ?? '-')).'</td>
            <td>'.htmlspecialchars($s['type'] ?? 'PVT').'</td>
            <td>'.htmlspecialchars($s['vehicle'] ?? 'VAN 16 Seater').'</td>
            <td>'.htmlspecialchars((int)($s['vehicle_count'] ?? ($s['no_of_vehicle'] ?? 1))).'</td>
            <td>'.htmlspecialchars($s['day_date'] ?? '').'</td>
            <td>'.htmlspecialchars(!empty($s['guide']) ? 'Yes' : 'No').'</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="6" class="small-muted">No sightseeing data.</td></tr>';
}
$html .= '
    </tbody>
  </table>
</div>';

/* -------------------------
   Transfers (best-effort build)
--------------------------*/
$html .= '
<div class="section-box">
  <div class="section-title">TRANSFER TYPE</div>
  <table class="tbl" width="100%" cellpadding="6" cellspacing="0">
    <thead>
      <tr><th>ROUTE</th><th>VEHICLE</th><th>NO. OF VEHICLE</th></tr>
    </thead>
    <tbody>';
if ($travel) {
    foreach ($travel as $trf) {
        $route = trim((string)($trf['pickup_point_name'] ?? $trf['pickup_point'] ?? ($trf['pickup_point_id'] ? 'Pickup' : '')) . ' - ' . ($trf['city_name'] ?? ''));
        $route = $route ?: '-';
        $html .= '<tr><td>'.htmlspecialchars($route).'</td><td>'.htmlspecialchars($trf['vehicle'] ?? 'PVT VAN 16 Seater').'</td><td>'.htmlspecialchars((int)($trf['vehicle_count'] ?? 1)).'</td></tr>';
    }
} else {
    $html .= '<tr><td colspan="3" class="small-muted">No transfer data.</td></tr>';
}
$html .= '
    </tbody>
  </table>
</div>';

/* -------------------------
   Travel Plan table (NO prices) - inserted BEFORE itinerary
   Use fixed column widths to avoid wrapping issues
--------------------------*/
$html .= '
<div class="section-box">
  <div class="section-title">Travel Plan</div>
  <table class="tbl" width="100%" cellpadding="6" cellspacing="0" style="table-layout:fixed;">
    <col style="width:40px;">
    <col style="width:90px;">
    <col style="width:110px;">
    <col style="width:70px;">
    <col style="width:120px;">
    <col>
    <col style="width:140px;">
    <col style="width:80px;">
    <col style="width:50px;">
    <thead>
      <tr>
        <th>Day</th>
        <th>Date</th>
        <th>City</th>
        <th>Pickup</th>
        <th>Pickup Point</th>
        <th>Sightseeing</th>
        <th>Activities</th>
        <th>Meal</th>
        <th>Guide</th>
      </tr>
    </thead>
    <tbody>';
if ($travel) {
    foreach ($travel as $d) {
        // prevent undesired long words breaking: keep as-is but fixed layout helps
        $pickupPoint = get_table_label($conn, 'pickup_points', $d['pickup_point_id'] ?? 0, ['pickup_name','name','point_name','title','label']);
        $mealName = get_table_label($conn, 'meals', $d['meal_id'] ?? 0, ['meal_name','name','title']);
        $activities = !empty($d['activity_ids']) ? get_activity_names($conn, $d['activity_ids']) : ($d['activity'] ?? '');
        $html .= '<tr>
            <td>'.htmlspecialchars((int)($d['day_no'] ?? 0)).'</td>
            <td>'.htmlspecialchars($d['day_date'] ?? '').'</td>
            <td>'.htmlspecialchars($d['city_name'] ?? '').'</td>
            <td>'.htmlspecialchars($d['pickup_time'] ?? '').'</td>
            <td>'.htmlspecialchars($pickupPoint ?: '-').'</td>
            <td>'.htmlspecialchars($d['sightseeing_name'] ?? '-').'</td>
            <td>'.htmlspecialchars($activities ?: '-').'</td>
            <td>'.htmlspecialchars($mealName ?: '-').'</td>
            <td>'.htmlspecialchars(!empty($d['guide']) ? $d['guide'] : 'No').'</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="9" class="small-muted">No travel plan available.</td></tr>';
}
$html .= '
    </tbody>
  </table>
</div>';

/* -------------------------
   Day-wise Itinerary
--------------------------*/
$html .= '<div class="section-box"><div class="section-title">Day-wise Itinerary</div>';

if (!$travel) {
    $html .= '<div class="small-muted">Travel plan not available.</div>';
} else {
    foreach ($travel as $d) {
        $pickup = get_table_label($conn,'pickup_points',$d['pickup_point_id'] ?? 0, ['pickup_name','name','point_name','title','label']);
        $meal   = get_table_label($conn,'meals',$d['meal_id'] ?? 0, ['meal_name','food','name','title']);
        $act    = !empty($d['activity_ids']) ? get_activity_names($conn,$d['activity_ids']) : '';
        $d['pickup_point_name']=$pickup;
        $d['meal_name']=$meal;
        $paragraph = generate_day_paragraph($d,$act);

        $html .= '<div class="itinerary-day">
          <div style="font-weight:700;">Day '.((int)($d['day_no'] ?? 0)).' — '.htmlspecialchars($d['day_date'] ?? '').' <span style="font-weight:600; color:#666; margin-left:8px;">'.htmlspecialchars($d['city_name'] ?? '').'</span></div>
          <div style="margin-top:6px;">'.$paragraph.'</div>
          <div style="margin-top:8px; font-size:11px;" class="small-muted">
            <b>Pickup:</b> '.htmlspecialchars($d['pickup_time'] ?? '-') .' &nbsp; | &nbsp;
            <b>Pickup Point:</b> '.htmlspecialchars($pickup ?: '-') .' &nbsp; | &nbsp;
            <b>Meal:</b> '.htmlspecialchars($meal ?: '-') .' &nbsp; | &nbsp;
            <b>Guide:</b> '.htmlspecialchars(!empty($d['guide']) ? $d['guide'] : 'No').'
          </div>
        </div>';
    }
}
$html .= '</div>';

/* -------------------------
   Inclusions / Exclusions / Cancellation
--------------------------*/
$inclusions = nl2br(htmlspecialchars($quotation['inclusions'] ?? "Transportation with air-conditioned as program PVT.\nTour arrival and departure airport transfers\nAccommodation base on a twin-share basis.\nBreakfast as mentioned in program.\nEnglish Speaking Guide as per itinerary.\nAll Entrance fee, Meals as mentioned in program."));
$exclusions = nl2br(htmlspecialchars($quotation['exclusions'] ?? "International airfare & domestic flights not included.\nBeverages and other meals not indicated.\nEarly check-in and late check-out charges.\nTravel insurance and personal expenses."));
$cancellation = nl2br(htmlspecialchars($quotation['cancellation_policy'] ?? "All cancellation must be made in writing.\n10% charge after confirmation.\n75% between 29-15 days.\n100% less than 14 days."));

$html .= '
<br><div class="section-box">
  <div class="section-title">Inclusion</div>
  <div class="note">'.$inclusions.'</div>
</div>

<div class="section-box">
  <div class="section-title">Exclusion</div>
  <div class="note">'.$exclusions.'</div>
</div>

<div class="section-box">
  <div class="section-title">Cancellation Policy</div>
  <div class="note">'.$cancellation.'</div>
</div>

<div style="text-align:center; font-size:10px; color:#666; margin-top:8px;">
  <em>Note: Prices quoted are subject to change and availability. Quotation valid for 7 days from issue.</em>
</div>
';

/* -------------------------
   Generate PDF (TCPDF)
--------------------------*/

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// TURN ON COLOR SUPPORT
$pdf->SetCompression(false);  
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 12);

// Important for HTML color rendering
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Use Unicode font (supports color + HTML)
$pdf->SetFont('dejavusans', '', 10);

$pdf->AddPage();

// FIX: allow loading logo from project folders
define('K_PATH_IMAGES', __DIR__ . '/../public/');

// RENDER HTML
$pdf->writeHTML($html, true, false, true, false, '');

// CLEAR BUFFER
if (ob_get_length()) ob_end_clean();

// OUTPUT
$fname = "Quotation_".preg_replace('/[^A-Za-z0-9_\-]/','',($quotation["quotation_no"] ?? $id)).".pdf";
$pdf->Output($fname, 'I');
exit;

