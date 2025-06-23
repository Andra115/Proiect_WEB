 

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


function downloadFile(fileId, userId) {
  if (!userId) {
    alert('User ID is required.');
    return;
  }

  fetch('/php/prepareDownload.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams({ fileId, userId })
})
.then(async res => {
  const text = await res.text();
  try {
    return JSON.parse(text);
  } catch (e) {
    console.error("Invalid JSON response:", text);
    throw e;
  }
})
.then(data => {
  if (data.success) {
    const a = document.createElement('a');
    a.href = `/php/download.php?token=${encodeURIComponent(data.token)}`;
    a.download = '';
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  } else {
    alert("Download failed: " + data.error + " / " + data.message);
  }
})
.catch(err => {
  console.error("Download error:", err);
  alert("An unexpected error occurred.");
});
}


document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const type = this.getAttribute('data-type');
        fetch('php/filter_files.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'type=' + encodeURIComponent(type)
        })
        .then(response => response.text())
        .then(html => {
            document.querySelector('.files-list').innerHTML = html;
        });
    });
});

function getFileIcon(type) {
    const icons = {
        'image': '../assets/image.png',
        'pdf': '../assets/pdf.png',
        'document': '../assets/document.png',
        'spreadsheet': '../assets/spreadsheet.png',
        'presentation': '../assets/presentation.png',
        'audio': '../assets/audio.png',
        'video': '../assets/video.png',
        'archive': '../assets/archive.png'
    };
    return icons[type?.trim()] || '../assets/file.png';
}

function refreshFiles() {
    fetch('../php/get_files.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const container = document.querySelector('.files-list');
                container.innerHTML = '';

                if (data.files.length === 0) {
                    container.innerHTML = '<div class="no-files">No files found</div>';
                } else {
                    data.files.forEach(file => {
                        const item = document.createElement('div');
                        item.classList.add('file-item');

                        const icon = getFileIcon(file.file_type);
                        const formattedDate = new Date(file.uploaded_at).toLocaleString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric',
                            hour: 'numeric',
                            minute: 'numeric',
                            hour12: true
                        });

                        item.innerHTML = `
                        <div class="file-info">
                            <div class="file-name-section">
                                <div class="file-menu-wrapper">
                                    <button class="file-menu-btn" onclick="toggleMenu(this)">&#x22EE;</button>
                                    <div class="file-menu">
                                        <button class="file-menu-option" onclick="renameFile('${file.file_id}', '${file.user_id}')">Rename</button>
                                        <button class="file-menu-option" onclick="deleteFile('${file.file_id}', '${file.user_id}')">Delete</button>
                                        <button class="file-menu-option" onclick="downloadFile('${file.file_id}', '${file.user_id}')">Download</button>
                                    </div>
                                </div>
                                <img src="${icon}" alt="File" class="file-icon">
                                <span class="file-name">${file.file_name}</span>
                            </div>
                            <span class="file-date">${formattedDate}</span>
                        </div>
                        `;
                        container.appendChild(item);
                    });
                }
            } else {
                console.error("Error fetching files:", data.error);
            }
        })
        .catch(err => console.error("Fetch error:", err));
}

function refreshStats() {
    fetch('../php/get_stats.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('totalFiles').textContent = data.totalFiles;
                document.getElementById('totalGB').textContent = data.totalGB+'GB';
            } else {
                console.error("Stats fetch failed:", data.error);
            }
        })
        .catch(err => {
            console.error("Stats fetch error:", err);
        });
}


refreshFiles();
refreshStats();

setInterval(() => {
    refreshFiles();
    refreshStats();
}, 10000);

