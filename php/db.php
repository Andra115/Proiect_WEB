<?php
$host = "shuttle.proxy.rlwy.net";
$port = "25644";
$dbname = "railway";
$user = "postgres";
$password = "jfzNVzXvXHClRDSEJbgBHFzQbQiQBebH";

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

try {
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}