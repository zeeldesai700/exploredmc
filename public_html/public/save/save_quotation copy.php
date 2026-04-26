<?php 
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid Request");
}

/* -------------------------------------------------------
   BASIC HEADER FIELDS
--------------------------------------------------------*/
$quotation_no   = $_POST['quotation_no'] ?? '';
$customer_id    = (int)($_POST['customer_id'] ?? 0);
$country_id     = (int)($_POST['country_id'] ?? 0);
$travel_date    = $_POST['travel_date'] ?? null;
$departure_date = $_POST['departure_date'] ?? null;
$car_id         = (int)($_POST['car_id'] ?? 0);

$adults   = (int)($_POST['adults'] ?? 0);
$children = (int)($_POST['children'] ?? 0);
$no_bed   = (int)($_POST['no_bed_child'] ?? 0);
$rooms    = (int)($_POST['rooms'] ?? 0);

$nights   = (int)($_POST['nights'] ?? 0);
$days     = (int)($_POST['days'] ?? 0);

/* -------------------------------------------------------
   TOTALS (FROM FRONTEND)
   We'll cast to float for totals
--------------------------------------------------------*/
$hotel_total     = (float)($_POST['hotel_total'] ?? 0);
$activity_total  = (float)($_POST['activity_total'] ?? 0);
$meal_total      = (float)($_POST['meal_total'] ?? 0);
$transport_total = (float)($_POST['transport_total'] ?? 0);
$guide_total     = (float)($_POST['guide_total'] ?? 0);
$net_total       = (float)($_POST['net_total'] ?? 0);
$grand_total     = (float)($_POST['grand_total'] ?? 0);

$has_guide = ($guide_total > 0 ? "Yes" : "No");

/* -------------------------------------------------------
   EXTRA CHARGES (NEW)
   Collect names/amounts, produce JSON and total
--------------------------------------------------------*/
$extra_names   = $_POST['extra_charge_name']  ?? [];
$extra_amounts = $_POST['extra_charge_amount'] ?? [];
$extra_charges = [];
$extra_total_val = 0.0;

for ($i = 0; $i < max(count($extra_names), count($extra_amounts)); $i++) {
    $name = isset($extra_names[$i]) ? trim($extra_names[$i]) : '';
    $amt  = isset($extra_amounts[$i]) ? (float)$extra_amounts[$i] : 0.0;
    if ($name !== '' || $amt != 0.0) {
        $extra_charges[] = [
            'name' => $name,
            'amount' => $amt
        ];
        $extra_total_val += $amt;
    }
}
$extra_total = (float)($_POST['extra_total'] ?? $extra_total_val);
if ($extra_total == 0.0) {
    // prefer computed sum if POST omitted or zero
    $extra_total = $extra_total_val;
}
$extra_charges_json = json_encode($extra_charges, JSON_UNESCAPED_UNICODE);

/* -------------------------------------------------------
   CATEGORY SUMMARY FIELDS (24 FIELDS)
--------------------------------------------------------*/
$hotel_adult_total     = (float)($_POST['hotel_adult_total'] ?? 0);
$hotel_child_total     = (float)($_POST['hotel_child_total'] ?? 0);
$hotel_per_adult       = (float)($_POST['hotel_per_adult'] ?? 0);
$hotel_per_child       = (float)($_POST['hotel_per_child'] ?? 0);

$activity_adult_total  = (float)($_POST['activity_adult_total'] ?? 0);
$activity_child_total  = (float)($_POST['activity_child_total'] ?? 0);
$activity_per_adult    = (float)($_POST['activity_per_adult'] ?? 0);
$activity_per_child    = (float)($_POST['activity_per_child'] ?? 0);

$meal_adult_total      = (float)($_POST['meal_adult_total'] ?? 0);
$meal_child_total      = (float)($_POST['meal_child_total'] ?? 0);
$meal_per_adult        = (float)($_POST['meal_per_adult'] ?? 0);
$meal_per_child        = (float)($_POST['meal_per_child'] ?? 0);

$transport_adult_total = (float)($_POST['transport_adult_total'] ?? 0);
$transport_child_total = (float)($_POST['transport_child_total'] ?? 0);
$transport_per_adult   = (float)($_POST['transport_per_adult'] ?? 0);
$transport_per_child   = (float)($_POST['transport_per_child'] ?? 0);

