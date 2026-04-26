<?php
require_once __DIR__ . '/../../config/db.php';

$city_id         = isset($_POST['city_id']) ? (int)$_POST['city_id'] : 0;
$pickup_point_id = isset($_POST['pickup_point_id']) ? (int)$_POST['pickup_point_id'] : 0;

if ($city_id > 0) {

    /* 🔹 Base SQL */
    $sql = "
        SELECT id, name, guide_rate
        FROM sightseeings
        WHERE city_id = ?
    ";

    /* 🔹 Optional Pickup Point filter */
    if ($pickup_point_id > 0) {
        $sql .= " AND pickup_point_id = ?";
    }

    $sql .= " ORDER BY name ASC";

    /* 🔹 Prepare */
    if ($pickup_point_id > 0) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $city_id, $pickup_point_id);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $city_id);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    echo '<option value="">Select Sightseeing</option>';

    while ($row = $res->fetch_assoc()) {

        $id        = (int)$row['id'];
        $name      = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
        $guideRate = number_format((float)$row['guide_rate'], 2, '.', '');

        echo "<option value=\"$id\" data-guide=\"$guideRate\">$name</option>";
    }
}
