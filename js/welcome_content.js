

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
