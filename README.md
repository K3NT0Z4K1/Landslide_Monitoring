# SlopeGuard — Advanced Landslide Early Warning System

A real-time landslide monitoring and early warning system using IoT sensor nodes, LoRa wireless communication, Firebase Realtime Database, and a live web dashboard deployed on Vercel.

---

## How the System Works

```
Sensor Node (Arduino MEGA)
  → reads temperature, humidity, soil moisture, rainfall every 1 minute
  → if alert conditions are met, sends immediately regardless of interval
  → transmits data wirelessly via LoRa radio
        ↓
Master Node (ESP32)
  → receives LoRa packet from sensor node
  → determines alert level (Normal / Warning / High Risk)
  → sends data to Firebase Realtime Database over WiFi
        ↓
Firebase Realtime Database (Cloud)
  → stores all sensor readings
  → stores node status and alert levels
        ↓
Dashboard (Vercel — index.html)
  → anyone with the link can log in
  → shows live sensor data, charts, map, and alert banner
  → updates automatically the moment new data arrives
```

---

## Alert Logic

Risk level is determined by combining soil moisture (ADC reading) and rainfall (mm):

| Level | Condition | Behavior |
|---|---|---|
| Normal | Soil ≤ 500 and Rain ≤ 10 mm | Sends every 1 minute |
| Warning | Soil > 500 and Rain > 10 mm | Sends immediately |
| High Risk | Soil > 700 and Rain > 20 mm | Sends immediately |

When Warning or High Risk is triggered on the sensor node, it bypasses the 1 minute interval and transmits right away. The dashboard alert banner updates within seconds.

---

## Hardware

### Sensor Nodes (×2 or ×3) — Arduino MEGA
| Component | Purpose |
|---|---|
| DHT22 | Temperature and humidity |
| Soil Moisture Sensor | Soil saturation (ADC reading on A0) |
| Rain Gauge | Rainfall in mm (tipping bucket on pin 5) |
| GPS Module | Node location coordinates |
| LoRa02 (915MHz) | Wireless data transmission to master |

### Master Node (×1) — ESP32
| Component | Purpose |
|---|---|
| LoRa02 (915MHz) | Receives packets from sensor nodes |
| WiFi (built-in) | Sends data to Firebase over internet |
| SIM900A | GSM module for SMS alerts (future) |
| Buzzer + Relay | Local audio alert (future) |

---

## Pin Configuration

### Sensor Node (Arduino MEGA)
| Pin | Component |
|---|---|
| 7 | DHT22 data |
| A0 | Soil moisture sensor |
| 5 | Rain gauge interrupt |
| 10 | LoRa NSS |
| 9 | LoRa RST |
| 2 | LoRa DIO0 |

### Master Node (ESP32)
| Pin | Component |
|---|---|
| 5 | LoRa NSS |
| 14 | LoRa RST |
| 2 | LoRa DIO0 |

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
├── index.html              ← Full dashboard (login + dashboard + map)
├── vercel.json             ← Vercel deployment config
└── README.md               ← This file
```

---

## Firebase Database Structure

```
Firebase Realtime Database/
├── sensor_readings/
│   ├── node_1/
│   │   └── -AutoID/
│   │       ├── node_id: 1
│   │       ├── temperature: 27.5
│   │       ├── humidity: 85
│   │       ├── soil_moisture: 620
│   │       ├── rainfall: 12.4
│   │       ├── alert: "NORMAL"
│   │       └── timestamp: 1748000000000
│   ├── node_2/
│   └── node_3/
│
└── sensor_nodes/
    ├── node_1/
    │   ├── name: "Node 1"
    │   ├── location: "Lower Slope A"
    │   ├── lat: 8.2489
    │   ├── lng: 124.7532
    │   ├── status: "ACTIVE"
    │   └── alert: "NORMAL"
    ├── node_2/
    └── node_3/
