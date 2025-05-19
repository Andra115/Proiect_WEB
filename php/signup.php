<?php
session_start();
require_once 'db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $passwordInput = $_POST['password'] ?? '';

    if ($username && $passwordInput) {
        try {
            $stmt = $pdo->prepare('SELECT createUser(:username, :password)');
            $stmt->execute(['username' => $username, 'password' => $passwordInput]);
            $message = "Signup successful. You can now log in.";
        } catch (PDOException $e) {
            $errorCode = $e->getCode();
            if ($errorCode === 'P0003') {
                $message = 'Username already taken.';
            } else {
                $message = 'Database error: ' . $e->getMessage();
            }
        }
    } else {
        $message = "Please enter username and password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Sign Up - CloudBox</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <div class="container">
    <h1>Create Your CloudBox Account</h1>
    <p class="description">
      Set up your CloudBox account to start syncing and securing your files across the cloud.
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
      <button type="submit">Sign Up</button>
    </form>

    <div class="footer">
      Already have an account? <a href="login.php">Log in</a>.
    </div>
  </div>
</body>
</html>