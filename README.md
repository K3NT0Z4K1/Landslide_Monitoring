# GeoWatch — Landslide Monitoring System

A real-time landslide monitoring dashboard built with PHP, MySQL, Chart.js, and Leaflet. Sensor nodes deployed across slope areas transmit soil moisture, rainfall, temperature, and humidity data, which is visualized on a live dashboard with automatic risk level detection.

---

## Features

- **Real-time sensor monitoring** — Live readings from up to 3 sensor nodes, auto-refreshed every 3 seconds
- **Automatic risk detection** — Threshold-based alert logic classifies conditions as Normal, Warning, or High Risk
- **Interactive map** — Leaflet map showing live node locations with color-coded status markers
- **Sensor charts** — Historical trend charts for temperature, humidity, rainfall, and soil moisture
- **Admin dashboard** — Session-protected interface with sidebar navigation
- **Responsive layout** — Adapts to tablet and mobile screen sizes

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML, CSS, JavaScript |
| Backend | PHP 8.x |
| Database | MySQL / MariaDB |
| Charts | Chart.js |
| Maps | Leaflet.js + OpenStreetMap |
| Icons | Boxicons |
| Fonts | DM Sans, DM Mono (Google Fonts) |

---

## Project Structure

```
landslide_monitoring/
├── api/
│   ├── alert_logic.php       # Evaluates sensor data and writes alert level
│   ├── get_history.php       # Returns last N readings for a node (used by charts)
│   ├── get_latest.php        # Returns latest reading for a node (used by live cards)
│   ├── get_nodes.php         # Returns all nodes with their latest alert level
│   └── receive_data.php      # Receives POST data from sensor hardware
├── assets/
│   ├── css/
│   │   ├── style.css         # Dashboard design system
│   │   └── login.css         # Login page styles
│   └── js/
│       ├── app.js            # Live sensor card updates
│       ├── charts.js         # Chart.js chart initialization and refresh
│       └── map.js            # Leaflet map initialization and node markers
├── auth/
│   ├── auth_check.php        # Session guard (include at top of protected pages)
│   ├── login.php             # Login page and form handler
│   └── logout.php            # Destroys session and redirects
├── config/
│   └── db.php                # MySQLi database connection
├── dashboard/
│   ├── index.php             # Main dashboard page
│   └── map.php               # Full-screen sensor map page
└── landslide_monitoring.sql  # Database schema and seed data
```

---

## Setup

### Requirements

- PHP 8.x with MySQLi extension
- MySQL 5.7+ or MariaDB 10.4+
- A local server (XAMPP, Laragon, or similar)

### Installation

**1. Clone the repository**
```bash
git clone https://github.com/YOUR_USERNAME/landslide_monitoring.git
```

**2. Import the database**

Open phpMyAdmin (or your MySQL client) and import `landslide_monitoring.sql`. This creates the schema and seeds 3 sensor nodes and sample readings.

**3. Configure the database connection**

Open `config/db.php` and update the credentials if needed:
```php
$conn = new mysqli("localhost", "root", "", "landslide_monitoring");
```

**4. Place the project in your server root**

For XAMPP, move the folder to `htdocs/`:
```
C:/xampp/htdocs/landslide_monitoring/
```

**5. Open the application**
```
http://localhost/landslide_monitoring/auth/login.php
```

**Default credentials**
```
Username: admin
Password: admin123
```

> Replace the hardcoded credentials in `auth/login.php` with a database-backed auth system before any production use.

---

## Sensor Nodes

Three nodes are seeded in the database, all located in the Lower Slope area near coordinates `8.2495, 124.7541` (Davao Region, Northern Mindanao).

| Node | Location | Coordinates |
|---|---|---|
| Node 1 | Lower Slope A | 8.2489, 124.7532 |
| Node 2 | Lower Slope B | 8.2495, 124.7541 |
| Node 3 | Lower Slope C | 8.2501, 124.7550 |

---

## Alert Thresholds

Risk level is determined by combining soil moisture (ADC reading) and rainfall (mm):

| Level | Condition |
|---|---|
| Normal | Soil ≤ 500 and Rain ≤ 10 mm |
| Warning | Soil > 500 or Rain > 10 mm |
| High Risk | Soil > 700 and Rain > 20 mm |

Thresholds are defined in `assets/js/app.js` as constants and can be adjusted:
```js
const SOIL_WARNING = 500;
const SOIL_DANGER  = 700;
const RAIN_WARNING = 10;
const RAIN_DANGER  = 20;
```

---

## Receiving Sensor Data

External hardware (e.g. Arduino + ESP8266) can POST readings to `api/receive_data.php`:

```
POST /api/receive_data.php
Content-Type: application/x-www-form-urlencoded

node_id=1&temperature=27.5&humidity=85&soil=620&rain=12.4
```

---

## Screenshots

> _Add screenshots of the login page, dashboard, and map here._

---

## License

MIT License. See `LICENSE` for details.
