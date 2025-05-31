<?php
session_start();
require_once 'db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $passwordInput = $_POST['password'] ?? '';

    if ($email && $passwordInput) 
    {
        try {
        $stmt = $pdo->prepare('SELECT checkLogin(:email, :password)');
        $stmt->execute(['email' => $email, 'password' => $passwordInput]);
        $loginSuccess = $stmt->fetchColumn();

        if ($loginSuccess) {
            session_start();
    $key = "Aceasta este o cheie supersecreta";
    $iss_time = time();
    $userIdStmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email');
    $userIdStmt->execute(['email' => $email]);
    $userId = $userIdStmt->fetchColumn();
    
  
    $payload = ["iss" => "https://localhost:8000",
        "iat" => $iss_time,
        "exp" => $iss_time + 2592000,
        "user_id"=> $userId
    ];
    $jwt = JWT::encode($payload, $key, 'HS256');
    $_SESSION['user_id'] = $userId; 
    
     $remember = isset($_POST['remember']) ? 'true' : 'false';
    
    echo "<script>
    const jwt = " . json_encode($jwt) . ";
    const remember = " . json_encode($remember) . "; // This properly quotes it
    
    if (remember === 'true') { // Now string comparison works
        localStorage.setItem('jwt', jwt);
    } else {
        sessionStorage.setItem('jwt', jwt);
    }
    
    window.location.href = 'welcome.php';
</script>";
    exit;
}
    
      } catch (PDOException $e) {
        $errorCode = $e->getCode();
        if ($errorCode === 'P0001') {
            $message = 'There is no user with this email address.';
        } elseif ($errorCode === 'P0002') {
            $message = 'The password is incorrect.';
        } else {
            $message = 'Database error: ' . $e->getMessage();
        }
    }
    }
    
    } else {
        $message = "";
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Login to Cloud9</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lemon&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/style.css" />
</head>
<body>
  <div class="login">

  <img src="../assets/logo_white_on_blue_background.jpeg" alt="Logo" class="logo"/>

    <form method="POST" action="">
      <input type="text" name="email" placeholder="Email" required class="loginFormItem">
      <input type="password" name="password" placeholder="Password" required class="loginFormItem">
      <div class="rememberMe">
        <input type="checkbox" name="remember" value="1"> Remember me </div>
        <?php if ($message): ?>
      <div><?= htmlspecialchars($message) ?> </div>
    <?php endif; ?>
      <div> <button type="submit" class="loginButton">Log in</button> </div>
    </form>

      <div class="noAccount">No account? <a href="signup.php">Sign up here.</a></div>
  </div>
</body>
</html>