<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Sightseeing Template");

/* ✅ HEADERS – MATCHES IMPORT CODE EXACTLY */
$sheet->fromArray([
    [
        "Sightseeing Name",     // A
        "Country ID",           // B
        "City ID",              // C
        "Pickup Point ID",      // D
        "Guide Rate",           // E
        "Activity Name",        // F
        "Adult Price",          // G
        "Child Price",          // H
        "Car ID",               // I
        "Car From Date",        // J ✅
        "Car To Date",          // K ✅
        "Half Day",             // L
        "Full Day",             // M
        "Itinerary"             // N ✅ LAST
    ]
]);

/* Optional: Auto width for better usability */
foreach (range('A', 'N') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=sightseeing_template.xlsx");

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