$guide_adult_total     = (float)($_POST['guide_adult_total'] ?? 0);
$guide_child_total     = (float)($_POST['guide_child_total'] ?? 0);
$guide_per_adult       = (float)($_POST['guide_per_adult'] ?? 0);
$guide_per_child       = (float)($_POST['guide_per_child'] ?? 0);

$grand_adult_total     = (float)($_POST['grand_adult_total'] ?? 0);
$grand_child_total     = (float)($_POST['grand_child_total'] ?? 0);
$grand_per_adult       = (float)($_POST['grand_per_adult'] ?? 0);
$grand_per_child       = (float)($_POST['grand_per_child'] ?? 0);

/* -------------------------------------------------------
   USER INFO (NEW)
--------------------------------------------------------*/
$user_id = $_POST['user_id'] ?? 0;
$user_name = $_POST['user_name'] ?? '';


/* -------------------------------------------------------
   QUOTATION JSON (travel plan)
--------------------------------------------------------*/
if (!isset($_POST['quotation_json'])) {
    die("Missing quotation_json");
}
$quotation_json = $_POST['quotation_json'];
$q = json_decode($quotation_json, true);
if (!$q || !isset($q['travel_plan'])) {
    die("Invalid quotation_json: travel_plan missing");
}

/* -------------------------------------------------------
   INSERT MAIN QUOTATION 
   (added extra_total, extra_charges_json, user_id, user_name)
--------------------------------------------------------*/

