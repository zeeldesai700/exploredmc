<?php
require_once __DIR__.'/../../config/db.php';

$id = $_POST['passport_id'] ?? 0;
$name = trim($_POST['passport_name'] ?? '');
$no   = trim($_POST['passport_no'] ?? '');

/* if empty → force BOOKED */
if(empty($name) && empty($no)){
    $name = 'Done';
    $no   = '-';
}

/* get confirmation + city */
$row = $conn->query("
SELECT confirmation_no, city_name 
FROM confirmation_guide 
WHERE id = $id
")->fetch_assoc();

$cn   = $row['confirmation_no'];
$city = $row['city_name'];

/* update ALL rows */
$stmt = $conn->prepare("
UPDATE confirmation_guide 
SET passport_name = ?, 
    passport_no = ?, 
    passport_status = 'yes'
WHERE confirmation_no = ? 
AND city_name = ?
");

$stmt->bind_param("ssss", $name, $no, $cn, $city);
$stmt->execute();

echo "success";