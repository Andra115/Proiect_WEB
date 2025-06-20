<?php
require_once 'db.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$userId = $_SESSION['user_id'];
if(!isset($userId)) {
   try {
    $key = "Aceasta este o cheie supersecreta";
    $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
    $userId = $decoded->user_id;
}
catch (Exception $e) {
    header("Location: /../../login.php");
    exit;
   }
}
$fileType = isset($_SESSION['selected_file_type']) ? $_SESSION['selected_file_type'] : '';
$searchedFileName = isset($_SESSION['searched_file_name']) ? $_SESSION['searched_file_name'] : '';

$stmt = $pdo->prepare('SELECT * FROM get_user_files(:user_id, :type, :searched_file_name)');
$stmt->execute(['user_id' => $userId, 'type' => $fileType, 'searched_file_name' => $searchedFileName]);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalFiles = count($files);
$totalBytes = array_sum(array_column($files, 'file_size'));
$totalGB = number_format($totalBytes / (1024 * 1024 * 1024), 3);

$storageStmt = $pdo->prepare("SELECT * FROM get_user_storage(:user_id)");
$storageStmt->execute(['user_id' => $userId]);
$storage = $storageStmt->fetch(PDO::FETCH_ASSOC);
$total_storage = $storage['total_storage'];
$space_available = $storage['total_available'];
$used_storage = $total_storage - $space_available;
$used_storage_gb = $used_storage / (1024 ** 3);
$total_storage_gb = $total_storage / (1024 ** 3);

?>
<div class="welcome-content">
    <h2>Cloud9 Storage Dashboard</h2>
    <div class="stats-row">
        <div class="storage-card">
            <h3>Quick Stats</h3>
            <ul>
                <li>Total Files: <?php echo $totalFiles; ?></li>
                <li>Files Occupy: <?php echo $totalGB; ?> GB</li>
            </ul>
        </div>
        <div class="upload-card">
            <a href="upload.php" class="upload_btn">Upload new file</a>
        </div>
    </div>
    <div class="files-container">
        <div class="files-header">
            <div class="file-info">
                <span class="column-name">File Name</span>
                <span class="column-date">Created At</span>
            </div>
        </div>
        <div class="files-list">
            <?php if (empty($files)): ?>
                <div class="no-files">No files found</div>
            <?php else: ?>
                <?php foreach ($files as $file): ?>
                    <div class="file-item">
                            <div class="file-name-section">
                                <div class="file-menu-wrapper">
                                    <button class="file-menu-btn" onclick="toggleMenu(this)">
                                        &#x22EE;
                                    </button>
                                    <div class="file-menu">
                                        <button class="file-menu-option" onclick="renameFile('<?php echo $file['file_id']; ?>', '<?php echo $userId; ?>')">Rename</button>
                                        <button class="file-menu-option" onclick="deleteFile('<?php echo $file['file_id']; ?>', '<?php echo $userId; ?>')">Delete</button>
                                        <button class="file-menu-option" onclick="downloadFile('<?php echo $file['file_id']; ?>', '<?php echo $userId; ?>')">Download</button>
                                    </div>
                                </div>
                                <?php
                                $type = rtrim($file['file_type']);
                                switch ($type) {
                                    case 'image':
                                        $icon = '../assets/image.png';
                                        break;
                                    case 'pdf':
                                        $icon = '../assets/pdf.png';
                                        break;
                                    case 'document':
                                        $icon = '../assets/document.png';
                                        break;
                                    case 'spreadsheet':
                                        $icon = '../assets/spreadsheet.png';
                                        break;
                                    case 'presentation':
                                        $icon = '../assets/presentation.png';
                                        break;
                                    case 'audio':
                                        $icon = '../assets/audio.png';
                                        break;
                                    case 'video':
                                        $icon = '../assets/video.png';
                                        break;
                                    case 'archive':
                                        $icon = '../assets/archive.png';
                                        break;
                                    default:
                                        $icon = '../assets/file.png';
                                }
                                ?>
                                <img src="<?php echo $icon; ?>" alt="File" class="file-icon">
                                <span class="file-name"><?php echo htmlspecialchars($file['file_name']); ?></span>
                            </div>
                            <span class="file-date"><?php echo date('M j, Y, g:i A', strtotime($file['uploaded_at'])); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleMenu(btn) {
    document.querySelectorAll('.file-menu').forEach(menu => {
        if (menu !== btn.nextElementSibling) menu.style.display = 'none';
    });
    const menu = btn.nextElementSibling;
    menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.file-menu-wrapper')) {
        document.querySelectorAll('.file-menu').forEach(menu => menu.style.display = 'none');
    }
});

function renameFile(fileId,userId) {
    const newName = prompt('Enter new file name:');
    if (!newName) {
        alert('File name cannot be empty.');
        return;
    }
    if(!userId) {
        alert('User ID is required.');
        return;
    }
    fetch('rename.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ file_id: fileId, new_name: newName, user_id: userId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('File renamed successfully!');
           
        } else {
            alert('Error renaming file: ' + data.error);
        }
    })
    .catch(error => {
        alert('AJAX error: ' + error);
    });
}


function deleteFile(fileId,userId) {
   
    if(!userId) {
        alert('User ID is required.');
        return;
    }
    fetch('delete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ file_id: fileId, user_id: userId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('File deleted successfully!');
           
        } else {
            alert('Error deleting file: ' + data.message + ' ' + data.error + ' ' + data.http_code + ' ' + data.chunk_file_id + ' ' + data.provider + ' ' + data.account_info + ' ' + data.entered);
        }
    })
    .catch(error => {
        alert('AJAX error: ' + error);
    });
}


function downloadFile(fileId,userId) {
    if(!userId) {
        alert('User ID is required.');
        return;
    }
    fetch('download.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ file_id: fileId, user_id: userId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('File downloaded successfully!');
           
        } else {
            alert('Error downloading file: ' + data.error);
        }
    })
    .catch(error => {
        alert('AJAX error: ' + error);
    });
}
</script> 