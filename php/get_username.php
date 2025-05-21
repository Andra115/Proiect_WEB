<?php
require_once 'db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$userId = $data['user_id'] ?? null;

if (!$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing user_id']);
    exit;
}

$stmt = $pdo->prepare('SELECT username FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$username = $stmt->fetchColumn();

if ($username) {
    echo json_encode(['username' => $username]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
}