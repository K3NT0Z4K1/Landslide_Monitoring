<?php
/* ============================================================
   SlopeGuard — Get Sensor History
   Used by: charts and readings table (charts.js)
============================================================ */

include "../config/db.php";
header("Content-Type: application/json");

$node  = isset($_GET['node'])  ? (int)$_GET['node']  : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($node < 1 || $node > 3) {
  echo json_encode(["error" => "Invalid node"]);
  exit;
}

$sql = "
  SELECT
    temperature,
    humidity,
    soil_moisture,
    rainfall,
    status,
    DATE_FORMAT(created_at, '%H:%i:%s') AS time,
    created_at AS datetime
  FROM sensor_readings
  WHERE node_id = ?
  ORDER BY created_at DESC
  LIMIT ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $node, $limit);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
  $data[] = $row;
}

/* Reverse so chart shows oldest → newest */
echo json_encode(array_reverse($data));
?>
