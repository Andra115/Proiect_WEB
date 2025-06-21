<?php
session_start();

/*$creds = json_decode(file_get_contents(__DIR__ . '/../../dropbox_credentials.json'), true);

$client_id = $creds['client_id'];
$client_secret = $creds['client_secret'];
$redirect_uri = $creds['redirect_uri'];
$scopes = $creds['scopes'];*/

$creds = json_decode(getenv("DROPBOX_CREDS"), true);
if (!$creds) {
    die('Error: Invalid credentials file');
}

$client_id = $creds['client_id'];
$client_secret = $creds['client_secret'];
$redirect_uri = $creds['redirect_uri'];
$scopes = $creds['scopes'];

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$auth_url = 'https://www.dropbox.com/oauth2/authorize?' . http_build_query([
    'client_id' => $client_id,
    'response_type' => 'code',
    'redirect_uri' => $redirect_uri,
    'token_access_type' => 'offline',
    'scope' => $scopes,
    'state' => $state,
    'force_reapprove' => 'true'
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['auth_url' => $auth_url]);
    exit;
} else {
    header("Location: $auth_url");
    exit;
}
?>