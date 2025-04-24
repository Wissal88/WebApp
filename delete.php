<?php
session_start();
require_once('config/db.php');

$type = $_POST['type'] ?? null;
$id = $_POST['id'] ?? null;

if (!$type || !$id) {
    die("❌ Missing type or ID.");
}

$allowedTypes = [
    'product' => 'products',
    'user'    => 'users',
    'client'  => 'clients',
    'sale'    => 'sales'
];

if (!array_key_exists($type, $allowedTypes)) {
    die("❌ Invalid type.");
}

$table = $allowedTypes[$type];

$stmt = $conn->prepare("DELETE FROM $table WHERE id = :id");
$stmt->execute(['id' => $id]);

header("Location: " . $_SERVER['HTTP_REFERER']);
exit();
