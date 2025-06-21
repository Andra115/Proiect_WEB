<?php
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
    exit;
}

require 'db.php';

$fileId = $_POST['fileId'] ?? null;
$userId = $_POST['userId'] ?? null;

if (!$fileId || !$userId || $fileId === 'undefined') {
    echo json_encode(['success' => false, 'error' => 'Missing fileId or userId']);
    exit;
}

$credsBox = json_decode(file_get_contents(__DIR__ . '/../box_credentials.json'), true);
$credsDropbox = json_decode(file_get_contents(__DIR__ . '/../dropbox_credentials.json'), true);
$credsGoogle = json_decode(file_get_contents(__DIR__ . '/../driver_credentials.json'), true);

if (!$credsBox || !$credsDropbox || !$credsGoogle) {
    http_response_code(500);
    echo json_encode(['error' => 'Error: Credentials file not found or invalid']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM files WHERE file_id = ?");
$stmt->execute([$fileId]);
$file = $stmt->fetch();

if (!$file) {
    echo json_encode(['success' => false, 'error' => 'DB: File not found']);
    exit;
}


if ($file['account_id']) {
    $stmt = $pdo->prepare("SELECT * FROM cloud_accounts WHERE account_id = ?");
    $stmt->execute([$file['account_id']]);
    $account = $stmt->fetch();

    //okay so this is for single chunked files(so most of them reastically speaking)
    if (!$account) {
        echo json_encode(['success' => false, 'error' => 'DB: Cloud account not found']);
        exit;
    }

    $provider = $account['provider'];
    $accessToken = $account['access_token'];
    $refreshToken = $account['refresh_token'];
    $expiry = $account['token_expiry'];
    $accountId = $account['account_id'];

    
    $accessToken = refreshTokenIfNeeded($provider, $accessToken, $refreshToken, $expiry, $accountId, $credsBox, $credsDropbox, $credsGoogle, $pdo);
    if (!$accessToken) {
        echo json_encode(['success' => false, 'error' => 'Token refresh failed']);
        exit;
    }

    $stmtId = $pdo->prepare("SELECT chunk_file_id, chunk_path FROM file_chunks WHERE file_id = ?");
    $stmtId->execute([$fileId]);
    $chunkk = $stmtId->fetch();

    if (!$chunkk) {
        echo json_encode(['success' => false, 'error' => 'No chunks found for this file']);
        exit;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'dl_');
    if ($provider == 'dropbox') {
        $success = downloadChunkStreaming($provider, $chunkk['chunk_path'], $accessToken, $tmp);
    } else {
        $success = downloadChunkStreaming($provider, $chunkk['chunk_file_id'], $accessToken, $tmp);
    }

    if (!$success) {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch file']);
        exit;
    }

    $token = uniqid('dl_', true);
    file_put_contents(sys_get_temp_dir() . "/$token.json", json_encode([
        'path' => $tmp,
        'name' => $file['file_name']
    ]));
    echo json_encode(['success' => true, 'token' => $token]);
    exit;
}

// and from here starts the procesing of chunks that im not sure if it works yet cause the upload is lowkey still a work in progress
$stmt = $pdo->prepare("SELECT fc.chunk_file_id, fc.chunk_path, ac.provider, ac.access_token, ac.refresh_token, ac.token_expiry, ac.account_id, fc.chunk_index 
                         FROM file_chunks fc JOIN cloud_accounts ac ON fc.account_id = ac.account_id WHERE fc.file_id = ? ORDER BY fc.chunk_index ASC");
$stmt->execute([$fileId]);
$chunks = $stmt->fetchAll();

if (!$chunks) {
    echo json_encode(['success' => false, 'error' => 'No chunks found']);
    exit;
}


$finalPath = tempnam(sys_get_temp_dir(), 'final_');
$finalHandle = fopen($finalPath, 'wb');

if (!$finalHandle) {
    echo json_encode(['success' => false, 'error' => 'Could not create final file']);
    exit;
}


foreach ($chunks as $chunk) {
    $provider = $chunk['provider'];
    $cloudFileId = $chunk['chunk_file_id'];
    $accessToken = $chunk['access_token'];
    $chunkPath = $chunk['chunk_path'];
    $expiry = $chunk['token_expiry'];
    $refreshToken = $chunk['refresh_token'];
    $accountId = $chunk['account_id'];

    
    $accessToken = refreshTokenIfNeeded($provider, $accessToken, $refreshToken, $expiry, $accountId, $credsBox, $credsDropbox, $credsGoogle, $pdo);
    if (!$accessToken) {
        fclose($finalHandle);
        unlink($finalPath);
        echo json_encode(['success' => false, 'error' => 'Token refresh failed for chunk ' . $chunk['chunk_index']]);
        exit;
    }

   
    if ($provider == 'dropbox') {
        $success = downloadChunkDirectly($provider, $chunk['chunk_path'], $accessToken, $finalHandle);
    } else {
        $success = downloadChunkDirectly($provider, $chunk['chunk_file_id'], $accessToken, $finalHandle);
    }

    if (!$success) {
        fclose($finalHandle);
        unlink($finalPath);
        echo json_encode(['success' => false, 'error' => "Failed to fetch chunk {$chunk['chunk_index']}"]);
        exit;
    }
}

fclose($finalHandle);


$token = uniqid('dl_', true);
if (!isset($_SESSION['downloads'])) {
    $_SESSION['downloads'] = [];
}
$_SESSION['downloads'][$token] = [
    'path' => $finalPath,
    'name' => $file['file_name']
];

echo json_encode(['success' => true, 'token' => $token]);

function refreshTokenIfNeeded($provider, $accessToken, $refreshToken, $expiry, $accountId, $credsBox, $credsDropbox, $credsGoogle, $pdo) {
    $sec = 300; 
    
    if ((strtotime($expiry) - time()) < $sec) {
        if ($provider == 'box') {
            $url = "https://api.box.com/oauth2/token";
            $params = [
                'grant_type' => 'refresh_token',
                'client_id' => $credsBox['client_id'],
                'client_secret' => $credsBox['client_secret'],
                'refresh_token' => $refreshToken
            ];
        } else if ($provider == 'dropbox') {
            $url = "https://api.dropboxapi.com/oauth2/token";
            $params = [
                'grant_type' => 'refresh_token',
                'client_id' => $credsDropbox['client_id'],
                'client_secret' => $credsDropbox['client_secret'],
                'refresh_token' => $refreshToken
            ];
        } else if ($provider == 'google') {
            $url = "https://oauth2.googleapis.com/token";
            $params = [
                'grant_type' => 'refresh_token',
                'client_id' => $credsGoogle['web']['client_id'],
                'client_secret' => $credsGoogle['web']['client_secret'],
                'refresh_token' => $refreshToken
            ];
        }
        
        $tokenData = refreshAccessToken($url, $params);
        $newAccessToken = $tokenData['access_token'] ?? null;
        $expiresIn = $tokenData['expires_in'] ?? null;
        $newRefreshToken = $tokenData['refresh_token'] ?? $refreshToken;

        if ($newAccessToken == null) {
            return false;
        }

        if ($expiresIn == null) {
            $expiresIn = ($provider == 'dropbox') ? 14400 : 3600;
        }

        $token_expiry = time() + $expiresIn;
        $token_expiry_formatted = date('Y-m-d H:i:s', $token_expiry);

        try {
            if ($provider == 'google' || $provider == 'dropbox') {
                $stmtUpdate = $pdo->prepare("UPDATE cloud_accounts SET access_token = ?, token_expiry = ? WHERE account_id = ?");
                $stmtUpdate->execute([$newAccessToken, $token_expiry_formatted, $accountId]);
            } else {
                $stmtUpdate = $pdo->prepare("UPDATE cloud_accounts SET access_token = ?, refresh_token = ?, token_expiry = ? WHERE account_id = ?");
                $stmtUpdate->execute([$newAccessToken, $newRefreshToken, $token_expiry_formatted, $accountId]);
            }
        } catch (Exception $e) {
            error_log('Database error: ' . $e->getMessage());
            return false;
        }

        return $newAccessToken;
    }
    
    return $accessToken;
}


function downloadChunkStreaming($provider, $cloudFileId, $accessToken, $saveTo) {
    switch ($provider) {
        case 'dropbox':
            $url = "https://content.dropboxapi.com/2/files/download";
            $headers = [
                "Authorization: Bearer $accessToken",
                "Dropbox-API-Arg: " . json_encode(["path" => $cloudFileId])
            ];
            break;

        case 'box':
            $url = "https://api.box.com/2.0/files/$cloudFileId/content";
            $headers = ["Authorization: Bearer $accessToken"];
            break;

        case 'google':
            $url = "https://www.googleapis.com/drive/v3/files/$cloudFileId?alt=media";
            $headers = ["Authorization: Bearer $accessToken"];
            break;

        default:
            return false;
    }

    $outFile = fopen($saveTo, 'wb');
    if (!$outFile) {
        return false;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FILE => $outFile,
        CURLOPT_BUFFERSIZE => 8192
    ]);
    
    $success = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    fclose($outFile);

    if ($error) {
        error_log("cURL Error: " . $error);
        unlink($saveTo);
        return false;
    }

    if ($httpCode !== 200) {
        error_log("HTTP Error: $httpCode");
        unlink($saveTo);
        return false;
    }

    return $success !== false;
}


function downloadChunkDirectly($provider, $cloudFileId, $accessToken, $fileHandle) {
    switch ($provider) {
        case 'dropbox':
            $url = "https://content.dropboxapi.com/2/files/download";
            $headers = [
                "Authorization: Bearer $accessToken",
                "Dropbox-API-Arg: " . json_encode(["path" => $cloudFileId])
            ];
            break;

        case 'box':
            $url = "https://api.box.com/2.0/files/$cloudFileId/content";
            $headers = ["Authorization: Bearer $accessToken"];
            break;

        case 'google':
            $url = "https://www.googleapis.com/drive/v3/files/$cloudFileId?alt=media";
            $headers = ["Authorization: Bearer $accessToken"];
            break;

        default:
            return false;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FILE => $fileHandle,
        CURLOPT_BUFFERSIZE => 8192
    ]);
    
    $success = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("cURL Error: " . $error);
        return false;
    }

    if ($httpCode !== 200) {
        error_log("HTTP Error: $httpCode");
        return false;
    }

    return $success !== false;
}

function refreshAccessToken($url, $params) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("Curl error: " . curl_error($ch));
        curl_close($ch);
        return null;
    }

    curl_close($ch);
    return json_decode($response, true);
}
?>