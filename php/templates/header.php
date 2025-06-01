<?php
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
?>
<header class="main-header">
    <div class="search-container">
        <input type="text" placeholder="Search files..." class="search-bar">
    </div>
    <div class="profile-section">
        <a href="profile.php" class="user-info">
            <span class="username" id="username-display">loading...</span>
            <img src="../../assets/profile.png" alt="Profile" class="profile-icon">
        </a>
    </div>
</header> 