<?php
include '../../config/db.php';

$id = $_POST['id'];
$amount = $_POST['amount'];
$date = $_POST['paid_date'];
$note = $_POST['note'] ?? '';

$row = $conn->query("SELECT * FROM agent_accounts WHERE id=$id")->fetch_assoc();

$newPaid = $row['paid_amount'] + $amount;
$newRemaining = $row['total_quotation_price'] - $newPaid;

$status = ($newRemaining <= 0) ? 'paid' : 'pending';

$conn->query("
UPDATE agent_accounts SET
paid_amount = '$newPaid',
remaining_amount = '$newRemaining',
payment_status = '$status'
WHERE id = $id
");

$note = $_POST['note'] ?? '';

$conn->query("
INSERT INTO agent_payment_history
(agent_payment_id, payment_date, amount, note)
VALUES ($id, '$date', '$amount', '$note')
");

echo "success";