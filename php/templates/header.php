<?php
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
?>
<header class="main-header">
    <div class="search-container">
        <form action="update_search.php" method="POST" class="search-form" id="search-form">
            <div class="search-input-wrapper">
                <input type="text" name="search" placeholder="Search files..." class="search-bar" id="search-bar" value="<?php echo isset($_SESSION['searched_file_name']) ? htmlspecialchars($_SESSION['searched_file_name']) : ''; ?>" autocomplete="off">
                <button type="button" class="clear-search">&times;</button>
            </div>
            <button type="submit" class="search-button">
                <img src="../assets/search.png" alt="Search" class="search-icon">
            </button>
        </form>
    </div>
    <div class="profile-section">
        <a href="profile.php" class="user-info">
            <span class="username" id="username-display">loading...</span>
            <img src="../../assets/profile.png" alt="Profile" class="profile-icon">
        </a>
    </div>
</header> 
<script>
const searchBar = document.getElementById('search-bar');
const clearBtn = document.querySelector('.clear-search');

function updateClearBtnVisibility() {
    clearBtn.style.display = searchBar.value ? 'block' : 'none';
}

searchBar.addEventListener('input', updateClearBtnVisibility);
document.addEventListener('DOMContentLoaded', updateClearBtnVisibility);

clearBtn.addEventListener('click', async function() {
    searchBar.value = '';
    updateClearBtnVisibility();
    const formData = new FormData();
    formData.append('search', '');
    const response = await fetch('update_search.php', {
        method: 'POST',
        body: formData
    });
    const result = await response.json();
    const filesList = document.querySelector('.files-list');
    if (!filesList) return;
    filesList.innerHTML = '';
    if (result.files && result.files.length > 0) {
        result.files.forEach(file => {
            let icon;
            switch (file.file_type.trim()) {
                case 'image': icon = '../assets/image.png'; break;
                case 'pdf': icon = '../assets/pdf.png'; break;
                case 'document': icon = '../assets/document.png'; break;
                case 'spreadsheet': icon = '../assets/spreadsheet.png'; break;
                case 'presentation': icon = '../assets/presentation.png'; break;
                case 'audio': icon = '../assets/audio.png'; break;
                case 'video': icon = '../assets/video.png'; break;
                case 'archive': icon = '../assets/archive.png'; break;
                default: icon = '../assets/file.png';
            }
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item';
            fileItem.innerHTML = `
                <div class=\"file-info\">
                    <div class=\"file-name-section\">
                        <img src=\"${icon}\" alt=\"File\" class=\"file-icon\">
                        <span class=\"file-name\">${file.file_name.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</span>
                    </div>
                    <span class=\"file-date\">${new Date(file.uploaded_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true })}</span>
                </div>
            `;
            filesList.appendChild(fileItem);
        });
    } else {
        filesList.innerHTML = '<div class=\"no-files\">No files found</div>';
    }
});

document.getElementById('search-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const response = await fetch('update_search.php', {
        method: 'POST',
        body: formData
    });
    const result = await response.json();
    const filesList = document.querySelector('.files-list');
    if (!filesList) return;
    filesList.innerHTML = '';
    if (result.files && result.files.length > 0) {
        result.files.forEach(file => {
            let icon;
            switch (file.file_type.trim()) {
                case 'image': icon = '../assets/image.png'; break;
                case 'pdf': icon = '../assets/pdf.png'; break;
                case 'document': icon = '../assets/document.png'; break;
                case 'spreadsheet': icon = '../assets/spreadsheet.png'; break;
                case 'presentation': icon = '../assets/presentation.png'; break;
                case 'audio': icon = '../assets/audio.png'; break;
                case 'video': icon = '../assets/video.png'; break;
                case 'archive': icon = '../assets/archive.png'; break;
                default: icon = '../assets/file.png';
            }
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item';
            fileItem.innerHTML = `
                <div class=\"file-info\">
                    <div class=\"file-name-section\">
                        <img src=\"${icon}\" alt=\"File\" class=\"file-icon\">
                        <span class=\"file-name\">${file.file_name.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</span>
                    </div>
                    <span class=\"file-date\">${new Date(file.uploaded_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true })}</span>
                </div>
            `;
            filesList.appendChild(fileItem);
        });
    } else {
        filesList.innerHTML = '<div class=\"no-files\">No files found</div>';
    }
});
</script> 