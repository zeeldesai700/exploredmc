<?php
require_once __DIR__ . '/../../config/db.php';

$sightseeing_id = (int)($_POST['sightseeing'] ?? 0);
$travel_date    = $_POST['travel_date'] ?? null;

if (!$sightseeing_id || !$travel_date) {
    echo json_encode([]);
    exit;
}

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
  AND ? BETWEEN d.start_date AND d.end_date
  AND d.start_date = (
        SELECT MAX(d2.start_date)
        FROM sightseeing_car_rates_dates d2
        WHERE d2.car_id = d.car_id
          AND d2.sightseeing_id = d.sightseeing_id
          AND ? BETWEEN d2.start_date AND d2.end_date
    )
ORDER BY c.car_name
";
$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "iss",
    $sightseeing_id,
    $travel_date,
    $travel_date
);

$stmt->execute();

$res = $stmt->get_result();
$data = [];

while ($row = $res->fetch_assoc()) {
    $data[] = [
        "id"       => (int)$row['id'],
        "car_name" => $row['car_name'],
        "seater"   => $row['seater'],
        "full_day" => (float)$row['full_day'],
        "half_day" => (float)$row['half_day'],
    ];
}

echo json_encode($data);
