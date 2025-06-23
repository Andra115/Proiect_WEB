<?php

session_start();

require_once __DIR__ . '/../../vendor/autoload.php';
$googleCredentialsJson = getenv('GOOGLE_DRIVE_CREDS');
$tempCredPath = sys_get_temp_dir() . '/google-creds.json';
file_put_contents($tempCredPath, $googleCredentialsJson);


$client = new Google_Client();
$client->setAuthConfig($tempCredPath);
//$client->setAuthConfig(__DIR__ . '/../../driver_credentials.json');
$client->addScope(\Google\Service\Drive::DRIVE_FILE);
$client->addScope('https://www.googleapis.com/auth/userinfo.email');
$client->addScope('https://www.googleapis.com/auth/userinfo.profile');
$client->addScope('https://www.googleapis.com/auth/drive');
$client->setAccessType('offline');
$client->setPrompt('consent');

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;
$client->setState($state);

$authUrl = $client->createAuthUrl();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['auth_url' => $authUrl]);
    exit;
} else {
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
}
?>
 