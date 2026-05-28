/* ==============================================================
   SlopeGuard — DB Migration
   Run this once to add the two new columns to sensor_readings
   that the Serial Monitor panel relies on.
   ============================================================== */

ALTER TABLE sensor_readings
  ADD COLUMN rssi       SMALLINT    NULL COMMENT 'LoRa RSSI (dBm)'       AFTER status,
  ADD COLUMN raw_packet VARCHAR(120) NULL COMMENT 'Raw CSV packet from node' AFTER rssi;


/* ==============================================================
   receive_data.php — add these two lines inside the INSERT
   so the new columns get stored.

   Find your existing INSERT block and add rssi + raw_packet:

   Original:
     INSERT INTO sensor_readings (node_id, temperature, humidity, soil_moisture, rainfall, status, created_at)
     VALUES (?, ?, ?, ?, ?, ?, NOW())

   Replace with:
     INSERT INTO sensor_readings (node_id, temperature, humidity, soil_moisture, rainfall, status, rssi, raw_packet, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())

   And bind the two extra params after $status:
     $rssi       = isset($_POST['rssi'])       ? (int)$_POST['rssi']       : null;
     $rawPacket  = isset($_POST['raw_packet']) ? $_POST['raw_packet']       : null;

   Then in bind_param change "sssss" → "sssssiss"  (i for rssi int, s for raw_packet string)
   and add $rssi, $rawPacket to the bind list.
   ============================================================== */
