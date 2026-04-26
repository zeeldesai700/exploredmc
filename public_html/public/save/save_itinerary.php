<?php
require_once __DIR__.'/../../config/db_pdo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request');
}

$itineraries = $_POST['itinerary'] ?? [];

$stmt = $pdo->prepare("
UPDATE quotation_travels
SET itinerary_text = ?
WHERE id = ?
");

foreach ($itineraries as $travel_id => $text) {
    $stmt->execute([
        trim($text),
        (int)$travel_id
    ]);
}

header("Location: quotation_view.php?id=".$_POST['quotation_id']);
exit;
