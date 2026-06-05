<?php

/* ============================================================
   SlopeGuard — Receive Sensor Data
   Called by: Master_Node.ino (ESP32)
   Method: POST
   Fields: node_id, temperature, humidity, soil_moisture,
           rainfall, status, rssi, raw_packet
============================================================ */

include "../config/db.php";

/* ---------------------------
   GET POST DATA
--------------------------- */
$node      = isset($_POST['node_id'])       ? (int)$_POST['node_id']      : null;
$temp      = $_POST['temperature']          ?? null;
$hum       = $_POST['humidity']             ?? null;
$soil      = $_POST['soil_moisture']        ?? null;
$rain      = $_POST['rainfall']             ?? null;
$status    = $_POST['status']               ?? 'SAFE';
$rssi      = isset($_POST['rssi'])          ? (int)$_POST['rssi']         : null;
$rawPacket = isset($_POST['raw_packet'])    ? $_POST['raw_packet']         : null;

/* ---------------------------
   VALIDATION
--------------------------- */
if (!$node || $node < 1 || $node > 3) {
  http_response_code(400);
  echo "ERROR: Invalid node ID";
  exit;
}

$allowed = ['SAFE', 'CAUTION', 'WARNING', 'DANGER'];
if (!in_array($status, $allowed)) {
  http_response_code(400);
  echo "ERROR: Invalid status value — " . htmlspecialchars($status);
  exit;
}

/* ---------------------------
   INSERT SENSOR READING
--------------------------- */
$stmt = $conn->prepare("
  INSERT INTO sensor_readings
  (node_id, temperature, humidity, soil_moisture, rainfall, status, rssi, raw_packet)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("iddddsis", $node, $temp, $hum, $soil, $rain, $status, $rssi, $rawPacket);
$stmt->execute();

/* ---------------------------
   UPDATE NODE STATUS
--------------------------- */
$upd = $conn->prepare("
  UPDATE sensor_nodes
  SET last_seen = NOW(), status = 'ACTIVE', alert = ?
  WHERE id = ?
");
$upd->bind_param("si", $status, $node);
$upd->execute();

/* ---------------------------
   LOG ALERT HISTORY
   CAUTION, WARNING, and DANGER
--------------------------- */
if ($status === 'CAUTION' || $status === 'WARNING' || $status === 'DANGER') {
  $log = $conn->prepare("
    INSERT INTO alert_history
    (node_id, soil_moisture, rainfall, status)
    VALUES (?, ?, ?, ?)
  ");
  $log->bind_param("idds", $node, $soil, $rain, $status);
  $log->execute();
}

echo "DATA RECEIVED";
?>