<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Welcome</title>
  <script src="https://cdn.jsdelivr.net/npm/jwt-decode/build/jwt-decode.min.js"></script>
</head>
<body>
  <h1>Welcome, <span id="username">loading...</span>!</h1>
  <a href="logout.php">Logout</a>

  <script>
    const token = localStorage.getItem('jwt');
    if (!token) {
      window.location.href = 'login.php';
    }

    let decoded;
    try {
      decoded = jwt_decode(token);
    } catch (err) {
      console.error('Invalid token:', err);
      window.location.href = 'login.php';
    }

    fetch('get_username.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ user_id: decoded.user_id })
    })
    .then(res => res.json())
    .then(data => {
      if (data.username) {
        document.getElementById('username').textContent = data.username;
      } else {
        throw new Error('No username returned');
      }
    })
    .catch(() => window.location.href = 'login.php');
  </script>
</body>
</html>