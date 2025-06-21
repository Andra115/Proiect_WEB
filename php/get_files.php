<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];
$fileType = $_SESSION['selected_file_type'] ?? '';
$searchedFileName = $_SESSION['searched_file_name'] ?? '';

try {
    $stmt = $pdo->prepare('SELECT * FROM get_user_files(:p_user_id, :p_type, :p_searched)');
    $stmt->execute(['p_user_id' => $userId, 'p_type' => $fileType, 'p_searched' => $searchedFileName]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'files' => $files]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
