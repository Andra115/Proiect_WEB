<?php
session_start();
require_once 'db.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$_SESSION['selected_file_type'] = $data['type'];

$userId = $_SESSION['user_id'];
$fileType = isset($_SESSION['selected_file_type']) ? $_SESSION['selected_file_type'] : '';
$searchedFileName = isset($_SESSION['searched_file_name']) ? $_SESSION['searched_file_name'] : '';

$stmt = $pdo->prepare('SELECT * FROM get_user_files(:user_id, :type, :searched_file_name)');
$stmt->execute(['user_id' => $userId, 'type' => $fileType, 'searched_file_name' => $searchedFileName]);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode(['files' => $files]);
exit; 