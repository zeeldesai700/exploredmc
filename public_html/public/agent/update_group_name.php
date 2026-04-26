<?php
require_once __DIR__ . '/../../config/db.php';

$id = $_POST['id'] ?? 0;
$group = $_POST['group_name'] ?? 'no';

$stmt = $conn->prepare("UPDATE agent_accounts SET group_name=? WHERE id=?");
$stmt->bind_param("si", $group, $id);

echo $stmt->execute() ? "OK" : "ERROR";