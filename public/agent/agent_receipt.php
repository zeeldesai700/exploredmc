<?php
include '../../config/db.php';

$id = $_GET['id'];
$r = $conn->query("SELECT * FROM agent_accounts WHERE id=$id")->fetch_assoc();
?>

<h3>Agent Payment Receipt</h3>

<p>Agent: <?= $r['agent_name'] ?></p>
<p>Confirmation: <?= $r['confirmation_no'] ?></p>

<p>Total: <?= $r['total_quotation_price'] ?></p>
<p>Paid: <?= $r['paid_amount'] ?></p>
<p>Remaining: <?= $r['remaining_amount'] ?></p>

<button onclick="window.print()">Print</button>