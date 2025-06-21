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

function updateFileType(type, btn) {
    document.querySelectorAll('.view-btn, .file-type-btn').forEach(b => b.classList.remove('active'));
    if (type === '') {
        document.querySelector('.view-btn').classList.add('active');
    } else if (btn) {
        btn.classList.add('active');
    }
    fetch('update_file_type.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: type })
    }).then(() => {
        window.location.reload();
    });
}

function connectCloudService(provider) {

    fetch(`/../php/cloud/${provider}.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.auth_url) {
            window.location.href = data.auth_url;
        } else {
            alert('Error: Authentication URL not provided');
        }
    })
    .catch(error => {
        alert('Error connecting to cloud service: ' + error);
    });
}


