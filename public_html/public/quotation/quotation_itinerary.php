<?php
session_start();
require_once __DIR__ . '/../../config/db_pdo.php';

$quotation_id = (int)($_GET['quotation_id'] ?? 0);
if ($quotation_id <= 0) {
    die("Invalid quotation ID");
}

/* ================= FETCH QUOTATION BASIC ================= */
$q = $pdo->prepare("
    SELECT quotation_no 
    FROM quotations 
    WHERE id = ?
");
$q->execute([$quotation_id]);
$quotation = $q->fetch(PDO::FETCH_ASSOC);

if (!$quotation) {
    die("Quotation not found");
}

/* ================= FETCH DAY WISE TRAVEL ================= */
$stmt = $pdo->prepare("
SELECT 
    qt.id AS travel_id,
    qt.day_no,
    qt.day_date,
    qt.itinerary_text,
    c.name AS city_name
FROM quotation_travels qt
LEFT JOIN cities c ON c.id = qt.city_id
WHERE qt.quotation_id = ?
ORDER BY qt.day_no ASC
");
$stmt->execute([$quotation_id]);
$days = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Quotation Itinerary</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
textarea {
    resize: vertical;
}
.day-title {
    font-weight: bold;
    background: #f8f9fa;
}
</style>
</head>
<body>

<div class="container-fluid mt-4">

    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                Itinerary – Quotation No: <?= htmlspecialchars($quotation['quotation_no']) ?>
            </h5>
        </div>

        <form method="post" action="save_itinerary.php">
            <input type="hidden" name="quotation_id" value="<?= $quotation_id ?>">

            <div class="card-body p-0">
                <table class="table table-bordered table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:8%">Day</th>
                            <th style="width:12%">Date</th>
                            <th style="width:15%">City</th>
                            <th>Itinerary Description</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php if (empty($days)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-danger">
                                No travel plan found for this quotation
                            </td>
                        </tr>
                    <?php else: ?>

                        <?php foreach ($days as $d): ?>
                        <tr>
                            <td class="day-title">
                                Day <?= (int)$d['day_no'] ?>
                            </td>

                            <td>
                                <?= $d['day_date'] 
                                    ? date("d-m-Y", strtotime($d['day_date'])) 
                                    : '-' ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($d['city_name'] ?? '—') ?>
                            </td>

                            <td>
                                <textarea
                                    name="itinerary[<?= (int)$d['travel_id'] ?>]"
                                    class="form-control"
                                    rows="5"
                                    placeholder="Enter itinerary for Day <?= (int)$d['day_no'] ?>"
                                ><?= htmlspecialchars($d['itinerary_text'] ?? '') ?></textarea>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>
                </table>
            </div>

            <div class="card-footer text-end">
                <a href="quotation_view.php?id=<?= $quotation_id ?>" 
                   class="btn btn-secondary">
                    Back
                </a>

                <button type="submit" class="btn btn-success">
                    Save Itinerary
                </button>
            </div>
        </form>
    </div>

</div>

</body>
</html>
