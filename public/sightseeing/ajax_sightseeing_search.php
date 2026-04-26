<?php
require_once __DIR__ . '/../../config/db.php';

$q = trim($_GET['q'] ?? '');

$where = '';
if ($q !== '') {
    $safe = $conn->real_escape_string($q);
    $where = "WHERE s.name LIKE '%$safe%'";
}

/* 🔹 Fetch Sightseeing */
$sql = "
    SELECT 
        s.*,
        c.name  AS country_name,
        ci.name AS city_name,
        pp.pickup_name AS pickup_name
    FROM sightseeings s
    JOIN countries c ON s.country_id = c.id
    JOIN cities ci ON s.city_id = ci.id
    LEFT JOIN pickup_points pp ON s.pickup_point_id = pp.id
    $where
    ORDER BY s.id DESC
";

$sightseeings = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

/* 🔹 Activities */
$activities_sql = "
    SELECT a.*, s.id AS sightseeing_id
    FROM sightseeing_activities a
    JOIN sightseeings s ON a.sightseeing_id = s.id
    ORDER BY a.id ASC
";
$activities_result = $conn->query($activities_sql)->fetch_all(MYSQLI_ASSOC);

$activities_by_sightseeing = [];
foreach ($activities_result as $a) {
    $activities_by_sightseeing[$a['sightseeing_id']][] = $a;
}

/* 🔹 DATE-WISE Car Rates */
$car_sql = "
    SELECT 
        d.*, 
        c.car_name, 
        c.seater,
        d.sightseeing_id
    FROM sightseeing_car_rates_dates d
    JOIN cars c ON d.car_id = c.id
    ORDER BY d.start_date ASC
";

$car_result = $conn->query($car_sql)->fetch_all(MYSQLI_ASSOC);

$car_by_sightseeing = [];
foreach ($car_result as $cr) {
    $car_by_sightseeing[$cr['sightseeing_id']][] = $cr;
}

if (empty($sightseeings)) {
    echo '<tr><td colspan="9" class="text-center text-muted">No sightseeing found</td></tr>';
    exit;
}

/* 🔹 OUTPUT TABLE ROWS */
foreach ($sightseeings as $s):
?>
<tr>
    <td><?= htmlspecialchars($s['name']) ?></td>
    <td><?= htmlspecialchars($s['country_name']) ?></td>
    <td><?= htmlspecialchars($s['city_name']) ?></td>
    <td><?= $s['pickup_name'] ? htmlspecialchars($s['pickup_name']) : '<span class="text-muted">—</span>' ?></td>
    <td class="fw-bold text-primary">₹ <?= number_format($s['guide_rate'], 2) ?></td>

    <!-- ITINERARY -->
    <td>
        <?php if (!empty($s['itinerary'])): ?>
            <div style="max-height:120px; overflow:auto; white-space:pre-line;">
                <?= nl2br(htmlspecialchars($s['itinerary'])) ?>
            </div>
        <?php else: ?>
            <span class="text-muted">No itinerary</span>
        <?php endif; ?>
    </td>

    <!-- ACTIVITIES -->
    <td>
        <?php if (!empty($activities_by_sightseeing[$s['id']])): ?>
            <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Activity</th>
                        <th>Adult</th>
                        <th>Child</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($activities_by_sightseeing[$s['id']] as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['activity_name']) ?></td>
                        <td>₹ <?= number_format($a['adult_price'], 2) ?></td>
                        <td>₹ <?= number_format($a['child_price'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <span class="text-muted">No activities</span>
        <?php endif; ?>
    </td>

    <!-- CAR RATES -->
    <td>
        <?php if (!empty($car_by_sightseeing[$s['id']])): ?>
            <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Car</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Half Day</th>
                        <th>Full Day</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($car_by_sightseeing[$s['id']] as $cr): ?>
                    <tr>
                        <td><?= htmlspecialchars($cr['car_name']) ?> (<?= $cr['seater'] ?>)</td>
                        <td><?= date('d M Y', strtotime($cr['start_date'])) ?></td>
                        <td><?= date('d M Y', strtotime($cr['end_date'])) ?></td>
                        <td>₹ <?= number_format($cr['half_day'], 2) ?></td>
                        <td>₹ <?= number_format($cr['full_day'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <span class="text-muted">No car rates</span>
        <?php endif; ?>
    </td>

    <!-- ACTION -->
    <td>
        <a href="sightseeing_edit.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
        <a href="sightseeing_delete.php?id=<?= $s['id'] ?>"
           onclick="return confirm('Delete this sightseeing?')"
           class="btn btn-sm btn-danger mt-1">Delete</a>
    </td>
</tr>
<?php endforeach; ?>
