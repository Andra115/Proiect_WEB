<?php

ignore_user_abort(true);
set_time_limit(0);


require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');


if (php_sapi_name() === 'cli') {
 
    $input = json_decode($argv[1], true);
} else {
  
    $input = json_decode(file_get_contents('php://input'), true);
}

if (!$input || !isset($input['account_id'], $input['access_token'], $input['email'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No account id/access token/email provided']);
    exit;
}

$account_id = $input['account_id'];
$access_token = $input['access_token'];
$email = $input['email'];
$provider = $input['provider'];
$user_id = $input['user_id'];

if (!$account_id || !$access_token || !$email || !$provider || !$user_id) {
    error_log("Erro not enough parameters");
    exit(1);
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
                'size' => $item['size'],
                'created_at' => $item['created_at'],
                'extension' => $item['extension'] ?? 'unknown',
                'type' => $item['type'],
            ];
        } elseif ($item['type'] === 'folder') {
            $files = array_merge($files, listAllBoxFiles($access_token, $item['id']));
        }
    }

    return $files;
}


function listAllDropboxFiles($access_token)
{
    $files = [];
    $cursor = null;
    
    do {
        $ch = curl_init('https://api.dropboxapi.com/2/files/list_folder' . ($cursor ? '/continue' : ''));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $access_token",
            "Content-Type: application/json"
        ]);
        
        $post_data = $cursor ? ['cursor' => $cursor] : ['path' => '', 'recursive' => true];
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        if (!isset($data['entries'])) break;
        
        foreach ($data['entries'] as $item) {
            if ($item['.tag'] === 'file') {
                $path_parts = pathinfo($item['name']);
                $files[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'size' => $item['size'],
                    'created_at' => $item['server_modified'],
                    'extension' => $path_parts['extension'] ?? 'unknown',
                    'type' => 'file'
                ];
            }
        }
        
        $cursor = $data['cursor'] ?? null;
        $has_more = $data['has_more'] ?? false;
    } while ($has_more);
    
    return $files;
}



function listAllGoogleDriveFiles($access_token, $folderId = 'root') {
    $files = [];
    $pageToken = null;
    
    do {
        $url = "https://www.googleapis.com/drive/v3/files?q='" . $folderId . "' in parents and trashed = false"
              . "&fields=nextPageToken, files(id, name, mimeType, size, createdTime)"
              . ($pageToken ? "&pageToken=" . $pageToken : "");
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $access_token"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        if (!isset($data['files'])) return $files;
        
        foreach ($data['files'] as $item) {
            if ($item['mimeType'] !== 'application/vnd.google-apps.folder') {
                $path_parts = pathinfo($item['name']);
                $files[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'size' => isset($item['size']) ? (int)$item['size'] : 0,
                    'created_at' => $item['createdTime'],
                    'extension' => $path_parts['extension'] ?? 'unknown',
                    'type' => 'file'
                ];
            } else {
                $files = array_merge($files, listAllGoogleDriveFiles($access_token, $item['id']));
            }
        }
        
        $pageToken = $data['nextPageToken'] ?? null;
    } while ($pageToken);
    
    return $files;
}



try {
    if($provider == 'box') {
        $all_files = listAllBoxFiles($access_token, '0');
        $total_used = array_sum(array_column($all_files, 'size'));

        $stmt = $pdo->prepare("UPDATE cloud_accounts SET total_space = ?, space_available = ? WHERE email = ? AND provider = 'box'");
        $stmt->execute([10737418240, 10737418240 - $total_used, $email]);
    }
    else if ($provider == 'dropbox'){
        $all_files = listAllDropboxFiles($access_token);
    }
    else if($provider == 'google'){
        $all_files = listAllGoogleDriveFiles($access_token);
    }

 
    $stmt = $pdo->prepare("SELECT file_name FROM files WHERE account_id = ?");
    $stmt->execute([$account_id]);
    $existingFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt2 = $pdo->prepare("SELECT chunk_file_id FROM file_chunks WHERE account_id = ?");
    $stmt2->execute([$account_id]);
    $existingFileIds = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    $stmtInsert = $pdo->prepare("INSERT INTO files (account_id, user_id, file_name, file_size, uploaded_at, type) VALUES (?, ?, ?, ?, ?, ?)");
    $newFiles = 0;

    foreach ($all_files as $file) {
        if (!in_array($file['id'], $existingFileIds)) {
            if (!in_array($file['name'], $existingFiles)) {
                $stmtInsert->execute([
                    $account_id,
                    $user_id,
                    $file['name'],
                    $file['size'],
                    $file['created_at'],
                    $file['extension']
                ]);
                $newFiles++;
            }
        }
    }

    $stmt = $pdo->prepare("SELECT file_id FROM files WHERE file_name LIKE ? AND account_id = ?");
    $insert = $pdo->prepare("INSERT INTO file_chunks (file_id, account_id, chunk_index, chunk_size, nr_of_chunks, chunk_file_id) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($all_files as $file) {
        if (!in_array($file['id'], $existingFileIds)) {
            $stmt->execute(['%' . $file['name'] . '%', $account_id]);
            $fileID = $stmt->fetchColumn();

            if ($fileID) {
                $insert->execute([
                    $fileID,
                    $account_id,
                    1,
                    $file['size'],
                    1,
                    $file['id']
                ]);
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Sync completed. Added $newFiles new files."
    ]);

} catch (Exception $e) {
    error_log("Error syncing files: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error during sync',
        'message' => $e->getMessage()
    ]);
}
