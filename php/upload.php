<?php
ini_set('memory_limit', '512M');
ini_set('upload_max_filesize', '50G');
ini_set('post_max_size', '50G');
ini_set('max_execution_time', 3600); 
ini_set('max_input_time', 3600);
session_start();
require_once 'db.php';
require_once 'cloud_upload.php';

$pageTitle = 'Upload File - Cloud9 Storage Manager';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $tmpPath = $_FILES['file']['tmp_name'];
        $originalName = $_FILES['file']['name'];
        $fileSize = $_FILES['file']['size'];
        $userId = $_SESSION['user_id'];
        $fileId = null;

        try {
            $pdo->beginTransaction();

            $baseName = pathinfo($originalName, PATHINFO_FILENAME);
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $uniqueName = $originalName;
            $counter = 1;
            while (true) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM files WHERE user_id = ? AND file_name = ?");
                $stmt->execute([$userId, $uniqueName]);
                if ($stmt->fetchColumn() == 0) break;
                $uniqueName = $baseName . "_" . $counter;
                if ($extension) $uniqueName .= "." . $extension;
                $counter++;
            }

            $stmt = $pdo->prepare("INSERT INTO files (user_id, file_name, file_size, uploaded_at) VALUES (?, ?, ?, NOW()) RETURNING file_id");
            $stmt->execute([$userId, $uniqueName, $fileSize]);
            $fileId = $stmt->fetchColumn();
            if (!$fileId) throw new Exception("Failed to create file record");

            $sql = "SELECT public.distribute_file_chunks(:file_id, :user_id, :file_size, :account_id)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':file_id' => $fileId,
                ':user_id' => $userId,
                ':file_size' => $fileSize,
                ':account_id' => null
            ]);

            $stmt = $pdo->prepare("SELECT chunk_index, chunk_size FROM file_chunks WHERE file_id = ? ORDER BY chunk_index ASC");
            $stmt->execute([$fileId]);
            $chunks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($chunks)) throw new Exception("No chunks were created for the file");

            if (count($chunks) === 1) {
                $stmt = $pdo->prepare("SELECT account_id FROM file_chunks WHERE file_id = ? LIMIT 1");
                $stmt->execute([$fileId]);
                $accountId = $stmt->fetchColumn();
                if ($accountId) {
                    $stmt = $pdo->prepare("UPDATE files SET account_id = ? WHERE file_id = ?");
                    $stmt->execute([$accountId, $fileId]);
                }
            }

            $stmt = $pdo->prepare("SELECT fc.chunk_id, fc.chunk_index, ac.provider, ac.access_token, ac.refresh_token, ac.token_expiry, ac.account_id 
                FROM file_chunks fc 
                JOIN cloud_accounts ac ON fc.account_id = ac.account_id 
                WHERE fc.file_id = ? ORDER BY fc.chunk_index ASC");
            $stmt->execute([$fileId]);
            $cloudChunks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($cloudChunks) !== count($chunks)) throw new Exception("Mismatch in chunk and account data");

            $chunkDir = __DIR__ . '/../uploads/chunks';
            if (!is_dir($chunkDir) && !mkdir($chunkDir, 0755, true)) {
                throw new Exception("Failed to create chunks directory");
            }

            $handle = fopen($tmpPath, 'rb');
            if (!$handle) throw new Exception("Could not open uploaded file for reading");

            foreach ($chunks as $i => $chunk) {
                $chunkNum = $chunk['chunk_index'];
                $chunkSize = $chunk['chunk_size'];
                $cloudChunk = $cloudChunks[$i];

                $chunkPath = $chunkDir . "/{$fileId}_chunk{$chunkNum}";
                $chunkHandle = fopen($chunkPath, 'wb');
                if (!$chunkHandle) throw new Exception("Could not create chunk file: {$chunkPath}");

                $remaining = $chunkSize;
                while ($remaining > 0) {
                    $buffer = fread($handle, min(8192, $remaining));
                    if ($buffer === false || strlen($buffer) === 0) {
                        throw new Exception("Error reading from source file");
                    }
                    if (fwrite($chunkHandle, $buffer) === false) {
                        throw new Exception("Error writing to chunk file: {$chunkPath}");
                    }
                    $remaining -= strlen($buffer);
                }
                fclose($chunkHandle);

                $provider = $cloudChunk['provider'];
                $accessToken = refreshTokenIfNeeded($provider,$cloudChunk['access_token'],$cloudChunk['refresh_token'],$cloudChunk['token_expiry'],$cloudChunk['account_id'],$credsBox, $credsDropbox, $credsGoogle,$pdo);
                if (!$accessToken) {
                    throw new Exception("Token refresh failed for chunk {$chunkNum}");
                }
                 else{
        
                if ($provider == 'dropbox') {
                    $expiresIn = 14400;
                } else {
                    $expiresIn = 3600;
                }
            
            $token_expiry = time() + $expiresIn;
            $token_expiry_formatted = date('Y-m-d H:i:s', $token_expiry);
        try{
            $stmtUpdate = $pdo->prepare("UPDATE cloud_accounts SET access_token = ?, token_expiry = ? WHERE account_id = ?");
            $stmtUpdate->execute([$accessToken, $token_expiry_formatted, $cloudChunk['account_id']]);
        } catch (Exception $e) {
            
            unlink($chunkPath);
            echo json_encode(['success' => false, 'error' => 'Database error while updating access token for chunk ' . $chunk['chunk_index']]);
            exit;
    }
    }
                $cloudFileName = "chunk_{$fileId}_{$chunkNum}_" . uniqid() . ".bin";
                $uploadResult = uploadChunkToCloud($provider, $chunkPath, $accessToken, $cloudFileName);
                if (!$uploadResult) {
                    throw new Exception("Cloud upload failed for chunk {$chunkNum}");
                }

                $stmt = $pdo->prepare("UPDATE file_chunks SET chunk_file_id = ?, chunk_path = ? WHERE chunk_id = ?");
                $stmt->execute([
                    $uploadResult['file_id'],
                    $uploadResult['path'],
                    $cloudChunk['chunk_id']
                ]);

                unlink($chunkPath);
            }

            fclose($handle);
            $pdo->commit();

            $message = 'File "' . htmlspecialchars($originalName) . '" uploaded and chunked successfully!';

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();

            if ($fileId) {
                try {
                    $pdo->prepare("DELETE FROM file_chunks WHERE file_id = ?")->execute([$fileId]);
                    $pdo->prepare("DELETE FROM files WHERE file_id = ?")->execute([$fileId]);
                } catch (PDOException $cleanupError) {
                    error_log("Cleanup failed: " . $cleanupError->getMessage());
                }
            }

            $message = 'Upload failed: ' . htmlspecialchars($e->getMessage());
            error_log("Upload error: " . $e->getMessage());
        }

    } else {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds the maximum allowed size (php.ini)',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds the maximum allowed size (form)',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        $errorCode = $_FILES['file']['error'] ?? null;
        $message = $uploadErrors[$errorCode] ?? 'Unknown upload error.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Cloud Storage Manager'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lemon&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="upload-page-container">
        <div class="upload-box">
            <h2>Upload a File</h2>
            <?php if ($message): ?>
                <div class="upload-message"><?php echo $message; ?></div>
            <?php endif; ?>
            <form action="upload.php" method="post" enctype="multipart/form-data" class="upload-form">
                <input type="file" name="file" required>
                <button type="submit" class="upload_btn">Upload</button>
            </form>
            <a href="welcome.php" class="back-btn">&larr; Back to Dashboard</a>
        </div>
    </div>
    <div class="footer-sidebar-filler"></div>
    <?php include 'templates/footer.php'; ?>
</body>
</html>
