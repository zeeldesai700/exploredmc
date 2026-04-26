<?php
require_once '../../config/db.php';

$id = (int)$_GET['id'];

$q = $conn->query("
  SELECT paid_amount, paid_date, created_at
  FROM hotel_payment_history
  WHERE hotel_confirmation_id = $id
  ORDER BY paid_date ASC
");

if ($q->num_rows === 0) {
  echo '<p class="text-muted text-center">No payment history found</p>';
  exit;
}

$total = 0;

echo '<table class="table table-sm table-bordered">';
echo '<thead class="table-light">
        <tr>
          <th>#</th>
          <th>Paid Date</th>
          <th>Amount</th>
        </tr>
      </thead><tbody>';

$i = 1;
while ($r = $q->fetch_assoc()) {
  $total += $r['paid_amount'];
  echo '<tr>
          <td>'.$i++.'</td>
          <td>'.date('d-m-Y', strtotime($r['paid_date'])).'</td>
          <td class="text-end">VND '.number_format($r['paid_amount'],2).'</td>
        </tr>';
}

echo '<tr class="fw-bold">
        <td colspan="2" class="text-end">Total Paid</td>
        <td class="text-end">VND '.number_format($total,2).'</td>
      </tr>';

echo '</tbody></table>';