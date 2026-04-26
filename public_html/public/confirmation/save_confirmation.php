<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

/* =========================
   VALIDATION
========================= */
$quotation_id = (int)($_POST['quotation_id'] ?? 0);
if ($quotation_id <= 0) die("Invalid quotation");

$hotel_option = $_POST['hotel_option'] ?? '';
$selectedOption = is_numeric($hotel_option) ? (int)$hotel_option : null;

/* =========================
   BASIC DATA
========================= */
$name   = trim($_POST['passenger_name'] ?? '');
$mobile = trim($_POST['passenger_mobile'] ?? '');

/* =========================
   TOTAL QUOTATION PRICE ✅
========================= */
$totalQuotationPrice = (float)($_POST['total_quotation_price'] ?? 0);

/* =========================
   CHECK / INSERT CONFIRMATION
========================= */
$check = $conn->prepare("SELECT id, confirmation_no FROM confirmations WHERE quotation_id=?");
$check->bind_param("i", $quotation_id);
$check->execute();
$res = $check->get_result();

if ($row = $res->fetch_assoc()) {

    $confirmation_id = (int)$row['id'];
    $confirmation_no = $row['confirmation_no'];

    $upd = $conn->prepare("
    UPDATE confirmations
    SET passenger_name=?,
        passenger_mobile=?,
        hotel_option=?
    WHERE id=?
");
$upd->bind_param("sssi", $name, $mobile, $hotel_option,  $confirmation_id);
    $upd->execute();

} else {

    $ins = $conn->prepare("
    INSERT INTO confirmations
    (quotation_id, passenger_name, passenger_mobile, hotel_option)
    VALUES (?, ?, ?, ?)
");
$ins->bind_param("isss", $quotation_id, $name, $mobile, $hotel_option);
    $ins->execute();

    $confirmation_id = $ins->insert_id;
    $confirmation_no = 'EV-' . str_pad($confirmation_id, 3, '0', STR_PAD_LEFT);

    $updNo = $conn->prepare("UPDATE confirmations SET confirmation_no=? WHERE id=?");
    $updNo->bind_param("si", $confirmation_no, $confirmation_id);
    $updNo->execute();
}



/* =========================
   RESET HOTEL DATA
========================= */
$conn->query("DELETE FROM confirmations_hotels WHERE confirmation_id=$confirmation_id");

/* =========================
   SAVE HOTELS (MANUAL)
========================= */
if ($hotel_option === 'manual' && !empty($_POST['manual_hotels'])) {

    foreach ($_POST['manual_hotels'] as $h) {

        $city   = trim($h['city'] ?? '');
        $hotel  = trim($h['hotel_name'] ?? '');
        $conf   = trim($h['hotel_confirmation_no'] ?? '');
        $cat    = trim($h['hotel_category'] ?? '');
        $room   = trim($h['room_category'] ?? '');
        $nights = (int)($h['stay_nights'] ?? 0);

        if ($city === '' || $hotel === '' || $nights <= 0) continue;

        $stmt = $conn->prepare("
            INSERT INTO confirmations_hotels
            (confirmation_id, option_no, city_name, hotel_name,
             hotel_confirmation_no, category, room_category,
             stay_nights, rooms)
            VALUES (?,0,?,?,?,?,?, ?,1)
        ");
        $stmt->bind_param(
            "isssssi",
            $confirmation_id,
            $city,
            $hotel,
            $conf,
            $cat,
            $room,
            $nights
        );
        $stmt->execute();
    }
}

/* =========================
   SAVE HOTELS (OPTION)
========================= */
if ($selectedOption !== null && !empty($_POST['hotels'][$selectedOption])) {

    foreach ($_POST['hotels'][$selectedOption] as $h) {

        $city     = trim($h['city_name'] ?? '');
        $hotel    = trim($h['hotel_name'] ?? '');
        $conf     = trim($h['hotel_confirmation_no'] ?? '');
        $cat      = trim($h['hotel_category'] ?? '');
        $room     = trim($h['room_category'] ?? '');
        $nights   = (int)($h['stay_nights'] ?? 0);
        $rooms    = (int)($h['rooms'] ?? 1);
        $due_date = !empty($h['due_date']) ? $h['due_date'] : null; // ✅ FIX
        $payment  = (float)($h['payment_amount'] ?? 0);

        if ($city === '' || $hotel === '' || $nights <= 0) {
            continue;
        }

        $stmt = $conn->prepare("
    INSERT INTO confirmations_hotels
    (
      confirmation_id,
      option_no,
      city_name,
      hotel_name,
      hotel_confirmation_no,
      category,
      room_category,
      stay_nights,
      rooms,
      due_date,
      payment_amount,
      paid_amount,
      remaining_amount,
      payment_status
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'pending')
");

$remaining = $payment; // ✅ remaining = total at start

$stmt->bind_param(
    "iisssssiisdd",
    $confirmation_id,
    $selectedOption,
    $city,
    $hotel,
    $conf,
    $cat,
    $room,
    $nights,
    $rooms,
    $due_date,
    $payment,
    $remaining
);

$stmt->execute();
    }
}

$cust = $conn->prepare("
    SELECT c.name AS agent_name
    FROM quotations q
    LEFT JOIN customers c ON q.customer_id = c.id
    WHERE q.id = ?
");

$cust->bind_param("i", $quotation_id);
$cust->execute();

$result = $cust->get_result();
$row = $result->fetch_assoc();

$agentName = $row['agent_name'] ?? 'Customer';

$cust->close();

/* =========================
   RESET TRAVEL
========================= */
$conn->query("DELETE FROM confirmations_travels WHERE confirmation_id=$confirmation_id");
$conn->query("DELETE FROM confirmation_guide WHERE confirmation_no='$confirmation_no'");
/* =========================
   SAVE TRAVEL DETAILS
========================= */
if (!empty($_POST['travel'])) {

foreach ($_POST['travel'] as $t) {

$car = strtolower($t['car'] ?? 'no');

$stmt = $conn->prepare("
INSERT INTO confirmations_travels
(
confirmation_id,
city_name,
travel_date,
flight_name,
pickup_time,
pickup_point,
sightseeing,
meal,
guide,
car
)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
"isssssssss",
$confirmation_id,
$t['city_name'],
$t['day_date'],
$t['flight_name'],
$t['pickup_time'],
$t['pickup_point'],
$t['sightseeing_name'],
$t['meal'],
$t['guide'],
$car
);

$stmt->execute();

        /* =========================
           SAVE GUIDE ENTRY
        ========================== */

       $guideValue = strtolower(trim($t['guide'] ?? 'no'));

if ($guideValue === 'yes') {

    $city = $t['city_name'];

    $userName = $_SESSION['user_name']
             ?? $_SESSION['username']
             ?? $_SESSION['name']
             ?? 'System';

    $guideStmt = $conn->prepare("
        INSERT INTO confirmation_guide
        (
            confirmation_id,
            confirmation_no,
            agent_name,
            user_name,
            city_name,
            guide_date,
            car,
            guide
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $guideStmt->bind_param(
        "isssssss",
        $confirmation_id,
        $confirmation_no,
        $agentName,
        $userName,
        $city,
        $t['day_date'],
        $car,
        $guideValue
    );

    $guideStmt->execute();
    $guideStmt->close();
}
        $stmt->close();
    }
}

/* =========================
   SAVE CHILD AGES ✅
========================= */
$conn->query("DELETE FROM confirmation_child_ages WHERE confirmation_id=$confirmation_id");

if (!empty($_POST['child_ages'])) {
    $stmt = $conn->prepare("
        INSERT INTO confirmation_child_ages (confirmation_id, child_age)
        VALUES (?, ?)
    ");
    foreach ($_POST['child_ages'] as $age) {
        $age = (int)$age;
        $stmt->bind_param("ii", $confirmation_id, $age);
        $stmt->execute();
    }
    $stmt->close();
}

/* =========================
   SAVE INFANT AGES ✅
========================= */
$conn->query("DELETE FROM confirmation_infant_ages WHERE confirmation_id=$confirmation_id");

if (!empty($_POST['infant_ages'])) {
    $stmt = $conn->prepare("
        INSERT INTO confirmation_infant_ages (confirmation_id, infant_age)
        VALUES (?, ?)
    ");
    foreach ($_POST['infant_ages'] as $age) {
        $age = (int)$age;
        $stmt->bind_param("ii", $confirmation_id, $age);
        $stmt->execute();
    }
    $stmt->close();
}

/* =========================
   CALCULATE HOTEL PRICE
========================= */
$hotelPrice = 0;
if ($selectedOption !== null) {
    $stmt = $conn->prepare("
        SELECT SUM(
            IFNULL(base_price,0)+
            IFNULL(extra_adult_price,0)+
            IFNULL(child_price,0)+
            IFNULL(nobed_price,0)
        ) AS total
        FROM quotation_hotels
        WHERE quotation_id=? AND option_no=?
    ");
    $stmt->bind_param("ii", $quotation_id, $selectedOption);
    $stmt->execute();
    $hotelPrice = (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
}

/* =========================
   CALCULATE TRAVEL PRICE
========================= */
$stmt = $conn->prepare("
    SELECT
        IFNULL(activity_total,0)+
        IFNULL(meal_total,0)+
        IFNULL(transport_total,0)+
        IFNULL(guide_total,0)+
        IFNULL(visa_total,0)+
        IFNULL(tip_total,0) AS total
    FROM quotations WHERE id=?
");
$stmt->bind_param("i", $quotation_id);
$stmt->execute();
$travelPrice = (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);

/* =========================
   UPDATE CONFIRMATION PRICES
========================= */
$upd = $conn->prepare("
    UPDATE confirmations
    SET hotel_price=?, travel_price=?
    WHERE id=?
");
$upd->bind_param("ddi", $hotelPrice, $travelPrice, $confirmation_id);
$upd->execute();

/* =========================
   AGENT ACCOUNT
========================= */
$totalAmount = $hotelPrice + $travelPrice;

$cust = $conn->prepare("
    SELECT c.name FROM quotations q
    LEFT JOIN customers c ON q.customer_id=c.id
    WHERE q.id=?
");
$cust->bind_param("i", $quotation_id);
$cust->execute();
$agentName = $cust->get_result()->fetch_assoc()['name'] ?? 'Customer';

$createdBy = $_SESSION['user_name']
          ?? $_SESSION['username']
          ?? $_SESSION['name']
          ?? 'System';

$stmt = $conn->prepare("
    INSERT INTO agent_accounts
    (agent_name, created_by, confirmation_no, confirmation_date, amount, total_quotation_price)
    VALUES (?, ?, ?, CURDATE(), ?,?)
");
$stmt->bind_param("sssdd", $agentName, $createdBy, $confirmation_no, $totalAmount, $totalQuotationPrice);
$stmt->execute();

/* =========================
   REDIRECT
========================= */
header("Location: confirmations_list.php");
exit;