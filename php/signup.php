<?php
session_start();
require_once 'db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST["email"]);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $message = "Invalid email format";
    } 
    else {
    $username = $_POST['username'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $passwordAgain = $_POST['reenterPassword'] ?? '';

    if ($email && $username && $passwordInput && $passwordAgain) {
        try {
            $stmt = $pdo->prepare('SELECT createUser(:email, :username, :password, :passwordAgain)');
            $stmt->execute(['email' => $email, 'username' => $username, 'password' => $passwordInput, 'passwordAgain' => $passwordAgain]);
            $result = $stmt->fetchColumn();
            $message = "Sign up successful! You can now log in.";
        } catch (PDOException $e) {
            $errorCode = $e->getCode();
            if ($errorCode === 'P0003') {
                $message = 'An account using this email address already exists.';
            } 
            else if ($errorCode === 'P0004') {
                $message = 'An account using this username already exists.';
            }
            else if ($errorCode === 'P0005') {
                $message = 'Username can only contain letters, numbers, underscores, and hyphens.';
            } 
            else if ($errorCode === 'P0006') {
                $message = 'Username must be at least 3 characters long.';
            }
            else if ($errorCode === 'P0007') {
                $message = 'Password must be at least 8 characters long.';
            } 
            else if ($errorCode === 'P0008') {
                $message = 'Passwords do not match.';
            }
            else {
                $message = 'Database error: ' . $e->getMessage();
            }
        }
    } else {
        $message = "";
    }
  }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Signup to Cloud9</title>
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
      <input type="text" name="username" placeholder="Username" required class="loginFormItem">
      <input type="password" name="password" placeholder="Password" required class="loginFormItem">
      <input type="password" name="reenterPassword" placeholder="Re-enter password" required class="loginFormItem">
      <div class="rememberMe">
        <?php if ($message): ?>
      <div><?= htmlspecialchars($message) ?> </div>
    <?php endif; ?>
      <div> <button type="submit" class="loginButton">Sign up</button> </div>
    </form>

      <div class="noAccount">Already have an account? <a href="login.php">Log in here.</a></div>
  </div>
</body>
</html>