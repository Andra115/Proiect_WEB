<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Welcome</title>
  <script src="https://cdn.jsdelivr.net/npm/jwt-decode/build/jwt-decode.min.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lemon&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/style.css" />
</head>
<body>
  <div class="connectionsMenu"> 
    <img src="../assets/logo_white_on_blue_background.jpeg" alt="Logo" class="logo"/>
  <h1 class="welcome">Welcome, <span id="username">loading...</span>!</h1>
  <a href="logout.php">Logout</a>
  
  </div>

  <script>
    const token = localStorage.getItem('jwt') || sessionStorage.getItem('jwt');
    if (!token) {
      window.location.href = 'login.php';
    }

    console.log('JWT from localStorage:', localStorage.getItem('jwt'));
    console.log('JWT from sessionStorage:', sessionStorage.getItem('jwt'));

    let decoded;
    try {
      decoded = jwt_decode(token);

      if (decoded.exp) {
    const now = Date.now() / 1000;
    console.log('Token expired:', now > decoded.exp);
}
    } catch (err) {
      console.error('Invalid token:', err);
      window.location.href = 'login.php';
    }

    fetch('get_username.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ jwt: token }) // Send the actual JWT
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