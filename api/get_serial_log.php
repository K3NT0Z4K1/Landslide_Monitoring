<?php
/**
 * get_serial_log.php
 * ------------------
 * Returns sensor_readings rows formatted for the Serial Monitor panel.
 * Supports incremental polling via `after_id` so the browser only
 * fetches NEW rows each poll cycle.
 *
 * Query params:
 *   node     (int)  Node ID filter (default: all nodes)
 *   after_id (int)  Return only rows with id > after_id (default: 0)
 *   limit    (int)  Max rows to return (default: 20, max: 100)
 *
 * Response: JSON array, oldest-first, each row:
 * {
 *   id, node_id, temperature, humidity, soil_moisture, rainfall,
 *   status, rssi, raw_packet, time (HH:MM:SS)
 * }
 */

require_once "../config/db.php";
header('Content-Type: application/json');
header('Cache-Control: no-store');

$node     = isset($_GET['node'])     ? (int)$_GET['node']     : 0;
$after_id = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;
$limit    = isset($_GET['limit'])    ? min((int)$_GET['limit'], 100) : 20;

/* Build query — node filter is optional */
if ($node > 0) {
  $stmt = $conn->prepare("
    SELECT
      id,
      node_id,
      temperature,
      humidity,
      soil_moisture,
      rainfall,
      status,
      rssi,
      raw_packet,
      DATE_FORMAT(created_at, '%H:%i:%S') AS time
    FROM sensor_readings
    WHERE node_id = ?
      AND id > ?
    ORDER BY id ASC
    LIMIT ?
  ");
  $stmt->bind_param("iii", $node, $after_id, $limit);
} else {
  $stmt = $conn->prepare("
    SELECT
      id,
      node_id,
      temperature,
      humidity,
      soil_moisture,
      rainfall,
      status,
      rssi,
      raw_packet,
      DATE_FORMAT(created_at, '%H:%i:%S') AS time
    FROM sensor_readings
    WHERE id > ?
    ORDER BY id ASC
    LIMIT ?
  ");
  $stmt->bind_param("ii", $after_id, $limit);
}

$stmt->execute();
$result = $stmt->get_result();
$rows   = [];

while ($row = $result->fetch_assoc()) {
  /* Build raw_packet string if column doesn't exist in older schemas */
  if (empty($row['raw_packet'])) {
    $row['raw_packet'] = implode(',', [
      $row['node_id'],
      number_format($row['temperature'],  2),
      number_format($row['humidity'],     2),
      $row['soil_moisture'],
      number_format($row['rainfall'],     2),
      $row['status']
    ]);
  }
  $rows[] = $row;
}

echo json_encode($rows);