```

---

## Setup Guide

### 1. Firebase

1. Go to [firebase.google.com](https://firebase.google.com) and open the project `landslide-monitoring-da1f4`
2. Go to **Realtime Database → Rules** and make sure rules allow read/write during development:
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

**Install libraries** in Arduino IDE:
- `LoRa` by Sandeep Mistry
- `DHT sensor library` by Adafruit

**Before uploading**, open `Sensor_Nodes.ino` and set the correct node ID at the top:
```cpp
#define NODE_ID  1   // Node 1
#define NODE_ID  2   // Node 2
#define NODE_ID  3   // Node 3
```
Upload a separate copy to each Arduino MEGA with the correct ID.

**What it does:**
- Reads sensors every loop (every ~1 second check)
- Sends a LoRa packet every 1 minute under normal conditions
- Sends a LoRa packet immediately if soil > 500 and rain > 10 (Warning)
- Sends a LoRa packet immediately if soil > 700 and rain > 20 (High Risk)

---

### 3. Master Node (ESP32)

**Install libraries** in Arduino IDE:
- `LoRa` by Sandeep Mistry
- `ArduinoJson` by Benoit Blanchon

**Before uploading**, open `Master_Node.ino` and update WiFi credentials:
```cpp
const char* ssid     = "YOUR_WIFI_SSID";
const char* password = "YOUR_WIFI_PASSWORD";
```

**What it does:**
- Listens for LoRa packets from any sensor node
- Parses the CSV payload (node_id, temp, humidity, soil, rain)
- Computes alert level
- POSTs a new reading to `sensor_readings/node_X` in Firebase
- PATCHes node status in `sensor_nodes/node_X` in Firebase
- Reconnects WiFi automatically if connection drops

**Serial monitor output when working correctly:**
```
---------------------------
Packet received : 1,27.50,85.00,620,12.40
RSSI            : -45
Node ID     : 1
Temperature : 27.50 C
Humidity    : 85.00 %
Soil        : 620
Rainfall    : 12.40 mm
Sending to Firebase...
HTTP Response : 200
Firebase ID   : {"name":"-ABC123xyz"}
Node status update : HTTP 200
```

---

### 4. Dashboard (Vercel)

The dashboard is a single `index.html` file deployed on Vercel. No backend server needed.

**To deploy:**
1. Push `index.html` and `vercel.json` to your GitHub repository
2. Go to [vercel.com](https://vercel.com) → New Project → Import your repo
3. Click Deploy — no build settings needed
4. Vercel gives you a live URL automatically

**To access:**
- Open the Vercel URL in any browser
- Login: `admin` / `admin123`
- Dashboard tab shows live sensor cards, charts, and recent readings table
- Map tab shows node locations with color-coded alert markers

**Dashboard updates:**
- Stat cards and alert banner update the moment Firebase receives new data
- Charts and table update with the last 20 readings per node
- Switch between nodes using the dropdown in the top right

---

## For Groupmates — Quick Reference

| Task | File to edit |
|---|---|
| Change node ID | `Sensor_Nodes.ino` → `#define NODE_ID` |
| Change WiFi credentials | `Master_Node.ino` → `ssid` and `password` |
| Change alert thresholds | `Sensor_Nodes.ino` and `Master_Node.ino` → threshold constants |
| Change send interval | `Sensor_Nodes.ino` → `const long INTERVAL = 60000` |
| Change dashboard login | `index.html` → `handleLogin()` function |
| Update node locations | Firebase Console → `sensor_nodes/node_X` → `lat` and `lng` |

---

## Troubleshooting

**Master node keeps crashing (watchdog reset)**
- Make sure `http.setTimeout(5000)` is in `Master_Node.ino`
- Check WiFi signal strength near the master node

**Master node shows "Waiting for sensor packets..." but nothing arrives**
- Confirm both LoRa modules are on the same frequency (`915E6`)
- Place sensor node and master node within 1 meter for initial testing
- Check physical wiring matches the pin definitions above

**Dashboard shows "--" for all values**
- Confirm master node serial monitor shows `HTTP Response : 200`
- Check Firebase Console → Realtime Database to see if data is arriving
- Make sure Firebase rules allow read access

**HTTP Response shows 401 (Unauthorized)**
- Firebase API key in `Master_Node.ino` may be incorrect
- Check Firebase Console → Project Settings → Web API Key

---

## License

MIT License. Free to use and modify for academic and research purposes.
