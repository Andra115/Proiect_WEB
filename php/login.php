<?php
// login.php
session_start();
require_once 'db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $passwordInput = $_POST['password'] ?? '';

    if ($username && $passwordInput) {
        $stmt = $pdo->prepare('SELECT checkLogin(:username, :password)');
        $stmt->execute(['username' => $username, 'password' => $passwordInput]);
        $loginSuccess = $stmt->fetchColumn();

        if ($loginSuccess) {
            $_SESSION['username'] = $username;
            header('Location: welcome.php');
            exit;
        } else {
            $message = "Login failed. Wrong username or password.";
        }
    } else {
        $message = "Please enter username and password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8" /><title>Login</title></head>
<body>
  <h1>Login</h1>
  <?php if ($message): ?>
    <p><?= htmlspecialchars($message) ?></p>
  <?php endif; ?>
  <form method="POST" action="">
    <label>Username: <input type="text" name="username" required></label><br>
    <label>Password: <input type="password" name="password" required></label><br>
    <button type="submit">Login</button>
  </form>
</body>
</html>