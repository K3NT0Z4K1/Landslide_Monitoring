<?php
include "../config/db.php";

$node = $_GET['node'] ?? 1;

$stmt = $conn->prepare("
  SELECT * FROM sensor_readings
  WHERE node_id=?
  ORDER BY created_at DESC
  LIMIT 1
");

$stmt->bind_param("i", $node);
$stmt->execute();

echo json_encode($stmt->get_result()->fetch_assoc());
