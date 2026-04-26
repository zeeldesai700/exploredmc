<?php
require_once __DIR__ . '/../../config/db.php';

$quotation_id = (int)($_GET['quotation_id'] ?? 0);
if ($quotation_id <= 0) {
    echo json_encode(["status" => "error", "msg" => "Invalid ID"]);
    exit;
}

$q = $conn->prepare("SELECT * FROM confirmations WHERE quotation_id = ? LIMIT 1");
$q->bind_param("i", $quotation_id);
$q->execute();
$res = $q->get_result()->fetch_assoc();

if ($res) {
    echo json_encode(["status" => "ok", "data" => $res]);
} else {
    echo json_encode(["status" => "none"]);
}
?>
