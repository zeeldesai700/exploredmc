<?php
require_once __DIR__ . '/../../config/db.php';

$hotel_id = (int) $_GET['hotel_id'];

// hotel name
$hotelRes = $conn->query("SELECT name FROM hotels WHERE id = $hotel_id");
$hotel = $hotelRes->fetch_assoc();
?>

<div class="mb-3">
    <h5><?= htmlspecialchars($hotel['name']) ?></h5>
</div>

<?php
// fetch room categories
$roomRes = $conn->query("
   SELECT *
   FROM hotel_rooms
   WHERE hotel_id = $hotel_id
   ORDER BY room_category ASC
");

if ($roomRes->num_rows > 0) {
?>

<table class="table table-bordered table-sm">
    <thead class="table-dark">
        <tr>
            <th>Price</th>
            <th>Extra Adult</th>
            <th>Extra Child</th>
            <th>No Bed Child</th>
            <th>From Date</th>
            <th>To Date</th>
        </tr>
    </thead>

    <tbody>
<?php
while($room = $roomRes->fetch_assoc()) {

    $room_id = (int)$room['id'];

    // fetch seasonal
    $seasonRes = $conn->query("
        SELECT *
        FROM hotel_room_seasons
        WHERE room_id = $room_id
        ORDER BY date_from ASC
    ");

    echo "<tr class='table-primary'>
            <td colspan='6'><strong>".htmlspecialchars($room['room_category'])."</strong></td>
          </tr>";

    if ($seasonRes->num_rows == 0) {
        echo "<tr>
                <td colspan='6' class='text-muted'>No seasonal price added</td>
              </tr>";
        continue;
    }

    while ($s = $seasonRes->fetch_assoc()) {
        echo "<tr>
                <td>".number_format($s['room_price'],2)."</td>
                <td>".number_format($s['extra_adult'],2)."</td>
                <td>".number_format($s['extra_child'],2)."</td>
                <td>".number_format($s['no_bed_child'],2)."</td>
                <td>".$s['date_from']."</td>
                <td>".$s['date_to']."</td>
              </tr>";
    }
}
?>
    </tbody>
</table>

<?php
} else {
    echo "<p class='text-muted'>No rooms found for this hotel.</p>";
}
?>
