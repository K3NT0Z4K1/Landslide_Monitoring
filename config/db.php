<?php
$conn = new mysqli("localhost", "root", "", "slopeguard");
if ($conn->connect_error) {
  die("DB Connection Failed");
}
?>