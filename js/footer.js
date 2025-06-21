function updateStorageBar() {
    fetch('../php/get_storage.php')
        .then(res => res.json())
        .then(data => {
            if (data.percentageUsed !== undefined) {
                document.getElementById('storage-bar').style.width = data.percentageUsed + '%';
                document.getElementById('storage-text').textContent =
                    data.used_storage_gb + 'GB of ' + data.total_storage_gb + 'GB used';
            }
        }).catch(() => {
    document.getElementById('storage-text').textContent = 'Error loading storage info';;
 });
}


updateStorageBar();
setInterval(updateStorageBar, 10000);