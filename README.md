# SlopeGuard
### Advanced Landslide Early Warning System

A real-time landslide monitoring and early warning system built with IoT sensor nodes, LoRa wireless communication, PHP/MySQL backend, and a live web dashboard. Deployed on Hostinger shared hosting.

---

## Features

- Real-time sensor monitoring — temperature, humidity, soil moisture, and rainfall from up to 3 active nodes
- Heartbeat + event-driven transmission — routine data every 5 minutes, immediate alert on abnormal conditions
- Automated risk classification — computed on sensor node and forwarded by master node
- SMS alerts via SIM900A — WARNING and DANGER events notify a designated number instantly
- Buzzer/relay activation — local physical alarm on the master node
- Live dashboard — stat cards, trend charts, sensor map with color-coded markers
- Alert history log — full record of all WARNING and DANGER events with export
- Data export — download sensor readings as CSV or JSON
- Session-based authentication — protected admin dashboard

---

## Technologies Used

| Layer | Technology |
|---|---|
| Sensor Nodes | Arduino MEGA |
| Master Node | ESP32 |
| Wireless | LoRa 915MHz — SF12, BW125, CR5, SyncWord 0x12 |
| Backend | PHP 8.x |
| Database | MySQL / MariaDB |
| Frontend | HTML, CSS, JavaScript |
| Charts | Chart.js |
| Map | Leaflet.js + OpenStreetMap |
| Icons | Boxicons |
| Fonts | DM Sans + DM Mono (Google Fonts) |
| Hosting | Hostinger Shared Hosting |
| Deployment | FTP |

---

## Folder Structure

```
SEISMO-SAFE/
├── api/                        ← REST API endpoints (called by dashboard JS and ESP32)
│   ├── receive_data.php        ← accepts POST from master node, writes all tables
│   ├── get_latest.php          ← returns latest reading for a node
│   ├── get_history.php         ← returns last N readings for charts and table
│   ├── get_nodes.php           ← returns all nodes with status for map
│   └── get_alert_history.php   ← returns alert event log
│
├── arduino-codes/              ← hardware source code (GitHub only, not on server)
│   ├── Master_Node.ino         ← ESP32 — receives LoRa, sends to server, buzzer, SMS
│   └── Sensor_Nodes.ino        ← Arduino MEGA — reads sensors, transmits over LoRa
│
├── assets/
│   ├── css/
│   │   ├── style.css           ← main dashboard stylesheet
│   │   └── login.css           ← login page stylesheet
│   └── js/
│       ├── app.js              ← live stat card updates
│       ├── charts.js           ← Chart.js initialization and refresh
│       └── map.js              ← Leaflet map initialization and node markers
│
├── auth/
│   ├── auth_check.php          ← session guard — include at top of protected pages
│   ├── login.php               ← login page and form handler
│   └── logout.php              ← destroys session and redirects
│
├── config/
│   └── db.php                  ← database connection — update credentials here
│
├── dashboard/
│   ├── index.php               ← main dashboard (stat cards, charts, table)
│   ├── map.php                 ← full sensor map page
│   └── alerts.php              ← alert history log with export
│
├── db/
│   └── seismosafe.sql          ← database schema and seed data (import once, then keep in GitHub)
│
├── index.php                   ← root redirect → /auth/login.php
├── .htaccess                   ← security rules, directory listing off
└── README.md
```

---

## Localhost Setup

### Requirements

- PHP 8.x with MySQLi extension
- MySQL 5.7+ or MariaDB 10.4+
- XAMPP, Laragon, or similar local server

### Steps

**1. Clone the repository**
```bash
git clone https://github.com/YOUR_USERNAME/seismo-safe.git
```

**2. Move to your server root**

For XAMPP:
```
C:/xampp/htdocs/seismo-safe/
```

**3. Import the database**

Open phpMyAdmin → create a new database called `seismosafe` → import `db/seismosafe.sql`.

**4. Configure database connection**

Open `config/db.php` and update:
```php
$conn = new mysqli("localhost", "root", "", "seismosafe");
```

**5. Open in browser**
```
http://localhost/seismo-safe/
```

Default login: `admin` / `admin123`

---

## Hostinger Deployment Guide

### Step 1 — Create database on Hostinger

1. Log in to Hostinger control panel (hPanel)
2. Go to **Hosting → Databases → MySQL Databases**
3. Create a new database — note the database name, username, and password
4. Hostinger prefixes your database name with your account username (e.g. `u123456789_seismosafe`)

### Step 2 — Import database

1. In hPanel go to **phpMyAdmin**
2. Select your new database
3. Click **Import** → upload `db/seismosafe.sql`
4. **Important:** Remove the `CREATE DATABASE` and `USE` lines from the SQL file before importing — Hostinger does not allow creating databases via SQL

### Step 3 — Update `config/db.php`

```php
<?php
$conn = new mysqli(
  "localhost",
  "u123456789_your_db_user",    // from Hostinger MySQL panel
  "your_db_password",
  "u123456789_seismosafe"       // your database name
);

if ($conn->connect_error) {
  http_response_code(500);
  die(json_encode(["error" => "Service unavailable"]));
}

$conn->set_charset("utf8mb4");
?>
```

### Step 4 — Upload via FTP

Use FileZilla or any FTP client:

- **Host:** `ftp.yourdomain.com`
- **Username:** your Hostinger FTP username
- **Password:** your Hostinger FTP password
- **Port:** 21

Upload the following to `public_html/`:
```
api/
assets/
auth/
config/
dashboard/
index.php
.htaccess
```

