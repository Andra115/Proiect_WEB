<?php
session_start();
header("Content-Security-Policy: default-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self'");
require_once 'db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $email = trim($_POST["email"] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "message" => "Invalid email format"]);
        exit;
    } else {
        $username = $_POST['username'] ?? '';
        $passwordInput = $_POST['password'] ?? '';
        $passwordAgain = $_POST['reenterPassword'] ?? '';

        if ($email && $username && $passwordInput && $passwordAgain) {
            try {
                $stmt = $pdo->prepare('SELECT createUser(:email, :username, :password, :passwordAgain)');
                $stmt->execute(['email' => $email, 'username' => $username, 'password' => $passwordInput, 'passwordAgain' => $passwordAgain]);
                $result = $stmt->fetchColumn();
                echo json_encode(["success" => true, "message" => "Sign up successful! You can now log in."]);
                exit;
            } catch (PDOException $e) {
                $errorCode = $e->getCode();
                if ($errorCode === 'P0003') {
                    $msg = 'An account using this email address already exists.';
                } else if ($errorCode === 'P0004') {
                    $msg = 'Username can only contain letters, numbers, underscores, and hyphens.';
                } else if ($errorCode === 'P0005') {
                    $msg = 'Username must be at least 3 characters long.';
                } else if ($errorCode === 'P0006') {
                    $msg = 'Password must be at least 8 characters long.';
                } else if ($errorCode === 'P0007') {
                    $msg = 'Passwords do not match.';
                } else {
                    $msg = 'Database error: ' . $e->getMessage();
                }
                echo json_encode(["success" => false, "message" => $msg]);
                exit;
            }
        } else {
            echo json_encode(["success" => false, "message" => "All fields are required."]);
            exit;
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Signup to Cloud9</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lemon&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/style.css">
</head>
<body>
  <div class="login">

  <img src="../assets/logo_white_on_blue_background.jpeg" alt="Logo" class="logo">

    <form method="POST">
      <input type="text" name="email" placeholder="Email" required class="loginFormItem" style="padding-right: 32px;">
      <input type="text" name="username" placeholder="Username" required class="loginFormItem">
      <input type="password" name="password" placeholder="Password" required class="loginFormItem">
      <input type="password" name="reenterPassword" placeholder="Re-enter password" required class="loginFormItem">
      <div class="rememberMe"></div>
      <div id="signup-error-message" class="error-message"></div>
      <div> <button type="submit" class="loginButton">Sign up</button> </div>
    </form>

      <div class="noAccount">Already have an account? <a href="login.php">Log in here.</a></div>
  </div>
  
  <script src="../js/signup.js"></script>
</body>
</html>