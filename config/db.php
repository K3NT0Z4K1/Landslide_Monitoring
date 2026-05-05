<?php
$conn = new mysqli("localhost", "root", "", "landslide_monitoring");
if ($conn->connect_error) {
  die("DB Connection Failed");
}
?>
