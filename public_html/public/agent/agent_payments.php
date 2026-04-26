<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();
require_once __DIR__ . '/../../config/db.php';

$page_title = "Agent Payments";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';

/* ================= FILTER ================= */

$where = [];

// ONLY dropdown filter (no role restriction)
if(!empty($_GET['user'])){
  $userFilter = $conn->real_escape_string(trim($_GET['user']));
  $where[] = "LOWER(a.created_by) = LOWER('$userFilter')";
}

$whereSQL = "";
if(!empty($where)){
  $whereSQL = "WHERE " . implode(" AND ", $where);
}
?>

<style>
.card:hover {
  transform: translateY(-2px);
  transition: 0.2s;
}
</style>

<div class="container mt-4">

<h4 class="mb-3">Agent Payment Management</h4>

<!-- ================= SUMMARY ================= -->
<div class="row g-2 mb-3">
<?php

// same filter for summary (remove alias a.)
$sumWhere = "";

// 👤 Employee → only own summary
if($_SESSION['role'] != 'admin'){
  $user = $conn->real_escape_string(trim($_SESSION['user_name']));
  $sumWhere = "WHERE LOWER(created_by) = LOWER('$user')";
}

// 👑 Admin → all users (no where)

$sumRes = $conn->query("
SELECT 
created_by AS user_name,
SUM(total_quotation_price) as total,
SUM(paid_amount) as paid,
SUM(remaining_amount) as outstanding
FROM agent_accounts
$sumWhere
GROUP BY created_by
");

while($s = $sumRes->fetch_assoc()):
?>
  <div class="col-md-3 col-6">
    <div class="card shadow-sm border-0 p-2">

      <div class="fw-bold small text-dark mb-1">
        <?= ucwords(htmlspecialchars($s['user_name'])) ?>
      </div>

      <div class="small text-muted">
        Total: ₹ <?= number_format($s['total'],2) ?>
      </div>

      <div class="small text-success">
        Paid: ₹ <?= number_format($s['paid'],2) ?>
      </div>

      <div class="small text-danger fw-semibold">
        Due: ₹ <?= number_format($s['outstanding'],2) ?>
      </div>

    </div>
  </div>
<?php endwhile; ?>
</div>

<!-- ================= USER FILTER ================= -->
<?php
$users = $conn->query("SELECT DISTINCT created_by FROM agent_accounts ORDER BY created_by ASC");
$selected_user = $_GET['user'] ?? '';
?>

<div class="mb-3">
  <form method="GET">
    <label class="me-2 fw-semibold">Filter User:</label>

    <select name="user" class="form-select w-auto d-inline"
            onchange="this.form.submit()">

      <option value="">All Users</option>

      <?php while($u = $users->fetch_assoc()): ?>
  <option value="<?= htmlspecialchars($u['created_by']) ?>"
    <?= ($selected_user == $u['created_by']) ? 'selected' : '' ?>>
    <?= ucwords(htmlspecialchars($u['created_by'])) ?>
  </option>
      <?php endwhile; ?>

    </select>
  </form>
</div>

<?php
/* ================= MAIN TABLE ================= */

$sql = "
SELECT 
a.created_by AS user_name,
a.id,
a.agent_name,
a.confirmation_no,

c.created_at,  -- ✅ NEW (confirmation date)

MIN(ct.travel_date) as travel_date,

-- ✅ TOTAL PAX
(q.adults + q.extra_adults + q.children + q.no_bed_child + q.infants) AS total_pax,

a.group_name,
a.total_quotation_price AS total_amount,
a.paid_amount,
a.remaining_amount,
a.payment_status AS status

FROM agent_accounts a

-- ✅ JOIN confirmations
LEFT JOIN confirmations c 
ON c.confirmation_no = a.confirmation_no

-- ✅ JOIN travel table
LEFT JOIN confirmations_travels ct 
ON ct.confirmation_id = c.id

-- ✅ JOIN quotations
LEFT JOIN quotations q 
ON q.id = c.quotation_id

$whereSQL

GROUP BY a.id
ORDER BY 
CASE WHEN a.payment_status = 'paid' THEN 1 ELSE 0 END ASC,
ct.travel_date ASC
";

$res = $conn->query($sql);
?>

<table class="table table-bordered table-sm">
<thead>
<tr>
  <th>Create Date</th> <!-- NEW -->
  <th>User</th>
  <th>Agent</th>
  <th>Confirmation</th>
  <th>Total Pax</th> <!-- NEW -->
  <th>Travel Date</th>
  <th>Group</th>
  <th>Total</th>
  <th>Paid</th>
  <th>Outstanding</th>
  <th>Status</th>
  <th>Action</th>
</tr>
</thead>

<tbody>
<?php while($r = $res->fetch_assoc()): ?>
<tr>

<td>
<?= !empty($r['created_at']) 
    ? date('d-m-Y', strtotime($r['created_at'])) 
    : '-' ?>
</td>

<td><?= ucwords(htmlspecialchars($r['user_name'] ?? '-')) ?></td>

<td><?= htmlspecialchars($r['agent_name']) ?></td>

<td><?= htmlspecialchars($r['confirmation_no']) ?></td>

<td class="fw-bold text-primary">
<?= $r['total_pax'] ?? 0 ?>
</td>

<td>
<?= !empty($r['travel_date']) 
    ? date('d-m-Y', strtotime($r['travel_date'])) 
    : '-' ?>
</td>

<td>
<select class="form-select form-select-sm"
        onchange="updateGroup(<?= $r['id'] ?>, this.value)">
  <option value="no" <?= ($r['group_name']=='no')?'selected':'' ?>>No</option>
  <option value="yes" <?= ($r['group_name']=='yes')?'selected':'' ?>>Yes</option>
</select>
</td>

<td><?= number_format($r['total_amount'],2) ?></td>

<td class="text-success"><?= number_format($r['paid_amount'],2) ?></td>

<td class="text-danger fw-bold"><?= number_format($r['remaining_amount'],2) ?></td>

<td>
<span class="badge <?= $r['status']=='paid'?'bg-success':'bg-warning' ?>">
<?= ucfirst($r['status']) ?>
</span>
</td>

<td>
<?php if($r['remaining_amount'] > 0): ?>
<button type="button" class="btn btn-danger btn-sm"
onclick="openAgentPaymentModal(<?= (int)$r['id'] ?>, <?= (float)$r['remaining_amount'] ?>)">
Pay
</button>
<?php endif; ?>

<button class="btn btn-primary btn-sm"
onclick="viewLedger(<?= $r['id'] ?>)">
Ledger
</button>
</td>

</tr>
<?php endwhile; ?>
</tbody>
</table>

</div>

<!-- PAYMENT MODAL -->
<div class="modal fade" id="paymentModal">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Agent Payment</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <label>Date</label>
        <input type="date" id="payDate" class="form-control mb-2">

        <label>Amount</label>
        <input type="number" id="payAmount" class="form-control mb-2">

        <label>Notes</label>
        <textarea id="payNote" class="form-control"></textarea>

        <input type="hidden" id="paymentId">

      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" onclick="confirmPayment()">Confirm</button>
      </div>

    </div>
  </div>
</div>

<!-- LEDGER MODAL -->
<div class="modal fade" id="ledgerModal">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Ledger</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body" id="ledgerContent">
        Loading...
      </div>

    </div>
  </div>
</div>

<script>
let paymentModal;
let ledgerModal;

// INIT
document.addEventListener("DOMContentLoaded", function(){

  const paymentEl = document.getElementById('paymentModal');
  const ledgerEl  = document.getElementById('ledgerModal');

  paymentModal = new bootstrap.Modal(paymentEl);
  ledgerModal  = new bootstrap.Modal(ledgerEl);

});

// OPEN PAYMENT
function openAgentPaymentModal(id, remaining){

  document.getElementById('paymentId').value = id;
  document.getElementById('payAmount').value = remaining;
  document.getElementById('payDate').value = '';
  document.getElementById('payNote').value = '';

  paymentModal.show();
}

// CONFIRM PAYMENT
function confirmPayment(){

  let id     = document.getElementById('paymentId').value;
  let amount = parseFloat(document.getElementById('payAmount').value);
  let date   = document.getElementById('payDate').value;
  let note   = document.getElementById('payNote').value;

  if(!date){ alert('Select date'); return; }
  if(!amount || amount <= 0){ alert('Invalid amount'); return; }

  fetch('mark_agent_paid.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`id=${id}&amount=${amount}&paid_date=${date}&note=${encodeURIComponent(note)}`
  })
  .then(res => res.text())
  .then(res => {
    paymentModal.hide();
    setTimeout(()=>location.reload(),300);
  })
  .catch(()=>alert('Payment failed'));
}

// VIEW LEDGER
function viewLedger(id){
  ledgerModal.show();
  document.getElementById('ledgerContent').innerHTML = "Loading...";

  fetch('fetch_agent_ledger.php?id='+id)
    .then(res=>res.text())
    .then(html=>{
      document.getElementById('ledgerContent').innerHTML = html;
    });
}

// PRINT
function printReceipt(id){
  window.open('agent_receipt.php?id='+id, '_blank');
}

// ✅ UPDATE GROUP
function updateGroup(id, value){

  fetch('update_group_name.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`id=${id}&group_name=${value}`
  })
  .then(res=>res.text())
  .then(res=>{
    console.log("Group updated:", res);
  })
  .catch(()=>{
    alert('Update failed');
  });

}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>