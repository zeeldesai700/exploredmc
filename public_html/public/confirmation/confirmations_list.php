<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

$page_title = "All Confirmations";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';

// Fetch all confirmations
$sql = "
SELECT 
    cf.id,
    cf.passenger_mobile,
    q.quotation_no,
    c.name AS customer_name,
    (
        SELECT MIN(travel_date)
        FROM confirmations_travels ct
        WHERE ct.confirmation_id = cf.id
    ) AS travel_date
FROM confirmations cf
LEFT JOIN quotations q 
    ON cf.quotation_id = q.id
LEFT JOIN customers c 
    ON q.customer_id = c.id
ORDER BY cf.id DESC
";


$res = $conn->query($sql);
?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">All Confirmations</h4>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">

            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Quotation No</th>
                            <th>Customer</th>
                            <th>Travel Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while ($r = $res->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int)$r['id'] ?></td>
                            <td><?= htmlspecialchars($r['quotation_no']) ?></td>
                            <td><?= htmlspecialchars($r['customer_name']) ?></td>
                            <td><?= htmlspecialchars($r['travel_date']) ?></td>

                            <td>
                                <a class="btn btn-sm btn-outline-primary"
                                   href="view_confirmation.php?id=<?= (int)$r['id'] ?>">View</a>

                                <a class="btn btn-sm btn-outline-warning"
                                   href="edit_confirmation.php?id=<?= (int)$r['id'] ?>">Edit</a>

                                <a class="btn btn-sm btn-outline-success"
                                   href="confirmation_pdf.php?id=<?= (int)$r['id'] ?>">PDF</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
