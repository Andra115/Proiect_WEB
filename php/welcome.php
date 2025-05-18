<?php
// welcome.php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8" /><title>Welcome</title></head>
<body>
  <h1>Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h1>
  <p>You successfully logged in.</p>
  <a href="logout.php">Logout</a>
</body>
</html>