<?php
require_once __DIR__ . '/../../config/db.php';

$id = $_GET['id'] ?? 0;

if ($id) {

    // 1) Delete car rates linked to sightseeing
    $stmt1 = $conn->prepare("DELETE FROM sightseeing_car_rates WHERE sightseeing_id=?");
    $stmt1->bind_param("i", $id);
    $stmt1->execute();

    // 2) Delete activities linked to sightseeing
    $stmt2 = $conn->prepare("DELETE FROM sightseeing_activities WHERE sightseeing_id=?");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();

    // 3) Delete sightseeing
    $stmt3 = $conn->prepare("DELETE FROM sightseeings WHERE id=?");
    $stmt3->bind_param("i", $id);
    $stmt3->execute();
}

header("Location: sightseeing_list.php");
exit;
