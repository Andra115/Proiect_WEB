<?php

ignore_user_abort(true);
set_time_limit(0);

require_once __DIR__ . '/../db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['account_id'], $input['access_token'], $input['email'])) {
    exit;
}

$account_id = $input['account_id'];
$access_token = $input['access_token'];
$email = $input['email'];
$provider = $input['provider'];

if ($provider !== 'box') {
    exit('Invalid provider'); //doesnt work for the other 2 quite yet
}
function listAllFiles($access_token, $folderId = '0',$provider) {
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
            $files = array_merge($files, listAllFiles($access_token, $item['id'],$provider));
        }
    }

    return $files;
}

try {
   
    $all_files = listAllFiles($access_token, '0', $provider);
    $total_used = array_sum(array_column($all_files, 'size'));

   
    $stmt = $pdo->prepare("UPDATE cloud_accounts SET total_space = ?, space_available = ? WHERE email = ? AND provider = 'box'");
    $stmt->execute([10737418240, 10737418240 - $total_used, $email]);

    $stmt = $pdo->prepare("SELECT file_name FROM files WHERE account_id = ?");
    $stmt->execute([$account_id]);
    $existingFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

 
    $stmtInsert = $pdo->prepare("INSERT INTO files (account_id, file_name, file_size, uploaded_at, type) VALUES (?, ?, ?, ?, ?)");

    foreach ($all_files as $file) {
        if (!in_array($file['name'], $existingFiles)) {
            $stmtInsert->execute([
                $account_id,
                $file['name'],
                $file['size'],
                date('Y-m-d H:i:s', strtotime($file['created_at'])),
                $file['extension']
            ]);
        }
    }

   
    $stmt = $pdo->prepare("SELECT file_id FROM files WHERE file_name LIKE ? AND account_id = ?");
    $insert = $pdo->prepare("INSERT INTO file_chunks (file_id, account_id, chunk_index, chunk_size, nr_of_chunks, chunk_file_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt2 = $pdo->prepare("SELECT chunk_file_id FROM file_chunks WHERE account_id = ?");
    $stmt2->execute([$account_id]);
    $existingFileIds = $stmt2->fetchAll(PDO::FETCH_COLUMN);

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

} catch (Exception $e) {
   
}
?>