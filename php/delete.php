<?php
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

if (!$input || !$input['file_id'] || !$input['user_id']) {
    http_response_code(400);
    echo json_encode(['error' => 'Error: Invalid input']);
    exit;
}

$user_id = $input['user_id'];
$file_id = $input['file_id'];

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
if (!$chunkIds) {
    http_response_code(404);
    echo json_encode(['error' => 'Error: No chunks found for this file']);
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
    $http_code = 0;
    $entered = "false";
   try{
    $stmtAcc=$pdo->prepare("SELECT ca.account_id, ca.access_token, ca.refresh_token, ca.provider, ca.token_expiry 
                            FROM cloud_accounts ca JOIN file_chunks fc ON ca.account_id = fc.account_id WHERE fc.chunk_id = ?");
    $stmtAcc->execute([$chunkId]);
    $accountInfo = $stmtAcc->fetch(PDO::FETCH_ASSOC);
   } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }

    try{
        $stmt = $pdo->prepare("SELECT fc.chunk_file_id, fc.chunk_path FROM file_chunks fc WHERE fc.chunk_id = ?");
        $stmt->execute([$chunkId]);
        list($chunkFileId, $chunkPath) = $stmt->fetch(PDO::FETCH_NUM);
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

        $sec = 300; //it ll check that the acces token is available for at least 5 minutes more just in case
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

            $accessToken = $tokenData['access_token'] ?? null;
            $expiresIn = $tokenData['expires_in'] ?? null;
            $refreshToken = $tokenData['refresh_token'] ?? null;  

            if($accessToken == null){
                echo json_encode([
                    'success' => false,
                    'message' => 'Access token refresh failed. Reconnect account.',
                    'token_data' => $tokenData,
                    'provider' => $provider,
                    'account_id' => $accountId
                ]);
                exit;
            }

            if($expiresIn == null){
                if($provider == 'dropbox'){
                    $expiresIn = 14400; 
                } else{
                    $expiresIn = 3600; 
                }
            }
                 $token_expiry = time() + $expiresIn;
                 $token_expiry_formatted = date('Y-m-d H:i:s', $token_expiry);   

                 if($provider == 'google' || $provider == 'dropbox'){
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
    }
            if($provider == 'box'){
                $entered = "true - box";
                 $url = "https://api.box.com/2.0/files/$chunkFileId";

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer $accessToken",
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
                $http_code = $httpCode;

                if($httpCode === 204){
                    $stmt = $pdo->prepare("DELETE FROM file_chunks WHERE chunk_id=?");
                    $stmt->execute([$chunkId]);
                    $nr++;
                    
                }
                else{
                    echo json_encode([
                        'success' => false,
                        'message' => 'Box chunk could not be deleted',
                        'dropbox_response' => $response,
                        'http_code' => $httpCode,
                        'chunk_file_id' => $chunkFileId
                    ]);
                    exit;
                }
            }
            else if($provider == 'dropbox'){
                $entered = "true - dropbox";

                $url = "https://api.dropboxapi.com/2/files/delete_v2";
                $data = json_encode(['path' => "$chunkPath"]);

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
                $http_code = $httpCode;
                if($httpCode === 200){
                    $stmt = $pdo->prepare("DELETE FROM file_chunks WHERE chunk_id=?");
                    $stmt->execute([$chunkId]);
                    $nr++;
                    
                }
                else{
                    echo json_encode([
                        'success' => false,
                        'message' => 'Dropbox chunk could not be deleted',
                        'dropbox_response' => $response,
                        'http_code' => $httpCode,
                        'chunk_file_id' => $chunkFileId
                    ]);
                    exit;
                }
            }
            else if ($provider == 'google'){
                $entered = "true - google";
                $url = "https://www.googleapis.com/drive/v3/files/$chunkFileId";

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer $accessToken",
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
                $http_code = $httpCode;
                if($httpCode === 204){
                    $stmt = $pdo->prepare("DELETE FROM file_chunks WHERE chunk_id=?");
                    $stmt->execute([$chunkId]);
                    $nr++;
                    
                }
                else{
                    echo json_encode([
                        'success' => false,
                        'message' => 'Google Drive chunk could not be deleted',
                        'dropbox_response' => $response,
                        'http_code' => $httpCode,
                        'chunk_file_id' => $chunkFileId,
                        'http_code' => $http_code,
                        'entered' => $entered
                    ]);
                    exit;
                }
            }
        
        
    }
    else{
        echo json_encode([
            'success' => false,
            'message' => 'Account info not found',
            'chunk_file_id' => $chunkFileId
        ]);
        exit;
    }

    if ($nr == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Chunk not deleted and no explicit error triggered' . $nr . ' ' . $nrOfChunks,
            'provider' => $provider,
            'chunk_file_id' => $chunkFileId,
            'account_info' => $accountInfo,
            'entered' => $entered,
            'http_code' => $http_code
        ]);
        exit;
    }
}


