<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();

require_once __DIR__ . '/../../config/db.php';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';


$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$car = $conn->query("SELECT * FROM cars WHERE id=$id")->fetch_assoc();
if (!$car) die("Car not found!");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $car_name = trim($_POST['car_name']);
    $seater   = (int)$_POST['seater'];

    if ($car_name !== '' && $seater > 0) {
        $stmt = $conn->prepare("UPDATE cars SET car_name=?, seater=? WHERE id=?");
        $stmt->bind_param("sii", $car_name, $seater, $id);
        $stmt->execute();
        header("Location: car_list.php?msg=updated");
        exit;
    } else {
        $msg = "Please fill all fields!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Car</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">Edit Car</div>
        <div class="card-body">
            <?php if (!empty($msg)): ?><div class="alert alert-danger"><?= $msg ?></div><?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Car Name</label>
                    <input type="text" name="car_name" class="form-control" value="<?= htmlspecialchars($car['car_name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Seater</label>
                    <input type="number" name="seater" class="form-control" min="1" value="<?= $car['seater'] ?>" required>
                </div>
                <a href="car_list.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Update</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
