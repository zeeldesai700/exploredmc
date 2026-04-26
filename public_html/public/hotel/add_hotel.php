<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();

require_once __DIR__ . '/../../config/db.php';

/* ================= PREFILL FROM POPUP ================= */
$cityPrefill     = (int)($_GET['city_id'] ?? 0);
$categoryPrefill = $_GET['category'] ?? '';

/* =======================================================
   SAVE HOTEL + ROOM + SEASON (SINGLE SET)
======================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ---------- HOTEL ---------- */
    $stmt = $conn->prepare("
        INSERT INTO hotels (name, category, address, country_id, city_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "sssii",
        $_POST['name'],
        $_POST['category'],
        $_POST['address'],
        $_POST['country'],
        $_POST['city']
    );
    $stmt->execute();
    $hotel_id = $conn->insert_id;

    /* ---------- ROOM ---------- */
    if (!empty($_POST['room_cat'][0])) {

        $stmtRoom = $conn->prepare("
            INSERT INTO hotel_rooms (hotel_id, room_category)
            VALUES (?, ?)
        ");
        $stmtRoom->bind_param("is", $hotel_id, $_POST['room_cat'][0]);
        $stmtRoom->execute();
        $room_id = $conn->insert_id;

        /* ---------- SEASON ---------- */
if (!empty($_POST['from'][0][0]) && !empty($_POST['to'][0][0])) {

    // ✅ ASSIGN TO VARIABLES (bind_param NEEDS VARIABLES)
    $price = $_POST['price'][0][0] ?? 0;
    $ea    = $_POST['extra_adult'][0][0] ?? 0;
    $ec    = $_POST['extra_child'][0][0] ?? 0;
    $nb    = $_POST['no_bed'][0][0] ?? 0;
    $df    = $_POST['from'][0][0];
    $dt    = $_POST['to'][0][0];

    $stmtS = $conn->prepare("
        INSERT INTO hotel_room_seasons
        (room_id, room_price, extra_adult, extra_child, no_bed_child, date_from, date_to)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmtS->bind_param(
        "iddddss",
        $room_id,
        $price,
        $ea,
        $ec,
        $nb,
        $df,
        $dt
    );

    $stmtS->execute();
}
    }

    echo "
    <div class='alert alert-success m-3'>✔ Hotel Saved Successfully</div>
    <script>
        window.parent.postMessage('HOTEL_SAVED','*');
    </script>
    ";
    exit;
}

/* ================= COUNTRY LIST ================= */
$countries = $conn->query("SELECT * FROM countries ORDER BY name ASC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Hotel</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        body { background:#f8f9fa; }
        .room-block {
            border:1px solid #ddd;
            border-radius:8px;
            padding:12px;
            background:#fff;
        }
    </style>
</head>

<body>
<div class="container mt-3">
    
    <form method="POST">

        <!-- HOTEL INFO -->
        <div class="card p-3 mb-3">
            <h6>Hotel Information</h6>

            <label>Hotel Name</label>
            <input name="name" class="form-control mb-2" required>

            <label>Category</label>
            <select name="category" class="form-select mb-2">
                <?php foreach (['1-star','2-star','3-star','4-star','5-star'] as $c): ?>
                    <option <?=($c === $categoryPrefill ? 'selected' : '')?>>
                        <?=$c?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Address</label>
            <textarea name="address" class="form-control mb-2"></textarea>

            <label>Country</label>
            <select name="country" id="country" class="form-select mb-2" required>
                <option value="">Select</option>
                <?php while($c = $countries->fetch_assoc()): ?>
                    <option value="<?=$c['id']?>"><?=$c['name']?></option>
                <?php endwhile; ?>
            </select>

            <label>City</label>
            <select name="city" id="city" class="form-select mb-2" required>
                <?php if ($cityPrefill): ?>
                    <option value="<?=$cityPrefill?>" selected>Preselected</option>
                <?php else: ?>
                    <option value="">Select</option>
                <?php endif; ?>
            </select>
        </div>

        <!-- ROOM + SEASON (SINGLE SET) -->
        <div class="room-block mb-3">
            <h6>Room & Seasonal Price</h6>

            <label>Room Category</label>
            <input name="room_cat[]" class="form-control mb-2" required>

            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>Price</th>
                        <th>EA</th>
                        <th>EC</th>
                        <th>NoBed</th>
                        <th>From</th>
                        <th>To</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><input name="price[0][]" class="form-control"></td>
                        <td><input name="extra_adult[0][]" class="form-control"></td>
                        <td><input name="extra_child[0][]" class="form-control"></td>
                        <td><input name="no_bed[0][]" class="form-control"></td>
                        <td><input type="date" name="from[0][]" class="form-control" required></td>
                        <td><input type="date" name="to[0][]" class="form-control" required></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- SAVE ONLY -->
        <div class="d-flex gap-2">
    <button type="submit" class="btn btn-success flex-fill">
        Save Hotel
    </button>

    <button type="button" id="closeAddHotel"
        class="btn btn-outline-secondary flex-fill">
        Close
    </button>
</div>
 </form>

<script>
/* LOAD CITIES */
$("#country").on("change", function(){
    const cid = $(this).val();
    $("#city").html('<option>Loading...</option>');
    $.get("../fetch/get_cities.php?country_id=" + cid, function(html){
        $("#city").html(html);
    });
});

</script>

<script>
/* CLOSE POPUP SAFELY */
$(document).on("click", "#closeAddHotel", function (e) {
    e.preventDefault();
    e.stopPropagation();

    window.parent.postMessage("HOTEL_CLOSE", "*");
});
</script>

</body>
</html>
