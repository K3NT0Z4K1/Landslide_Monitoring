<?php
session_start();

if (isset($_SESSION['admin'])) {
  header("Location: ../dashboard/index.php");
  exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'] ?? '';

  if ($username === "admin" && $password === "admin123") {
    $_SESSION['admin'] = true;
    header("Location: ../dashboard/index.php");
    exit;
  } else {
    $error = "Invalid username or password.";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Landslide Monitoring</title>
  <link rel="stylesheet" href="../assets/css/login.css">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>
<body>

  <div class="wrapper">
    <div class="login-header">
      <span class="login-logo">⛰️</span>
      <h1>Landslide Monitor</h1>
      <p class="login-subtitle">Sensor Monitoring System</p>
    </div>

    <form method="POST" autocomplete="off">

      <?php if ($error): ?>
        <div class="error-msg">⚠ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="input-group">
        <label for="username">Username</label>
        <div class="input-box">
          <input type="text" id="username" name="username" placeholder="Enter username" required>
          <i class='bx bxs-user'></i>
        </div>
      </div>

      <div class="input-group">
        <label for="password">Password</label>
        <div class="input-box">
          <input type="password" id="password" name="password" placeholder="Enter password" required>
          <i class='bx bxs-lock-alt'></i>
        </div>
      </div>

      <div class="remember-forgot">
        <label>
          <input type="checkbox" name="remember"> Remember me
        </label>
        <a href="#">Forgot password?</a>
      </div>

      <button type="submit" class="btn">Sign In</button>

    </form>
  </div>

  <p class="login-watermark">LANDSLIDE MONITORING SYSTEM · DAVAO REGION</p>

</body>
</html>