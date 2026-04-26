<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();

require_once __DIR__ . '/../../config/db.php';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';

/* Fetch countries */
$countries = $conn->query("SELECT * FROM countries ORDER BY name")->fetch_all(MYSQLI_ASSOC);

/* Fetch cars */
$cars = $conn->query("SELECT * FROM cars ORDER BY car_name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name            = $_POST['name'];
    $country_id      = $_POST['country_id'];
    $city_id         = $_POST['city_id'];
    $pickup_point_id = $_POST['pickup_point_id'] ?? null;
    $guide_rate      = $_POST['guide_rate'] ?? 0;
    $itinerary       = $_POST['itinerary'] ?? '';

    /* INSERT SIGHTSEEING */
    $stmt = $conn->prepare("
        INSERT INTO sightseeings
        (name, country_id, city_id, pickup_point_id, guide_rate, itinerary)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("siiids",
        $name,
        $country_id,
        $city_id,
        $pickup_point_id,
        $guide_rate,
        $itinerary
    );

    if ($stmt->execute()) {

        $sightseeing_id = $stmt->insert_id;

        /* ACTIVITIES */
        if (!empty($_POST['activity_name'])) {
            $stmtAct = $conn->prepare("
                INSERT INTO sightseeing_activities
                (sightseeing_id, activity_name, adult_price, child_price)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($_POST['activity_name'] as $i => $act_name) {
                if ($act_name !== '') {
                    $stmtAct->bind_param(
                        "isdd",
                        $sightseeing_id,
                        $act_name,
                        $_POST['activity_price'][$i],
                        $_POST['activity_child_price'][$i]
                    );
                    $stmtAct->execute();
                }
            }
        }

        /* CAR RATES */
        /* DATE-WISE CAR RATES */
if (!empty($_POST['car_id'])) {

    $stmtCar = $conn->prepare("
        INSERT INTO sightseeing_car_rates_dates
        (sightseeing_id, car_id, start_date, end_date, half_day, full_day)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($_POST['car_id'] as $i => $car_id) {

        if (empty($car_id)) continue;

        $stmtCar->bind_param(
            "iissdd",
            $sightseeing_id,
            $car_id,
            $_POST['car_from_date'][$i],
            $_POST['car_to_date'][$i],
            $_POST['car_half_day'][$i],
            $_POST['car_full_day'][$i]
        );

        $stmtCar->execute();
    }

        }

        $msg = "Sightseeing added successfully!";
    } else {
        $msg = "Error: " . $stmt->error;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Sightseeing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="container py-4">

<h3>Add Sightseeing</h3>
<?php if (!empty($msg)) echo "<div class='alert alert-info'>$msg</div>"; ?>

<form method="post" class="row g-3">

    <div class="col-md-4">
        <label class="form-label">Sightseeing Name</label>
        <input type="text" name="name" class="form-control" required>
    </div>

    <div class="col-md-3">
        <label class="form-label">Guide Rate (₹)</label>
        <input type="number" step="0.01" name="guide_rate" class="form-control" required>
    </div>

    <div class="col-md-3">
        <label class="form-label">Country</label>
        <select class="form-select" name="country_id" id="country" required>
            <option value="">-- Select Country --</option>
            <?php foreach ($countries as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label">City</label>
        <select class="form-select" name="city_id" id="city" required>
            <option value="">-- Select City --</option>
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label">Pickup Point</label>
        <select class="form-select" name="pickup_point_id" id="pickup_point">
            <option value="">-- Select Pickup Point --</option>
        </select>
    </div>

    <!-- ITINERARY -->
    <div class="col-md-12">
        <label class="form-label">Itinerary</label>
        <textarea name="itinerary" class="form-control" rows="4"
                  placeholder="Day-wise sightseeing itinerary"></textarea>
    </div>

    <!-- ACTIVITIES -->
    <div class="col-md-12">
        <label class="form-label">Activities</label>
        <table class="table table-bordered" id="activityTable">
            <thead>
                <tr>
                    <th>Activity Name</th>
                    <th>Adult Price</th>
                    <th>Child Price</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><input type="text" name="activity_name[]" class="form-control"></td>
                    <td><input type="number" step="0.01" name="activity_price[]" class="form-control"></td>
                    <td><input type="number" step="0.01" name="activity_child_price[]" class="form-control"></td>
                    <td><button type="button" class="btn btn-success addRow">+</button></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- CAR RATES -->
    <div class="col-md-12">
        <label class="form-label">Car Rates</label>
        <table class="table table-bordered" id="carRateTable">
            <thead>
<tr>
  <th>Car</th>
  <th>From Date</th>
  <th>To Date</th>
  <th>Half Day</th>
  <th>Full Day</th>
  <th></th>
</tr>
</thead>

            <tbody>
                <tr>
  <td>
    <select name="car_id[]" class="form-select">
      <option value="">Select Car</option>
      <?php foreach ($cars as $car): ?>
        <option value="<?= $car['id'] ?>">
          <?= htmlspecialchars($car['car_name']) ?> (<?= $car['seater'] ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </td>

  <td><input type="date" name="car_from_date[]" class="form-control" required></td>
  <td><input type="date" name="car_to_date[]" class="form-control" required></td>
  <td><input type="number" step="0.01" name="car_half_day[]" class="form-control"></td>
  <td><input type="number" step="0.01" name="car_full_day[]" class="form-control"></td>

  <td>
    <button type="button" class="btn btn-success addCarRow">+</button>
  </td>
</tr>

            </tbody>
        </table>
    </div>

    <div class="col-12">
        <button class="btn btn-primary">Save</button>
    </div>
</form>

<!-- ✅ EXCEL DOWNLOAD / UPLOAD (UPDATED & COMPLETE) -->
<div class="d-flex justify-content-between align-items-center mt-4">
    <a href="download_sightseeing_template.php" class="btn btn-secondary">
        Download Excel Template
    </a>

    <form action="import_sightseeing_excel.php" method="post"
          enctype="multipart/form-data" class="d-flex gap-2">
        <input type="file" name="excel_file" class="form-control" required accept=".xlsx">
        <button class="btn btn-success">Upload Excel</button>
    </form>
</div>

<script>
$("#country").change(function(){
    let country_id = $(this).val();
    $("#city").html('<option>Loading...</option>');
    $("#pickup_point").html('<option value="">-- Select Pickup Point --</option>');
    if(country_id){
        $.get("../fetch/get_cities.php?country_id=" + country_id, function(data){
            $("#city").html(data);
        });
    }
});

$("#city").change(function(){
    let city_id = $(this).val();
    $("#pickup_point").html('<option>Loading...</option>');
    if(city_id){
        $.post("../fetch/get_pickup_points.php", { city_id }, function(data){
            $("#pickup_point").html(data);
        });
    }
});

$(document).on("click",".addRow",function(){
    $("#activityTable tbody").append(`
        <tr>
            <td><input type="text" name="activity_name[]" class="form-control"></td>
            <td><input type="number" step="0.01" name="activity_price[]" class="form-control"></td>
            <td><input type="number" step="0.01" name="activity_child_price[]" class="form-control"></td>
            <td><button type="button" class="btn btn-danger removeRow">-</button></td>
        </tr>
    `);
});
$(document).on("click",".removeRow",function(){
    $(this).closest("tr").remove();
});

$(document).on("click",".addCarRow",function(){
    $("#carRateTable tbody").append(`$(".carRateTable tbody tr:first").html()`);
});
$(document).on("click",".removeCarRow",function(){
    $(this).closest("tr").remove();
});
</script>

</body>
</html>
