<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();

require_once __DIR__ . '/../../config/db.php';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';


$cars = $conn->query("SELECT * FROM cars ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

// Delete car
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM cars WHERE id=$id");
    header("Location: car_list.php?msg=deleted");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Car List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="d-flex justify-content-between mb-3">
        <h3>Car List</h3>
        <a href="car_add.php" class="btn btn-success">+ Add Car</a>
    </div>
    <table class="table table-bordered table-striped">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Car</th>
                <th>Seater</th>
                <th>Created</th>
                <th width="180">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cars as $c): ?>
            <tr>
                <td><?= $c['id'] ?></td>
                <td><?= htmlspecialchars($c['car_name']) ?></td>
                <td><?= $c['seater'] ?></td>
                <td><?= $c['created_at'] ?></td>
                <td>
                    <a href="car_edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                    <a href="?delete=<?= $c['id'] ?>" class="btn btn-sm btn-danger"
                       onclick="return confirm('Delete this car?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($cars)): ?>
            <tr><td colspan="5" class="text-center text-muted">No cars added yet</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
