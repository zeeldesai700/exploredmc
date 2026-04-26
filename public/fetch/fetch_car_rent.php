<?php
require_once __DIR__ . '/../../config/db.php';

$sight_id    = (int)($_POST['sightseeing_id'] ?? 0);
$car_id      = (int)($_POST['car_id'] ?? 0);
$travel_date = $_POST['travel_date'] ?? date('Y-m-d');

if ($sight_id && $car_id) {

    $sql = "
SELECT
    c.id,
    c.car_name,
    c.seater,
    d.full_day,
    d.half_day
FROM sightseeing_car_rates_dates d
JOIN cars c ON c.id = d.car_id
WHERE d.sightseeing_id = ?
  AND d.car_id = ?
  AND ? BETWEEN d.start_date AND d.end_date
ORDER BY d.start_date DESC
LIMIT 1
";


    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $sight_id, $car_id, $travel_date);
    $stmt->execute();

    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {

        $half = (float)$row['half_day'];
        $full = (float)$row['full_day'];

        echo "<option value='full' data-full='{$full}' data-half='{$half}' selected>
                Full Day (₹{$full})
              </option>";

        echo "<option value='half' data-full='{$full}' data-half='{$half}'>
                Half Day (₹{$half})
              </option>";

    } else {
        echo "<option value=''>No Car Rate for Selected Date</option>";
    }
}
