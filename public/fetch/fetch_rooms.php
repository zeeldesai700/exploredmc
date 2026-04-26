<?php 
require_once __DIR__ . '/../../config/db.php';

if (empty($_POST['hotel_id'])) exit;

$hotel_id = (int)$_POST['hotel_id'];

echo '<option value="">Select Room</option>';

// Fetch rooms (only category, no price)
$stmt = $conn->prepare("
    SELECT id, room_category
    FROM hotel_rooms
    WHERE hotel_id = ?
    ORDER BY room_category ASC
");
$stmt->bind_param("i", $hotel_id);
$stmt->execute();
$stmt->bind_result($room_id, $cat);

while ($stmt->fetch()) {
    echo "<option value=\"$room_id\">".htmlspecialchars($cat)."</option>";
}

$stmt->close();
?>
