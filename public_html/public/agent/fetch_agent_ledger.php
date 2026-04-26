<?php
include '../../config/db.php';

$id = $_GET['id'];

$res = $conn->query("
SELECT * FROM agent_payment_history
WHERE agent_payment_id = $id
ORDER BY payment_date DESC
");

echo "<table class='table table-sm table-bordered'>";
echo "<tr>
<th>Date</th>
<th>Amount</th>
<th>Notes</th>
</tr>";

while($r = $res->fetch_assoc()){
  echo "<tr>
    <td>{$r['payment_date']}</td>
    <td>{$r['amount']}</td>
    <td>".htmlspecialchars($r['note'])."</td>
  </tr>";
}

echo "</table>";