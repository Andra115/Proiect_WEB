<?php

session_start();

require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: /../../login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
require_once __DIR__ . '/../../vendor/autoload.php';

$client = new Google_Client();
$client->setAuthConfig(__DIR__ . '/../../driver_credentials.json');
$client->addScope(Google\Service\Drive::DRIVE); 
$client->addScope('https://www.googleapis.com/auth/userinfo.email');
$client->addScope('https://www.googleapis.com/auth/userinfo.profile'); 
$client->addScope('https://www.googleapis.com/auth/drive');
$client->setAccessType('offline'); 
$client->setPrompt('consent');


if (isset($_GET['error'])) {
   
    echo 'Error: ' . htmlspecialchars($_GET['error']);
    exit;
}

if (isset($_GET['code'])) {
    try {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
} catch (Exception $e) {
    echo 'Exception caught: ', $e->getMessage();
    var_dump($e);
    exit;
}


    if (isset($token['error'])) {
       
        echo 'Error while fetching access token: ' . htmlspecialchars($token['error']);
        exit;
    }

   
    $_SESSION['drive_access_token'] = $token['access_token'];
    if (isset($token['refresh_token'])) {
    $_SESSION['drive_refresh_token'] = $token['refresh_token'];
    } elseif (!isset($_SESSION['refresh_token'])) {
        echo "No refresh token returned. Try revoking access and reauthorizing.";
        exit;
    }

    $access_token = $token['access_token'];
    $refresh_token = $token['refresh_token'] ?? ($_SESSION['drive_refresh_token'] ?? null);

   
    $client->setAccessToken($token);
    
   
    $oauth2 = new Google\Service\Oauth2($client);
    $user_info = $oauth2->userinfo->get();
    $email = $user_info->email ?? null;
    if (!$email) {
        die('Failed to retrieve Google Drive email.');
    }

    $driveService = new Google\Service\Drive($client);
    $about = $driveService->about->get(['fields' => 'storageQuota']);
    $user_storage_info = $about->getStorageQuota();
    $total_space = $user_storage_info->getLimit() ?? 16106127360; // and once again just in case it doesnt work falling on default free acc
    $used = $user_storage_info->getUsage() ?? 0;
        

try{
    $stmt = $pdo->prepare("SELECT account_id FROM cloud_accounts WHERE email = ? AND provider = 'google' AND user_id = ?");
    $stmt->execute([$email,$user_id]);
    $account_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $account_id = $account_result ? $account_result['account_id'] : null;

    $token_expiry = time() + ($token['expires_in'] ?? 3600);
    $token_expiry_formatted = date('Y-m-d H:i:s', $token_expiry);
    $space_available= $total_space - $used;

    if (!$account_id) {
        $stmt = $pdo->prepare("INSERT INTO cloud_accounts (user_id, provider, email, access_token, refresh_token, token_expiry, total_space, space_available) 
            VALUES (?, 'google', ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $email,
            $access_token,
            $refresh_token,
            $token_expiry_formatted,
            $total_space, 
            $space_available
        ]);
        $stmt = $pdo->prepare("SELECT account_id FROM cloud_accounts WHERE email = ? AND provider = 'google' AND user_id = ?");
        $stmt->execute([$email,$user_id]);
        $account_id = $stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("UPDATE cloud_accounts SET total_space = ?, space_available = ?, access_token = ?, token_expiry = ? WHERE email = ? AND provider = 'google' AND user_id = ?");
        $stmt->execute([$total_space,$space_available,$access_token, $token_expiry_formatted, $email, $user_id]);
    }
} catch (Exception $e) {
    die('Database error: ' . $e->getMessage());
}

    
    $_SESSION['pending_sync'] = [
            'account_id' => $account_id,
            'access_token' => $access_token,
            'email' => $email,
            'provider' => 'google',
            'user_id' => $user_id
        ];
    
    header('Location: /php/welcome.php');

    exit;
} else {
   
    echo 'Invalid request.';
    exit;
}

?>