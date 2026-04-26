<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

$tab    = $_GET['tab'] ?? 'pending';
$filter = $_GET['filter'] ?? 'all';

/* =========================
   FILTER CONDITIONS
========================= */
$whereDate = "";
if ($filter === 'week') {
    $whereDate = "AND ch.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
} elseif ($filter === 'month') {
    $whereDate = "AND MONTH(ch.due_date) = MONTH(CURDATE())
                  AND YEAR(ch.due_date) = YEAR(CURDATE())";
}
?>

<style>
  .due-soon {
    background-color: #dc3545 !important; /* red */
    color: #ff0000 !important;            /* white */
  }

  .due-soon td {
  background-color: #ff0019 !important; /* red */
    color: #ffffff !important;
  }
</style>

<?php $page_title = 'Hotel Payments'; include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/nav.php'; ?>

<div class="container mt-4">

<h4 class="mb-3">Hotel Payments</h4>

<!-- ================= TABS ================= -->
<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'pending' ? 'active' : '' ?>" href="?tab=pending">
      Pending Payments
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'paid' ? 'active' : '' ?>" href="?tab=paid">
      Payment Done
    </a>
  </li>
</ul>

<?php if ($tab === 'pending'): ?>

<!-- ================= FILTERS ================= -->
<div class="mb-3">
  <a href="?tab=pending&filter=all" class="btn btn-sm <?= $filter==='all'?'btn-primary':'btn-outline-primary' ?>">All</a>
  <a href="?tab=pending&filter=week" class="btn btn-sm <?= $filter==='week'?'btn-primary':'btn-outline-primary' ?>">This Week</a>
  <a href="?tab=pending&filter=month" class="btn btn-sm <?= $filter==='month'?'btn-primary':'btn-outline-primary' ?>">This Month</a>
</div>

<?php
$sql = "
SELECT 
  ch.id,
  ch.city_name,
  ch.hotel_name,
  ch.hotel_confirmation_no,
  ch.category,
  ch.room_category,
  ch.stay_nights,
  ch.rooms,
  ch.due_date,
  ch.payment_amount,
  ch.paid_amount,
  ch.remaining_amount,
  c.confirmation_no
FROM confirmations_hotels ch
JOIN confirmations c ON c.id = ch.confirmation_id
WHERE ch.payment_status = 'pending'
$whereDate
ORDER BY ch.due_date ASC
";
$res = $conn->query($sql);
?>

<table class="table table-bordered table-sm align-middle">
<thead class="table-light">
<tr>
  <th>Confirmation</th>
  <th>City</th>
  <th>Hotel</th>
  <th>Hotel Conf No</th>
  <th>Category</th>
  <th>Nights</th>
  <th>Rooms</th>
  <th>Due Date</th>
  <th>Total</th>
  <th>Paid</th>
  <th>Remaining</th>
  <th>Action</th>
</tr>
</thead>
<tbody>

<?php if ($res->num_rows === 0): ?>
<tr>
  <td colspan="12" class="text-center text-muted">No pending payments</td>
</tr>
<?php endif; ?>

<?php while ($r = $res->fetch_assoc()): ?>
<?php
$dueDate = new DateTime($r['due_date']);
$today   = new DateTime('today');
$limit   = (clone $today)->modify('+10 days');

$isDueSoon = ($dueDate <= $limit);
?>

<tr class="<?= $isDueSoon ? 'due-soon' : '' ?>">

  <td><?= htmlspecialchars($r['confirmation_no']) ?></td>
  <td><?= htmlspecialchars($r['city_name']) ?></td>
  <td><?= htmlspecialchars($r['hotel_name']) ?></td>
  <td><?= htmlspecialchars($r['hotel_confirmation_no']) ?></td>
  <td><?= htmlspecialchars($r['category'].' / '.$r['room_category']) ?></td>
  <td class="text-center"><?= (int)$r['stay_nights'] ?></td>
  <td class="text-center"><?= (int)$r['rooms'] ?></td>
  <td><?= date('d-m-Y', strtotime($r['due_date'])) ?></td>

  <td class="text-end">VND <?= number_format($r['payment_amount'],2) ?></td>
  <td class="text-end text-success">VND <?= number_format($r['paid_amount'],2) ?></td>
  <td class="text-end fw-bold <?= $r['remaining_amount'] > 0 ? 'text-danger':'text-success' ?>">
    VND <?= number_format($r['remaining_amount'],2) ?>
  </td>

  <td class="text-center">

  <?php if ($r['remaining_amount'] > 0): ?>
    <button class="btn btn-danger btn-sm"
      onclick="openPaymentModal(<?= $r['id'] ?>, <?= $r['remaining_amount'] ?>)">
      Pay
    </button>
  <?php endif; ?>

  <button class="btn btn-outline-primary btn-sm ms-1"
    onclick="viewHistory(<?= $r['id'] ?>)">
    👁
  </button>