**Do NOT upload:**
```
arduino-codes/      ← hardware source code, not needed on server
db/                 ← SQL file already imported
README.md           ← GitHub only
CONTEXT.md          ← GitHub only
.git/               ← never upload
```

### Step 5 — Update master node URL

Open `arduino-codes/Master_Node.ino` and update:
```cpp
const char* serverURL = "http://yourdomain.com/api/receive_data.php";
```

Re-upload the sketch to the ESP32.

### Step 6 — Test

1. Visit `http://yourdomain.com` — should redirect to login
2. Log in with `admin` / `admin123`
3. Power on the master node and watch serial monitor for `HTTP Response : 200`
4. Dashboard should show live sensor data within 5 minutes (or immediately on alert)

---

## Database Schema

### `sensor_nodes`
Stores the 3 sensor node locations and their current alert status.

| Column | Type | Description |
|---|---|---|
| id | INT | Node ID (1, 2, 3) |
| node_name | VARCHAR | Display name |
| latitude | DECIMAL | GPS latitude |
| longitude | DECIMAL | GPS longitude |
| location | VARCHAR | Location label |
| status | ENUM | ACTIVE or OFFLINE |
| alert | VARCHAR | SAFE, WARNING, DANGER |
| last_seen | TIMESTAMP | Last data received |

### `sensor_readings`
All sensor readings from all nodes.

| Column | Type | Description |
|---|---|---|
| id | INT | Auto increment |
| node_id | INT | Foreign key to sensor_nodes |
| temperature | FLOAT | Degrees Celsius |
| humidity | FLOAT | Percentage |
| soil_moisture | INT | Percentage (0–100) |
| rainfall | FLOAT | mm per hour |
| status | VARCHAR | SAFE, WARNING, DANGER |
| created_at | TIMESTAMP | Auto timestamp |

### `alert_history`
Only WARNING and DANGER events. SAFE readings are never written here.

| Column | Type | Description |
|---|---|---|
| id | INT | Auto increment |
| node_id | INT | Foreign key to sensor_nodes |
| soil_moisture | INT | Soil % at time of alert |
| rainfall | FLOAT | Rainfall at time of alert |
| status | VARCHAR | WARNING or DANGER |
| created_at | TIMESTAMP | Auto timestamp |

---

## API Overview

All endpoints are in `api/`. Base URL: `http://yourdomain.com/api/`

| Endpoint | Method | Description |
|---|---|---|
| `receive_data.php` | POST | Accepts sensor data from ESP32 master node |
| `get_latest.php?node=1` | GET | Returns latest reading for the specified node |
| `get_history.php?node=1&limit=20` | GET | Returns last N readings for charts and table |
| `get_nodes.php` | GET | Returns all nodes with status for the map |
| `get_alert_history.php?limit=100` | GET | Returns alert event log |

### `receive_data.php` expected POST fields

| Field | Type | Description |
|---|---|---|
| node_id | int | Node number (1, 2, or 3) |
| temperature | float | Temperature in °C |
| humidity | float | Humidity percentage |
| soil_moisture | int | Soil moisture percentage (0–100) |
| rainfall | float | Rainfall in mm/hr |
| status | string | SAFE, WARNING, or DANGER |

---

## Authentication

Login is session-based using PHP sessions.

- Login page: `auth/login.php`
- Credentials: `admin` / `admin123` (hardcoded — replace before production)
- Session guard: `auth_check.php` — include at the top of every protected page
- Session is destroyed on logout via `auth/logout.php`

---

## Troubleshooting

**Dashboard shows blank or 500 error after FTP upload**
- Check `config/db.php` credentials match your Hostinger database exactly
- Make sure the database was imported successfully in phpMyAdmin
- Check Hostinger error logs in hPanel → Files → Error Logs

**Master node shows HTTP -1 or no response**
- Confirm `serverURL` in `Master_Node.ino` is correct and the domain is live
- If using HTTPS, add `http.setInsecure()` before `http.begin()`
- Check WiFi is connected — serial monitor shows `WiFi Connected`

**Dashboard shows `--` for all sensor values**
- Confirm master node serial monitor shows `HTTP Response : 200`
- Confirm `receive_data.php` field names match what master node sends (`soil_moisture`, `rainfall`, `status`)
- Check phpMyAdmin → `sensor_readings` table to confirm rows are being inserted

**Login redirects to wrong URL or loops**
- Confirm all `header("Location: ...")` paths use root-relative paths starting with `/`
- Clear browser cookies and try again

**Map shows no markers**
- Open browser console and check for fetch errors on `/api/get_nodes.php`
- Confirm `sensor_nodes` table has data (3 rows seeded from SQL import)

**Alert history is empty**
- Confirm `receive_data.php` has the `alert_history` INSERT block
- Confirm STATUS is being sent correctly by master node

**FTP upload succeeds but site shows directory listing**
- Confirm `.htaccess` was uploaded and `Options -Indexes` is present
- Some Hostinger plans require enabling `.htaccess` in hPanel

---

## Future Improvements

- Firebase Authentication or database-backed login to replace hardcoded credentials
- GPS integration — live coordinates in LoRa payload, dynamic node positions on map
- NTP real-time clock on ESP32 — replace `millis()` timestamps with actual datetime
- Node offline detection — automatically mark nodes OFFLINE if no data received in N minutes
- Alert history export on the alerts page
- Email notifications alongside SMS
- Multi-user roles — admin vs viewer

---

## Contributors

| Name | Role |
|---|---|
| _(your name here)_ | Lead Developer, Hardware |
| _(groupmate name)_ | Hardware, Sensor Integration |
| _(groupmate name)_ | Testing, Documentation |

---

## License

MIT License. Free to use and modify for academic and research purposes.