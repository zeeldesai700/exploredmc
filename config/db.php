<?php
// config/db.php
// Update with your local DB credentials
$DB_HOST = '127.0.0.1';
$DB_USER = 'u661539097_nspatel11';
$DB_PASS = 'Nikunj@2652';
$DB_NAME = 'u661539097_explore';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
?>
