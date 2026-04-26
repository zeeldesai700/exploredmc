<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();

require_once __DIR__ . '/../../config/db.php';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
// Fetch countries
$countries = $conn->query("SELECT * FROM countries ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Fetch Cars
$cars = $conn->query("SELECT * FROM cars ORDER BY car_name")->fetch_all(MYSQLI_ASSOC);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch Sightseeing with guide_rate
$stmt = $conn->prepare("SELECT * FROM sightseeings WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$sight = $stmt->get_result()->fetch_assoc();

if (!$sight) {
    die("Sightseeing not found!");
}

/* ---------- UPDATE SIGHTSEEING ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_sightseeing'])) {

    $name       = trim($_POST['name']);
    $country_id = (int)$_POST['country_id'];
    $city_id    = (int)$_POST['city_id'];
    $guide_rate = ($_POST['guide_rate'] !== "") ? (float)$_POST['guide_rate'] : 0;
    $pickup_point_id = !empty($_POST['pickup_point_id'])
        ? (int)$_POST['pickup_point_id']
        : null;

    $itinerary = $_POST['itinerary'] ?? '';

    $stmt = $conn->prepare("
        UPDATE sightseeings 
        SET name = ?, 
            country_id = ?, 
            city_id = ?, 
            pickup_point_id = ?, 
            guide_rate = ?, 
            itinerary = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "siiidsi",
        $name,
        $country_id,
        $city_id,
        $pickup_point_id,
        $guide_rate,
        $itinerary,
        $id
    );

    if ($stmt->execute()) {
        header("Location: sightseeing_list.php?msg=updated");
        exit;
    } else {
        $msg = "Error: " . $stmt->error;
    }
}


/* ---------- ADD ACTIVITY ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_activity'])) {
    $act_name = trim($_POST['activity_name']);
    $adult    = ($_POST['adult_price'] !== '') ? (float)$_POST['adult_price'] : 0;
    $child    = ($_POST['child_price'] !== '') ? (float)$_POST['child_price'] : 0;

    if ($act_name !== '') {
        $stmt = $conn->prepare("
            INSERT INTO sightseeing_activities (sightseeing_id, activity_name, adult_price, child_price)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("isdd", $id, $act_name, $adult, $child);
        $stmt->execute();
    }
}

/* ---------- DELETE ACTIVITY ---------- */
if (isset($_GET['delete_activity'])) {
    $act_id = (int)$_GET['delete_activity'];
    $conn->query("DELETE FROM sightseeing_activities WHERE id = $act_id AND sightseeing_id = $id");
}

/* ---------- UPDATE SINGLE ACTIVITY ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_activity'])) {

    $act_id = (int)$_POST['act_id'];
    $name   = trim($_POST['act_name']);
    $adult  = ($_POST['adult_price'] !== '') ? (float)$_POST['adult_price'] : 0;
    $child  = ($_POST['child_price'] !== '') ? (float)$_POST['child_price'] : 0;

    $stmt = $conn->prepare("
        UPDATE sightseeing_activities
        SET activity_name = ?, adult_price = ?, child_price = ?
        WHERE id = ? AND sightseeing_id = ?
    ");
    $stmt->bind_param("sddii", $name, $adult, $child, $act_id, $id);
    $stmt->execute();
}

/* ---------- CAR RATES: ADD ---------- */
/* ---------- CAR RATES: ADD (DATE-WISE) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_car_rate'])) {

    $stmt = $conn->prepare("
        INSERT INTO sightseeing_car_rates_dates
        (sightseeing_id, car_id, start_date, end_date, half_day, full_day)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iissdd",
        $id,
        $_POST['car_id'],
        $_POST['start_date'],
        $_POST['end_date'],
        $_POST['half_day'],
        $_POST['full_day']
    );

    $stmt->execute();
}

/* ---------- CAR RATES: DELETE ---------- */
if (isset($_GET['delete_car_rate'])) {
    $rate_id = (int)$_GET['delete_car_rate'];
    $conn->query("
        DELETE FROM sightseeing_car_rates_dates
        WHERE id = $rate_id AND sightseeing_id = $id
    ");
}


/* ---------- CAR RATES: UPDATE ---------- */
/* ---------- CAR RATES: UPDATE (DATE-WISE) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_car_rate'])) {

    $stmt = $conn->prepare("
        UPDATE sightseeing_car_rates_dates
        SET 
            car_id = ?, 
            start_date = ?, 
            end_date = ?, 
            half_day = ?, 
            full_day = ?
        WHERE id = ? AND sightseeing_id = ?
    ");

    $stmt->bind_param(
        "issddii",
        $_POST['car_id'],
        $_POST['start_date'],
        $_POST['end_date'],
        $_POST['half_day'],
        $_POST['full_day'],
        $_POST['rate_id'],
        $id
    );

    $stmt->execute();
}

/* ---------- Load Cities ---------- */
$cities = [];
if (!empty($sight['country_id'])) {
    $res = $conn->query("SELECT id, name FROM cities WHERE country_id = {$sight['country_id']} ORDER BY name");
    $cities = $res->fetch_all(MYSQLI_ASSOC);
}

/* ---------- Load Pickup Points ---------- */
$pickup_points = [];
if (!empty($sight['city_id'])) {
    $res = $conn->query("
        SELECT id, pickup_name 
        FROM pickup_points 
        WHERE city_id = {$sight['city_id']}
        ORDER BY pickup_name
    ");
    $pickup_points = $res->fetch_all(MYSQLI_ASSOC);
}

/* ---------- Load Activities ---------- */
$activities = $conn->query("
    SELECT * FROM sightseeing_activities
    WHERE sightseeing_id = $id 
    ORDER BY id DESC
")->fetch_all(MYSQLI_ASSOC);

/* ---------- Load Car Rates ---------- */
$car_rates = $conn->query("
    SELECT d.*, c.car_name, c.seater
    FROM sightseeing_car_rates_dates d
    JOIN cars c ON d.car_id = c.id
    WHERE d.sightseeing_id = $id
    ORDER BY d.start_date DESC
")->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Sightseeing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="bg-light">

<div class="container mt-5">

    <?php if (!empty($msg)): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="card shadow-lg mb-4">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Edit Sightseeing</h4>
        </div>
        <div class="card-body">

            <!-- MAIN SIGHTSEEING UPDATE -->
            <form method="post">
                <input type="hidden" name="update_sightseeing" value="1">

                <div class="row mb-3">

                    <div class="col-md-4">
                        <label class="form-label">Country</label>
                        <select id="country" name="country_id" class="form-select" required>
                            <option value="">-- Select Country --</option>
                            <?php foreach ($countries as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($c['id'] == $sight['country_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">City</label>
                        <select id="city" name="city_id" class="form-select" required>
                            <option value="">-- Select City --</option>
                            <?php foreach ($cities as $ci): ?>
                                <option value="<?= $ci['id'] ?>" <?= ($ci['id'] == $sight['city_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ci['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                 
                    <div class="col-md-4">
    <label class="form-label">Pickup Point</label>
    <select id="pickup_point" name="pickup_point_id" class="form-select">
        <option value="">-- Select Pickup Point --</option>
        <?php foreach ($pickup_points as $pp): ?>
            <option value="<?= $pp['id'] ?>"
                <?= ($pp['id'] == $sight['pickup_point_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($pp['pickup_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

                    <div class="col-md-4">
                        <label class="form-label">Sightseeing Name</label>
                        <input type="text" name="name" class="form-control"
                               value="<?= htmlspecialchars($sight['name']) ?>" required>
                    </div>

                    <!-- ⭐ NEW FIELD: GUIDE RATE ⭐ -->
                    <div class="col-md-4 mt-3">
                        <label class="form-label">Guide Rate ($)</label>
                        <input type="number" step="0.01" name="guide_rate" class="form-control"
                               value="<?= htmlspecialchars($sight['guide_rate']) ?>" required>
                    </div>

                    <!-- ✅ ITINERARY -->
                <div class="col-md-12">
                    <label class="form-label">Itinerary</label>
                    <textarea name="itinerary" class="form-control" rows="4"
                        placeholder="Day-wise sightseeing itinerary"><?= htmlspecialchars($sight['itinerary']) ?></textarea>
                </div>


                </div>

                <div class="d-flex justify-content-between">
                    <a href="sightseeing_list.php" class="btn btn-secondary">Back</a>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>

            </form>
        </div>
    </div>

    <!-- ACTIVITIES SECTION -->
    <div class="card shadow-lg mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Activities</h5>
        </div>

        <div class="card-body">

            <!-- Add Activity -->
            <form method="post" class="row g-2 mb-4">
                <input type="hidden" name="add_activity" value="1">

                <div class="col-md-4">
                    <input type="text" name="activity_name" class="form-control" placeholder="Activity Name" required>
                </div>

                <div class="col-md-3">
                    <input type="number" step="0.01" name="adult_price" class="form-control"
                           placeholder="Adult Price ($)">
                </div>

                <div class="col-md-3">
                    <input type="number" step="0.01" name="child_price" class="form-control"
                           placeholder="Child Price ($)">
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-success w-100">Add</button>
                </div>
            </form>

            <!-- Activity List -->
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Activity</th>
                        <th>Adult</th>
                        <th>Child</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($activities as $a): ?>
                    <tr>
                        <form method="post">
                            <input type="hidden" name="update_activity" value="1">
                            <input type="hidden" name="act_id" value="<?= $a['id'] ?>">

                            <td>
                                <input type="text" name="act_name" class="form-control"
                                       value="<?= htmlspecialchars($a['activity_name']) ?>">
                            </td>

                            <td>
                                <input type="number" step="0.01" name="adult_price" class="form-control"
                                       value="<?= htmlspecialchars($a['adult_price']) ?>">
                            </td>

                            <td>
                                <input type="number" step="0.01" name="child_price" class="form-control"
                                       value="<?= htmlspecialchars($a['child_price']) ?>">
                            </td>

                            <td class="text-center">
                                <button class="btn btn-primary btn-sm">Save</button>
                                <a href="?id=<?= $id ?>&delete_activity=<?= $a['id'] ?>"
                                   onclick="return confirm('Delete this activity?')"
                                   class="btn btn-danger btn-sm">Delete</a>
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

        </div>
    </div>

    <!-- CAR RATES SECTION -->
    <div class="card shadow-lg mb-5">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">Car Rates</h5>
        </div>

        <div class="card-body">

            <!-- Add Car Rate -->
            <form method="post" class="row g-2 mb-4">
    <input type="hidden" name="add_car_rate" value="1">

    <div class="col-md-3">
        <select name="car_id" class="form-select" required>
            <option value="">Select Car</option>
            <?php foreach ($cars as $car): ?>
                <option value="<?= $car['id'] ?>">
                    <?= htmlspecialchars($car['car_name']) ?> (<?= $car['seater'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-2">
        <input type="date" name="start_date" class="form-control" required>
    </div>

    <div class="col-md-2">
        <input type="date" name="end_date" class="form-control" required>
    </div>

    <div class="col-md-2">
        <input type="number" step="0.01" name="half_day" class="form-control"
               placeholder="Half Day $">
    </div>

    <div class="col-md-2">
        <input type="number" step="0.01" name="full_day" class="form-control"
               placeholder="Full Day $">
    </div>

    <div class="col-md-1">
        <button class="btn btn-info w-100">Add</button>
    </div>
</form>


            <!-- Car Rate List -->
            <table class="table table-bordered">
<thead>
<tr>
    <th>Car</th>
    <th>From</th>
    <th>To</th>
    <th>Half Day</th>
    <th>Full Day</th>
    <th>Action</th>
</tr>
</thead>

<tbody>
<?php foreach ($car_rates as $cr): ?>
<tr>
<form method="post">
<input type="hidden" name="update_car_rate" value="1">
<input type="hidden" name="rate_id" value="<?= $cr['id'] ?>">

<td>
<select name="car_id" class="form-select">
<?php foreach ($cars as $car): ?>
<option value="<?= $car['id'] ?>"
    <?= ($car['id'] == $cr['car_id']) ? 'selected' : '' ?>>
    <?= htmlspecialchars($car['car_name']) ?> (<?= $car['seater'] ?>)
</option>
<?php endforeach; ?>
</select>
</td>

<td>
<input type="date" name="start_date" class="form-control"
       value="<?= $cr['start_date'] ?>">
</td>

<td>
<input type="date" name="end_date" class="form-control"
       value="<?= $cr['end_date'] ?>">
</td>

<td>
<input type="number" step="0.01" name="half_day" class="form-control"
       value="<?= $cr['half_day'] ?>">
</td>

<td>
<input type="number" step="0.01" name="full_day" class="form-control"
       value="<?= $cr['full_day'] ?>">
</td>

<td class="text-center">
<button class="btn btn-primary btn-sm">Save</button>
<a href="?id=<?= $id ?>&delete_car_rate=<?= $cr['id'] ?>"
   onclick="return confirm('Delete this rate?')"
   class="btn btn-danger btn-sm">Delete</a>
</td>

</form>
</tr>
<?php endforeach; ?>
</tbody>
</table>


        </div>
    </div>
</div>

<script>
// Load cities on country change
$('#country').on('change', function () {
    const cid = $(this).val();
    $('#city').html('<option>Loading...</option>');
    $('#pickup_point').html('<option value="">-- Select Pickup Point --</option>');

    if (cid) {
        $.get('../fetch/get_cities.php?country_id=' + cid, function (html) {
            $('#city').html(html);
        });
    } else {
        $('#city').html('<option value="">-- Select City --</option>');
    }
});

// Load pickup points on city change
$('#city').on('change', function () {
    const city_id = $(this).val();
    $('#pickup_point').html('<option>Loading...</option>');

    if (city_id) {
        $.post('../fetch/get_pickup_points.php', { city_id }, function (html) {
            $('#pickup_point').html(html);
        });
    } else {
        $('#pickup_point').html('<option value="">-- Select Pickup Point --</option>');
    }
});
</script>


</body>
</html>
