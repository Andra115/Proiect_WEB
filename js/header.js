
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
                        <div class="file-menu-wrapper">
                                    <button class="file-menu-btn" onclick="toggleMenu(this)">&#x22EE;</button>
                                    <div class="file-menu">
                                        <button class="file-menu-option" onclick="renameFile('${file.file_id}', '${file.user_id}')">Rename</button>
                                        <button class="file-menu-option" onclick="deleteFile('${file.file_id}', '${file.user_id}')">Delete</button>
                                        <button class="file-menu-option" onclick="downloadFile('${file.file_id}', '${file.user_id}')">Download</button>
                                    </div>
                                </div>
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
                        <div class="file-menu-wrapper">
                                    <button class="file-menu-btn" onclick="toggleMenu(this)">&#x22EE;</button>
                                    <div class="file-menu">
                                        <button class="file-menu-option" onclick="renameFile('${file.file_id}', '${file.user_id}')">Rename</button>
                                        <button class="file-menu-option" onclick="deleteFile('${file.file_id}', '${file.user_id}')">Delete</button>
                                        <button class="file-menu-option" onclick="downloadFile('${file.file_id}', '${file.user_id}')">Download</button>
                                    </div>
                                </div>
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