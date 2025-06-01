<?php
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
    
    <div class="view-optionAds">
        <button class="view-btn active">Recent Files</button>
        <button class="view-btn">File Types</button>
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
</script> 