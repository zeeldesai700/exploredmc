<?php
// public/seed_admin.php - seed default admin user (run once, then delete this file for security)
require_once __DIR__ . '/../config/db.php';

$email = 'admin@example.com';
$name = 'Admin';
$password_plain = 'admin123';
$hash = password_hash($password_plain, PASSWORD_BCRYPT);

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    echo "User already exists. You can now login as $email";
    exit;
}

$stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
$stmt->bind_param('sss', $name, $email, $hash);
if ($stmt->execute()) {
    echo "Admin created: $email / Password: $password_plain\nPlease DELETE this file now.";
} else {
    echo "Error creating admin: " . $conn->error;
}
