<?php
include "../config/db.php";

$node = $_POST['node_id'];
$soil = $_POST['soil'];
$rain = $_POST['rain'];
$vib  = $_POST['vibration'];

$level = "NORMAL";
$msg = "Normal ground conditions";

if ($soil > 600 || $rain > 10 || $vib >= 2) {
  $level = "WARNING";
  $msg = "Potential landslide risk detected";
}

if ($soil > 750 && $rain > 25 && $vib >= 3) {
  $level = "DANGER";
  $msg = "IMMINENT LANDSLIDE RISK";
}

$stmt = $conn->prepare("
  INSERT INTO alerts (node_id, alert_level, message)
  VALUES (?,?,?)
");
$stmt->bind_param("iss", $node, $level, $msg);
$stmt->execute();

echo $level;
