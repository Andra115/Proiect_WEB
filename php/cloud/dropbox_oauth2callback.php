<?php
session_start();
require_once __DIR__ . '/../db.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: /../../login.php");
    exit;
}
$user_id = $_SESSION['user_id'];

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

        $access_token = $token['access_token'];
        $refresh_token = $token['refresh_token'] ?? null;
        $token_expiry = time() + ($token['expires_in'] ?? 14400); //this one has 4 hours apparently compared to the other two, revolutionary really

    
        $ch = curl_init("https://api.dropboxapi.com/2/users/get_current_account");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $access_token",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'null');

        $response = curl_exec($ch);
        curl_close($ch);


        $user_info = json_decode($response, true);
    
        $email = $user_info['email'] ?? null;
        if (!$email) {
            die("Failed to retrieve dropbox email.");
        }

        
        $ch = curl_init("https://api.dropboxapi.com/2/users/get_space_usage");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token","Content-Type: application/json" ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'null');
        $response = curl_exec($ch);
        curl_close($ch);

        $user_storage_info = json_decode($response, true);
        $used = $user_storage_info['used'] ?? null;
        $allocation = $user_storage_info['allocation'] ?? null;
        $total_space = $allocation['allocated'] ?? 2147483648; //it should be able to get this info but in case it doesnt we ll assume 2GB free plan
        
    try{
        $stmt = $pdo->prepare("SELECT account_id FROM cloud_accounts WHERE email = ? AND provider = 'dropbox' AND user_id = ?");
        $stmt->execute([$email, $user_id]);
        $account_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $account_id = $account_result ? $account_result['account_id'] : null;
        $token_expiry_formatted = date('Y-m-d H:i:s', $token_expiry);
        $space_available = $total_space - $used;
        if (!$account_id) {
            $stmt = $pdo->prepare("INSERT INTO cloud_accounts (user_id, provider, email, access_token, refresh_token, token_expiry, total_space, space_available) VALUES (?, 'dropbox', ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id,
                $email,
                $access_token,
                $refresh_token,
                $token_expiry_formatted,
                $total_space, 
                $space_available  
            ]);
            $stmt = $pdo->prepare("SELECT account_id FROM cloud_accounts WHERE email = ? AND provider = 'dropbox' AND user_id = ?");
            $stmt->execute([$email, $user_id]);
            $account_id = $stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("UPDATE cloud_accounts SET total_space= ?, space_available=?, access_token = ?, token_expiry = ? WHERE email = ? AND provider = 'dropbox' AND user_id = ?");
            $stmt->execute([$total_space, $space_available, $access_token, $token_expiry_formatted, $email, $user_id]);
        }
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
       
       $sync_url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/sync_files.php';
    $sync_data = json_encode([
        'account_id' => $account_id,
        'access_token' => $access_token,
        'email' => $email,
        'provider' => 'box'
    ]);
  
    $ch = curl_init($sync_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $sync_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
    
     curl_exec($ch);
    curl_close($ch);
        
       
        header("Location: /php/welcome.php");
        exit;
    } else {
        echo "OAuth failed (HTTP $http_code): " . htmlspecialchars($response);
    }
} else {
    echo "No authorization code provided.";
}
?>