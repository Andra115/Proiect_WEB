<?php
session_start();

$creds_file = __DIR__ . '/../../dropbox_credentials.json';
if (!file_exists($creds_file)) {
    die('Credentials file not found');
}

$creds = json_decode(file_get_contents($creds_file), true);
if (!$creds) {
    die('Invalid credentials file');
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

header('Location: ' . $auth_url);
exit;
?>