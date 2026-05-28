<?php
/**
 * get_alert_summary.php
 * ---------------------
 * Returns a run-length-encoded digest of recent sensor_readings
 * for the Alert Summary sidebar widget.
 *
 * Query params:
 *   limit (int)  How many readings to scan (default: 50, max: 200)
 *
 * Response: JSON array of run objects, newest-first:
 * [
 *   {
 *     "status":     "SAFE",
 *     "count":      12,
 *     "time_range": "05:00–05:47"   // or single time if count=1
 *   },
 *   ...
 * ]
 */

require_once "../config/db.php";
header('Content-Type: application/json');
header('Cache-Control: no-store');

$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 200) : 50;

$stmt = $conn->prepare("
  SELECT status, DATE_FORMAT(created_at, '%H:%i') AS t
  FROM sensor_readings
  ORDER BY created_at DESC
  LIMIT ?
");
$stmt->bind_param("i", $limit);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* Run-length encode — rows are newest-first */
$runs = [];
foreach ($rows as $r) {
  $s = $r['status'];
  $t = $r['t'];
  if (empty($runs) || $runs[count($runs)-1]['status'] !== $s) {
    $runs[] = [
      'status'     => $s,
      'count'      => 1,
      'start_time' => $t,   /* oldest in this run */
      'end_time'   => $t,   /* newest in this run */
    ];
  } else {
    $runs[count($runs)-1]['count']++;
    $runs[count($runs)-1]['start_time'] = $t; /* moving back in time */
  }
}

/* Build response */
$out = [];
foreach ($runs as $run) {
  $range = ($run['start_time'] === $run['end_time'])
    ? $run['end_time']
    : $run['end_time'] . '–' . $run['start_time'];

  $out[] = [
    'status'     => $run['status'],
    'count'      => $run['count'],
    'time_range' => $range,
  ];
}

echo json_encode(array_slice($out, 0, 10));
