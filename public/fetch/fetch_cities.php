<?php
require_once __DIR__ . '/../../config/db.php';

if (isset($_POST['country_id'])) {
    $country_id = (int)$_POST['country_id'];
    $res = $conn->query("SELECT id,name FROM cities WHERE country_id = $country_id ORDER BY name ASC");

    echo '<option value="">Select City</option>';
    while($row = $res->fetch_assoc()){
        $id = (int)$row['id'];
        $nm = htmlspecialchars($row['name']);
        echo "<option value=\"$id\">$nm</option>";
    }
}
