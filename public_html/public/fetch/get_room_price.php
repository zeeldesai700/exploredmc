<?php
require_once __DIR__ . '/../../config/db.php';

$room_id   = (int)($_POST['room_id'] ?? 0);
$checkin   = $_POST['travel_date'] ?? date("Y-m-d");
$nights    = (int)($_POST['nights'] ?? 0);

// default response
$response = [
  'room_price'   => 0,
  'extra_adult'  => 0,
  'extra_child'  => 0,
  'no_bed_child' => 0,
];

if(!$room_id || !$checkin || $nights<=0){
    echo json_encode($response);
    exit;
}

// -----------------------------------------------------
// LOOP date-by-date and accumulate seasonal values
// -----------------------------------------------------
$totalRoom = 0;
$totalEA = 0;
$totalEC = 0;
$totalNB = 0;

$start = new DateTime($checkin);
$end   = (clone $start)->modify("+{$nights} day");

$cur = clone $start;

while($cur < $end){

    $d = $cur->format("Y-m-d");

    $sql = $conn->prepare("
        SELECT room_price, extra_adult, extra_child, no_bed_child 
        FROM hotel_room_seasons
        WHERE room_id = ?
        AND ? BETWEEN date_from AND date_to
        LIMIT 1
    ");
    $sql->bind_param("is", $room_id, $d);
    $sql->execute();
    $season = $sql->get_result()->fetch_assoc();

    if($season){
        $totalRoom += (float)$season['room_price'];
        $totalEA   += (float)$season['extra_adult'];
        $totalEC   += (float)$season['extra_child'];
        $totalNB   += (float)$season['no_bed_child'];
    }

    $cur->modify("+1 day");
}

$response['room_price']   = $totalRoom;
$response['extra_adult']  = $totalEA;
$response['extra_child']  = $totalEC;
$response['no_bed_child'] = $totalNB;

echo json_encode($response);
