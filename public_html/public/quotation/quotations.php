<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

/* =========================
   DELETE QUOTATION
========================= */
if (isset($_GET['delete'])) {

    $delete_id = (int)$_GET['delete'];
    if ($delete_id > 0) {

        $conn->begin_transaction();

        try {
            // delete activity rows
            $conn->query("
                DELETE qta
                FROM quotation_travel_activities qta
                JOIN quotation_travels qt
                    ON qt.id = qta.quotation_travel_id
                WHERE qt.quotation_id = $delete_id
            ");

            // delete travel plan
            $conn->query("DELETE FROM quotation_travels WHERE quotation_id = $delete_id");

            // delete hotels
            $conn->query("DELETE FROM quotation_hotels WHERE quotation_id = $delete_id");

            // delete quotation
            $conn->query("DELETE FROM quotations WHERE id = $delete_id");

            $conn->commit();
            header("Location: quotations.php?msg=deleted");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            die("Delete failed: " . $e->getMessage());
        }
    }
}

/* =========================
   FETCH QUOTATIONS
========================= */
$q = trim($_GET['q'] ?? '');

$sql = "
    SELECT q.*, c.name AS customer_name
    FROM quotations q
    JOIN customers c ON q.customer_id = c.id
";

if ($q !== '') {
    $safe = '%' . $conn->real_escape_string($q) . '%';
    $sql .= " WHERE c.name LIKE '$safe' OR q.quotation_no LIKE '$safe'";
}

$sql .= " ORDER BY q.id DESC";

$rows = [];
$res = $conn->query($sql);
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
}
?>

<?php $page_title = 'Quotations'; include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/nav.php'; ?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">All Quotations</h4>
        <a class="btn btn-primary" href="quotation_new.php">+ New Quotation</a>
    </div>

    <form class="mb-3" method="get">
        <div class="input-group">
            <input class="form-control" name="q"
                   placeholder="Search by customer or quotation no"
                   value="<?= htmlspecialchars($q); ?>">
            <button class="btn btn-outline-secondary">Search</button>
        </div>
    </form>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="alert alert-success">Quotation deleted successfully.</div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">

            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                    <thead>
                        <tr>
                            <th>User Name</th>
                            <th>Quotation No</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Created</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php foreach ($rows as $r): ?>
                        <?php
                            $cid = (int)$r['id'];
                            $conf = $conn->query(
                                "SELECT id FROM confirmations WHERE quotation_id = $cid LIMIT 1"
                            );
                            $has_confirmation = ($conf && $conf->num_rows > 0);
                        ?>

                        <tr>
                            <td><?= htmlspecialchars($r['user_name'] ?? '—'); ?></td>
                            <td><?= htmlspecialchars($r['quotation_no']); ?></td>
                            <td><?= htmlspecialchars($r['customer_name']); ?></td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?= htmlspecialchars($r['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars($r['currency']) . ' ' . number_format($r['grand_total'], 2); ?>
                            </td>
                            <td><?= htmlspecialchars($r['created_at']); ?></td>

                            <td class="text-center text-nowrap">
                                <div class="btn-group btn-group-sm" role="group">

                                    <a class="btn btn-outline-info"
                                       href="quotation_itinerary.php?quotation_id=<?= $cid ?>">
                                        Itinerary
                                    </a>

                                    <a class="btn btn-outline-primary"
                                       href="quotation_view.php?id=<?= $cid ?>">
                                        View
                                    </a>

                                    <a class="btn btn-outline-warning"
                                       href="edit_quotation.php?id=<?= $cid ?>">
                                        Edit
                                    </a>

                                    <a class="btn btn-outline-success"
                                       href="quotation_pdf.php?id=<?= $cid ?>">
                                        PDF
                                    </a>

                                    <a class="btn btn-outline-danger"
                                       href="quotations.php?delete=<?= $cid ?>"
                                       onclick="return confirm('Are you sure you want to delete this quotation?');">
                                        Delete
                                    </a>

                                    <?php if ($has_confirmation): ?>
                                        <a class="btn btn-outline-dark"
                                           href="../confirmation/edit_confirmation.php?id=<?= $cid ?>">
                                            Edit Confirmation
                                        </a>
                                    <?php else: ?>
                                        <a class="btn btn-outline-success"
                                           href="../confirmation/confirmation_add.php?quotation_id=<?= $cid ?>">
                                            Add Confirmation
                                        </a>
                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
