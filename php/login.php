<?php
session_start();
require_once 'db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $passwordInput = $_POST['password'] ?? '';

    if ($username && $passwordInput) 
    {
        try {
        $stmt = $pdo->prepare('SELECT checkLogin(:username, :password)');
        $stmt->execute(['username' => $username, 'password' => $passwordInput]);
        $loginSuccess = $stmt->fetchColumn();

        if ($loginSuccess) {
            $_SESSION['username'] = $username;
            header('Location: welcome.php');
            exit;
        }
      } catch (PDOException $e) {
        $errorCode = $e->getCode();
        if ($errorCode === 'P0001') {
            $message = 'Username not found.';
        } elseif ($errorCode === 'P0002') {
            $message = 'Incorrect password.';
        } else {
            $message = 'Database error: ' . $e->getMessage();
        }
    }
    }
    
    } else {
        $message = "Please enter username and password.";
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Welcome to CloudBox</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <div class="container">
    <h1>Welcome to CloudBox</h1>
    <p class="description">
      CloudBox helps you manage, sync, and secure your files across Dropbox, Google Drive, and OneDrive. 
      Enjoy encrypted storage, backup redundancy, and seamless file access — all in one place.
    </p>

    <?php if ($message): ?>
      <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <label>Username:
        <input type="text" name="username" required>
      </label>
      <label>Password:
        <input type="password" name="password" required>
      </label>
      <button type="submit">Login</button>
    </form>

    <div class="footer">
      Need an account? <a href="signup.php">Create one here</a>.
    </div>
  </div>
</body>
</html>