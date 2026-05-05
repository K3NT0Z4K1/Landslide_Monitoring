<?php
session_start();

if (!isset($_SESSION['admin'])) {
  header("Location: ../auth/login.php");
  exit;
}
