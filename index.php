<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Redirecting...</title>
  <script src="https://cdn.jsdelivr.net/npm/jwt-decode/build/jwt-decode.min.js"></script>
</head>
<body>
  <script>
    const token = localStorage.getItem('jwt') || sessionStorage.getItem('jwt');

    if (token) {
      try {
        jwt_decode(token);
        window.location.href = 'php/welcome.php';
      } catch (err) {
        console.warn('Invalid JWT:', err);
        localStorage.removeItem('jwt');
        sessionStorage.removeItem('jwt');
        window.location.href = 'php/login.php';
      }
    } else {
      window.location.href = 'php/login.php';
    }
  </script>
</body>
</html>