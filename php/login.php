<?php
session_start();
header("Content-Security-Policy: default-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self'");require_once 'db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $email = $_POST['email'] ?? '';
    $passwordInput = $_POST['password'] ?? '';

    if ($email && $passwordInput) {
        try {
            $stmt = $pdo->prepare('SELECT checkLogin(:email, :password)');
            $stmt->execute(['email' => $email, 'password' => $passwordInput]);
            $loginSuccess = $stmt->fetchColumn();

            if ($loginSuccess) {
                /*$jwtConfig = json_decode(file_get_contents(__DIR__ . '/../jwt.json'), true);
                $key = $jwtConfig['key'];*/
                $jwtConfig = json_decode(getenv("JWT_JSON"), true);
                $key = $jwtConfig['key'];
                $iss_time = time();
                $userIdStmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email');
                $userIdStmt->execute(['email' => $email]);
                $userId = $userIdStmt->fetchColumn();

                $payload = [
                    "iss" => "https://localhost:8000",
                    "iat" => $iss_time,
                    "exp" => $iss_time + 2592000,
                    "user_id" => $userId
                ];
                $jwt = JWT::encode($payload, $key, 'HS256');
                $_SESSION['user_id'] = $userId;

                echo json_encode([
                    "success" => true,
                    "jwt" => $jwt,
                    "user_id" => $userId
                ]);
                exit;
            } else {
                echo json_encode([
                    "success" => false,
                    "message" => "Invalid email or password."
                ]);
                exit;
            }
        } catch (PDOException $e) {
            $errorCode = $e->getCode();
            if ($errorCode === 'P0001') {
                $msg = 'There is no account using this email address.';
            } else if ($errorCode === 'P0002') {
                $msg = 'Incorrect password.';
            } else {
                $msg = 'Database error: ' . $e->getMessage();
            }
            echo json_encode([
                "success" => false,
                "message" => $msg
            ]);
            exit;
        }
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Email and password are required."
        ]);
        exit;
    }
} else {
    $message = "";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login to Cloud9</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lemon&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>
    <div class="login">
        <img src="../assets/logo_white_on_blue_background.jpeg" alt="Logo" class="logo">
        <form method="POST">
            <input type="text" name="email" placeholder="Email" required class="loginFormItem">
            <input type="password" name="password" placeholder="Password" required class="loginFormItem">
            <div class="rememberMe">
                <input type="checkbox" name="remember" value="1"> Remember me
            </div>
            <div id="login-error-message" class="error-message"></div>
            <div>
                <button type="submit" class="loginButton">Log in</button>
            </div>
        </form>
        <div class="noAccount">No account? <a href="signup.php">Sign up here.</a></div>
    </div>
    <script src="../js/login.js"></script>
</body>

</html>