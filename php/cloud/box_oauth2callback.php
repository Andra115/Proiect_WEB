<?php
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: /../../login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
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

    $stmt = $pdo->prepare("SELECT account_id FROM cloud_accounts WHERE email = ? AND provider = 'box'");
    $stmt->execute([$email]);
    $account_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $account_id = $account_result ? $account_result['account_id'] : null;

    $token_expiry = time() + $token['expires_in'];


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
        $stmt = $pdo->prepare("SELECT account_id FROM cloud_accounts WHERE email = ? AND provider = 'box'");
        $stmt->execute([$email]);  
        $account_id = $stmt->fetchColumn(); 
        
    } else {
        $stmt = $pdo->prepare("UPDATE cloud_accounts SET access_token = ?, token_expiry=? WHERE email = ? AND provider = 'box'");
        $stmt->execute([$access_token,date('Y-m-d H:i:s', $token_expiry), $email]);
    }
    //getting all files from this cloud account
    function listAllFiles($access_token, $folderId = '0')
    {
        $files = [];

        $url = "https://api.box.com/2.0/folders/$folderId/items?limit=1000&fields=id,name,size,created_at,type";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $access_token",
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        if (!isset($data['entries'])) return $files;

        foreach ($data['entries'] as $item) {
            if ($item['type'] === 'file') {
                $files[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'size' => $item['size'],
                    'created_at' => $item['created_at'],
                    'type' => $item['type'],
                ];
            } elseif ($item['type'] === 'folder') {
                $files = array_merge($files, listAllFiles($access_token, $item['id']));
            }
        }

        return $files;
    }

    $all_files = listAllFiles($access_token, '0');
    $total_used = 0;
    $total_used = array_sum(array_column($all_files, 'size'));
    try {
        $stmt = $pdo->prepare("
        UPDATE cloud_accounts  SET total_space = ?, space_available = ? WHERE email = ? AND provider = 'box'");
        $stmt->execute([
            10737418240, //i can t get the total space from api so we re gonna assume 10 gb free plan
            10737418240 - $total_used,
            $email
        ]);
    } catch (PDOException $e) {
        die("DB error: " . $e->getMessage());
    }


    try {
        $stmt = $pdo->prepare("SELECT file_id FROM files WHERE user_id = ? AND account_id = ?");
        $stmt->execute([$user_id, $account_id]);
    

        $existingFileIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stmtInsert = $pdo->prepare("INSERT INTO files (user_id,account_id,file_name, file_size,uploaded_at, type) VALUES (?,?, ?, ?, ?, ?)");

        foreach ($all_files as $file) {
            if (!in_array($file['id'], $existingFileIds)) {
                $stmtInsert->execute([
                    $user_id,
                    $account_id,
                    $file['name'],
                    $file['size'],
                    date('Y-m-d H:i:s', strtotime($file['created_at'])),
                    $file['type']
                ]);
            }
        }
    } catch (PDOException $e) {
        die("DB error: " . $e->getMessage());
    }




    header("Location: /php/welcome.php");
    exit;
} else {
    die('OAuth failed: ' . htmlspecialchars($response));
}
