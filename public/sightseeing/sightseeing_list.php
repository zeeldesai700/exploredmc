<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();

require_once __DIR__ . '/../../config/db.php';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
/* 🔹 Fetch sightseeing list */
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

/* 🔹 Car Rates */
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
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sightseeing List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
.table td,
.table th{
    vertical-align: middle;
    padding:6px 8px;
    font-size:13px;
}

.table-sm td,
.table-sm th{
    font-size:12px;
    padding:4px 6px;
}

.itinerary-box{
    max-height:100px;
    overflow:auto;
    white-space:pre-line;
    font-size:12px;
    line-height:1.4;
}

.main-table{
    table-layout:auto;
}

.car-table{
    font-size:12px;
    width:100%;
}

.car-table th,
.car-table td{
    padding:4px 6px;
    white-space:nowrap;
}

.activities-table{
    width:100%;
}

.activities-table th,
.activities-table td{
    padding:4px 6px;
}
td{
    word-break:break-word;
}
    </style>
</head>

<body class="bg-light">
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Sightseeing List</h3>
        <a href="sightseeing_add.php" class="btn btn-success">+ Add Sightseeing</a>
    </div>

    <!-- 🔍 LIVE SEARCH -->
    <div class="row mb-3">
        <div class="col-md-4">
            <input type="text"
                   id="sightseeingSearch"
                   class="form-control"
                   placeholder="🔍 Search sightseeing name...">
        </div>
    </div>

    <table class="table table-bordered table-striped align-middle main-table">
        <thead class="table-dark">
<tr>
    <th width="160">Transfer</th>
    <th width="70">Country</th>
    <th width="70">City</th>
    <th width="80">Pickup</th>
    <th width="50">Guide</th>
    <th width="290">Itinerary</th>
    <th width="280">Activities</th>
    <th width="420">Car Rates</th>
<th width="65">Action</th>
</tr>
</thead>

        <tbody id="sightseeingTableBody">
        <?php foreach ($sightseeings as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['name']) ?></td>
                <td><?= htmlspecialchars($s['country_name']) ?></td>
                <td><?= htmlspecialchars($s['city_name']) ?></td>
                <td><?= $s['pickup_name'] ? htmlspecialchars($s['pickup_name']) : '<span class="text-muted">—</span>' ?></td>
                <td class="fw-bold text-primary">$ <?= number_format($s['guide_rate'], 0) ?></td>

                <!-- ✅ ITINERARY -->
                <td>
                    <?php if (!empty($s['itinerary'])): ?>
                        <div class="itinerary-box">
                            <?= nl2br(htmlspecialchars($s['itinerary'])) ?>
                        </div>
                    <?php else: ?>
                        <span class="text-muted">No itinerary</span>
                    <?php endif; ?>
                </td>

                <!-- ACTIVITIES -->
                <td>
                    <?php if (!empty($activities_by_sightseeing[$s['id']])): ?>
                        <table class="table table-sm table-bordered mb-0 activities-table">
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
                                        <td>$ <?= number_format($a['adult_price'], 0) ?></td>
                                        <td>$ <?= number_format($a['child_price'], 0) ?></td>
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
                        <table class="table table-sm table-bordered mb-0 car-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:180px">Car</th>
<th style="width:110px">From</th>
<th style="width:110px">To</th>
<th style="width:80px">Half Day</th>
<th style="width:80px">Full Day</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($car_by_sightseeing[$s['id']] as $cr): ?>
                                    <tr>
    <td>
        <?= $cr['seater'] ?> Seater<br>
<small class="text-muted"><?= htmlspecialchars($cr['car_name']) ?></small>
</td>
    <td><?= date('d M Y', strtotime($cr['start_date'])) ?></td>
    <td><?= date('d M Y', strtotime($cr['end_date'])) ?></td>
    <td class="text-center">$ <?= number_format($cr['half_day'], 0) ?></td>
<td class="text-center">$ <?= number_format($cr['full_day'], 0) ?></td>
</tr>

                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <span class="text-muted">No car rates</span>
                    <?php endif; ?>
                </td>

                <!-- ACTION -->
                <td class="text-center">
    <div class="d-grid gap-1">
        <a href="sightseeing_edit.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
        <a href="sightseeing_delete.php?id=<?= $s['id'] ?>"
           onclick="return confirm('Delete this sightseeing?')"
           class="btn btn-sm btn-danger">Delete</a>
    </div>
</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- 🔥 LIVE SEARCH JS -->
<script>
$(document).ready(function () {

    let timer = null;

    $('#sightseeingSearch').on('keyup', function () {
        clearTimeout(timer);

        let keyword = $(this).val();

        timer = setTimeout(function () {
            $.ajax({
                url: 'ajax_sightseeing_search.php',
                type: 'GET',
                data: { q: keyword },
                success: function (html) {
                    $('#sightseeingTableBody').html(html);
                }
            });
        }, 300);
    });

});
</script>

</body>
</html>
