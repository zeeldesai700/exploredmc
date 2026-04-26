<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/db.php';

/* ================= CONFIG ================= */
$CLOUDMERSIVE_API_KEY = '411c32df-35a9-4980-bfa4-e333bf40f9f6';

/* ================= GET QUOTATION ================= */
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('INVALID ID');
}

$q = $conn->query("
    SELECT quotation_no
    FROM quotations
    WHERE id = $id
")->fetch_assoc();

if (!$q) {
    die('QUOTATION NOT FOUND');
}

$quotationNo = $q['quotation_no'];

/* ================= PDF PATH ================= */
$pdfFile = __DIR__ . "/pdf/Quotation_{$quotationNo}.pdf";

if (!file_exists($pdfFile)) {
    die("PDF NOT FOUND: $pdfFile");
}

/* ================= CALL CLOUDMERSIVE ================= */
$ch = curl_init('https://api.cloudmersive.com/convert/pdf/to/docx');

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Apikey: ' . $CLOUDMERSIVE_API_KEY
    ],
    CURLOPT_POSTFIELDS => [
        'inputFile' => new CURLFile($pdfFile)
    ]
]);

$docxData = curl_exec($ch);

if ($docxData === false) {
    die('CURL ERROR: ' . curl_error($ch));
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    die('CLOUDMERSIVE ERROR. HTTP CODE: ' . $httpCode);
}

/* ================= SEND WORD FILE ================= */
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="Quotation_'.$quotationNo.'.docx"');
header('Content-Length: ' . strlen($docxData));
header('Cache-Control: no-store');

echo $docxData;
exit;
