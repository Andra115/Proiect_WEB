<?php
session_start();
$token = $_GET['token'] ?? '';
$metaFile = sys_get_temp_dir() . "/$token.json";

if (!file_exists($metaFile)) {
    http_response_code(404);
    exit("Token not found");
}

$download = json_decode(file_get_contents($metaFile), true);
unlink($metaFile); 

$filePath = $download['path'];
$fileName = $download['name'];

if (!file_exists($filePath)) {
    http_response_code(410);
    exit("File expired");
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

while (ob_get_level()) ob_end_clean();
readfile($filePath);
unlink($filePath);
exit;
