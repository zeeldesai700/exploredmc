<?php
session_start();
require_once __DIR__ . '/../../config/db_pdo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request');
}

$payload = json_decode($_POST['quotation_json'], true);
if (!$payload || empty($payload['totals'])) {
    die('Invalid quotation data');
}

$t = $payload['totals'];

try {
    $pdo->beginTransaction();

/* BASIC */
$data = [
    'user_id'      => $_SESSION['user_id'] ?? null,
    'user_name'    => $_SESSION['user_name'] ?? null,
    'customer_id'  => (int)$_POST['customer_id'],
    'country_id'   => (int)$_POST['country_id'],
    'travel_date'  => $_POST['travel_date'],
    'departure_date'=> $_POST['departure_date'],
    'car_id'       => (int)$_POST['car_id'],
    'adults'       => (int)$_POST['adults'],
    'extra_adults' => (int)$_POST['extra_adults'],
    'children'     => (int)$_POST['children'],
    'infants'      => (int)($_POST['infants'] ?? 0),
    'no_bed_child' => (int)$_POST['no_bed_child'],
    'rooms'        => (int)$_POST['rooms'],
    'nights'       => (int)$_POST['nights'],
    'days'         => (int)$_POST['days'],
    'status'       => 'Draft',
    'currency'     => $_POST['currency'] ?? '$',

    /* HOTEL */
    'hotel_total' => $t['hotel']['total'],
    'hotel_per_adult' => $t['hotel']['per_adult'],
    'hotel_per_extra_adult' => $t['hotel']['per_extra_adult'],
    'hotel_per_child' => $t['hotel']['per_child'],
    'hotel_per_child_no_bed' => $t['hotel']['per_child_no_bed'],

    /* ACTIVITY */
    'activity_total' => $t['activity']['total'],
    'activity_per_adult' => $t['activity']['per_adult'],
    'activity_per_extra_adult' => $t['activity']['per_extra_adult'],
    'activity_per_child' => $t['activity']['per_child'],
    'activity_per_child_no_bed' => $t['activity']['per_child_no_bed'],

    /* MEAL */
    'meal_total' => $t['meal']['total'],
    'meal_per_adult' => $t['meal']['per_adult'],
    'meal_per_extra_adult' => $t['meal']['per_extra_adult'],
    'meal_per_child' => $t['meal']['per_child'],
    'meal_per_child_no_bed' => $t['meal']['per_child_no_bed'],

    /* TRANSPORT */
    'transport_total' => $t['transport']['total'],
    'transport_per_adult' => $t['transport']['per_adult'],
    'transport_per_extra_adult' => $t['transport']['per_extra_adult'],
    'transport_per_child' => $t['transport']['per_child'],
    'transport_per_child_no_bed' => $t['transport']['per_child_no_bed'],

    /* GUIDE */
    'guide_total' => $t['guide']['total'],
    'guide_per_adult' => $t['guide']['per_adult'],
    'guide_per_extra_adult' => $t['guide']['per_extra_adult'],
    'guide_per_child' => $t['guide']['per_child'],
    'guide_per_child_no_bed' => $t['guide']['per_child_no_bed'],

    /* VISA & TIP */
    'visa_total' => $t['visa']['total'],
    'visa_per_adult' => $t['visa']['per_adult'],
    'visa_per_extra_adult' => $t['visa']['per_extra_adult'],
    'visa_per_child' => $t['visa']['per_child'],
    'visa_per_child_no_bed' => $t['visa']['per_child_no_bed'],

    'tip_total'  => $t['tip']['total'],
    'tip_per_adult' => $t['tip']['per_adult'],
    'tip_per_extra_adult' => $t['tip']['per_extra_adult'],
    'tip_per_child' => $t['tip']['per_child'],
    'tip_per_child_no_bed' => $t['tip']['per_child_no_bed'],

    /* EXTRA */
    'extra_charges_json' => json_encode($payload['extra_charges'] ?? []),
    'extra_total' => (float)($payload['extra_total'] ?? 0),
    'has_guide' => ($t['guide']['total'] > 0 ? 'Yes' : 'No'),

    /* GRAND */
    'grand_total' => $t['grand']['total'],
    'grand_per_adult' => $t['grand']['per_adult'],
    'grand_per_extra_adult' => $t['grand']['per_extra_adult'],
    'grand_per_child' => $t['grand']['per_child'],
    'grand_per_child_no_bed' => $t['grand']['per_child_no_bed'],
];

/* BUILD SQL */
$cols = implode(',', array_keys($data));
$vals = ':' . implode(',:', array_keys($data));

$sql = $pdo->prepare("INSERT INTO quotations ($cols) VALUES ($vals)");
$sql->execute($data);

$quotation_id = $pdo->lastInsertId();

$quotation_no = '#EV-' . (10000 + (int)$quotation_id);

$upd = $pdo->prepare("
    UPDATE quotations
    SET quotation_no = :quotation_no
    WHERE id = :id
");

$upd->execute([
    ':quotation_no' => $quotation_no,
    ':id'           => $quotation_id
]);

/* ================= SAVE HOTELS (OPTION-WISE – FINAL & CORRECT) ================= */

if (!empty($_POST['hotel']) && is_array($_POST['hotel'])) {

    // 1️⃣ delete old hotels for this quotation
    $pdo->prepare(
        "DELETE FROM quotation_hotels WHERE quotation_id = ?"
    )->execute([$quotation_id]);

    // 2️⃣ prepare insert
    $stmt = $pdo->prepare("
        INSERT INTO quotation_hotels
        (
            quotation_id,
            option_no,
            city_id,
            category,
            hotel_id,
            room_category_id,
            stay_nights,
            rooms,
            base_price,
            extra_adult_price,
            child_price,
            nobed_price,
            price
        )
        VALUES
        (
            :quotation_id,
            :option_no,
            :city_id,
            :category,
            :hotel_id,
            :room_category_id,
            :stay_nights,
            :rooms,
            :base_price,
            :extra_adult_price,
            :child_price,
            :nobed_price,
            :price
        )
    ");

    // 3️⃣ loop options (hotel[1], hotel[2], ...)
    foreach ($_POST['hotel'] as $optionNo => $rows) {

        // safety check
        if (empty($rows['city_id']) || !is_array($rows['city_id'])) {
            continue;
        }

        // 4️⃣ loop rows inside option
        foreach ($rows['city_id'] as $i => $city_id) {

            if (!$city_id) continue;

            $stmt->execute([
                ':quotation_id'      => $quotation_id,
                ':option_no'         => (int)$optionNo,

                ':city_id'           => (int)$city_id,
                ':category'          => $rows['category'][$i] ?? '',

                ':hotel_id'          => (int)($rows['hotel_id'][$i] ?? 0),
                ':room_category_id'  => (int)($rows['room_category_id'][$i] ?? 0),

                ':stay_nights'       => (int)($rows['stay_nights'][$i] ?? 1),
                ':rooms'             => (int)($_POST['rooms'] ?? 1),

                // ✅ unit prices (from hidden inputs filled by JS)
                ':base_price'        => (float)($rows['base_price'][$i] ?? 0),
                ':extra_adult_price' => (float)($rows['extra_adult_price'][$i] ?? 0),
                ':child_price'       => (float)($rows['child_price'][$i] ?? 0),
                ':nobed_price'       => (float)($rows['nobed_price'][$i] ?? 0),

                // ✅ total hotel row price
                ':price'             => (float)($rows['price'][$i] ?? 0),
            ]);
        }
    }
}


/* ================= SAVE TRAVEL PLAN + AUTO ITINERARY ================= */
if (!empty($payload['travels']) && is_array($payload['travels'])) {

    /* ---------- DELETE OLD TRAVELS ---------- */
    $pdo->prepare(
        "DELETE FROM quotation_travels WHERE quotation_id = ?"
    )->execute([$quotation_id]);

    /* ---------- DELETE OLD MULTI CARS ---------- */
    $pdo->prepare("
        DELETE c
        FROM quotation_travel_cars c
        JOIN quotation_travels t ON t.id = c.quotation_travel_id
        WHERE t.quotation_id = ?
    ")->execute([$quotation_id]);

    /* ---------- TRAVEL INSERT ---------- */
    $stmtT = $pdo->prepare("
        INSERT INTO quotation_travels (
            quotation_id,
            day_no,
            day_date,
            city_id,
            pickup_time,
            pickup_point_id,
            sightseeing_id,
            car_id,
            activity_price,
            car_rent_type,
            car_rent_price,
            meal_id,
            meal_price,
            guide,
            guide_price,
            itinerary_text
        ) VALUES (
            :quotation_id,
            :day_no,
            :day_date,
            :city_id,
            :pickup_time,
            :pickup_point_id,
            :sightseeing_id,
            :car_id,
            :activity_price,
            :car_rent_type,
            :car_rent_price,
            :meal_id,
            :meal_price,
            :guide,
            :guide_price,
            :itinerary_text
        )
    ");

    /* ---------- ACTIVITY INSERT ---------- */
    $stmtA = $pdo->prepare("
        INSERT INTO quotation_travel_activities
        (quotation_travel_id, activity_id, activity_price)
        VALUES (?, ?, ?)
    ");

    /* ---------- MULTI CAR INSERT ---------- */
    $stmtC = $pdo->prepare("
        INSERT INTO quotation_travel_cars
        (quotation_travel_id, car_id, car_rent_type, car_price)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($payload['travels'] as $tr) {

        $day = (int)$tr['day_no'];
        $date_raw = $tr['day_date'] ?? null;
        $formatted_date = $date_raw ? date("d-m-Y", strtotime($date_raw)) : "";

        /* ---------- CITY ---------- */
        $city_name = "";
        if (!empty($tr['city_id'])) {
            $q = $pdo->prepare("SELECT name FROM cities WHERE id = ?");
            $q->execute([$tr['city_id']]);
            $city_name = $q->fetchColumn() ?: "";
        }

        /* ---------- SIGHTSEEING ---------- */
        $sight_name = "";
        $sight_itinerary = "";

        if (!empty($tr['sightseeing_id'])) {
            $q = $pdo->prepare("
                SELECT name, itinerary
                FROM sightseeings
                WHERE id = ?
            ");
            $q->execute([$tr['sightseeing_id']]);
            $row = $q->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $sight_name = trim($row['name']);
                $sight_itinerary = trim($row['itinerary']);
            }
        }

        $title = trim($city_name . ($sight_name ? " - $sight_name" : ""));
        if (!$title) $title = "Free Day / Leisure";

        /* ---------- ITINERARY ---------- */
        if ($sight_itinerary) {
            $itinerary_text =
                "Day $day | ($formatted_date) : $title\n\n" .
                $sight_itinerary . "\n\n";
        } else {
            $itinerary_text =
                "Day $day | ($formatted_date) : $title\n\n" .
                "Breakfast at Hotel.\n" .
                "Day free for leisure or optional activities.\n\n";
        }

        /* ---------- INSERT TRAVEL (NO CAR HERE) ---------- */
        $stmtT->execute([
            ':quotation_id'    => $quotation_id,
            ':day_no'          => $day,
            ':day_date'        => $date_raw,
            ':city_id'         => $tr['city_id'] ?? null,
            ':pickup_time'     => $tr['pickup_time'] ?? null,
            ':pickup_point_id' => $tr['pickup_point_id'] ?? null,
            ':sightseeing_id'  => $tr['sightseeing_id'] ?? null,

            // 🔥 MULTI CAR → NULL
            ':car_id'          => null,
            ':car_rent_type'   => null,

            ':activity_price'  => (float)($tr['activity_price'] ?? 0),
            ':car_rent_price'  => (float)($tr['car_rent_price'] ?? 0),

            ':meal_id'         => $tr['meal_id'] ?? null,
            ':meal_price'      => (float)($tr['meal_price'] ?? 0),
            ':guide'           => $tr['guide'] ?? 'No',
            ':guide_price'     => (float)($tr['guide_price'] ?? 0),
            ':itinerary_text'  => $itinerary_text
        ]);

        $travel_id = $pdo->lastInsertId();

        /* ---------- INSERT ACTIVITIES ---------- */
        if (!empty($tr['activity_ids'])) {
            foreach ($tr['activity_ids'] as $aid) {
                $stmtA->execute([
                    $travel_id,
                    (int)$aid,
                    (float)($tr['activity_price'] ?? 0)
                ]);
            }
        }

       
       /* ---------- INSERT MULTI CARS ---------- */
if (!empty($tr['cars']) && is_array($tr['cars'])) {
    foreach ($tr['cars'] as $car) {

        if (empty($car['id'])) continue;

        // ✅ FINAL PRICE NORMALIZATION (price OR rate)
        $carPrice = 0;

        if (isset($car['price']) && $car['price'] !== '') {
            $carPrice = (float) str_replace(',', '', $car['price']);
        } elseif (isset($car['rate']) && $car['rate'] !== '') {
            $carPrice = (float) str_replace(',', '', $car['rate']);
        }

        $stmtC->execute([
            $travel_id,
            (int)$car['id'],
            $car['mode'] ?? null,
            $carPrice
        ]);
    }
}

   }
}


    $pdo->commit();

    header("Location: quotation_view.php?id=" . $quotation_id);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("Save failed: " . $e->getMessage());
}