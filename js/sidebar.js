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

    fetch('update_file_type.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ type: type })
    })
    .then(response => response.json())
    .then(result => {
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
                    <div class="file-name-section">
                        <div class="file-menu-wrapper">
                            <button class="file-menu-btn" onclick="toggleMenu(this)">&#x22EE;</button>
                            <div class="file-menu">
                                <button class="file-menu-option" onclick="renameFile('${file.file_id}')">Rename</button>
                                <button class="file-menu-option" onclick="deleteFile('${file.file_id}')">Delete</button>
                                <button class="file-menu-option" onclick="downloadFile('${file.file_id}')">Download</button>
                            </div>
                        </div>
                        <img src="${icon}" alt="File" class="file-icon">
                        <span class="file-name">${file.file_name.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</span>
                    </div>
                    <span class="file-date">${new Date(file.uploaded_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true })}</span>
                `;
                filesList.appendChild(fileItem);
            });
        } else {
            filesList.innerHTML = '<div class="no-files">No files found</div>';
        }
    });
}