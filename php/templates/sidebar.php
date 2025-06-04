<?php
// Initialize selected_file_type if not set
if (!isset($_SESSION['selected_file_type'])) {
    $_SESSION['selected_file_type'] = '';
}

$drives = [
    ['name' => 'Google Drive 1', 'connected' => true],
    ['name' => 'Google Drive 2', 'connected' => false],
    ['name' => 'Dropbox 1', 'connected' => true],
    ['name' => 'Dropbox 2', 'connected' => true],
];
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
    
    <div class="connections-menu">
        <h3>Connected Drives</h3>
        <ul class="drives-list">
            <?php foreach ($drives as $drive): ?>
            <li class="drive-item">
                <span class="connection-status <?php echo $drive['connected'] ? 'connected' : 'disconnected'; ?>"></span>
                <?php echo htmlspecialchars($drive['name']); ?>
            </li>
            <?php endforeach; ?>
        </ul>
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

function updateFileType(type) {
    // Remove active class from all buttons
    document.querySelectorAll('.view-btn, .file-type-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    // Add active class to clicked button
    if (type === '') {
        document.querySelector('.view-btn').classList.add('active');
    } else {
        event.currentTarget.classList.add('active');
    }

    // Send AJAX request to update session
    fetch('update_file_type.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ type: type })
    })
    .then(() => {
        // Reload the page to show filtered results
        window.location.reload();
    });
}
</script> 