if($nr == $nrOfChunks){

    try{
        $stmt = $pdo->prepare("SELECT ac.providor, ac.access_token, ac.email, ac.user_id FROM cloud_accounts ac JOIN file_chunks fc ON ac.account_id = fc.account_id WHERE fc.file_id = ? LIMIT 1");
        $stmt->execute([$file_id]);
        $accountInfo = $stmt->fetch(PDO::FETCH_ASSOC);
       
        
    }catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }

    $provider = $accountInfo['providor'] ?? null;
    $accessToken = $accountInfo['access_token'] ?? null;
    $email = $accountInfo['email'] ?? null;
    $user_id = $accountInfo['user_id'] ?? null;
   try{ 
    $stmt = $pdo->prepare("DELETE FROM files WHERE file_id=?");
    $stmt->execute([$file_id]);
   } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }

    if ($provider == 'box') {
         $all_files = listAllBoxFiles($accessToken, '0');
        $total_used = array_sum(array_column($all_files, 'size'));

        $stmt = $pdo->prepare("UPDATE cloud_accounts SET total_space = ?, space_available = ? WHERE email = ? AND provider = 'box' AND user_id = ?");
        $stmt->execute([10737418240, 10737418240 - $total_used, $email, $user_id]);
    } else if ($provider == 'dropbox') {

        $ch = curl_init("https://api.dropboxapi.com/2/users/get_space_usage");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken","Content-Type: application/json" ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'null');
        $response = curl_exec($ch);
        curl_close($ch);

        $user_storage_info = json_decode($response, true);
        $used = $user_storage_info['used'] ?? null;
        $allocation = $user_storage_info['allocation'] ?? null;
        $total_space = $allocation['allocated'] ?? 2147483648; //it should be able to get this info but in case it doesnt we ll assume 2GB free plan
        
        $space_available = $total_space - $used;

         $stmt = $pdo->prepare("UPDATE cloud_accounts SET total_space= ?, space_available=?WHERE email = ? AND provider = 'dropbox' AND user_id = ?");
         $stmt->execute([$total_space, $space_available, $email, $user_id]);

    } else if ($provider == 'google') {

        $client->setAccessToken($accessToken);

        $driveService = new Google\Service\Drive($client);
        $about = $driveService->about->get(['fields' => 'storageQuota']);
        $user_storage_info = $about->getStorageQuota();
        $total_space = $user_storage_info->getLimit() ?? 16106127360;
        $used = $user_storage_info->getUsage() ?? 0;

        $space_available= $total_space - $used;

        $stmt = $pdo->prepare("UPDATE cloud_accounts SET total_space = ?, space_available = ? WHERE email = ? AND provider = 'google' AND user_id = ?");
        $stmt->execute([$total_space,$space_available, $email, $user_id]);
        
    } else {
        echo json_encode(['success' => true, 'message' => 'Space couldnt be synced after deletion.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'File deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Some chunks could not be deleted. '.$nr.'/'.$nrOfChunks]);
}


function listAllBoxFiles($access_token, $folderId = '0')
{
    $files = [];
    $url = "https://api.box.com/2.0/folders/$folderId/items?limit=1000&fields=id,name,size,created_at,extension,type";

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
                'size' => $item['size']
            ];
        } elseif ($item['type'] === 'folder') {
            $files = array_merge($files, listAllBoxFiles($access_token, $item['id']));
        }
    }

    return $files;
}




?>