<?php
require_once __DIR__.'/../../config/db.php';

$city_id = (int)($_POST['city_id'] ?? 0);

$q = $conn->prepare("
    SELECT id, pickup_name, pickup_category 
    FROM pickup_points 
    WHERE city_id = ?
    ORDER BY pickup_name
");
$q->bind_param("i", $city_id);
$q->execute();
$res = $q->get_result();

echo '<option value="">Select Pickup</option>';
while ($r = $res->fetch_assoc()) {
    echo '<option value="'.$r['id'].'" 
          data-category="'.$r['pickup_category'].'">'
          .htmlspecialchars($r['pickup_name']).'
          </option>';
}
