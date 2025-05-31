<?php
session_start();


$creds_file = __DIR__ . '/../../dropbox_credentials.json';
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


if (isset($_GET['error'])) {
    die('OAuth error: ' . htmlspecialchars($_GET['error_description'] ?? $_GET['error']));
}


if (!isset($_GET['state']) || !isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    die('Invalid state parameter');
}


unset($_SESSION['oauth_state']);

if (isset($_GET['code'])) {
    $ch = curl_init('https://api.dropboxapi.com/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'code' => $_GET['code'],
        'grant_type' => 'authorization_code',
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri' => $redirect_uri,
    ]));
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        curl_close($ch);
        die('Curl error: ' . curl_error($ch));
    }
    
    curl_close($ch);

    $token = json_decode($response, true);

    if ($http_code === 200 && isset($token['access_token'])) {
     
        $_SESSION['dropbox_access_token'] = $token['access_token'];
        if (isset($token['refresh_token'])) {
            $_SESSION['dropbox_refresh_token'] = $token['refresh_token'];
        }
        
       
        header("Location: welcome.php");
        exit;
    } else {
        echo "OAuth failed (HTTP $http_code): " . htmlspecialchars($response);
    }
} else {
    echo "No authorization code provided.";
}
?>