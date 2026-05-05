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

  // SIMPLE DEMO LOGIN (replace with DB later)
  if ($username === "admin" && $password === "admin123") {
    $_SESSION['admin'] = true;
    header("Location: ../dashboard/index.php");
    exit;
  } else {
    $error = "Invalid username or password";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login Form</title>

  <!-- SAME CSS -->
  <link rel="stylesheet" href="../assets/css/login.css">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>

  <div class="wrapper">
    <form method="POST">

      <h1>Login</h1>

      <!-- ERROR MESSAGE -->
      <?php if ($error): ?>
        <p style="color:red; text-align:center; margin-bottom:10px;">
          <?= htmlspecialchars($error) ?>
        </p>
      <?php endif; ?>

      <div class="input-box">
        <input type="text" name="username" placeholder="Username" required>
        <i class='bx bxs-user'></i>
      </div>

      <div class="input-box">
        <input type="password" name="password" placeholder="Password" required>
        <i class='bx bxs-lock-alt'></i>
      </div>

      <div class="remember-forgot">
        <label>
          <input type="checkbox" name="remember"> Remember Me
        </label>
        <a href="#">Forgot Password</a>
      </div>

      <button type="submit" class="btn">Login</button>

      <div class="register-link">
        <p>Don’t have an account? <a href="#">Register</a></p>
      </div>

    </form>
  </div>

</body>
</html>
