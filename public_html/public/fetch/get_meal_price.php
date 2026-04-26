<?php
require_once __DIR__ . '/../../config/db.php';

if (isset($_POST['meal_id'])) {
    $meal_id = (int)$_POST['meal_id'];

    $res = $conn->query("SELECT price FROM meals WHERE id = $meal_id LIMIT 1");

    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo (float)$row['price'];
    } else {
        echo "0";
    }
}
?>
