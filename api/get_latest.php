<?php
/* ============================================================
   SlopeGuard — Get Latest Sensor Reading
   Used by: dashboard live cards (app.js)
============================================================ */

include "../config/db.php";
header("Content-Type: application/json");

$node = isset($_GET['node']) ? (int)$_GET['node'] : 1;

$stmt = $conn->prepare("
  SELECT * FROM sensor_readings
  WHERE node_id = ?
  ORDER BY created_at DESC
  LIMIT 1
");
$stmt->bind_param("i", $node);
$stmt->execute();

echo json_encode($stmt->get_result()->fetch_assoc());
?>
