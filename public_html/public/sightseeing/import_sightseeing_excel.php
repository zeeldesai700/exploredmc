<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_FILES['excel_file']['tmp_name'])) {
    header("Location: add_sightseeing.php?msg=No file uploaded&type=error");
    exit;
}

try {

    $conn->begin_transaction();

    $file = $_FILES['excel_file']['tmp_name'];
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    $sightseeingMap = [];

    /* COUNTERS */
    $sightseeingCount = 0;
    $activityCount    = 0;
    $carCount         = 0;

    /* SKIP TRACKING */
    $skippedCount = 0;
    $skippedRows  = [];

    foreach ($rows as $index => $row) {

        $excelRowNo = $index + 1;

        // Skip header
        if ($index === 0) {
            continue;
        }

        // Expect 14 columns (UPDATED)
        if (count($row) < 14) {
            $skippedCount++;
            $skippedRows[] = $excelRowNo;
            continue;
        }

        /* ✅ UPDATED COLUMN MAPPING */
        list(
            $name,             // A
            $country_id,       // B
            $city_id,          // C
            $pickup_point_id,  // D
            $guide_rate,       // E
            $activity_name,    // F
            $adult_price,      // G
            $child_price,      // H
            $car_id,           // I
            $start_date,       // J ✅
            $end_date,         // K ✅
            $half_day,         // L
            $full_day,         // M
            $itinerary         // N
        ) = array_pad($row, 14, null);

        /* 🔴 Mandatory validation */
        if (
            empty($name) ||
            empty($country_id) ||
            empty($city_id) ||
            empty($car_id) ||
            empty($start_date) ||
            empty($end_date)
        ) {
            $skippedCount++;
            $skippedRows[] = $excelRowNo;
            continue;
        }

        /* Defaults / Sanitization */
        $pickup_point_id = is_numeric($pickup_point_id) ? $pickup_point_id : null;
        $guide_rate  = is_numeric($guide_rate)  ? $guide_rate  : 0;
        $adult_price = is_numeric($adult_price) ? $adult_price : 0;
        $child_price = is_numeric($child_price) ? $child_price : 0;
        $half_day    = is_numeric($half_day)    ? $half_day    : 0;
        $full_day    = is_numeric($full_day)    ? $full_day    : 0;
        $itinerary   = trim((string)$itinerary);

        /* --------------------------------------
           INSERT SIGHTSEEING (ONLY ONCE)
        -------------------------------------- */
        if (!isset($sightseeingMap[$name])) {

            $stmt = $conn->prepare("
                INSERT INTO sightseeings
                (name, country_id, city_id, pickup_point_id, guide_rate, itinerary)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "siiids",
                $name,
                $country_id,
                $city_id,
                $pickup_point_id,
                $guide_rate,
                $itinerary
            );
            $stmt->execute();

            $sightseeing_id = $stmt->insert_id;
            $sightseeingMap[$name] = $sightseeing_id;
            $sightseeingCount++;

        } else {
            $sightseeing_id = $sightseeingMap[$name];
        }

        /* --------------------------------------
           ACTIVITY (OPTIONAL)
        -------------------------------------- */
        if (!empty($activity_name)) {
            $stmtAct = $conn->prepare("
                INSERT INTO sightseeing_activities
                (sightseeing_id, activity_name, adult_price, child_price)
                VALUES (?, ?, ?, ?)
            ");
            $stmtAct->bind_param(
                "isdd",
                $sightseeing_id,
                $activity_name,
                $adult_price,
                $child_price
            );
            $stmtAct->execute();
            $activityCount++;
        }

        /* --------------------------------------
           DATE-WISE CAR RATE (UPDATED)
        -------------------------------------- */
        $stmtCar = $conn->prepare("
            INSERT INTO sightseeing_car_rates_dates
            (sightseeing_id, car_id, start_date, end_date, half_day, full_day)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmtCar->bind_param(
            "iissdd",
            $sightseeing_id,
            $car_id,
            $start_date,
            $end_date,
            $half_day,
            $full_day
        );
        $stmtCar->execute();
        $carCount++;
    }

    if ($sightseeingCount === 0 && $activityCount === 0 && $carCount === 0) {
        $conn->rollback();
        header("Location: sightseeing_list.php?msg=No valid data found in Excel&type=warning");
        exit;
    }

    $conn->commit();

    $skipMsg = $skippedCount > 0
        ? " | Skipped Rows: " . implode(', ', $skippedRows)
        : "";

    $msg = "Excel Imported Successfully | "
         . "Sightseeing: $sightseeingCount, "
         . "Activities: $activityCount, "
         . "Car Rates: $carCount"
         . $skipMsg;

    header("Location: sightseeing_list.php?msg=" . urlencode($msg) . "&type=success");
    exit;

} catch (Exception $e) {

    $conn->rollback();
    header(
        "Location: sightseeing_list.php?msg="
        . urlencode("Import failed: " . $e->getMessage())
        . "&type=error"
    );
    exit;
}
