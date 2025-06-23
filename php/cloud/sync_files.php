<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');


if (php_sapi_name() === 'cli') {
 
    $input = json_decode($argv[1], true);
} else {
  
    $input = json_decode(file_get_contents('php://input'), true);
}

if (!$input || !isset($input['account_id'], $input['access_token'], $input['email'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Error: Invalid input']);
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
                'path' => null
            ];
        } elseif ($item['type'] === 'folder') {
            $files = array_merge($files, listAllBoxFiles($access_token, $item['id']));
        }
    }

    return $files;
}


function listAllDropboxFiles($access_token, $cursor = null)
{
    $files = [];

    $url = $cursor === null
        ? 'https://api.dropboxapi.com/2/files/list_folder'
        : 'https://api.dropboxapi.com/2/files/list_folder/continue';

    $postFields = $cursor === null
        ? json_encode(['path' => '', 'recursive' => true, 'include_media_info' => false])
        : json_encode(['cursor' => $cursor]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
   
    curl_close($ch);

    $data = json_decode($response, true);
    if (!isset($data['entries'])) return $files;

    foreach ($data['entries'] as $entry) {
        if ($entry['.tag'] === 'file') {
            $path_parts = pathinfo($entry['name']);
            $files[] = [
                'id' => $entry['id'],
                'name' => $entry['name'],
                'size' => $entry['size'],
                'created_at' => $entry['client_modified'],
                'extension' => $path_parts['extension'] ?? 'unknown',
                'type' => 'file',
                'path' => $entry['path_display']
            ];
        }
    }

    if (!empty($data['has_more']) && isset($data['cursor'])) {
        $files = array_merge($files, listAllDropboxFiles($access_token, $data['cursor']));
    }

    return $files;
}



function listAllGoogleDriveFiles($access_token, $folderId = 'root')
{
    $files = [];
    $pageToken = null;

    do {
        $url = "https://www.googleapis.com/drive/v3/files?q='" . $folderId . "' in parents and trashed = false"
             . "&fields=nextPageToken, files(id, name, mimeType, size, createdTime)"
             . ($pageToken ? "&pageToken=" . $pageToken : "");

        $ch = curl_init("https://www.googleapis.com/drive/v3/files?q=" . urlencode("'" . $folderId . "' in parents and trashed=false") . "&fields=nextPageToken,files(id,name,mimeType,size,createdTime)" . ($pageToken ? "&pageToken=" . $pageToken : ""));
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
                    'type' => 'file',
                    'path' => null
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

        $stmt = $pdo->prepare("UPDATE cloud_accounts SET total_space = ?, space_available = ? WHERE email = ? AND provider = 'box' AND user_id = ?");
        $stmt->execute([10737418240, 10737418240 - $total_used, $email, $user_id]);
    }
    else if ($provider == 'dropbox'){
        $all_files = listAllDropboxFiles($access_token);
    }
    else if($provider == 'google'){
        $all_files = listAllGoogleDriveFiles($access_token);
    }

 
    $stmt = $pdo->prepare("SELECT f.file_name FROM files f JOIN file_chunks fc ON f.file_id = fc.file_id WHERE fc.account_id = ?");
    $stmt->execute([$account_id]);
    $existingFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt2 = $pdo->prepare("SELECT chunk_file_id FROM file_chunks WHERE account_id = ?");
    $stmt2->execute([$account_id]);
    $existingFileIds = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    $stmtInsert = $pdo->prepare("INSERT INTO files (account_id, user_id, file_name, file_size, uploaded_at, type) VALUES (?, ?, ?, ?, ?, ?)");
    $newFiles = 0;
    
    $insert = $pdo->prepare("INSERT INTO file_chunks (file_id, account_id, chunk_index, chunk_size, nr_of_chunks, chunk_file_id, chunk_path) VALUES (?, ?, ?, ?, ?, ?, ?)");


    foreach ($all_files as $file) {
        if (!in_array($file['id'], $existingFileIds) && $file['id'] != 'pending') {
            if (!in_array($file['name'], $existingFiles)) {
                $stmtInsert->execute([
                    $account_id,
                    $user_id,
                    $file['name'],
                    $file['size'],
                    $file['created_at'],
                    $file['extension']
                ]);
                $fileID = $pdo->lastInsertId();
                $insert->execute([
                    $fileID,
                    $account_id,
                    1,
                    $file['size'],
                    1,
                    $file['id'],
                    $file['path']
                ]);
                $newFiles++;
            }
        }
    }

    $stmtDeleteChunks = $pdo->prepare("DELETE FROM file_chunks WHERE account_id = ? AND chunk_file_id = ?");
    $deletedFiles = 0;

    $cloudFileIds = [];
    foreach ($all_files as $file) {
        $cloudFileIds[] = $file['id'];
    }

    foreach ($existingFileIds as $fileId) {
        if (!in_array($fileId, $cloudFileIds)) {
           
            $stmtDeleteChunks->execute([$account_id, $fileId]);
            $deletedFiles++;
        }
    }
    
    $stmtDelete=$pdo->prepare("DELETE FROM files f WHERE NOT EXISTS (SELECT 1 FROM file_chunks fc WHERE fc.file_id=f.file_id) AND f.account_id = ?");
    $stmtDelete->execute([$account_id]);

    $filesAdded="";
    if($newFiles > 0) {
        $filesAdded = " Added $newFiles new files. ";
    } 
    
    $filesDeleted = "";
    if($deletedFiles > 0) {
        $filesDeleted = " Deleted $deletedFiles files.";
    }



    echo json_encode([
        'success' => true,
        'message' => "Sync completed.$filesAdded$filesDeleted",
    ]);

} catch (Exception $e) {
    error_log("Error syncing files: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error during sync',
        'message' => $e->getMessage()
    ]);
}
