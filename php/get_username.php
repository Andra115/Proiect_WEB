<?php
session_start(); 
require_once 'db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$jwt = $data['jwt'] ?? null;

if (!$jwt) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing JWT']);
    exit;
}

try {
    /*$jwtConfig = json_decode(file_get_contents(__DIR__ . '/../jwt.json'), true);
    $key = $jwtConfig['key'];*/
    $jwtConfig = json_decode(getenv("JWT_JSON"), true);
    $key = $jwtConfig['key'];
    $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
    $userId = $decoded->user_id;

    if (!$userId) {
        echo json_encode(['error' => 'Invalid token payload']);
        exit;
    }

    $_SESSION['user_id'] = $userId;
    
    

    $stmt = $pdo->prepare('SELECT * FROM get_user_info(:p_user_id)');
    $stmt->execute(['p_user_id' => (int)$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo json_encode([
            'username' => $user['username'],
            'email' => $user['email']
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
    }
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired token']);
}
?>