<?php
require_once __DIR__ . '/../../config/db.php';

if (isset($_POST['activity_ids']) && is_array($_POST['activity_ids'])) {
    $ids = array_map('intval', $_POST['activity_ids']);
    $ids = implode(",", $ids);

    if (!empty($ids)) {
        $sql = "SELECT SUM(price) AS total_price 
                FROM activities 
                WHERE id IN ($ids)";
        $res = $conn->query($sql);

        if ($res && $row = $res->fetch_assoc()) {
            echo (float)$row['total_price'];
            exit;
        }
    }
}

echo "0"; // default if nothing found
