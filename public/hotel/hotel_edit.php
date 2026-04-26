<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();

require_once __DIR__ . '/../../config/db.php';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';

// ---------------- GET HOTEL ID ----------------
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid Hotel ID');
}
$hotel_id = (int)$_GET['id'];

// ---------------- UPDATE HOTEL ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name       = $_POST['name'] ?? '';
    $category   = $_POST['category'] ?? '';
    $address    = $_POST['address'] ?? '';
    $country_id = (int)($_POST['country'] ?? 0);
    $city_id    = (int)($_POST['city'] ?? 0);

    $stmt = $conn->prepare("
        UPDATE hotels
        SET name = ?, category = ?, address = ?, country_id = ?, city_id = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sssiii", $name, $category, $address, $country_id, $city_id, $hotel_id);

    if ($stmt->execute()) {

        // 1) Delete all old seasons of rooms of this hotel
        $conn->query("
            DELETE s FROM hotel_room_seasons s
            INNER JOIN hotel_rooms r ON r.id = s.room_id
            WHERE r.hotel_id = {$hotel_id}
        ");

        // 2) Delete old room categories
        $conn->query("DELETE FROM hotel_rooms WHERE hotel_id = {$hotel_id}");

        // 3) Re-insert rooms + seasons from POST
        if (!empty($_POST['room_cat']) && is_array($_POST['room_cat'])) {

            foreach ($_POST['room_cat'] as $i => $room_cat) {

                $room_cat = trim($room_cat);
                if ($room_cat === '') continue;

                // Insert room category
                $stmtRoom = $conn->prepare("
                    INSERT INTO hotel_rooms (hotel_id, room_category)
                    VALUES (?, ?)
                ");
                $stmtRoom->bind_param("is", $hotel_id, $room_cat);
                $stmtRoom->execute();
                $room_id = $stmtRoom->insert_id;

                // Seasons for this room index $i
                if (!empty($_POST['price'][$i]) && is_array($_POST['price'][$i])) {

                    foreach ($_POST['price'][$i] as $j => $price) {

                        $price = (float)($_POST['price'][$i][$j] ?? 0);
                        $ea    = (float)($_POST['extra_adult'][$i][$j] ?? 0);
                        $ec    = (float)($_POST['extra_child'][$i][$j] ?? 0);
                        $nb    = (float)($_POST['no_bed'][$i][$j] ?? 0);
                        $df    = $_POST['from'][$i][$j] ?? null;
                        $dt    = $_POST['to'][$i][$j] ?? null;

                        // Skip empty rows (no price, no date)
                        if ($price == 0 && $df == '' && $dt == '') {
                            continue;
                        }

                        $stmtSeason = $conn->prepare("
                            INSERT INTO hotel_room_seasons
                            (room_id, room_price, extra_adult, extra_child, no_bed_child, date_from, date_to)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmtSeason->bind_param(
                            "iddddss",
                            $room_id,
                            $price,
                            $ea,
                            $ec,
                            $nb,
                            $df,
                            $dt
                        );
                        $stmtSeason->execute();
                    }
                }
            }
        }

        $message = "<div class='alert alert-success mt-2'>Hotel updated successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger mt-2'>Error: {$stmt->error}</div>";
    }
}

// ---------------- FETCH CURRENT DATA ----------------
$hotel = $conn->query("SELECT * FROM hotels WHERE id = {$hotel_id}")->fetch_assoc();
if (!$hotel) {
    die('Hotel not found');
}

$countries = $conn->query("SELECT * FROM countries ORDER BY name ASC");
$cities    = $conn->query("SELECT * FROM cities WHERE country_id = {$hotel['country_id']} ORDER BY name ASC");

// Fetch room categories
$roomRows  = $conn->query("SELECT * FROM hotel_rooms WHERE hotel_id = {$hotel_id} ORDER BY room_category ASC");

// we will need a JS start index for new rooms
$existingRoomIndex = 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Hotel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .room-block{
            border:1px solid #dee2e6;
            border-radius:8px;
            padding:12px;
            margin-bottom:15px;
            background:#fff;
        }
        .room-header{
            display:flex;
            justify-content:space-between;
            align-items:center;
        }
    </style>
</head>
<body class="bg-light">

<div class="container mt-4">

    <h4>Edit Hotel</h4>
    <?php if (!empty($message)) echo $message; ?>

    <form method="POST">

        <div class="card mb-3">
            <div class="card-body">

                <table class="table table-sm">
                    <tr>
                        <th style="width: 15%;">Name</th>
                        <td><input name="name" class="form-control" value="<?= htmlspecialchars($hotel['name']) ?>" required></td>
                    </tr>
                    <tr>
                        <th>Category</th>
                        <td>
                            <select name="category" class="form-select">
                                <?php foreach (["1-star","2-star","3-star","4-star","5-star","Luxury"] as $cat): ?>
                                    <option value="<?= $cat ?>" <?= $hotel['category']===$cat ? 'selected' : '' ?>>
                                        <?= $cat ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Address</th>
                        <td><textarea name="address" class="form-control"><?= htmlspecialchars($hotel['address']) ?></textarea></td>
                    </tr>
                    <tr>
                        <th>Country</th>
                        <td>
                            <select name="country" id="country" class="form-select" required>
                                <option value="">Select Country</option>
                                <?php while ($c = $countries->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>" <?= $hotel['country_id'] == $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>City</th>
                        <td>
                            <select name="city" id="city" class="form-select" required>
                                <option value="">Select City</option>
                                <?php while ($c = $cities->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>" <?= $hotel['city_id'] == $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </td>
                    </tr>
                </table>

            </div>
        </div>

        <!-- ROOMS + SEASONS -->
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Room Categories & Seasonal Pricing</h5>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRoom()">+ Add Room</button>
        </div>

        <div id="roomWrapper">
            <?php
            $i = 0;
            while ($room = $roomRows->fetch_assoc()):
                $room_id = (int)$room['id'];
                $seasons = $conn->query("SELECT * FROM hotel_room_seasons WHERE room_id = {$room_id} ORDER BY date_from ASC");
            ?>
            <div class="room-block" data-room-index="<?= $i ?>">
                <div class="room-header mb-2">
                    <div style="flex:1;">
                        <label class="form-label mb-1">Room Category</label>
                        <input name="room_cat[<?= $i ?>]" class="form-control" value="<?= htmlspecialchars($room['room_category']) ?>" required>
                    </div>
                    <button type="button" class="btn btn-sm btn-danger ms-2" onclick="removeRoom(this)">Delete Room</button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-2 season-table">
                        <thead class="table-light">
                            <tr>
                                <th>Price</th>
                                <th>Extra Adult</th>
                                <th>Extra Child</th>
                                <th>No Bed Child</th>
                                <th>From</th>
                                <th>To</th>
                                <th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($seasons && $seasons->num_rows > 0): ?>
                                <?php while ($s = $seasons->fetch_assoc()): ?>
                                <tr>
                                    <td><input name="price[<?= $i ?>][]"        class="form-control form-control-sm" type="number" step="0.01" value="<?= $s['room_price'] ?>"></td>
                                    <td><input name="extra_adult[<?= $i ?>][]"  class="form-control form-control-sm" type="number" step="0.01" value="<?= $s['extra_adult'] ?>"></td>
                                    <td><input name="extra_child[<?= $i ?>][]"  class="form-control form-control-sm" type="number" step="0.01" value="<?= $s['extra_child'] ?>"></td>
                                    <td><input name="no_bed[<?= $i ?>][]"       class="form-control form-control-sm" type="number" step="0.01" value="<?= $s['no_bed_child'] ?>"></td>
                                    <td><input name="from[<?= $i ?>][]"         class="form-control form-control-sm" type="date"   value="<?= $s['date_from'] ?>"></td>
                                    <td><input name="to[<?= $i ?>][]"           class="form-control form-control-sm" type="date"   value="<?= $s['date_to'] ?>"></td>
                                    <td><button type="button" class="btn btn-sm btn-danger" onclick="removeSeason(this)">X</button></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <!-- show one empty row -->
                                <tr>
                                    <td><input name="price[<?= $i ?>][]"        class="form-control form-control-sm" type="number" step="0.01"></td>
                                    <td><input name="extra_adult[<?= $i ?>][]"  class="form-control form-control-sm" type="number" step="0.01"></td>
                                    <td><input name="extra_child[<?= $i ?>][]"  class="form-control form-control-sm" type="number" step="0.01"></td>
                                    <td><input name="no_bed[<?= $i ?>][]"       class="form-control form-control-sm" type="number" step="0.01"></td>
                                    <td><input name="from[<?= $i ?>][]"         class="form-control form-control-sm" type="date"></td>
                                    <td><input name="to[<?= $i ?>][]"           class="form-control form-control-sm" type="date"></td>
                                    <td><button type="button" class="btn btn-sm btn-danger" onclick="removeSeason(this)">X</button></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addSeason(this)">+ Add Seasonal Rate</button>
            </div>
            <?php
                $existingRoomIndex = $i;
                $i++;
            endwhile;
            ?>
        </div>

        <div class="text-end mt-3 mb-5">
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>

    </form>
</div>

<script>
// start index for new rooms
let nextRoomIndex = <?= $existingRoomIndex + 1 ?>;

// Add new room block
function addRoom() {
    const i = nextRoomIndex++;
    const html = `
    <div class="room-block" data-room-index="${i}">
        <div class="room-header mb-2">
            <div style="flex:1;">
                <label class="form-label mb-1">Room Category</label>
                <input name="room_cat[${i}]" class="form-control" required>
            </div>
            <button type="button" class="btn btn-sm btn-danger ms-2" onclick="removeRoom(this)">Delete Room</button>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-2 season-table">
                <thead class="table-light">
                    <tr>
                        <th>Price</th>
                        <th>Extra Adult</th>
                        <th>Extra Child</th>
                        <th>No Bed Child</th>
                        <th>From</th>
                        <th>To</th>
                        <th style="width:40px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><input name="price[${i}][]"       class="form-control form-control-sm" type="number" step="0.01"></td>
                        <td><input name="extra_adult[${i}][]" class="form-control form-control-sm" type="number" step="0.01"></td>
                        <td><input name="extra_child[${i}][]" class="form-control form-control-sm" type="number" step="0.01"></td>
                        <td><input name="no_bed[${i}][]"      class="form-control form-control-sm" type="number" step="0.01"></td>
                        <td><input name="from[${i}][]"        class="form-control form-control-sm" type="date"></td>
                        <td><input name="to[${i}][]"          class="form-control form-control-sm" type="date"></td>
                        <td><button type="button" class="btn btn-sm btn-danger" onclick="removeSeason(this)">X</button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addSeason(this)">+ Add Seasonal Rate</button>
    </div>`;
    document.getElementById('roomWrapper').insertAdjacentHTML('beforeend', html);
}

// Add season row inside existing room
function addSeason(btn) {
    const roomBlock = btn.closest('.room-block');
    const i = roomBlock.getAttribute('data-room-index');
    const tbody = roomBlock.querySelector('tbody');
    const row = `
    <tr>
        <td><input name="price[${i}][]"       class="form-control form-control-sm" type="number" step="0.01"></td>
        <td><input name="extra_adult[${i}][]" class="form-control form-control-sm" type="number" step="0.01"></td>
        <td><input name="extra_child[${i}][]" class="form-control form-control-sm" type="number" step="0.01"></td>
        <td><input name="no_bed[${i}][]"      class="form-control form-control-sm" type="number" step="0.01"></td>
        <td><input name="from[${i}][]"        class="form-control form-control-sm" type="date"></td>
        <td><input name="to[${i}][]"          class="form-control form-control-sm" type="date"></td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="removeSeason(this)">X</button></td>
    </tr>`;
    tbody.insertAdjacentHTML('beforeend', row);
}


function removeSeason(btn) {
    btn.closest('tr').remove();
}

function removeRoom(btn) {
    btn.closest('.room-block').remove();
}

// Load cities when country changes
$("#country").change(function () {
    let country_id = $(this).val();
    $("#city").html('<option value="">Loading...</option>');
    if (!country_id) {
        $("#city").html('<option value="">Select City</option>');
        return;
    }
    $.get("../fetch/get_cities.php?country_id=" + country_id, function (data) {
        $("#city").html(data);
    });
});
</script>

</body>
</html>
