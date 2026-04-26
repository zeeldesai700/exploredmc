<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();

require_once __DIR__ . '/../../config/db.php';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';

/* =======================================================
   SAVE HOTEL + ROOMS + SEASON PRICES
======================================================= */
if($_SERVER['REQUEST_METHOD']=="POST"){

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

    if(!empty($_POST['room_cat'])){
        foreach($_POST['room_cat'] as $i => $cat){

            if(trim($cat)=='') continue;

            // parent room category
            $stmtRoom = $conn->prepare("
                INSERT INTO hotel_rooms (hotel_id, room_category)
                VALUES (?,?)
            ");
            $stmtRoom->bind_param("is",$hotel_id,$cat);
            $stmtRoom->execute();

            $room_id = $conn->insert_id;

            // seasonal prices for this category
            if(!empty($_POST['price'][$i])){
                foreach($_POST['price'][$i] as $j => $price){

                    $price        = $_POST['price'][$i][$j] ?? 0;
                    $ea           = $_POST['extra_adult'][$i][$j] ?? 0;
                    $ec           = $_POST['extra_child'][$i][$j] ?? 0;
                    $nb           = $_POST['no_bed'][$i][$j] ?? 0;
                    $df           = $_POST['from'][$i][$j] ?? null;
                    $dt           = $_POST['to'][$i][$j] ?? null;

                    $stmtS = $conn->prepare("
                        INSERT INTO hotel_room_seasons
                        (room_id,room_price,extra_adult,extra_child,no_bed_child,date_from,date_to)
                        VALUES (?,?,?,?,?,?,?)
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
        }
    }

    echo "<div class='alert alert-success'>✔ Hotel Saved Successfully!</div>";
}

/* COUNTRY DROPDOWN */
$countries = $conn->query("SELECT * FROM countries ORDER BY name ASC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Hotel</title>

    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <script src='https://code.jquery.com/jquery-3.6.0.min.js'></script>

    <style>
        .room-block{
            border:1px solid #ccc;
            border-radius:8px;
            padding:10px;
            margin-bottom:14px;
        }
    </style>
</head>
<body class='bg-light'>
<div class='container mt-4'>
    <h4>Add Hotel</h4>

    <form method='POST'>

        <div class='card p-3 mb-3'>
            <h6>Hotel Information</h6>

            <label>Hotel Name</label>
            <input name='name' class='form-control mb-2' required>

            <label>Category</label>
            <select name='category' class='form-select mb-2'>
                <option>1-star</option>
                <option>2-star</option>
                <option>3-star</option>
                <option>4-star</option>
                <option>5-star</option>
            </select>

            <label>Address</label>
            <textarea name='address' class='form-control mb-2'></textarea>

            <label>Country</label>
            <select name='country' id='country' class='form-select mb-2'>
                <option value=''>Select</option>
                <?php while($c=$countries->fetch_assoc()): ?>
                <option value="<?=$c['id']?>"><?=$c['name']?></option>
                <?php endwhile; ?>
            </select>

            <label>City</label>
            <select name='city' id='city' class='form-select mb-2'>
                <option value=''>Select</option>
            </select>
        </div>

        <div class='mb-3'>
            <button type='button' class='btn btn-primary btn-sm' onclick='addRoom()'>+ Add Room Category</button>
        </div>

        <div id='roomWrapper'></div>

        <button class='btn btn-success mt-3'>Save Hotel</button>
    </form>
</div>


<script>
function addRoom(){
    $("#roomWrapper").append(`
    <div class='room-block'>
        <h6>Room Category</h6>
        <input name='room_cat[]' class='form-control mb-2' required>

        <table class='table table-bordered season-table'>
            <thead>
                <tr>
                    <th>Room Price</th>
                    <th>EA</th>
                    <th>EC</th>
                    <th>NoBed</th>
                    <th>From</th>
                    <th>To</th>
                    <th></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>

        <button type='button' class='btn btn-outline-primary btn-sm' onclick='addSeason(this)'>+ Add Seasonal Rate</button>
    </div>
    `);
}

function addSeason(btn){
    let roomBlock = $(btn).closest(".room-block");
    let i = roomBlock.index(); // <-- room index

    $(btn).prev().find("tbody").append(`
    <tr>
        <td><input name="price[${i}][]" class="form-control"></td>
        <td><input name="extra_adult[${i}][]" class="form-control"></td>
        <td><input name="extra_child[${i}][]" class="form-control"></td>
        <td><input name="no_bed[${i}][]" class="form-control"></td>
        <td><input type="date" name="from[${i}][]" class="form-control"></td>
        <td><input type="date" name="to[${i}][]" class="form-control"></td>
        <td>
            <button type="button" onclick="$(this).closest('tr').remove()" class="btn btn-danger btn-sm">X</button>
        </td>
    </tr>
    `);
}



// load cities
$("#country").change(function(){
    let cid = $(this).val();
    $("#city").html('<option>Loading...</option>');
    $.get("../fetch/get_cities.php?country_id="+cid, function(d){
        $("#city").html(d);
    });
});
</script>

</body>
</html>
