# SlopeGuard — Advanced Landslide Early Warning System

A real-time landslide monitoring and early warning system using IoT sensor nodes, LoRa wireless communication, Firebase Realtime Database, and a live web dashboard deployed on Vercel.

---

## How the System Works

```
Sensor Node (Arduino MEGA)
  → reads temperature, humidity, soil moisture (%), rainfall every second
  → computes landslide risk level locally
  → heartbeat every 5 minutes under normal conditions
  → if risk is above NORMAL, transmits immediately regardless of interval
  → transmits data wirelessly via LoRa 915MHz
        ↓
Master Node (ESP32)
  → receives LoRa packet from sensor node
  → converts risk level to dashboard status (SAFE / WARNING / DANGER)
  → triggers buzzer relay for 10 seconds on WARNING or DANGER
  → sends SMS alert via SIM900A on WARNING or DANGER (1 min cooldown)
  → writes reading to Firebase Realtime Database over WiFi
  → logs WARNING and DANGER events to alert_history in Firebase
        ↓
Firebase Realtime Database (Cloud)
  → stores all sensor readings per node
  → stores node status and alert levels
  → stores alert history log
        ↓
Dashboard (Vercel — index.html)
  → admin logs in to access the dashboard
  → shows live sensor data, charts, map, and alert banner
  → updates automatically the moment new data arrives
  → alert history tab shows all past WARNING and DANGER events
  → export sensor readings as CSV or JSON
```

---

## Alert Logic

Risk level is computed on the sensor node using soil moisture percentage and rainfall (mm/hr):

### Sensor Node Risk Levels

| Risk | Condition |
|---|---|
| NORMAL | Below all thresholds |
| LOW_RISK | Soil ≥ 50% AND Rain ≥ 10 mm |
| MODERATE_RISK | Soil ≥ 80% alone, OR Rain ≥ 25 mm alone, OR Soil ≥ 67% AND Rain ≥ 20 mm |
| HIGH_RISK | Soil ≥ 80% AND Rain ≥ 20 mm or higher |

### Status Conversion (Master Node)

| Sensor Risk | Dashboard Status | Behavior |
|---|---|---|
| NORMAL | SAFE | Heartbeat every 5 minutes |
| LOW_RISK | WARNING | Transmit immediately |
| MODERATE_RISK | WARNING | Transmit immediately |
| HIGH_RISK | DANGER | Transmit immediately |

When WARNING or DANGER is triggered, the master node activates the buzzer, sends an SMS, and logs the event to `alert_history` in Firebase. The dashboard alert banner updates within seconds.

---

## Hardware

### Sensor Nodes (×2 or ×3) — Arduino MEGA

| Component | Purpose |
|---|---|
| DHT22 | Temperature and humidity |
| Soil Moisture Sensor (analog) | Soil saturation as percentage (A0) |
| DFRobot Rainfall Sensor (I2C) | Rainfall in mm/hr |
| GPS Module | Node location coordinates (integration pending) |
| LoRa02 (915MHz) | Wireless data transmission to master |

### Master Node (×1) — ESP32

| Component | Purpose |
|---|---|
| LoRa02 (915MHz) | Receives packets from sensor nodes |
| WiFi (built-in) | Sends data to Firebase over internet |
| SIM900A | GSM module for SMS alerts |
| Buzzer + Relay | Local audio alert on WARNING or DANGER |

---

## Pin Configuration

### Sensor Node (Arduino MEGA)

| Pin | Component |
|---|---|
| 7 | DHT22 data |
| A0 | Soil moisture sensor (analog) |
| SDA/SCL | DFRobot Rainfall Sensor (I2C) |
| 10 | LoRa NSS |
| 9 | LoRa RST |
| 2 | LoRa DIO0 |

### Master Node (ESP32)

| Pin | Component |
|---|---|
| 5 | LoRa NSS |
| 14 | LoRa RST |
| 2 | LoRa DIO0 |
| 33 | Buzzer Relay |
| 16 | SIM900A TX (ESP RX2) |
| 17 | SIM900A RX (ESP TX2) |

