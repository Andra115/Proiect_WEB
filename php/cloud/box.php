<?php
session_start();

$creds = json_decode(file_get_contents(__DIR__ . '/../../box_credentials.json'), true);

$client_id = $creds['client_id'];
$client_secret = $creds['client_secret'];
$redirect_uri = $creds['redirect_uri'];
$scopes = $creds['scopes'];

$state = bin2hex(random_bytes(16));  

$_SESSION['box_oauth_state'] = $state;

$params = http_build_query([
    'response_type' => 'code',
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'state' => $state,
    'scope' => $scope,
]);

$auth_url = "https://account.box.com/api/oauth2/authorize?$params";

header("Location: $auth_url");
exit;
?>
