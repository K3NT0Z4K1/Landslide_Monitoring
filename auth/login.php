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
  <title>GeoWatch — Sign In</title>
  <link rel="stylesheet" href="../assets/css/login.css">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>
<body>

<div class="login-page">

  <!-- LEFT: Branding -->
  <div class="login-left">
    <div class="login-left-inner">

      <svg width="64" height="64" viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg" class="login-logo-icon">
        <rect width="72" height="72" rx="16" fill="#1a3323"/>
        <polygon points="10,56 30,24 50,40 62,30 72,44 72,56" fill="#2d5a3a"/>
        <polygon points="4,56 24,32 44,56" fill="#3d7a50"/>
        <polygon points="30,56 50,30 72,56" fill="#2d5a3a"/>
        <line x1="0" y1="56" x2="72" y2="56" stroke="#5a9e6f" stroke-width="1.5"/>
        <path d="M 53 19 A 8 8 0 0 1 61 27" fill="none" stroke="#8cc4a0" stroke-width="2" stroke-linecap="round"/>
        <path d="M 49 15 A 14 14 0 0 1 65 29" fill="none" stroke="#8cc4a0" stroke-width="1.5" stroke-linecap="round" opacity="0.6"/>
        <circle cx="53" cy="19" r="2.5" fill="#8cc4a0"/>
      </svg>

      <h1 class="login-brand">GeoWatch</h1>
      <p class="login-brand-sub">Landslide Monitoring System</p>

      <div class="login-features">
        <div class="login-feature-item">
          <i class='bx bx-radio-circle-marked'></i>
          <span>Real-time sensor data from 3 active nodes</span>
        </div>
        <div class="login-feature-item">
          <i class='bx bx-bell'></i>
          <span>Automated risk detection and alerts</span>
        </div>
        <div class="login-feature-item">
          <i class='bx bx-map-alt'></i>
          <span>Live geospatial node monitoring</span>
        </div>
        <div class="login-feature-item">
          <i class='bx bx-line-chart'></i>
          <span>Historical sensor trend analysis</span>
        </div>
      </div>

      <p class="login-region">Davao Region &middot; Northern Mindanao</p>
    </div>
  </div>

  <!-- RIGHT: Form -->
  <div class="login-right">
    <div class="login-card">

      <div class="login-card-header">
        <p class="login-card-label">Administrator Access</p>
        <h2>Sign in to your account</h2>
        <p class="login-card-sub">Enter your credentials to access the dashboard</p>
      </div>

      <form method="POST" autocomplete="off">

        <?php if ($error): ?>
          <div class="error-msg">
            <i class='bx bx-error-circle'></i>
            <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <div class="input-group">
          <label for="username">Username</label>
          <div class="input-box">
            <i class='bx bx-user input-icon'></i>
            <input type="text" id="username" name="username" placeholder="Enter your username" required>
          </div>
        </div>

        <div class="input-group">
          <label for="password">Password</label>
          <div class="input-box">
            <i class='bx bx-lock-alt input-icon'></i>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>
          </div>
        </div>

        <div class="remember-forgot">
          <label class="remember-label">
            <input type="checkbox" name="remember">
            <span>Remember me</span>
          </label>
          <a href="#" class="forgot-link">Forgot password?</a>
        </div>

        <button type="submit" class="btn-login">
          <span>Sign In</span>
          <i class='bx bx-log-in-circle'></i>
        </button>

      </form>

    </div>

    <p class="login-footer">GeoWatch Monitoring System &copy; <?= date('Y') ?> &middot; Davao Region</p>
  </div>

</div>

</body>
</html>