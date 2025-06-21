<?php
require_once 'db.php';

$credsBox = json_decode(file_get_contents(__DIR__ . '/../box_credentials.json'), true);
$credsDropbox = json_decode(file_get_contents(__DIR__ . '/../dropbox_credentials.json'), true);
$credsGoogle = json_decode(file_get_contents(__DIR__ . '/../driver_credentials.json'), true);

if (!$credsBox || !$credsDropbox || !$credsGoogle) {
    throw new Exception('Error: Credentials file not found or invalid');
}

function uploadChunkToCloud($provider, $chunkPath, $accessToken, $fileName) {
    switch ($provider) {
        case 'google':
            return uploadToGoogleDrive($chunkPath, $accessToken, $fileName);
        case 'dropbox':
            return uploadToDropbox($chunkPath, $accessToken, $fileName);
        case 'box':
            return uploadToBox($chunkPath, $accessToken, $fileName);
        default:
            error_log("Unknown provider: $provider");
            return false;
    }
}

function testGoogleDriveAccess($accessToken) {
    $url = 'https://www.googleapis.com/drive/v3/about?fields=user,storageQuota';
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $accessToken"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Google Drive test cURL error: $error");
        return false;
    }
    
    if ($httpCode !== 200) {
        error_log("Google Drive test HTTP error: $httpCode - $response");
        return false;
    }
    
    $data = json_decode($response, true);
    if (!isset($data['user'])) {
        error_log("Google Drive test invalid response: $response");
        return false;
    }
    
    error_log("Google Drive API access test successful - User: " . $data['user']['emailAddress']);
    return true;
}

function uploadToGoogleDrive($filePath, $accessToken, $fileName) {
    if (!testGoogleDriveAccess($accessToken)) {
        error_log("Google Drive API access test failed");
        return false;
    }
    
    $fileSize = filesize($filePath);
    $mimeType = mime_content_type($filePath);
    
    error_log("Google Drive upload starting - File: $fileName, Size: $fileSize bytes, MIME: $mimeType");
    
    $initUrl = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable';
    
    $metadata = [
        'name' => $fileName,
        'parents' => ['root']
    ];
    
    $ch = curl_init($initUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json; charset=UTF-8",
            "X-Upload-Content-Type: $mimeType",
            "X-Upload-Content-Length: $fileSize"
        ],
        CURLOPT_POSTFIELDS => json_encode($metadata),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HEADER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    
    if ($error) {
        error_log("Google Drive init cURL error: $error");
        return false;
    }
    
    if ($httpCode !== 200) {
        error_log("Google Drive init HTTP error: $httpCode - $response");
        return false;
    }
    
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    $sessionUrl = null;
    if (preg_match('/Location:\s*(.+)/i', $headers, $matches)) {
        $sessionUrl = trim($matches[1]);
    }
    
    if (!$sessionUrl) {
        error_log("Google Drive init failed - no session URL in headers: $headers");
        return false;
    }
    
    error_log("Google Drive upload session created: $sessionUrl");
    
    $ch = curl_init($sessionUrl);
    curl_setopt_array($ch, [
        CURLOPT_PUT => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $accessToken",
            "Content-Type: $mimeType",
            "Content-Length: $fileSize"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_UPLOAD => true,
        CURLOPT_INFILE => fopen($filePath, 'rb'),
        CURLOPT_INFILESIZE => $fileSize
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Google Drive upload cURL error: $error");
        return false;
    }
    
    if ($httpCode !== 200) {
        error_log("Google Drive upload HTTP error: $httpCode - $response");
        return false;
    }
    
    $data = json_decode($response, true);
    if (!isset($data['id'])) {
        error_log("Google Drive upload invalid response: $response");
        return false;
    }
    
    $fileId = $data['id'];
    error_log("Google Drive upload successful - File ID: $fileId");
    
    return [
        'file_id' => $fileId,
        'path' => null 
    ];
}


function uploadToDropbox($filePath, $accessToken, $fileName) {
    $fileSize = filesize($filePath);
    $chunkSize = 150 * 1024 * 1024;
    $isLarge = $fileSize > 150 * 1024 * 1024;

    if (!$isLarge) {
        $url = 'https://content.dropboxapi.com/2/files/upload';
        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/octet-stream",
            "Dropbox-API-Arg: " . json_encode([
                "path" => "/$fileName",
                "mode" => "add",
                "autorename" => true,
                "mute" => false,
                "strict_conflict" => false
            ])
        ];
        $fileContent = file_get_contents($filePath);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fileContent,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) {
            error_log("Dropbox upload error: $error");
            return false;
        }
        $data = json_decode($response, true);
        if (!isset($data['id'])) {
            error_log("Dropbox upload invalid response: $response");
            return false;
        }
        error_log("Dropbox upload successful - File ID: " . $data['id']);
        return [
            'file_id' => $data['id'],
            'path' => $data['path_display']
        ];
    } else {
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            error_log("Dropbox session upload error: Could not open file");
            return false;
        }

        $url = 'https://content.dropboxapi.com/2/files/upload_session/start';
        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/octet-stream",
            "Dropbox-API-Arg: " . json_encode(["close" => false])
        ];
        $firstChunk = fread($handle, $chunkSize);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $firstChunk,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) {
            error_log("Dropbox session start error: $error");
            fclose($handle);
            return false;
        }
        $data = json_decode($response, true);
        if (!isset($data['session_id'])) {
            error_log("Dropbox session start invalid response: $response");
            fclose($handle);
            return false;
        }
        $sessionId = $data['session_id'];
        $offset = strlen($firstChunk);

        $chunkNum = 2;
        while ($offset < $fileSize) {
            $chunk = fread($handle, $chunkSize);
            $url = 'https://content.dropboxapi.com/2/files/upload_session/append_v2';
            $headers = [
                "Authorization: Bearer $accessToken",
                "Content-Type: application/octet-stream",
                "Dropbox-API-Arg: " . json_encode([
                    "cursor" => [
                        "session_id" => $sessionId,
                        "offset" => $offset
                    ],
                    "close" => false
                ])
            ];
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $chunk,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            error_log("Dropbox: Uploaded chunk $chunkNum, offset $offset, size " . strlen($chunk));
            if ($error) {
                error_log("Dropbox session append error: $error");
                fclose($handle);
                return false;
            }
            $offset += strlen($chunk);
            $chunkNum++;
        }
        fclose($handle);

        $url = 'https://content.dropboxapi.com/2/files/upload_session/finish';
        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/octet-stream",
            "Dropbox-API-Arg: " . json_encode([
                "cursor" => [
                    "session_id" => $sessionId,
                    "offset" => $fileSize
                ],
                "commit" => [
                    "path" => "/$fileName",
                    "mode" => "add",
                    "autorename" => true,
                    "mute" => false,
                    "strict_conflict" => false
                ]
            ])
        ];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => "",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) {
            error_log("Dropbox session finish error: $error");
            return false;
        }
        $data = json_decode($response, true);
        if (!isset($data['id'])) {
            error_log("Dropbox session finish invalid response: $response");
            return false;
        }
        error_log("Dropbox session upload successful - File ID: " . $data['id']);
        return [
            'file_id' => $data['id'],
            'path' => $data['path_display']
        ];
    }
}

