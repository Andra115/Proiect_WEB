<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Cloud Storage Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lemon&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/jwt-decode/build/jwt-decode.min.js"></script>
</head>
<body>
    <script>
        function getToken() {
            const token = localStorage.getItem('jwt') || sessionStorage.getItem('jwt');
            if (!token) {
                window.location.href = 'login.php';
                return null;
            }
            return token;
        }

        if (!getToken()) {
            throw new Error('No token found');
        }
    </script>

    <div class="profile-page">
        <div class="profile-container">
            <div class="profile-header">
                <img src="../assets/profile.png" alt="Profile" class="profile-picture">
                <h1>Profile Settings</h1>
            </div>
            <div class="profile-info">
                <div class="info-item">
                    <label>Email</label>
                    <span id="profile-email">Loading...</span>
                </div>
                <div class="info-item">
                    <label>Username</label>
                    <div class="edit-field">
                        <span id="profile-username">Loading...</span>
                        <button onclick="toggleUsernameEdit()" class="edit-btn">Edit</button>
                    </div>
                    <div class="edit-form" id="username-form" style="display: none;">
                        <input type="text" id="new-username" class="edit-input">
                        <button onclick="updateUsername()" class="save-btn">Save</button>
                        <button onclick="toggleUsernameEdit()" class="cancel-btn">Cancel</button>
                    </div>
                </div>
                <div class="info-item">
                    <label>Password</label>
                    <div class="edit-field">
                        <span>••••••••</span>
                        <button onclick="togglePasswordEdit()" class="edit-btn">Change</button>
                    </div>
                    <div class="edit-form" id="password-form" style="display: none;">
                        <input type="password" id="current-password" placeholder="Current Password" class="edit-input">
                        <input type="password" id="new-password" placeholder="New Password" class="edit-input">
                        <button onclick="updatePassword()" class="save-btn">Save</button>
                        <button onclick="togglePasswordEdit()" class="cancel-btn">Cancel</button>
                    </div>
                </div>
                <div id="update-message" class="message" style="display: none;"></div>
            </div>
            <div class="documentation-link-container">
                <a href="../documentatie.html" class="documentation-link">The app's documentation</a>
            </div>
            <div class="profile-actions">
                <button onclick="window.location.href='welcome.php'" class="back-btn">Back to Dashboard</button>
                <button onclick="handleLogout()" class="logout-btn">Logout</button>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', async () => {
            const token = getToken();
            if (!token) return;

            try {
                const response = await fetch('get_username.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ jwt: token })
                });
                const data = await response.json();
                if (data.username && data.email) {
                    document.getElementById('profile-username').textContent = data.username;
                    document.getElementById('profile-email').textContent = data.email;
                    document.getElementById('new-username').value = data.username;
                } else {
                    throw new Error('User data not found');
                }
            } catch (error) {
                console.error('Error fetching user data:', error);
                window.location.href = 'login.php';
            }
        });

        function showMessage(message, isError = false) {
            const messageEl = document.getElementById('update-message');
            messageEl.textContent = message;
            messageEl.style.color = isError ? '#dc3545' : '#28a745';
            messageEl.style.display = 'block';
            setTimeout(() => {
                messageEl.style.display = 'none';
            }, 3000);
        }

        function toggleUsernameEdit() {
            const displayEl = document.getElementById('profile-username').parentElement;
            const formEl = document.getElementById('username-form');
            displayEl.style.display = displayEl.style.display === 'none' ? 'flex' : 'none';
            formEl.style.display = formEl.style.display === 'none' ? 'block' : 'none';
        }

        function togglePasswordEdit() {
            const passwordItem = Array.from(document.querySelectorAll('.info-item')).find(item => 
                item.querySelector('label').textContent === 'Password'
            );
            const displayEl = passwordItem.querySelector('.edit-field');
            const formEl = document.getElementById('password-form');
            displayEl.style.display = displayEl.style.display === 'none' ? 'flex' : 'none';
            formEl.style.display = formEl.style.display === 'none' ? 'block' : 'none';

            document.getElementById('current-password').value = '';
            document.getElementById('new-password').value = '';
        }

        async function updateUsername() {
            const token = getToken();
            if (!token) return;
            
            const newUsername = document.getElementById('new-username').value;

            try {
                const response = await fetch('update_profile.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        jwt: token,
                        username: newUsername
                    })
                });
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('profile-username').textContent = newUsername;
                    toggleUsernameEdit();
                    showMessage('Username updated successfully');
                } else {
                    throw new Error(data.error || 'Failed to update username');
                }
            } catch (error) {
                showMessage(error.message, true);
            }
        }

        async function updatePassword() {
            const token = getToken();
            if (!token) return;
            
            const currentPassword = document.getElementById('current-password').value;
            const newPassword = document.getElementById('new-password').value;

            try {
                const response = await fetch('update_profile.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        jwt: token,
                        currentPassword,
                        newPassword
                    })
                });
                const data = await response.json();
                
                if (data.success) {
                    togglePasswordEdit();
                    showMessage('Password updated successfully');
                } else {
                    throw new Error(data.error || 'Failed to update password');
                }
            } catch (error) {
                showMessage(error.message, true);
            }
        }

        function handleLogout() {
            localStorage.removeItem('jwt');
            sessionStorage.removeItem('jwt');
            window.location.href = 'login.php';
        }
    </script>
</body>
</html> 