<?php
require_once __DIR__ . '/../../config/db.php';

$id = $_GET['id'] ?? 0;
$stmt = $conn->prepare("DELETE FROM meals WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: meal_list.php");
exit;
?>
