<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db.php';
$credsBox = json_decode(file_get_contents(__DIR__ . '/../box_credentials.json'), true);
$credsDropbox = json_decode(file_get_contents(__DIR__ . '/../dropbox_credentials.json'), true);
$credsGoogle = json_decode(file_get_contents(__DIR__ . '/../driver_credentials.json'), true);

if (!$credsBox || !$credsDropbox || !$credsGoogle) {
    http_response_code(500);
    echo json_encode(['error' => 'Error: Credentials file not found or invalid']);
    exit;
}

header('Content-Type: application/json');

if (php_sapi_name() === 'cli') {
 
    $input = json_decode($argv[1], true);
} else {
  
    $input = json_decode(file_get_contents('php://input'), true);
}

if (!$input || !$input['file_id'] || !$input['user_id'] || !$input['new_name']) {
    http_response_code(400);
    echo json_encode(['error' => 'Error: Invalid input']);
    exit;
}

$user_id = $input['user_id'];
$file_id = $input['file_id'];
$newName = $input['new_name'];

function refreshAccessToken($url, $params) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception("Curl error: " . curl_error($ch));
    }

    curl_close($ch);
    return json_decode($response, true);
}

try{
    $stmtInfo = $pdo->prepare("SELECT chunk_id FROM file_chunks WHERE file_id=? ");
    $stmtInfo->execute([$file_id]);
    $chunkIds = $stmtInfo->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

try{
    $stmtInfo = $pdo->prepare("SELECT chunk_id FROM file_chunks WHERE file_id=? ");
    $stmtInfo->execute([$file_id]);
    $chunkIds = $stmtInfo->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}


$id=$chunkIds[0];
try{
    $stmtNr = $pdo->prepare("SELECT nr_of_chunks FROM file_chunks WHERE chunk_id=?");
    $stmtNr->execute([$id]);
    $nrOfChunks = $stmtNr->fetchColumn();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
$nr=0;


foreach ($chunkIds as $chunkId) {
   try{
    $stmtAcc=$pdo->prepare("SELECT ca.account_id, ca.access_token, ca.refresh_token, ca.provider, ca.token_expiry 
                            FROM cloud_accounts ca JOIN file_chunks fc ON ca.account_id = fc.account_id WHERE fc.chunk_id = ?");
    $stmtAcc->execute([$chunkId]);
    $stmtAcc->execute([$chunkId]);
    $accountInfo = $stmtAcc->fetch(PDO::FETCH_ASSOC);
   } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }

    try{
        $stmt = $pdo->prepare("SELECT fc.chunk_file_id FROM file_chunks fc WHERE fc.chunk_id = ?");
        $stmt->execute([$chunkId]);
        $chunkFileId = $stmt->fetchColumn();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
    if($accountInfo){

        $accessToken = $accountInfo['access_token'];
        $refreshToken = $accountInfo['refresh_token'];
        $expiry = $accountInfo['token_expiry'];
        $provider = $accountInfo['provider'];
        $accountId = $accountInfo['account_id'];

        $sec = 300; //it ll check that the acces token is available for at least 5 more minutes just in case
        if ((strtotime($expiry) - time()) < $sec) {
            if($provider == 'box'){
                $url = "https://api.box.com/oauth2/token";
                $params = [
                    'grant_type' => 'refresh_token',
                    'client_id' => $credsBox['client_id'],
                    'client_secret' => $credsBox['client_secret'],
                    'refresh_token' => $refreshToken
                ];
            } else if($provider == 'dropbox'){
                $url = "https://api.dropboxapi.com/oauth2/token";
                $params = [
                    'grant_type' => 'refresh_token',
                    'client_id' => $credsDropbox['client_id'],
                    'client_secret' => $credsDropbox['client_secret'],
                    'refresh_token' => $refreshToken
                ];
            } else if ($provider == 'google'){
                $url = "https://oauth2.googleapis.com/token";
                $params = [
                    'grant_type' => 'refresh_token',
                    'client_id' => $credsGoogle['web']['client_id'],
                    'client_secret' => $credsGoogle['web']['client_secret'],
                    'refresh_token' => $refreshToken
                ];
            }
            $tokenData = refreshAccessToken($url, $params);  //acctok, expires, refresh
            if (isset($tokenData['error'])) {
                http_response_code(500);
                echo json_encode(['error' => 'Error refreshing access token: ' . $tokenData['error']]);
                exit;
            }
            $accessToken = $tokenData['access_token'] ?? null;
            $expiresIn = $tokenData['expires_in'] ?? null;
            $refreshToken = $tokenData['refresh_token'] ?? null;  

            if($expiresIn == null){
                if($provider == 'dropbox'){
                    $expiresIn = 14400; 
                } else{
                    $expiresIn = 3600; 
                }
            }
                 $token_expiry = time() + $expiresIn;
                 $token_expiry_formatted = date('Y-m-d H:i:s', $token_expiry);   
                if($provider == 'google'){
                    try{  $stmtUpdate = $pdo->prepare("UPDATE cloud_accounts SET access_token = ?, token_expiry = ? WHERE account_id = ?");
                        $stmtUpdate->execute([$accessToken, $token_expiry_formatted, $accountId]);
                      } catch (Exception $e) {
                           http_response_code(500);
                           echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
                           exit;
                      }
                }
                else{
               try{  $stmtUpdate = $pdo->prepare("UPDATE cloud_accounts SET access_token = ?, refresh_token = ?, token_expiry = ? WHERE account_id = ?");
                 $stmtUpdate->execute([$accessToken, $refreshToken, $token_expiry_formatted, $accountId]);
               } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
                    exit;
               }
            

        }
            if($provider == 'box'){
                
                 $url = "https://api.box.com/2.0/files/$chunkFileId";
                 $data = json_encode(["name" => $newName]);

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer $accessToken",
                    "content-type: application/json",
                    "Content-Length: " . strlen($data)
                ]);

                $response = curl_exec($ch);
                if ($response === false) {
                    $error = curl_error($ch);
                    curl_close($ch);
                    throw new Exception("cURL error: $error");
                }

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if($httpCode !== 200){
                    http_response_code(500);
                    echo json_encode(['error' => 'Error renaming file on Box: ' . $response]);
                    exit;
                }
                else{
                    $response = json_decode($response, true);
                    if(!isset($response['name'])){
                        http_response_code(500);
                        echo json_encode(['error' => 'Error renaming file on Box: Invalid response']);
                        exit;
                    }
                    else{
                        $nr++;
                    }
                }
                
            }
            else if($provider == 'dropbox'){

                $url = "https://api.dropboxapi.com/2/files/move_v2";
                $data = json_encode([
                "from_path" => $fromPath,
                "to_path" => $newName,
                "autorename" => false
            ]);

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer $accessToken",
                    "Content-Type: application/json"
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                $response = curl_exec($ch);
                if ($response === false) {
                    $error = curl_error($ch);
                    curl_close($ch);
                    throw new Exception("cURL error: $error");
                }

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                
                    $response = json_decode($response, true);
                    if(!isset($response['metadata']['name'])){
                        http_response_code(500);
                        echo json_encode(['error' => 'Error renaming file on Dropbox: Invalid response']);
                        exit;
                    }
                    else{
                        $nr++;
                    }
                   
                    
                
            }
            else if ($provider == 'google'){

                $url = "https://www.googleapis.com/drive/v3/files/$chunkFileId";
                $data = json_encode(["name" => $newName]);

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer $accessToken",
                    "Content-Type: application/json",
                    "Content-Length: " . strlen($data)
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                $response = curl_exec($ch);
                
                if ($response === false) {
                    $error = curl_error($ch);
                    curl_close($ch);
                    throw new Exception("cURL error: $error");
                }

                curl_close($ch);
                
                $res = json_decode($response, true);
                if(!isset($res['name'])){
                    http_response_code(500);
                    echo json_encode([
                        'error' => 'Error renaming file on Google Drive: Invalid response',
                        'google_response' => $response 
                    ]);
                    exit;
                } else {
                    $nr++;
                }
               
            }
        
        }
    }
}


if($nr == $nrOfChunks){
   try{ 
    $stmt = $pdo->prepare("UPDATE files SET name = ? WHERE file_id = ?");
    $stmt->execute([$newName, $file_id]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
    echo json_encode(['success' => true, 'message' => 'File renamed successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Some chunks could not be renamed']);
}



?>