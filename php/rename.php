<?php

session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (php_sapi_name() === 'cli') {

    $input = json_decode($argv[1], true);
} else {

    $input = json_decode(file_get_contents('php://input'), true);
}

if (!$input || !$input['file_id'] || !$input['user_id'] || !$input['new_name']) {
    http_response_code(400);
    echo json_encode(['error' => 'Error: Invalid input']);
    exit;
}

$user_id = $input['user_id'];
$file_id = $input['file_id'];
$newName = $input['new_name'];

   try{ 
    $stmt = $pdo->prepare("UPDATE files SET file_name = ? WHERE file_id = ?");
    $stmt->execute([$newName, $file_id]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
    echo json_encode(['success' => true, 'message' => 'File renamed successfully']);



?>