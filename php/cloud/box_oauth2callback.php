<?php
session_start();


$creds_file = __DIR__ . '/../../box_credentials.json';
if (!file_exists($creds_file)) {
    die('Error : Credentials file not found');
}

$creds = json_decode(file_get_contents($creds_file), true);
if (!$creds) {
    die('Error : Invalid credentials file');
}

$client_id = $creds['client_id'];
$client_secret = $creds['client_secret'];
$redirect_uri = $creds['redirect_uri'];


if (!isset($_GET['code'], $_GET['state']) || $_GET['state'] !== $_SESSION['box_oauth_state']) {
    die('Invalid OAuth state');
}



$code = $_GET['code'];

$token_url = "https://api.box.com/oauth2/token";

$post_fields = http_build_query([
    'grant_type' => 'authorization_code',
    'code' => $code,
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri' => $redirect_uri,
]);

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        curl_close($ch);
        die('Curl error: ' . curl_error($ch));
    }

curl_close($ch);

$token = json_decode($response, true);

if (isset($token['access_token']) && $http_code === 200) {
    $_SESSION['box_access_token'] = $token['access_token'];
    if (isset($token['refresh_token'])) {
            $_SESSION['box_refresh_token'] = $token['refresh_token'];
        }

    header("Location: upload.php");
    exit;
} else {
    die('OAuth failed: ' . htmlspecialchars($response));
}