---

## Project Structure

```
slopeguard/
├── arduino-codes/
│   ├── Master_Node.ino     ← ESP32 code (upload to master node)
│   └── Sensor_Nodes.ino    ← Arduino MEGA code (upload to each sensor node)
├── assets/
│   ├── css/
│   │   └── style.css       ← Dashboard stylesheet
│   └── js/
│       └── script.js       ← Dashboard scripts
├── index.html              ← Full dashboard (login + dashboard + map + alert history)
├── vercel.json             ← Vercel deployment config
├── CONTEXT.md              ← Project context for development continuity
└── README.md               ← This file
```

---

## Firebase Database Structure

```
Firebase Realtime Database/
├── sensor_readings/
│   ├── node_1/
│   │   └── -AutoID/
│   │       ├── node_id       : int
│   │       ├── temperature   : float
│   │       ├── humidity      : float
│   │       ├── soil_moisture : int   (percentage 0–100)
│   │       ├── rainfall      : float (mm/hr)
│   │       ├── status        : string (SAFE | WARNING | DANGER)
│   │       └── timestamp     : long  (millis from ESP32)
│   ├── node_2/
│   └── node_3/
│
├── sensor_nodes/
│   ├── node_1/
│   │   ├── name     : "Node 1"
│   │   ├── location : "Lower Slope A"
│   │   ├── lat      : 8.2489
│   │   ├── lng      : 124.7532
│   │   ├── status   : "ACTIVE"
│   │   └── alert    : string (SAFE | WARNING | DANGER)
│   ├── node_2/
│   └── node_3/
│
└── alert_history/
    └── -AutoID/
        ├── node_id       : int
        ├── soil_moisture : int   (percentage)
        ├── rainfall      : float
        ├── status        : string (WARNING | DANGER only)
        └── timestamp     : long  (millis from ESP32)
```

`alert_history` only contains WARNING and DANGER events. SAFE readings are never written here.

---

## Setup Guide

### 1. Firebase

