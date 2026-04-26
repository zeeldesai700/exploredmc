<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();

require_once __DIR__ . '/../../config/db.php';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';

// Handle add country
if (isset($_POST['add_country'])) {
    $name = trim($_POST['country_name']);
    if (!empty($name)) {
        $stmt = $conn->prepare("INSERT INTO countries (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        if ($stmt->execute()) {
            $msg = "✅ Country added successfully!";
        } else {
            $msg = "❌ Error: " . $stmt->error;
        }
    }
}

// Handle add city
if (isset($_POST['add_city'])) {
    $country_id = $_POST['country_id'];
    $name = trim($_POST['city_name']);
    if (!empty($country_id) && !empty($name)) {
        $stmt = $conn->prepare("INSERT INTO cities (country_id, name) VALUES (?, ?)");
        $stmt->bind_param("is", $country_id, $name);
        if ($stmt->execute()) {
            $msg = "✅ City added successfully!";
        } else {
            $msg = "❌ Error: " . $stmt->error;
        }
    }
}

// Fetch lists
$countries = $conn->query("SELECT id, name FROM countries ORDER BY id DESC");
$cities = $conn->query("
    SELECT c.id, c.name AS city_name, c.country_id, co.name AS country_name
    FROM cities c
    LEFT JOIN countries co ON co.id = c.country_id
    ORDER BY c.id DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Country & City</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">

    <h3 class="mb-4">Add Country & City</h3>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-info"><?= $msg ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Add Country -->
        <div class="col-md-6">
            <div class="card shadow-sm p-3 mb-3">
                <h5>Add Country</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Country Name</label>
                        <input type="text" name="country_name" class="form-control" required>
                    </div>
                    <button type="submit" name="add_country" class="btn btn-primary">Add Country</button>
                </form>
            </div>
        </div>

        <!-- Add City -->
        <div class="col-md-6">
            <div class="card shadow-sm p-3 mb-3">
                <h5>Add City</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Select Country</label>
                        <select name="country_id" class="form-select" required>
                            <option value="">Select Country</option>
                            <?php
                            $country_dropdown = $conn->query("SELECT id, name FROM countries ORDER BY name");
                            while ($row = $country_dropdown->fetch_assoc()):
                            ?>
                                <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">City Name</label>
                        <input type="text" name="city_name" class="form-control" required>
                    </div>

                    <button type="submit" name="add_city" class="btn btn-success">Add City</button>
                </form>
            </div>
        </div>
    </div>

    <!-- COUNTRY LIST -->
    <div class="card shadow-sm p-3 mt-4">
        <h5>Country List</h5>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th width="80">ID</th>
                    <th>Country Name</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $countries->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- CITY LIST -->
    <div class="card shadow-sm p-3 mt-4 mb-5">
        <h5>City List</h5>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th width="80">City ID</th>
                    <th>City Name</th>
                    <th width="120">Country ID</th>
                    <th>Country Name</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $cities->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['city_name']) ?></td>
                        <td><?= $row['country_id'] ?></td>
                        <td><?= htmlspecialchars($row['country_name']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
