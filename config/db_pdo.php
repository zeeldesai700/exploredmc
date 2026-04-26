<?php
// =====================================================
// PDO DATABASE CONNECTION
// =====================================================

$host = "127.0.0.1";
$db   = "u661539097_explore";   // 👈 your database name
$user = "u661539097_nspatel11";
$pass = "Nikunj@2652";                     // XAMPP default
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}
