<?php
require_once __DIR__ . '/../../config/db.php';

$id = (int)($_POST['quotation_id'] ?? 0);
if ($id <= 0) {
    die("Invalid quotation ID");
}

$name   = $_POST['passenger_name'] ?? '';
$mobile = $_POST['passenger_mobile'] ?? '';
$flight = $_POST['flight_name'] ?? '';
$time   = $_POST['flight_time'] ?? '';
$pnr    = $_POST['flight_pnr'] ?? '';

// check if exists → update
$check = $conn->query("SELECT id FROM confirmations WHERE quotation_id = $id LIMIT 1");

if ($check && $check->num_rows > 0) {

    $stmt = $conn->prepare("
        UPDATE confirmations SET 
        passenger_name=?, passenger_mobile=?, flight_name=?, flight_time=?, flight_pnr=?
        WHERE quotation_id=?
    ");
    $stmt->bind_param("sssssi", $name, $mobile, $flight, $time, $pnr, $id);
    $stmt->execute();
    echo "Confirmation Updated Successfully";

} else {

    $stmt = $conn->prepare("
        INSERT INTO confirmations 
        (quotation_id, passenger_name, passenger_mobile, flight_name, flight_time, flight_pnr)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isssss", $id, $name, $mobile, $flight, $time, $pnr);
    $stmt->execute();
    echo "Confirmation Saved Successfully";
}

?>
