<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);


require_once __DIR__ . '/../../vendor/autoload.php';

session_start();


$client = new Google_Client();
$client->setAuthConfig(__DIR__ . '/../../driver_credentials.json');
$client->addScope(Google\Service\Drive::DRIVE); 


if (isset($_GET['error'])) {
   
    echo 'Error: ' . htmlspecialchars($_GET['error']);
    exit;
}

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
       
        echo 'Error while fetching access token: ' . htmlspecialchars($token['error']);
        exit;
    }

   
    $_SESSION['access_token'] = $token['access_token'];
    if (isset($token['refresh_token'])) {
        $_SESSION['refresh_token'] = $token['refresh_token'];
    }

  
    header('Location: idkyet.php');
    exit;
} else {
   
    echo 'Invalid request.';
    exit;
}

?>