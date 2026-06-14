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
  <title>SlopeGuard — Sign In</title>
  <link rel="stylesheet" href="../assets/css/login.css">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>

<div class="login-page">

  <!-- LEFT: Branding -->
  <div class="login-left">
    <div class="login-left-inner">

      <div class="login-logo-wrap">
        <svg width="56" height="56" viewBox="0 0 96 96" fill="none" class="login-logo-icon">
          <defs><clipPath id="sc1"><path d="M48 6 L90 22 L90 58 Q90 80 48 92 Q6 80 6 58 L6 22 Z"/></clipPath></defs>
          <path d="M48 6 L90 22 L90 58 Q90 80 48 92 Q6 80 6 58 L6 22 Z" fill="#0d2a2b" stroke="#0e9fa0" stroke-width="2.5"/>
          <g clip-path="url(#sc1)" opacity="0.65">
            <line x1="0"  y1="96" x2="96" y2="0"   stroke="#0e9fa0" stroke-width="7"/>
            <line x1="8"  y1="104" x2="104" y2="8"  stroke="#1ab8a0" stroke-width="6"/>
            <line x1="-8" y1="88"  x2="88"  y2="-8" stroke="#0a7a7b" stroke-width="6"/>
            <line x1="16" y1="112" x2="112" y2="16" stroke="#0e9fa0" stroke-width="5"/>
            <line x1="-16" y1="80" x2="80"  y2="-16"stroke="#0a7a7b" stroke-width="4"/>
          </g>
          <g clip-path="url(#sc1)">
            <polygon points="32,74 48,42 64,74" fill="#0d2a2b" opacity="0.96"/>
            <polygon points="22,74 34,54 46,74" fill="#0d2a2b" opacity="0.9"/>
            <polygon points="44,45 48,36 52,45 48,42" fill="#e0f7f7" opacity="0.9"/>
          </g>
          <circle cx="10" cy="20" r="3" fill="#0e9fa0" opacity="0.75"/>
          <circle cx="86" cy="20" r="3" fill="#0e9fa0" opacity="0.75"/>
        </svg>
        <div>
          <div class="login-brand-name">SlopeGuard</div>
          <div class="login-brand-sub">ADVANCED LANDSLIDE EARLY WARNING SYSTEM</div>
        </div>
      </div>

      <div class="login-divider"></div>

      <p class="login-desc">
        Real-time early warning system for landslide detection across sensor nodes in Manolo Fortich, Bukidnon.
      </p>

      <div class="login-features">
        <div class="login-feature-item">
          <i class='bx bx-radio-circle-marked'></i>
          <span>Real-time data from 3 active sensor nodes</span>
        </div>
        <div class="login-feature-item">
          <i class='bx bx-bell'></i>
          <span>Automated risk detection and SMS alerts</span>
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

      <p class="login-region">Manolo Fortich, Bukidnon &middot; Philippines</p>
    </div>
  </div>

  <!-- RIGHT: Form -->
  <div class="login-right">
    <div class="login-card">
      <p class="login-card-label">Administrator Access</p>
      <h2>Sign in to your account</h2>
      <p class="login-card-sub">Enter your credentials to access the dashboard</p>

      <?php if ($error): ?>
        <div class="error-msg">
          <i class='bx bx-error-circle'></i>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <div class="input-group">
          <label>Username</label>
          <div class="input-box">
            <i class='bx bx-user input-icon'></i>
            <input type="text" name="username" placeholder="Enter your username" required>
          </div>
        </div>
        <div class="input-group">
          <label>Password</label>
          <div class="input-box">
            <i class='bx bx-lock-alt input-icon'></i>
            <input type="password" name="password" placeholder="Enter your password" required>
          </div>
        </div>
        <div class="remember-forgot">
          <label class="remember-label">
            <input type="checkbox" name="remember"> Remember me
          </label>
          <a href="#" class="forgot-link">Forgot password?</a>
        </div>
        <button type="submit" class="btn-login">
          <span>Sign In</span>
          <i class='bx bx-log-in-circle'></i>
        </button>
      </form>
    </div>
    <p class="login-footer-note">SlopeGuard &copy; <?= date('Y') ?> &middot; Davao Region</p>
  </div>

</div>

</body>
</html>