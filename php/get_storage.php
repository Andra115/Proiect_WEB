<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}
$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM get_user_storage(:user_id)");
    $stmt->execute(['user_id' => $user_id]);
    $storage = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

$total_storage = $storage['total_storage'];
$space_available = $storage['total_available'];
$used_storage = $total_storage - $space_available;

if ($total_storage != 0) {
    $total_storage_gb = $total_storage / (1024 ** 3);
    $used_storage_gb = $used_storage / (1024 ** 3);
    $percentageUsed = ($used_storage_gb / $total_storage_gb) * 100;
} else {
    $percentageUsed = 0;
    $total_storage_gb = 0;
    $used_storage_gb = 0;
}

echo json_encode([
    'percentageUsed' => $percentageUsed,
    'used_storage_gb' => round($used_storage_gb, 3),
    'total_storage_gb' => round($total_storage_gb, 3)
]);