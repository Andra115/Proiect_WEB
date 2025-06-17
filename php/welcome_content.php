<?php
require_once 'db.php';
$userId = $_SESSION['user_id'];
$fileType = isset($_SESSION['selected_file_type']) ? $_SESSION['selected_file_type'] : '';
$searchedFileName = isset($_SESSION['searched_file_name']) ? $_SESSION['searched_file_name'] : '';

$stmt = $pdo->prepare('SELECT * FROM get_user_files(:user_id, :type, :searched_file_name)');
$stmt->execute(['user_id' => $userId, 'type' => $fileType, 'searched_file_name' => $searchedFileName]);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalFiles = count($files);
$totalBytes = array_sum(array_column($files, 'file_size'));
$totalMB = number_format($totalBytes / (1024 * 1024), 2);
?>
<div class="welcome-content">
    <h2>Cloud9 Storage Dashboard</h2>
    
    <div class="stats-container">
        <div class="storage-card">
            <h3>Quick Stats</h3>
            <ul>
                <li>Total Files: <?php echo $totalFiles; ?></li>
                <li>Storage Used: <?php echo $totalMB; ?> MB</li>
            </ul>
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
                        <div class="file-info">
                            <div class="file-name-section">
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
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div> 