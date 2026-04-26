<?php
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

// no sightseeing id sent
if (!isset($_POST['sightseeing_id'])) {
    echo json_encode([]);
    exit;
}

$sight_id = (int)$_POST['sightseeing_id'];

$sql = "
    SELECT 
        id,
        activity_name AS name,
        adult_price AS adult,
        child_price AS child
    FROM sightseeing_activities
    WHERE sightseeing_id = $sight_id
    ORDER BY activity_name ASC
";

$res = $conn->query($sql);

$activities = [];

while ($row = $res->fetch_assoc()) {
    $activities[] = [
        "id"    => (int)$row["id"],
        "name"  => $row["name"],
        "adult" => (float)$row["adult"],
        "child" => (float)$row["child"]
    ];
}

echo json_encode($activities);
exit;
