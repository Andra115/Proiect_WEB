<?php


require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /../../login.php");
    exit;
}
$user_id = $_SESSION['user_id'];

function getConnectedDrives($userId)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT email, provider FROM cloud_accounts WHERE user_id = ? ORDER BY provider DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching drives: " . $e->getMessage());
        return [];
    }
}
$connectedDrives = getConnectedDrives($user_id);

?>


<button id="sidebar-toggle" class="sidebar-toggle">
    <span class="toggle-icon"></span>
</button>

<aside class="sidebar" id="sidebar">
    <div class="logo-container">
        <img src="../../assets/logo_white_on_blue_background.jpeg" alt="Logo" class="logo">
    </div>

    <div class="view-options">
        <button class="view-btn active" onclick="updateFileType('')">Recent Files</button>
        <div class="file-types-grid">
            <div class="file-types-row">
                <button class="file-type-btn" onclick="updateFileType('document')"><img src="../../assets/document.png" alt="Document" class="file-type-icon"></button>
                <button class="file-type-btn" onclick="updateFileType('pdf')"><img src="../../assets/pdf.png" alt="PDF" class="file-type-icon"></button>
                <button class="file-type-btn" onclick="updateFileType('spreadsheet')"><img src="../../assets/spreadsheet.png" alt="Spreadsheet" class="file-type-icon"></button>
                <button class="file-type-btn" onclick="updateFileType('presentation')"><img src="../../assets/presentation.png" alt="Presentation" class="file-type-icon"></button>
            </div>
            <div class="file-types-row">
                <button class="file-type-btn" onclick="updateFileType('image')"><img src="../../assets/image.png" alt="Image" class="file-type-icon"></button>
                <button class="file-type-btn" onclick="updateFileType('video')"><img src="../../assets/video.png" alt="Video" class="file-type-icon"></button>
                <button class="file-type-btn" onclick="updateFileType('audio')"><img src="../../assets/audio.png" alt="Audio" class="file-type-icon"></button>
                <button class="file-type-btn" onclick="updateFileType('archive')"><img src="../../assets/archive.png" alt="Archive" class="file-type-icon"></button>
            </div>
        </div>
    </div>

    <div class="divider"></div>

    <div class="cloud-services">
        <h3>Connect Services</h3>
        <div class="service-buttons">
            <a href="#" class="service-btn google-drive" onclick="connectCloudService('drive'); return false;" role="button">
                <img src="../../assets/google_drive_icon.png" alt="Google Drive" class="service-icon">
            </a>
            <a href="#" class="service-btn dropbox" onclick="connectCloudService('dropbox'); return false;" role="button">
                <img src="../../assets/dropbox_icon.png" alt="Dropbox" class="service-icon">
            </a>
            <a href="#" class="service-btn box" onclick="connectCloudService('box'); return false;" role="button">
                <img src="../../assets/box_icon.png" alt="Box" class="service-icon">
            </a>
        </div>
    </div>

    <div class="divider"></div>

    <div class="connections-menu">
        <h3>Connected Drives</h3>
        <div class="drives-container">
            <?php if (empty($connectedDrives)): ?>
                <div class="no-drives">
                    No connected drives yet.<br>
                    Connect a service above to get started.
                </div>
            <?php else: ?>
                <ul class="drives-list">
                    <?php foreach ($connectedDrives as $drive): ?>
                        <li class="drive-item">
                            <div class="drive-header">
                                <span class="connection-status connected"></span>
                                <span class="drive-name"><?php echo htmlspecialchars($drive['provider']); ?></span>
                            </div>
                            <div class="drive-email"><?php echo htmlspecialchars($drive['email']); ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</aside>

<script>
    document.getElementById('sidebar-toggle').addEventListener('click', function() {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('sidebar-toggle');
        sidebar.classList.toggle('active');
        toggle.classList.toggle('active');
    });

    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('sidebar-toggle');

        if (window.innerWidth <= 768 &&
            !event.target.closest('.sidebar') &&
            !event.target.closest('.sidebar-toggle') &&
            sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            toggle.classList.remove('active');
        }
    });

    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('sidebar-toggle');

        if (window.innerWidth > 768) {
            sidebar.classList.remove('active');
            toggle.classList.remove('active');
        }
    });

    document.querySelectorAll('.view-btn').forEach(button => {
        button.addEventListener('click', function() {
            document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
        });
    });



function updateFileType(type) {
    document.querySelectorAll('.view-btn, .file-type-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    if (type === '') {
        document.querySelector('.view-btn').classList.add('active');
    } else {
        event.currentTarget.classList.add('active');
    }
    function updateFileType(type) {

        document.querySelectorAll('.view-btn, .file-type-btn').forEach(btn => {
            btn.classList.remove('active');
        });


        if (type === '') {
            document.querySelector('.view-btn').classList.add('active');
        } else {
            event.currentTarget.classList.add('active');
        }


        fetch('update_file_type.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    type: type
                })
            })
            .then(() => {

                window.location.reload();
            });
    }
</script>
<script src="../../js/connectClouds.js"></script> 