<?php

require_once __DIR__.'/../../config/db.php';

$id = $_POST['guide_id'];
$name = $_POST['guide_name'];
$mobile = $_POST['guide_mobile'];

$stmt = $conn->prepare("
UPDATE confirmation_guide
SET
guide_name=?,
guide_mobile=?,
action_status='yes'
WHERE id=?
");

$stmt->bind_param("ssi",$name,$mobile,$id);
$stmt->execute();

header("Location: guide_booking.php");
exit;