<?php
require_once __DIR__ . '/../../config/db.php';

$city_id  = isset($_POST['city_id'])  ? (int)$_POST['city_id']  : 0;
$category = isset($_POST['category']) ? trim($_POST['category']) : '';

if ($city_id && $category !== '') {
    // Use prepared statement for safety
    $stmt = $conn->prepare("SELECT id, name FROM hotels WHERE city_id = ? AND category = ? ORDER BY name ASC");
    $stmt->bind_param("is", $city_id, $category);
    $stmt->execute();
    $res = $stmt->get_result();

    echo '<option value="">Select Hotel</option>';
    while($row = $res->fetch_assoc()){
        $id   = (int)$row['id'];
        $name = htmlspecialchars($row['name']);
        echo "<option value=\"$id\">$name</option>";
    }
} else {
    echo '<option value="">Select Hotel</option>';
}
