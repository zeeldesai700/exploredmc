<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();

require_once __DIR__ . '/../../config/db.php';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';


$sql = "
SELECT m.*, 
c.name as country_name, 
ci.name as city_name
FROM meals m
JOIN countries c ON m.country_id = c.id
JOIN cities ci ON m.city_id = ci.id
ORDER BY m.id DESC";

$meals = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Meal List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-4">

    <h3>Meal List</h3>

    <a href="meal_add.php" class="btn btn-success mb-3">+ Add Meal</a>

    <table class="table table-bordered table-striped table-sm">
        <thead>
            <tr>
                <th>Category</th>
                <th>Food</th>
                <th>Restaurant</th>

                <th>Adult Price</th>
                <th>Child Price</th>
                <th>No-bed Price</th>

                <th>Country</th>
                <th>City</th>
                <th width="150">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($meals as $m): ?>
            <tr>
                <td><?= htmlspecialchars($m['category']) ?></td>
                <td><?= htmlspecialchars($m['food']) ?></td>
                <td><?= htmlspecialchars($m['restaurant']) ?></td>

                <td>₹ <?= number_format($m['adult_price'],2) ?></td>
                <td>₹ <?= number_format($m['child_price'],2) ?></td>
                <td>₹ <?= number_format($m['no_bed_price'],2) ?></td>

                <td><?= htmlspecialchars($m['country_name']) ?></td>
                <td><?= htmlspecialchars($m['city_name']) ?></td>

                <td>
                    <a href="meal_edit.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                    <a href="meal_delete.php?id=<?= $m['id'] ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Delete this meal?');">Delete</a>
                </td>
            </tr>
        <?php endforeach;?>
        </tbody>
    </table>
</div>

</body>
</html>
