<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $searchTerm = isset($_POST['search']) ? trim($_POST['search']) : '';
    $_SESSION['searched_file_name'] = $searchTerm;
    $userId = $_SESSION['user_id'];
    $fileType = isset($_SESSION['selected_file_type']) ? $_SESSION['selected_file_type'] : '';
    $stmt = $pdo->prepare('SELECT * FROM get_user_files(:user_id, :type, :searched_file_name)');
    $stmt->execute(['user_id' => $userId, 'type' => $fileType, 'searched_file_name' => $searchTerm]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['files' => $files]);
    exit;
}
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
exit; 