</td>
</tr>
<?php endwhile; ?>

</tbody>
</table>

<?php else: ?>

<?php
$sql = "
SELECT 
  ch.*,
  c.confirmation_no
FROM confirmations_hotels ch
JOIN confirmations c ON c.id = ch.confirmation_id
WHERE ch.payment_status = 'paid'
ORDER BY ch.paid_date DESC
";
$res = $conn->query($sql);
?>

<table class="table table-bordered table-sm align-middle">
<thead class="table-light">
<tr>
  <th>Confirmation</th>
  <th>City</th>
  <th>Hotel</th>
  <th>Category</th>
  <th>Due Date</th>
  <th>Paid Date</th>
  <th>History</th>
</tr>
</thead>
<tbody>

<?php while ($r = $res->fetch_assoc()): ?>
<tr>
  <td><?= htmlspecialchars($r['confirmation_no']) ?></td>
  <td><?= htmlspecialchars($r['city_name']) ?></td>
  <td><?= htmlspecialchars($r['hotel_name']) ?></td>
  <td><?= htmlspecialchars($r['category'].' / '.$r['room_category']) ?></td>
  <td><?= date('d-m-Y', strtotime($r['due_date'])) ?></td>
  <td><?= date('d-m-Y', strtotime($r['paid_date'])) ?></td>
  <td class="text-center">
  <button class="btn btn-outline-primary btn-sm"
    onclick="viewHistory(<?= $r['id'] ?>)">
    👁
  </button>
</td>
</tr>
<?php endwhile; ?>

</tbody>
</table>

<?php endif; ?>

</div>

<!-- ================= PAYMENT MODAL ================= -->
<div class="modal fade" id="paymentModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Hotel Payment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <label class="form-label">Payment Date</label>
        <input type="date" id="modalPaidDate" class="form-control mb-2" required>

        <label class="form-label">Pay Amount</label>
        <input type="number" id="modalPayAmount" class="form-control" step="0.01" min="0" required>

        <label class="form-label mt-2">Remaining Payment Due Date</label>
<input type="date" id="modalDueDate" class="form-control">

        <input type="hidden" id="modalHotelId">
        <input type="hidden" id="modalRemaining">
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" onclick="confirmPayment()">Confirm</button>
      </div>

    </div>
  </div>
</div>

<!-- ================= PAYMENT HISTORY MODAL ================= -->
<div class="modal fade" id="historyModal" tabindex="-1">
  <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Payment History</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div id="historyContent" class="text-center text-muted">
          Loading...
        </div>
      </div>

    </div>
  </div>
</div>

<script>
let paymentModal;

document.addEventListener('DOMContentLoaded', () => {
  paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
});

function openPaymentModal(id, remaining) {
  document.getElementById('modalHotelId').value = id;
  document.getElementById('modalRemaining').value = remaining;
  document.getElementById('modalPayAmount').value = remaining;
  document.getElementById('modalPaidDate').value = '';
  paymentModal.show();
}

function confirmPayment() {
  const id = modalHotelId.value;
  const paidDate = modalPaidDate.value;
  const amount = parseFloat(modalPayAmount.value);
  const remaining = parseFloat(modalRemaining.value);
  const newDueDate = modalDueDate.value;

  if (!paidDate || amount <= 0 || amount > remaining) {
    alert('Invalid payment');
    return;
  }

  // If PARTIAL payment → new due date required
  if (amount < remaining && !newDueDate) {
    alert('Please select new due date for remaining payment');
    return;
  }

  fetch('mark_hotel_paid.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body:
      `id=${id}` +
      `&paid_date=${paidDate}` +
      `&amount=${amount}` +
      `&due_date=${newDueDate}`
  }).then(() => location.reload());
}
</script>

<script>
let historyModal;

document.addEventListener('DOMContentLoaded', () => {
  historyModal = new bootstrap.Modal(document.getElementById('historyModal'));
});

function viewHistory(hotelId) {
  historyModal.show();
  document.getElementById('historyContent').innerHTML = 'Loading...';

  fetch('fetch_hotel_payment_history.php?id=' + hotelId)
    .then(res => res.text())
    .then(html => {
      document.getElementById('historyContent').innerHTML = html;
    });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>