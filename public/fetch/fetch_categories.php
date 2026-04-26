<?php
require_once __DIR__ . '/../../config/db.php';

if (isset($_POST['city_id'])) {
    $city_id = (int)$_POST['city_id'];
    $sql = "SELECT DISTINCT category FROM hotels WHERE city_id = $city_id ORDER BY category";
    $res = $conn->query($sql);

    echo '<option value="">Select Category</option>';
    while($r = $res->fetch_assoc()){
        $cat = htmlspecialchars($r['category']);
        echo "<option value=\"$cat\">$cat</option>";
    }
}
