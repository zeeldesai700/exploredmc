<?php
require_once __DIR__ . '/../../config/db.php';

$country_id = (int)$_GET['country_id'];
$result = $conn->query("SELECT * FROM cities WHERE country_id = $country_id ORDER BY name ASC");

if ($result->num_rows > 0) {
    echo "<option value=''>Select City</option>";
    while($row = $result->fetch_assoc()) {
        echo "<option value='".$row['id']."'>".htmlspecialchars($row['name'])."</option>";
    }
} else {
    echo "<option value=''>No cities found</option>";
}
?>
