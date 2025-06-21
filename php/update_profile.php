<?php
require_once 'db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$jwt = $data['jwt'] ?? null;
$newUsername = $data['username'] ?? null;
$currentPassword = $data['currentPassword'] ?? null;
$newPassword = $data['newPassword'] ?? null;

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
    
    $pdo->beginTransaction();
    
    try {
        if ($newUsername) {
            $stmt = $pdo->prepare('SELECT changeUsername(:id, :username)');
            $stmt->execute([
                'id' => $userId,
                'username' => $newUsername
            ]);
        }
        
        if ($currentPassword && $newPassword) {
            try {
                $stmt = $pdo->prepare('SELECT changePassword(:id, :oldPassword, :newPassword)');
                $stmt->execute([
                    'id' => $userId,
                    'oldPassword' => $currentPassword,
                    'newPassword' => $newPassword
                ]);
            } catch (PDOException $e) {
                
                $errorCode = $e->getCode();
                switch($errorCode) {
                    case 'P0009':
                        throw new Exception('Current password is incorrect');
                    case 'P0010':
                        throw new Exception('New password must be different from the old one');
                    case 'P0011':
                        throw new Exception('Password must be at least 8 characters long');
                    default:
                        throw new Exception('Failed to update password');
                }
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired token']);
}
?> 