/*
Total columns before: 44
We add 4 columns: extra_total, extra_charges_json, user_id, user_name
Total placeholders: 48
*/
$stmt = $conn->prepare("
INSERT INTO quotations 
(
    quotation_no, customer_id, country_id, travel_date, departure_date, car_id,
    adults, children, no_bed_child, rooms, nights, days,

    grand_total,
    hotel_total, activity_total, meal_total, guide_total, has_guide,
    transport_total, net_total,

    hotel_adult_total, hotel_child_total, hotel_per_adult, hotel_per_child,
    activity_adult_total, activity_child_total, activity_per_adult, activity_per_child,
    meal_adult_total, meal_child_total, meal_per_adult, meal_per_child,
    transport_adult_total, transport_child_total, transport_per_adult, transport_per_child,
    guide_adult_total, guide_child_total, guide_per_adult, guide_per_child,
    grand_adult_total, grand_child_total, grand_per_adult, grand_per_child,

    extra_total, extra_charges_json, user_id, user_name
)
VALUES (
    ?,?,?,?,?,?,
    ?,?,?,?,?,?,

    ?,?,?,?,? ,?,
    ?,?,

    ?,?,?,?,
    ?,?,?,?,
    ?,?,?,?,
    ?,?,?,?,
    ?,?,?,?,
    ?,?,?,?,

    ?,?,?,?
)
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

// bind all as strings (simpler) but ensure numeric values are casted above
$bind_types = str_repeat("s", 48);

$bind_values = [
    $quotation_no, (string)$customer_id, (string)$country_id, $travel_date, $departure_date, (string)$car_id,
    (string)$adults, (string)$children, (string)$no_bed, (string)$rooms, (string)$nights, (string)$days,

    (string)$grand_total,
    (string)$hotel_total, (string)$activity_total, (string)$meal_total, (string)$guide_total, $has_guide,
    (string)$transport_total, (string)$net_total,

    (string)$hotel_adult_total, (string)$hotel_child_total, (string)$hotel_per_adult, (string)$hotel_per_child,
    (string)$activity_adult_total, (string)$activity_child_total, (string)$activity_per_adult, (string)$activity_per_child,
    (string)$meal_adult_total, (string)$meal_child_total, (string)$meal_per_adult, (string)$meal_per_child,
    (string)$transport_adult_total, (string)$transport_child_total, (string)$transport_per_adult, (string)$transport_per_child,
    (string)$guide_adult_total, (string)$guide_child_total, (string)$guide_per_adult, (string)$guide_per_child,
    (string)$grand_adult_total, (string)$grand_child_total, (string)$grand_per_adult, (string)$grand_per_child,

    (string)$extra_total, $extra_charges_json, (string)$user_id, $user_name
];

// Use call_user_func_array to bind dynamic array
$refs = [];
foreach ($bind_values as $k => $v) {
    $refs[$k] = &$bind_values[$k];
}
array_unshift($refs, $bind_types);
call_user_func_array([$stmt, 'bind_param'], $refs);

if (!$stmt->execute()) {
    // debug info (remove in production)
    die("Insert failed: (" . $stmt->errno . ") " . $stmt->error);
}

$quotation_id = $stmt->insert_id;
$stmt->close();

/* -------------------------------------------------------
   INSERT HOTEL ROWS
--------------------------------------------------------*/
if (!empty($_POST['hotel_city_id'])) {

    $stmtH = $conn->prepare("
        INSERT INTO quotation_hotels
        (quotation_id, city_id, category, hotel_id, room_category_id, stay_nights, price)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmtH) {
        die("Prepare hotel insert failed: " . $conn->error);
    }

    foreach ($_POST['hotel_city_id'] as $i => $cityRaw) {

        $city  = (int)($_POST['hotel_city_id'][$i] ?? 0);
        $cat   = $_POST['hotel_category'][$i] ?? '';
        $hid   = (int)($_POST['hotel_id'][$i] ?? 0);
        $room  = (int)($_POST['room_category_id'][$i] ?? 0);
        $stay  = (int)($_POST['stay_nights'][$i] ?? 0);
        $price = (float)($_POST['hotel_price'][$i] ?? 0);

        $stmtH->bind_param("iisiidd",
            $quotation_id, $city, $cat, $hid, $room, $stay, $price
        );
        $stmtH->execute();
    }
    $stmtH->close();
}

/* -------------------------------------------------------
   TRAVEL PLAN + AUTO GENERATE ITINERARY
--------------------------------------------------------*/

// NEW: Prepare with itinerary_text also
$stmtT = $conn->prepare("
INSERT INTO quotation_travels
(
    quotation_id, day_no, day_date, city_id, pickup_time, pickup_point_id,
    sightseeing_id, car_id, activity_ids, activity_price,
    car_rent_type, car_rent_price, meal_id, meal_price,
    guide, guide_price, itinerary_text
)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");

if (!$stmtT) {
    die("Prepare travel insert failed: " . $conn->error);
}

foreach ($q['travel_plan'] as $i => $t) {

    $day = $i + 1;

    /* -----------------------------------------
        AUTO BUILD ITINERARY FOR THIS DAY
    ------------------------------------------*/

    // DATE
    $date_raw = $t['date'] ?? "";
    $formatted_date = $date_raw ? date("d-m-Y", strtotime($date_raw)) : "";

    // CITY NAME
    $city_id = $t['city'] ?? 0;
    $city_name = "";
    if ($city_id) {
        $res = $conn->query("SELECT name FROM cities WHERE id = $city_id");
        if ($r = $res->fetch_assoc()) $city_name = $r['name'];
    }

    // SIGHTSEEING NAME
    $sight_id = $t['sightseeing'] ?? 0;
    $sight_name = "";
    if ($sight_id) {
        $res = $conn->query("SELECT name FROM sightseeings WHERE id = $sight_id");
        if ($r = $res->fetch_assoc()) $sight_name = $r['name'];
    }

    // Title
    $parts = [];
    if ($city_name) $parts[] = $city_name;
    if ($sight_name) $parts[] = $sight_name;
    $title = implode(" - ", $parts);
    if ($title == "") $title = "Free Day / Leisure";

    // Paragraph
    $paragraph = "";
    if ($sight_name != "") {
        $paragraph .= "Breakfast at Hotel.\n";
        $paragraph .= "Our driver will pick you up and proceed for $sight_name.\n";
        $paragraph .= "Enjoy the sightseeing and activities as per itinerary.\n\n";
    } else {
        $paragraph .= "Breakfast at Hotel.\n";
        $paragraph .= "Day free for leisure or optional activities.\n\n";
    }

    $overnight = $city_name ? "Overnight at $city_name." : "Overnight at Hotel.";

    // Final itinerary text
    $itinerary_text =
        "Day $day | ($formatted_date) : $title\n\n" .
        $paragraph .
        "$overnight\n";

    /* -----------------------------------------
        BIND AND INSERT TRAVEL ROW
    ------------------------------------------*/

    $pickup_time  = $t['pickup_time'] ?? null;
    $pickup_point = $t['pickup_point'] ?? null;
    $sightseeing  = $t['sightseeing'] ?? null;
    $car_main     = $q['car_id'] ?? 0;

    $activity_ids   = is_array($t['activity']) ? implode(",", $t['activity']) : "";
    $activity_price = (float)($t['activity_price'] ?? 0);

    $car_rent_type  = $t['car_rent'] ?? "";
    $car_rent_price = (float)($t['car_rent_price'] ?? 0);

    $meal_id    = $t['meal'] ?? null;
    $meal_price = (float)($t['meal_price'] ?? 0);

    $guide       = $t['guide'] ?? "No";
    $guide_price = (float)($t['guide_price'] ?? 0);

    $stmtT->bind_param(
        "iisisiiisdsdidsds",
        $quotation_id,
        $day,
        $date_raw,
        $city_id,
        $pickup_time,
        $pickup_point,
        $sightseeing,
        $car_main,
        $activity_ids,
        $activity_price,
        $car_rent_type,
        $car_rent_price,
        $meal_id,
        $meal_price,
        $guide,
        $guide_price,
        $itinerary_text   // <-- NEW
    );

    $stmtT->execute();
}

$stmtT->close();

/* -------------------------------------------------------
   SAVE COST SUMMARY JSON
--------------------------------------------------------*/
$cost_summary = [
    "hotel" => [
        "total" => $hotel_total,
        "adult_total" => $hotel_adult_total,
        "child_total" => $hotel_child_total,
        "per_adult" => $hotel_per_adult,
        "per_child" => $hotel_per_child
    ],
    "activity" => [
        "total" => $activity_total,
        "adult_total" => $activity_adult_total,
        "child_total" => $activity_child_total,
        "per_adult" => $activity_per_adult,
        "per_child" => $activity_per_child
    ],
    "meal" => [
        "total" => $meal_total,
        "adult_total" => $meal_adult_total,
        "child_total" => $meal_child_total,
        "per_adult" => $meal_per_adult,
        "per_child" => $meal_per_child
    ],
    "transport" => [
        "total" => $transport_total,
        "adult_total" => $transport_adult_total,
        "child_total" => $transport_child_total,
        "per_adult" => $transport_per_adult,
        "per_child" => $transport_per_child
    ],
    "guide" => [
        "total" => $guide_total,
        "adult_total" => $guide_adult_total,
        "child_total" => $guide_child_total,
        "per_adult" => $guide_per_adult,
        "per_child" => $guide_per_child
    ],
    "grand" => [
        "adult_total" => $grand_adult_total,
        "child_total" => $grand_child_total,
        "per_adult" => $grand_per_adult,
        "per_child" => $grand_per_child,
        "grand_total" => $grand_total + $extra_total // include extra charges for final reporting
    ],
    "extra" => [
        "total" => $extra_total,
        "items" => $extra_charges
    ]
];

$json = json_encode($cost_summary);

$u = $conn->prepare("UPDATE quotations SET cost_summary = ? WHERE id = ?");
if (!$u) {
    error_log("Prepare update cost_summary failed: " . $conn->error);
} else {
    $u->bind_param("si", $json, $quotation_id);
    $u->execute();
    $u->close();
}

/* -------------------------------------------------------
   UPDATE QUOTATION RECORD to include extra_total and extra json fields
   (in case other processes read them separately)
--------------------------------------------------------*/
$up = $conn->prepare("UPDATE quotations SET extra_total = ?, extra_charges_json = ?, user_id = ?, user_name = ? WHERE id = ?");
if ($up) {
    $up->bind_param("ssiss", $extra_total, $extra_charges_json, $user_id, $user_name, $quotation_id);
    $up->execute();
    $up->close();
} else {
    error_log("Prepare update extra/user failed: " . $conn->error);
}

echo "✅ Quotation Saved Successfully (ID: $quotation_id)";
?>
