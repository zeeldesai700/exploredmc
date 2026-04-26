<?php
require_once '../../config/db.php';

$id       = (int)$_POST['id'];
$date     = $_POST['paid_date'];
$amount   = (float)$_POST['amount'];
$newDue   = $_POST['due_date'] ?? null;

/* =========================
   1. INSERT PAYMENT HISTORY
========================= */
$conn->query("
  INSERT INTO hotel_payment_history
    (hotel_confirmation_id, paid_amount, paid_date)
  VALUES
    ($id, $amount, '$date')
");

/* =========================
   2. RECALCULATE TOTALS
========================= */
$q = $conn->query("
  SELECT
    ch.payment_amount,
    IFNULL(SUM(h.paid_amount),0) AS total_paid
  FROM confirmations_hotels ch
  LEFT JOIN hotel_payment_history h
    ON ch.id = h.hotel_confirmation_id
  WHERE ch.id = $id
");

$row = $q->fetch_assoc();

$totalPaid = (float)$row['total_paid'];
$remaining = (float)$row['payment_amount'] - $totalPaid;

if ($remaining < 0) {
    $remaining = 0;
}

$status = ($remaining == 0) ? 'paid' : 'pending';

/* =========================
   3. UPDATE MAIN HOTEL TABLE
========================= */
if ($status === 'paid') {

    // FULL PAYMENT
    $conn->query("
      UPDATE confirmations_hotels
      SET
        paid_amount      = $totalPaid,
        remaining_amount = 0,
        payment_status   = 'paid',
        paid_date        = '$date'
      WHERE id = $id
    ");

} else {

    // PARTIAL PAYMENT → OVERWRITE due_date
    $conn->query("
      UPDATE confirmations_hotels
      SET
        paid_amount      = $totalPaid,
        remaining_amount = $remaining,
        payment_status   = 'pending',
        paid_date        = '$date',
        due_date         = '$newDue'
      WHERE id = $id
    ");
}