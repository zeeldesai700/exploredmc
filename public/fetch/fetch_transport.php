<?php
require __DIR__ . '/../../config/db.php';

$res = $conn->query("SELECT id, car_name, seater FROM cars ORDER BY car_name ASC");

echo '<option value="">Select Car</option>';
while($c = $res->fetch_assoc()){
    echo "<option value='{$c['id']}'>".
            htmlspecialchars($c['car_name']) ." ({$c['seater']} seater)".
         "</option>";
}
exit;
