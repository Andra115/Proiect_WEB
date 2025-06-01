<?php
session_start();

$pageTitle = 'Welcome - Cloud9 Storage Manager';
$content = 'welcome_content.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Cloud Storage Manager'; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/jwt-decode/build/jwt-decode.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lemon&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="app-layout">
    <script>
        const token = localStorage.getItem('jwt') || sessionStorage.getItem('jwt');
        if (!token) {
            window.location.href = 'login.php';
        }

        try {
            const decoded = jwt_decode(token);
            if (decoded.exp) {
                const now = Date.now() / 1000;
                if (now > decoded.exp) {
                    window.location.href = 'login.php';
                }
            }
        } catch (err) {
            console.error('Invalid token:', err);
            window.location.href = 'login.php';
        }

        fetch('get_username.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ jwt: token })
        })
        .then(res => res.json())
        .then(data => {
            if (data.username) {
                window.USERNAME = data.username;
                const usernameDisplay = document.getElementById('username-display');
                if (usernameDisplay) {
                    usernameDisplay.textContent = data.username;
                }
                document.getElementById('app-content').style.display = 'block';
            } else {
                throw new Error('No username returned');
            }
        })
        .catch(() => window.location.href = 'login.php');
    </script>

    <div id="app-content" style="display: none">
        <?php include 'templates/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'templates/header.php'; ?>
            
            <main class="content-area">
                <?php 
                if (isset($content)) {
                    include $content;
                } else {
                    echo '<div class="default-content">Welcome to Cloud9 Storage Manager</div>';
                }
                ?>
            </main>
            
            <?php include 'templates/footer.php'; ?>
        </div>
    </div>

    <script>
        const updateUsername = () => {
            const usernameElement = document.querySelector('.username');
            if (usernameElement && window.USERNAME) {
                usernameElement.textContent = window.USERNAME;
            }
        };
        
        document.addEventListener('DOMContentLoaded', updateUsername);
    </script>
</body>
</html>