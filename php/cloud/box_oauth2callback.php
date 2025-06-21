<?php
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: /../login.php");
    exit;
}
$user_id = $_SESSION['user_id'];

/*$creds_file = __DIR__ . '/../../box_credentials.json';
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
*/

$creds = json_decode(getenv("BOX_CREDS"), true);
if (!$creds) {
    die('Error: Invalid credentials file');
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

    $access_token = $token['access_token'];
    $ch = curl_init("https://api.box.com/2.0/users/me");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token",
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $user_info = json_decode($response, true);
    $email = $user_info['login'] ?? null;
    if (!$email) {
        die('Error while trying to retrieve email');
    }

    try {
        $stmt = $pdo->prepare("SELECT account_id FROM cloud_accounts WHERE email = ? AND provider = 'box' AND user_id = ?");
        $stmt->execute([$email, $user_id]);
        $account_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $account_id = $account_result ? $account_result['account_id'] : null;

        $token_expiry = time() + ($token['expires_in'] ?? 3600);


        if (!$account_id) {
            $stmt = $pdo->prepare("INSERT INTO cloud_accounts (user_id,provider, email, access_token,refresh_token,token_expiry,total_space,space_available) 
                            VALUES (?, 'box', ?,?,?,?,?,?)");

            $token_expiry_formatted = date('Y-m-d H:i:s', $token_expiry);
            $stmt->execute([
                $user_id,
                $email,
                $token['access_token'],
                $token['refresh_token'],
                $token_expiry_formatted,
                10737418240,
                10737418240
            ]);
            $stmt = $pdo->prepare("SELECT account_id FROM cloud_accounts WHERE email = ? AND provider = 'box' AND user_id = ?");
            $stmt->execute([$email, $user_id]);
            $account_id = $stmt->fetchColumn();
        } else {
            $token_expiry_formatted = date('Y-m-d H:i:s', $token_expiry);
            $stmt = $pdo->prepare("UPDATE cloud_accounts SET access_token = ?, token_expiry=? WHERE email = ? AND provider = 'box' AND user_id = ?");
            $stmt->execute([$access_token, $token_expiry_formatted, $email, $user_id]);
        }
    } catch (Exception $e) {
        die('Database error: ' . $e->getMessage());
    }


    $_SESSION['pending_sync'] = [
        'account_id' => $account_id,
        'access_token' => $access_token,
        'email' => $email,
        'provider' => 'box',
        'user_id' => $user_id
    ];



    header("Location: /php/welcome.php");
    exit;
} else {
    die('OAuth failed: ' . htmlspecialchars($response));
}
