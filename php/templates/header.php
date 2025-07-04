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
<script src="../../js/header.js"></script> 

