<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

/* =========================
   VALIDATE CONFIRMATION ID
========================= */
$cid = (int)($_GET['id'] ?? 0);
if ($cid <= 0) {
    die("Invalid confirmation ID");
}

/* =========================
   FETCH CONFIRMATION
========================= */
$stmt = $conn->prepare("SELECT * FROM confirmations WHERE id=? LIMIT 1");
$stmt->bind_param("i", $cid);
$stmt->execute();
$confirmation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$confirmation) {
    die("Confirmation not found");
}

/* =========================
   FETCH CHILD / INFANT AGES
========================= */
$childAges  = [];
$infantAges = [];

/* Child */
$res = $conn->query("
    SELECT child_age
    FROM confirmation_child_ages
    WHERE confirmation_id = $cid
");
while ($r = $res->fetch_assoc()) {
    $childAges[] = (int)$r['child_age'];
}

/* Infant */
$res = $conn->query("
    SELECT infant_age
    FROM confirmation_infant_ages
    WHERE confirmation_id = $cid
");
while ($r = $res->fetch_assoc()) {
    $infantAges[] = (int)$r['infant_age'];
}

/* =========================
   FETCH HOTELS
========================= */
$hotels = [];
$res = $conn->query("
    SELECT *
    FROM confirmations_hotels
    WHERE confirmation_id = $cid
    ORDER BY id
");
while ($row = $res->fetch_assoc()) {
    $hotels[] = $row;
}

/* =========================
   FETCH TRAVEL PLAN
========================= */
$travels = [];
$res = $conn->query("
    SELECT *
    FROM confirmations_travels
    WHERE confirmation_id = $cid
    ORDER BY travel_date, id
");
while ($row = $res->fetch_assoc()) {
    $travels[] = $row;
}

/* =========================
   UPDATE CONFIRMATION
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* Passenger */
    $stmt = $conn->prepare("
    UPDATE confirmations
    SET passenger_name = ?
    WHERE id = ?
");
$stmt->bind_param(
    "si",
    $_POST['passenger_name'],
    $cid
);
$stmt->execute();
$stmt->close();

    /* -------------------------
       CHILD AGES
    ------------------------- */
    $conn->query("DELETE FROM confirmation_child_ages WHERE confirmation_id=$cid");

    if (!empty($_POST['child_ages'])) {
        $stmt = $conn->prepare("
            INSERT INTO confirmation_child_ages (confirmation_id, child_age)
            VALUES (?, ?)
        ");
        foreach ($_POST['child_ages'] as $age) {
            $age = (int)$age;
            $stmt->bind_param("ii", $cid, $age);
            $stmt->execute();
        }
        $stmt->close();
    }

    /* -------------------------
       INFANT AGES
    ------------------------- */
    $conn->query("DELETE FROM confirmation_infant_ages WHERE confirmation_id=$cid");

    if (!empty($_POST['infant_ages'])) {
        $stmt = $conn->prepare("
            INSERT INTO confirmation_infant_ages (confirmation_id, infant_age)
            VALUES (?, ?)
        ");
        foreach ($_POST['infant_ages'] as $age) {
            $age = (int)$age;
            $stmt->bind_param("ii", $cid, $age);
            $stmt->execute();
        }
        $stmt->close();
    }

    /* -------------------------
       HOTELS
    ------------------------- */
    if (!empty($_POST['hotels'])) {
        foreach ($_POST['hotels'] as $hid => $h) {

            $stmt = $conn->prepare("
    UPDATE confirmations_hotels
    SET
        hotel_name = ?,
        hotel_confirmation_no = ?,
        category = ?,
        room_category = ?,
        due_date = ?,
        payment_amount = ?
    WHERE id = ? AND confirmation_id = ?
");
            $stmt->bind_param(
    "sssssdii",
    $h['hotel_name'],
    $h['hotel_confirmation_no'],
    $h['hotel_category'],
    $h['room_category'],
    $h['due_date'],
    $h['payment_amount'],
    $hid,
    $cid
);
            $stmt->execute();
            $stmt->close();
        }
    }

    /* -------------------------
       TRAVEL PLAN
    ------------------------- */
    if (!empty($_POST['travel'])) {
        foreach ($_POST['travel'] as $tid => $t) {

            $stmt = $conn->prepare("
                UPDATE confirmations_travels
                SET
                    flight_name = ?,
                    pickup_point = ?,
                    sightseeing = ?
                WHERE id = ? AND confirmation_id = ?
            ");
            $stmt->bind_param(
                "sssii",
                $t['flight_name'],
                $t['pickup_point'],
                $t['sightseeing'],
                $tid,
                $cid
            );
            $stmt->execute();
            $stmt->close();
        }
    }

    /* =========================
       ✅ UPDATE AGENT ACCOUNT PRICE
    ========================== */
    if (isset($_POST['total_quotation_price'])) {

        $price = (float)$_POST['total_quotation_price'];
        $confirmation_no = $conn->real_escape_string($confirmation['confirmation_no']);

        $row = $conn->query("
            SELECT id, paid_amount 
            FROM agent_accounts
            WHERE confirmation_no = '$confirmation_no'
            ORDER BY id DESC
            LIMIT 1
        ")->fetch_assoc();

        if ($row) {

            $aid  = (int)$row['id'];
            $paid = (float)$row['paid_amount'];

            if ($price < $paid) {
                die("Error: Price cannot be less than already paid amount");
            }

            $remaining = $price - $paid;
            $status = ($remaining <= 0) ? 'paid' : 'pending';

            $stmt = $conn->prepare("
                UPDATE agent_accounts
                SET total_quotation_price=?, remaining_amount=?, payment_status=?
                WHERE id=?
            ");
            $stmt->bind_param("ddsi", $price, $remaining, $status, $aid);
            $stmt->execute();
            $stmt->close();
        }
    }


    header("Location: confirmations_list.php?msg=updated");
    exit;
}

$priceRow = $conn->query("
    SELECT total_quotation_price
    FROM agent_accounts
    WHERE confirmation_no = '{$confirmation['confirmation_no']}'
    ORDER BY id DESC
    LIMIT 1
")->fetch_assoc();

$totalQuotationPrice = $priceRow['total_quotation_price'] ?? 0;

$page_title = "Edit Confirmation";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>

<div class="container mt-4">

<div class="card shadow-sm">
<div class="card-header">
    <h5 class="mb-0">Edit Confirmation Letter</h5>
</div>

<div class="card-body">

<form method="POST">

<!-- ================= Passenger ================= -->
<h6>Passenger Details</h6>
<div class="row mb-3">
    <div class="col-md-6">
        <label class="form-label">Passenger Name</label>
        <input type="text" name="passenger_name"
               class="form-control"
               value="<?= htmlspecialchars($confirmation['passenger_name']) ?>" required>
    </div>
</div>

<!-- ================= Child Ages ================= -->
<?php if (!empty($childAges)): ?>
<h6 class="mt-3">Child Age Details</h6>
<div class="row">
<?php foreach ($childAges as $age): ?>
    <div class="col-md-2 mb-2">
        <input type="number" name="child_ages[]"
               value="<?= $age ?>" min="2" max="17"
               class="form-control">
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ================= Infant Ages ================= -->
<?php if (!empty($infantAges)): ?>
<h6 class="mt-3">Infant Age Details</h6>
<div class="row">
<?php foreach ($infantAges as $age): ?>
    <div class="col-md-2 mb-2">
        <input type="number" name="infant_ages[]"
               value="<?= $age ?>" min="0" max="3"
               class="form-control">
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ================= Hotels ================= -->
<?php if (!empty($hotels)): ?>
<h5 class="mt-4">Hotel Details</h5>

<div class="table-responsive">
<table class="table table-bordered table-sm align-middle text-center">
<thead class="table-light">
<tr>
    <th>City</th>
    <th>Hotel</th>
    <th>Hotel Conf No</th>
    <th>Category</th>
    <th>Room</th>
    <th>Due Date</th>
    <th>Amount</th>
</tr>
</thead>
<tbody>

<?php foreach ($hotels as $h): ?>
<tr>
    <td><?= htmlspecialchars($h['city_name']) ?></td>
    <td>
  <input type="text"
         name="hotels[<?= $h['id'] ?>][hotel_name]"
         value="<?= htmlspecialchars($h['hotel_name']) ?>"
         class="form-control form-control-sm"
         required>
</td>

    <td>
        <input type="text"
               name="hotels[<?= $h['id'] ?>][hotel_confirmation_no]"
               value="<?= htmlspecialchars($h['hotel_confirmation_no']) ?>"
               class="form-control form-control-sm">
    </td>

    <td>
        <input type="text"
               name="hotels[<?= $h['id'] ?>][hotel_category]"
               value="<?= htmlspecialchars($h['category']) ?>"
               class="form-control form-control-sm">
    </td>

    <td>
        <input type="text"
               name="hotels[<?= $h['id'] ?>][room_category]"
               value="<?= htmlspecialchars($h['room_category']) ?>"
               class="form-control form-control-sm">
    </td>

    <td>
        <input type="date"
               name="hotels[<?= $h['id'] ?>][due_date]"
               value="<?= htmlspecialchars($h['due_date']) ?>"
               class="form-control form-control-sm">
    </td>

    <td>
        <input type="number" step="0.01"
               name="hotels[<?= $h['id'] ?>][payment_amount]"
               value="<?= htmlspecialchars($h['payment_amount']) ?>"
               class="form-control form-control-sm">
    </td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>
<?php endif; ?>

<!-- ================= Travel Plan ================= -->
<h5 class="mt-4">Travel Plan</h5>

<div class="table-responsive">
<table class="table table-bordered table-sm align-middle text-center">
<thead class="table-light">
<tr>
    <th>Date</th>
    <th>Flight Name</th>
    <th>Pickup Point</th>
    <th>Sightseeing</th>
</tr>
</thead>
<tbody>

<?php foreach ($travels as $t): ?>
<tr>
    <td><?= date('d M Y', strtotime($t['travel_date'])) ?></td>

    <td>
        <input type="text"
               name="travel[<?= $t['id'] ?>][flight_name]"
               value="<?= htmlspecialchars($t['flight_name']) ?>"
               class="form-control form-control-sm">
    </td>

    <td>
        <input type="text"
               name="travel[<?= $t['id'] ?>][pickup_point]"
               value="<?= htmlspecialchars($t['pickup_point']) ?>"
               class="form-control form-control-sm">
    </td>

    <td>
        <input type="text"
               name="travel[<?= $t['id'] ?>][sightseeing]"
               value="<?= htmlspecialchars($t['sightseeing']) ?>"
               class="form-control form-control-sm">
    </td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>


<h5 class="mt-4">Quotation Summary</h5>
<div class="row mb-3">
  <div class="col-md-4">
    <label class="form-label fw-bold">Total Quotation Price</label>
    <input type="number" step="0.01" min="0"
           name="total_quotation_price"
           value="<?= htmlspecialchars($totalQuotationPrice) ?>"
           class="form-control" required>
  </div>
</div>

<div class="row mt-4">
  <div class="col-12 text-end">
    <button type="submit" class="btn btn-primary">
      Update Confirmation
    </button>
    <a href="confirmations_list.php" class="btn btn-secondary ms-2">
      Cancel
    </a>
  </div>
</div>

</form>
</div>
</div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>