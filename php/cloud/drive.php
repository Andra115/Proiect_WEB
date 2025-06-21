<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
session_start();

require_once __DIR__ . '/../../vendor/autoload.php';

$client = new Google_Client();
$client->setAuthConfig(__DIR__ . '/../../driver_credentials.json');
$client->addScope(\Google\Service\Drive::DRIVE_FILE);
$client->addScope('https://www.googleapis.com/auth/userinfo.email');
$client->addScope('https://www.googleapis.com/auth/userinfo.profile');
$client->addScope('https://www.googleapis.com/auth/drive');
$client->setAccessType('offline');
$client->setPrompt('consent');

$authUrl = $client->createAuthUrl();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['auth_url' => $authUrl]);
    exit;
} else {
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
}
?>
 