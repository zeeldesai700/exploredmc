<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

/* =====================================================
   BASIC VALIDATION
===================================================== */
$id = (int)($_POST['id'] ?? 0);
$json = $_POST['quotation_json'] ?? '';

if ($id <= 0 || $json === '') {
    die("Invalid request");
}

$data = json_decode($json, true);
if (!is_array($data)) {
    die("Invalid JSON data");
}

/* =====================================================
   START TRANSACTION
===================================================== */
$conn->begin_transaction();

try {

    /* =====================================================
       UPDATE MAIN QUOTATION
    ===================================================== */
    $stmt = $conn->prepare("
        UPDATE quotations SET
            customer_id = ?,
            country_id = ?,
            travel_date = ?,
            departure_date = ?,
            car_id = ?,
            adults = ?,
            extra_adults = ?,
            children = ?,
            infants = ?,
            no_bed_child = ?,
            rooms = ?,
            nights = ?,
            days = ?,

            hotel_total = ?,
            hotel_per_adult = ?,
            hotel_per_extra_adult = ?,
            hotel_per_child = ?,
            hotel_per_child_no_bed = ?,

            activity_total = ?,
            activity_per_adult = ?,
            activity_per_extra_adult = ?,
            activity_per_child = ?,
            activity_per_child_no_bed = ?,

            meal_total = ?,
            meal_per_adult = ?,
            meal_per_extra_adult = ?,
            meal_per_child = ?,
            meal_per_child_no_bed = ?,

            transport_total = ?,
            transport_per_adult = ?,
            transport_per_extra_adult = ?,
            transport_per_child = ?,
            transport_per_child_no_bed = ?,

            guide_total = ?,
            guide_per_adult = ?,
            guide_per_extra_adult = ?,
            guide_per_child = ?,
            guide_per_child_no_bed = ?,

            visa_total = ?,
            visa_per_adult = ?,
            visa_per_extra_adult = ?,
            visa_per_child = ?,
            visa_per_child_no_bed = ?,

            tip_total = ?,
            tip_per_adult = ?,
            tip_per_extra_adult = ?,
            tip_per_child = ?,
            tip_per_child_no_bed = ?,

            grand_total = ?,
            grand_per_adult = ?,
            grand_per_extra_adult = ?,
            grand_per_child = ?,
            grand_per_child_no_bed = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "iiissiiiiiiiddddddddddddddddddddddddddddddddddddi",
        $_POST['customer_id'],
        $_POST['country_id'],
        $_POST['travel_date'],
        $_POST['departure_date'],
        $_POST['car_id'],
        $_POST['adults'],
        $_POST['extra_adults'],
        $_POST['children'],
        $_POST['infants'],
        $_POST['no_bed_child'],
        $_POST['rooms'],
        $_POST['nights'],
        $_POST['days'],

        $_POST['hotel_total'],
        $_POST['hotel_per_adult'],
        $_POST['hotel_per_extra_adult'],
        $_POST['hotel_per_child'],
        $_POST['hotel_per_child_no_bed'],

        $_POST['activity_total'],
        $_POST['activity_per_adult'],
        $_POST['activity_per_extra_adult'],
        $_POST['activity_per_child'],
        $_POST['activity_per_child_no_bed'],

        $_POST['meal_total'],
        $_POST['meal_per_adult'],
        $_POST['meal_per_extra_adult'],
        $_POST['meal_per_child'],
        $_POST['meal_per_child_no_bed'],

        $_POST['transport_total'],
        $_POST['transport_per_adult'],
        $_POST['transport_per_extra_adult'],
        $_POST['transport_per_child'],
        $_POST['transport_per_child_no_bed'],

        $_POST['guide_total'],
        $_POST['guide_per_adult'],
        $_POST['guide_per_extra_adult'],
        $_POST['guide_per_child'],
        $_POST['guide_per_child_no_bed'],

        $_POST['visa_total'],
        $_POST['visa_per_adult'],
        $_POST['visa_per_extra_adult'],
        $_POST['visa_per_child'],
        $_POST['visa_per_child_no_bed'],

        $_POST['tip_total'],
        $_POST['tip_per_adult'],
        $_POST['tip_per_extra_adult'],
        $_POST['tip_per_child'],
        $_POST['tip_per_child_no_bed'],

        $_POST['grand_total'],
        $_POST['grand_per_adult'],
        $_POST['grand_per_extra_adult'],
        $_POST['grand_per_child'],
        $_POST['grand_per_child_no_bed'],

        $id
    );
    $stmt->execute();
    $stmt->close();

    /* =====================================================
       DELETE OLD CHILD DATA
    ===================================================== */
    $conn->query("DELETE FROM quotation_hotels WHERE quotation_id = $id");
    $conn->query("DELETE FROM quotation_travel_activities WHERE quotation_travel_id IN (
        SELECT id FROM quotation_travels WHERE quotation_id = $id
    )");
    $conn->query("DELETE FROM quotation_travels WHERE quotation_id = $id");

    /* =====================================================
       INSERT HOTELS
    ===================================================== */
    if (!empty($data['hotels'])) {
        $stmtH = $conn->prepare("
            INSERT INTO quotation_hotels
            (quotation_id, city_id, category_id, hotel_id, room_category_id, stay_nights, price)
            VALUES (?,?,?,?,?,?,?)
        ");

        foreach ($data['hotels'] as $h) {
            $stmtH->bind_param(
                "iiiiiid",
                $id,
                $h['city_id'],
                $h['category_id'],
                $h['hotel_id'],
                $h['room_category_id'],
                $h['stay_nights'],
                $h['price']
            );
            $stmtH->execute();
        }
        $stmtH->close();
    }

    /* =====================================================
       INSERT TRAVELS + ACTIVITIES
    ===================================================== */
    if (!empty($data['travels'])) {
        $stmtT = $conn->prepare("
            INSERT INTO quotation_travels
            (
                quotation_id, day_no, day_date, city_id,
                pickup_time, pickup_point_id, sightseeing_id,
                car_id, car_rent_type, car_rent_price,
                meal_id, meal_price
            )
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        $stmtA = $conn->prepare("
            INSERT INTO quotation_travel_activities
            (quotation_travel_id, activity_id, activity_price)
            VALUES (?,?,?)
        ");

        foreach ($data['travels'] as $i => $t) {

            $dayNo = $i + 1;

            $stmtT->bind_param(
                "iisssiiiisdd",
                $id,
                $dayNo,
                $t['day_date'],
                $t['city_id'],
                $t['pickup_time'],
                $t['pickup_point_id'],
                $t['sightseeing_id'],
                $t['car_id'],
                $t['car_rent_type'],
                $t['car_rent_price'],
                $t['meal_id'],
                $t['meal_price']
            );
            $stmtT->execute();

            $travelId = $stmtT->insert_id;

            if (!empty($t['activity_ids'])) {
                foreach ($t['activity_ids'] as $aid) {
                    $stmtA->bind_param(
                        "iid",
                        $travelId,
                        $aid,
                        $t['activity_price']
                    );
                    $stmtA->execute();
                }
            }
        }

        $stmtT->close();
        $stmtA->close();
    }

    /* =====================================================
       COMMIT
    ===================================================== */
    $conn->commit();

    echo "SUCCESS";

} catch (Exception $e) {

    $conn->rollback();
    http_response_code(500);
    echo "ERROR: " . $e->getMessage();
}
