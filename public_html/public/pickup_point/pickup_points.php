<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();

require_once __DIR__ . '/../../config/db.php';

$page_title = 'Pickup Points';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';

/* ---------------------------------------------------
   FETCH COUNTRIES
--------------------------------------------------- */
$countries = $conn->query("SELECT * FROM countries ORDER BY name")->fetch_all(MYSQLI_ASSOC);

/* ---------------------------------------------------
   ADD / UPDATE PICKUP POINT
--------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pickup_name     = $_POST['pickup_name'];
    $pickup_category = $_POST['pickup_category'];
    $country_id      = (int)$_POST['country_id'];
    $city_id         = (int)$_POST['city_id'];
    $id              = $_POST['id'] ?? '';

    if ($id) {
        $stmt = $conn->prepare("
            UPDATE pickup_points 
            SET pickup_name=?, pickup_category=?, country_id=?, city_id=? 
            WHERE id=?
        ");
        $stmt->bind_param("ssiii", $pickup_name, $pickup_category, $country_id, $city_id, $id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("
            INSERT INTO pickup_points 
            (pickup_name, pickup_category, country_id, city_id) 
            VALUES (?,?,?,?)
        ");
        $stmt->bind_param("ssii", $pickup_name, $pickup_category, $country_id, $city_id);
        $stmt->execute();
    }

    header("Location: pickup_points.php");
    exit;
}

/* ---------------------------------------------------
   DELETE
--------------------------------------------------- */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM pickup_points WHERE id=$id");
    header("Location: pickup_points.php");
    exit;
}

/* ---------------------------------------------------
   FETCH LIST
--------------------------------------------------- */
$sql = "
SELECT 
    pp.*, 
    c.name AS country_name, 
    ci.name AS city_name
FROM pickup_points pp
JOIN countries c ON pp.country_id = c.id
JOIN cities ci ON pp.city_id = ci.id
ORDER BY pp.id ASC
";
$pickup_points = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

/* ---------------------------------------------------
   EDIT MODE
--------------------------------------------------- */
$edit = null;
$cities = [];

if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit = $conn->query("SELECT * FROM pickup_points WHERE id=$id")->fetch_assoc();
    $cities = $conn->query("
        SELECT * FROM cities 
        WHERE country_id=".(int)$edit['country_id']
    )->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Pickup Points</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
<div class="container py-4">

<h3 class="mb-3">Pickup Points</h3>

<!-- ================= ADD / EDIT FORM ================= -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <?= $edit ? "Edit Pickup Point" : "Add Pickup Point" ?>
    </div>
    <div class="card-body">

        <form method="post">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">

            <div class="row mb-3">

                <?php if ($edit): ?>
                <!-- 🔹 SHOW PICKUP ID -->
                <div class="col-md-2">
                    <label class="form-label">Pickup ID</label>
                    <input type="text" class="form-control" value="<?= $edit['id'] ?>" readonly>
                </div>
                <?php endif; ?>

                <div class="col-md-3">
                    <label class="form-label">Pickup Point Name</label>
                    <input type="text" name="pickup_name" class="form-control"
                           value="<?= htmlspecialchars($edit['pickup_name'] ?? '') ?>" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Pickup Category</label>
                    <select name="pickup_category" class="form-select" required>
                        <option value="">-- Select Category --</option>
                        <option value="Airport" <?= ($edit && $edit['pickup_category']=='Airport')?'selected':'' ?>>Airport</option>
                        <option value="Hotel" <?= ($edit && $edit['pickup_category']=='Hotel')?'selected':'' ?>>Hotel</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Country</label>
                    <select id="country" name="country_id" class="form-select" required>
                        <option value="">-- Select Country --</option>
                        <?php foreach($countries as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($edit && $c['id']==$edit['country_id'])?'selected':'' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">City</label>
                    <select id="city" name="city_id" class="form-select" required>
                        <option value="">-- Select City --</option>
                        <?php foreach($cities as $ci): ?>
                            <option value="<?= $ci['id'] ?>" <?= ($edit && $ci['id']==$edit['city_id'])?'selected':'' ?>>
                                <?= htmlspecialchars($ci['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <button type="submit" class="btn btn-success">
                <?= $edit ? "Update" : "Add" ?>
            </button>

            <?php if ($edit): ?>
                <a href="pickup_points.php" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </form>

    </div>
</div>

<!-- ================= LIST TABLE ================= -->
<table class="table table-bordered table-striped">
    <thead class="table-dark">
        <tr>
            <th>ID</th> <!-- 🔹 NEW -->
            <th>Pickup Point</th>
            <th>Category</th>
            <th>Country</th>
            <th>City</th>
            <th width="150">Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($pickup_points as $pp): ?>
        <tr>
            <td class="fw-bold"><?= $pp['id'] ?></td>
            <td><?= htmlspecialchars($pp['pickup_name']) ?></td>
            <td>
                <span class="badge <?= $pp['pickup_category']=='Airport'?'bg-info':'bg-success' ?>">
                    <?= htmlspecialchars($pp['pickup_category']) ?>
                </span>
            </td>
            <td><?= htmlspecialchars($pp['country_name']) ?></td>
            <td><?= htmlspecialchars($pp['city_name']) ?></td>
            <td>
                <a href="pickup_points.php?edit=<?= $pp['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                <a href="pickup_points.php?delete=<?= $pp['id'] ?>"
                   onclick="return confirm('Delete this pickup point?')"
                   class="btn btn-sm btn-danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</div>

<script>
document.getElementById('country').addEventListener('change', function(){
    let cid = this.value;
    fetch('<?= BASE_URL ?>fetch/get_cities.php?country_id=' + cid)
        .then(res => res.text())
        .then(html => {
            document.getElementById('city').innerHTML = html;
        });
});
</script>

</body>
</html>
