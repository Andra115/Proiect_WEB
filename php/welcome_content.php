<?php
require_once 'db.php';
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT * FROM get_user_files(:user_id)');
$stmt->execute(['user_id' => $userId]);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalFiles = count($files);
$totalBytes = array_sum(array_column($files, 'file_size'));
$totalMB = number_format($totalBytes / (1024 * 1024), 2);
?>
<div class="welcome-content">
    <h2>Cloud9 Storage Dashboard</h2>
    
    <div class="storage-card">
        <h3>Quick Stats</h3>
        <ul>
            <li>Total Files: <?php echo $totalFiles; ?></li>
            <li>Storage Used: <?php echo $totalMB; ?> MB</li>
        </ul>
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
                <div class="no-files">No files uploaded yet</div>
            <?php else: ?>
                <?php foreach ($files as $file): ?>
                    <div class="file-item">
                        <div class="file-info">
                            <div class="file-name-section">
                                <img src="../assets/file.png" alt="File" class="file-icon">
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