function uploadToBox($filePath, $accessToken, $fileName) {
    $fileSize = filesize($filePath);
    error_log("Box upload starting - File: $fileName, Size: $fileSize bytes");
    
    $url = 'https://upload.box.com/api/2.0/files/content';
    
    $boundary = '----WebKitFormBoundary' . uniqid();
    $delimiter = "\r\n";
    
    $bodyHeader = '';
    $bodyHeader .= '--' . $boundary . $delimiter;
    $bodyHeader .= 'Content-Disposition: form-data; name="attributes"' . $delimiter;
    $bodyHeader .= 'Content-Type: application/json' . $delimiter . $delimiter;
    $bodyHeader .= json_encode([
        'name' => $fileName,
        'parent' => ['id' => '0'] 
    ]) . $delimiter;
    $bodyHeader .= '--' . $boundary . $delimiter;
    $bodyHeader .= 'Content-Disposition: form-data; name="file"; filename="' . $fileName . '"' . $delimiter;
    $bodyHeader .= 'Content-Type: application/octet-stream' . $delimiter . $delimiter;
    
    $bodyFooter = $delimiter . '--' . $boundary . '--' . $delimiter;
    
    $tempFile = tempnam(sys_get_temp_dir(), 'box_upload_');
    $tempHandle = fopen($tempFile, 'wb');
    
    if (!$tempHandle) {
        error_log("Box upload error: Could not create temp file");
        return false;
    }
    
    fwrite($tempHandle, $bodyHeader);
    
    $fileHandle = fopen($filePath, 'rb');
    if (!$fileHandle) {
        error_log("Box upload error: Could not open source file: $filePath");
        fclose($tempHandle);
        unlink($tempFile);
        return false;
    }
    
    $bytesWritten = 0;
    while (!feof($fileHandle)) {
        $chunk = fread($fileHandle, 8192);
        $written = fwrite($tempHandle, $chunk);
        $bytesWritten += $written;
    }
    fclose($fileHandle);
    
    fwrite($tempHandle, $bodyFooter);
    fclose($tempHandle);
    
    $tempFileSize = filesize($tempFile);
    error_log("Box upload - Temp file created: $tempFile, Size: $tempFileSize bytes, Bytes written: $bytesWritten");
    
    $postData = file_get_contents($tempFile);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $accessToken",
            "Content-Type: multipart/form-data; boundary=$boundary",
            "Content-Length: " . strlen($postData)
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 600, 
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_VERBOSE => true,
        CURLOPT_STDERR => fopen(sys_get_temp_dir() . '/box_curl.log', 'w')
    ]);
    
    error_log("Box upload - Starting CURL request to: $url");
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    unlink($tempFile);
    
    error_log("Box upload - CURL completed. HTTP Code: $httpCode, Error: $error");
    error_log("Box upload - Response: $response");
    error_log("Box upload - CURL Info: " . json_encode($info));
    
    if ($error) {
        error_log("Box upload cURL error: $error");
        return false;
    }
    
    if ($httpCode !== 201) { 
        error_log("Box upload HTTP error: $httpCode - $response");
        return false;
    }
    
    $data = json_decode($response, true);
    if (!isset($data['entries'][0]['id'])) {
        error_log("Box upload invalid response: $response");
        return false;
    }
    
    error_log("Box upload successful - File ID: " . $data['entries'][0]['id']);
    return [
        'file_id' => $data['entries'][0]['id'],
        'path' => null
    ];
}

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

        $newExpiry = time() + ($expiresIn ?? 3600);
        $newExpiryFormatted = date('Y-m-d H:i:s', $newExpiry);

        $stmt = $pdo->prepare("UPDATE cloud_accounts SET access_token = ?, refresh_token = ?, token_expiry = ? WHERE account_id = ?");
        $stmt->execute([$newAccessToken, $newRefreshToken, $newExpiryFormatted, $accountId]);

        return $newAccessToken;
    }
    
    return $accessToken;
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
        return false;
    }
    curl_close($ch);
    $data = json_decode($response, true);
    if (isset($data['error'])) {
        error_log("Token refresh error: " . $response);
        return false;
    }
    return $data;
}
?> 