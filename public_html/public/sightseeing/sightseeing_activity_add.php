<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();

require_once __DIR__ . '/../../config/db.php';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
// Get sightseeing id
if (!isset($_GET['sightseeing_id'])) {
    die("Sightseeing ID missing.");
}
$sightseeing_id = intval($_GET['sightseeing_id']);

// Fetch sightseeing name
$stmt = $conn->prepare("SELECT name FROM sightseeings WHERE id = ?");
$stmt->bind_param("i", $sightseeing_id);
$stmt->execute();
$sightseeing = $stmt->get_result()->fetch_assoc();
if (!$sightseeing) {
    die("Sightseeing not found.");
}

// Insert activity (with adult & child price)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $activity_name = trim($_POST['activity_name'] ?? '');
    $adult_price   = ($_POST['adult_price'] !== '') ? (float)$_POST['adult_price'] : 0;
    $child_price   = ($_POST['child_price'] !== '') ? (float)$_POST['child_price'] : 0;

    if (!empty($activity_name)) {

        $stmt = $conn->prepare("
            INSERT INTO sightseeing_activities 
            (sightseeing_id, activity_name, adult_price, child_price)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("isdd", $sightseeing_id, $activity_name, $adult_price, $child_price);
        $stmt->execute();

        header("Location: sightseeing_list.php");
        exit;

    } else {
        $error = "Please enter activity name.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Activity</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="container py-4">

    <h3>Add Activity for 
        <span class="text-primary"><?= htmlspecialchars($sightseeing['name']) ?></span>
    </h3>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="post" class="mb-3">

        <div class="mb-3">
            <label class="form-label">Activity Name</label>
            <input type="text" name="activity_name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Adult Price (₹)</label>
            <input type="number" step="0.01" name="adult_price" class="form-control" placeholder="Enter adult price">
        </div>

        <div class="mb-3">
            <label class="form-label">Child Price (₹)</label>
            <input type="number" step="0.01" name="child_price" class="form-control" placeholder="Enter child price">
        </div>

        <button type="submit" class="btn btn-success">Add Activity</button>
        <a href="sightseeing_list.php" class="btn btn-secondary">Back</a>

    </form>

</body>
</html>
