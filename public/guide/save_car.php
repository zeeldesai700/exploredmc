<?php
require_once __DIR__.'/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = isset($_POST['car_id']) ? (int)$_POST['car_id'] : 0;

    $driver_name   = isset($_POST['car_driver_name']) 
                     ? trim($_POST['car_driver_name']) 
                     : '';

    $driver_mobile = isset($_POST['car_driver_mobile']) 
                     ? trim($_POST['car_driver_mobile']) 
                     : '';

    if ($id <= 0) {
        die("Invalid car booking");
    }

    /* allow empty driver details */
    if ($driver_name === '') {
        $driver_name = null;
    }

    if ($driver_mobile === '') {
        $driver_mobile = null;
    }

    $stmt = $conn->prepare("
        UPDATE confirmation_guide
        SET 
            car_driver_name = ?,
            car_driver_mobile = ?,
            car_status = 'yes'
        WHERE id = ?
    ");

    $stmt->bind_param("ssi", $driver_name, $driver_mobile, $id);
    $stmt->execute();
    $stmt->close();
}

/* redirect back */
header("Location: guide_booking.php");
exit;
?>