1. Go to [firebase.google.com](https://firebase.google.com) and open the project `landslide-monitoring-da1f4`
2. Go to **Realtime Database → Rules** and set rules for development:
```json
{
  "rules": {
    ".read": true,
    ".write": true
  }
}
```
> Change these to authenticated rules before final deployment.

---

### 2. Sensor Node (Arduino MEGA)

**Install libraries in Arduino IDE:**
- `LoRa` by Sandeep Mistry
- `DHT sensor library` by Adafruit
- `DFRobot_RainfallSensor` by DFRobot

**Before uploading**, open `Sensor_Nodes.ino` and set the correct node ID at the top:
```cpp
#define NODE_ID  1   // change to 2 or 3 for other nodes
```

Upload a separate copy to each Arduino MEGA with the correct ID set.

**What it does:**
- Reads all sensors every second
- Computes landslide risk level locally
- Transmits heartbeat every 5 minutes under normal conditions
- Transmits immediately on any risk above NORMAL

**Serial monitor output when working correctly:**
```
---------------------------
Heartbeat — 5 minute interval reached
Payload Sent   : 1,27.50,85.00,62,12.40,NORMAL
Temperature    : 27.50 C
Humidity       : 85.00 %
Soil Moisture  : 62%
Rain 1 Hour    : 12.40 mm
Landslide Risk : NORMAL
```

---

### 3. Master Node (ESP32)

**Install libraries in Arduino IDE:**
- `LoRa` by Sandeep Mistry
- `ArduinoJson` by Benoit Blanchon

**Before uploading**, open `Master_Node.ino` and update WiFi credentials:
```cpp
const char* ssid     = "YOUR_WIFI_SSID";
const char* password = "YOUR_WIFI_PASSWORD";
```

**What it does:**
- Listens for LoRa packets from any sensor node
- Parses the 6-field CSV payload
- Converts sensor risk level to dashboard status
- Triggers buzzer and SMS on WARNING or DANGER
- POSTs reading to `sensor_readings/node_X` in Firebase
- PATCHes node status in `sensor_nodes/node_X` in Firebase
- POSTs to `alert_history` in Firebase on WARNING or DANGER
- Reconnects WiFi automatically if connection drops

**Serial monitor output when working correctly:**
```
--------------------
Received : 1,27.50,85.00,62,12.40,NORMAL
RSSI     : -45
Node ID  : 1
Temp     : 27.50 C
Humidity : 85.00 %
Soil     : 62%
Rain     : 12.40 mm
Status   : SAFE
Firebase Response : 200
Node status update : HTTP 200
```

---

### 4. Dashboard (Vercel)

The dashboard is a single `index.html` file deployed on Vercel. No backend server needed.

**To deploy:**
1. Push all files to your GitHub repository
2. Go to [vercel.com](https://vercel.com) → New Project → Import your repo
3. Click Deploy — no build settings needed
4. Vercel gives you a live URL automatically

**To access:**
- Open the Vercel URL in any browser
- Login: `admin` / `admin123`

**Dashboard tabs:**
- **Dashboard** — live stat cards, alert banner, temperature/humidity chart, rainfall chart, soil moisture chart, recent readings table with CSV and JSON export
- **Sensor Map** — Leaflet map with color-coded node markers and status popups
- **Alert History** — summary strip (total, danger count, warning count, last event) and full event log table

**Dashboard updates:**
- Stat cards and alert banner update the moment Firebase receives new data
- Charts and table show the last 20 readings for the selected node
- Alert history shows the last 100 WARNING and DANGER events in real time
- Switch between nodes using the dropdown in the top right

---

## For Groupmates — Quick Reference

| Task | File to edit |
|---|---|
| Change node ID | `Sensor_Nodes.ino` → `#define NODE_ID` |
| Change WiFi credentials | `Master_Node.ino` → `ssid` and `password` |
| Change alert thresholds | `Sensor_Nodes.ino` → threshold constants at top of file |
| Change heartbeat interval | `Sensor_Nodes.ino` → `const long INTERVAL = 300000` |
| Change SMS recipient | `Master_Node.ino` → `String phoneNumber` |
| Change dashboard login | `script.js` → `handleLogin()` function |
| Update node locations | Firebase Console → `sensor_nodes/node_X` → `lat` and `lng` |
| View alert history | Dashboard → Alert History tab |
| Export sensor data | Dashboard → Recent Sensor Readings → Export CSV or Export JSON |

---

## Troubleshooting

**Master node keeps crashing (watchdog reset)**
- Confirm `http.setTimeout(5000)` is present in all HTTP calls in `Master_Node.ino`
- Check WiFi signal strength near the master node

**Master node shows "Waiting for sensor packets..." but nothing arrives**
- Confirm both LoRa modules are on the same frequency (`915E6`)
- Confirm both use the same LoRa settings: SF12, BW125, CR5, SyncWord 0x12
- Place sensor node and master node within 1 meter for initial testing
- Check physical wiring matches the pin definitions above

**Dashboard shows "--" for all values**
- Confirm master node serial monitor shows `Firebase Response : 200`
- Check Firebase Console → Realtime Database to confirm data is arriving
- Make sure Firebase rules allow read and write access

**HTTP Response shows 401 (Unauthorized)**
- Firebase API key in `Master_Node.ino` may be incorrect
- Check Firebase Console → Project Settings → Web API Key

**Rain sensor returns abnormal values**
- Values outside 0–300 mm are automatically set to 0 in `Sensor_Nodes.ino`
- Check I2C wiring on SDA/SCL pins
- Confirm `DFRobot_RainfallSensor` library is installed

**SMS not sending**
- Confirm SIM900A pins match `SIM900_RX 16` and `SIM900_TX 17`
- Check that the SIM card has SMS credit and is active
- Watch serial monitor for `SMS Alert Sent` confirmation

---

## License

MIT License. Free to use and modify for academic and research purposes.