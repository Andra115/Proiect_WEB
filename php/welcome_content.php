<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}
$user_id = $_SESSION['user_id'];

?>
<div class="welcome-content">
    <h2>Cloud9 Storage Dashboard</h2>
    <div class="stats-row">
        <div class="storage-card">
            <h3>Quick Stats</h3>
            <ul id="statsList">
                <li>Total Files: <span id="totalFiles" class="loading-message">Loading...</span></li>
                <li>Files Occupy: <span id="totalGB" class="loading-message">Loading...</span> GB</li>
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
        <div class="files-list" id="filesList">
            <div class="loading-message">Loading files...</div>
        </div>
    </div>
</div>

<script src="../../js/welcome_content.js"></script>