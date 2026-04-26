<?php
require '../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// HEADERS
$sheet->setCellValue('A1', 'Category');
$sheet->setCellValue('B1', 'Food');
$sheet->setCellValue('C1', 'Restaurant');
$sheet->setCellValue('D1', 'Adult Price');
$sheet->setCellValue('E1', 'Child Price');
$sheet->setCellValue('F1', 'No Bed Child Price');
$sheet->setCellValue('G1', 'Country ID');
$sheet->setCellValue('H1', 'City ID');

// Optional Sample Row
$sheet->setCellValue('A2', 'Breakfast');
$sheet->setCellValue('B2', 'Poha');
$sheet->setCellValue('C2', 'Hotel XYZ');
$sheet->setCellValue('D2', '100');
$sheet->setCellValue('E2', '80');
$sheet->setCellValue('F2', '50');
$sheet->setCellValue('G2', '1');
$sheet->setCellValue('H2', '10');

// DOWNLOAD
$filename = "meal_template.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
