#include <SPI.h>
#include <LoRa.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

/* ---------------------------
   PIN DEFINITIONS
--------------------------- */
#define NSS  5
#define RST  14
#define DIO0 2

/* ---------------------------
   WIFI CREDENTIALS
   Change before deploying
--------------------------- */
const char* ssid     = "YOUR_WIFI_SSID";
const char* password = "YOUR_WIFI_PASSWORD";

/* ---------------------------
   FIREBASE CONFIG
   Project ID from Firebase Console
--------------------------- */
const char* FIREBASE_HOST    = "landslide-monitoring-da1f4-default-rtdb.asia-southeast1.firebasedatabase.app";
const char* FIREBASE_API_KEY = "AIzaSyAz_06xdYHF1LMugG5Xi48hYuYqnJBv_gc";

/* ---------------------------
   SETUP
--------------------------- */
void setup() {
  Serial.begin(115200);

  /* Connect to WiFi */
  Serial.print("Connecting to WiFi");
  WiFi.begin(ssid, password);

  int retries = 0;
  while (WiFi.status() != WL_CONNECTED && retries < 20) {
    delay(500);
    Serial.print(".");
    retries++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nWiFi Connected");
    Serial.print("IP Address: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("\nWiFi Failed — continuing without connection");
  }

  /* Initialize LoRa */
  LoRa.setPins(NSS, RST, DIO0);
  if (!LoRa.begin(915E6)) {
    Serial.println("LoRa initialization failed");
    while (1);
  }

  Serial.println("---------------------------");
  Serial.println("Master Node Ready");
  Serial.println("Backend : Firebase Realtime DB");
  Serial.println("Waiting for sensor packets...");
  Serial.println("---------------------------");
}

/* ---------------------------
   MAIN LOOP
--------------------------- */
void loop() {

  /* Reconnect WiFi if dropped */
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi lost — reconnecting...");
    WiFi.reconnect();
    delay(3000);
    return;
  }

  /* Listen for incoming LoRa packet */
  int packetSize = LoRa.parsePacket();
  if (packetSize) {
    String data = "";
    while (LoRa.available()) {
      data += (char)LoRa.read();
    }

    Serial.println("---------------------------");
    Serial.println("Packet received : " + data);
    Serial.print("RSSI            : ");
    Serial.println(LoRa.packetRssi());

    parseAndSend(data);
  }
}

/* ---------------------------
   PARSE CSV PACKET
   Format: node_id,temp,hum,soil,rain
--------------------------- */
void parseAndSend(String data) {
  int p1 = data.indexOf(',');
  int p2 = data.indexOf(',', p1 + 1);
  int p3 = data.indexOf(',', p2 + 1);
  int p4 = data.indexOf(',', p3 + 1);

  if (p1 == -1 || p2 == -1 || p3 == -1 || p4 == -1) {
    Serial.println("ERROR: Malformed packet — skipping");
    return;
  }

  int   node_id = data.substring(0, p1).toInt();
  float temp    = data.substring(p1 + 1, p2).toFloat();
  float hum     = data.substring(p2 + 1, p3).toFloat();
  int   soil    = data.substring(p3 + 1, p4).toInt();
  float rain    = data.substring(p4 + 1).toFloat();

  if (node_id < 1 || node_id > 3) {
    Serial.println("ERROR: Invalid node ID — skipping");
    return;
  }

  Serial.println("Node ID     : " + String(node_id));
  Serial.println("Temperature : " + String(temp, 2) + " C");
  Serial.println("Humidity    : " + String(hum, 2)  + " %");
  Serial.println("Soil        : " + String(soil));
  Serial.println("Rainfall    : " + String(rain, 2) + " mm");

  sendToFirebase(node_id, temp, hum, soil, rain);
  updateNodeStatus(node_id, temp, hum, soil, rain);
}

/* ---------------------------
   DETERMINE ALERT LEVEL
--------------------------- */
String getAlertLevel(int soil, float rain) {
  if (soil > 700 && rain > 20) return "DANGER";
  if (soil > 500 && rain > 10) return "WARNING";
  return "NORMAL";
}

/* ---------------------------
   SEND READING TO FIREBASE
   POST to sensor_readings/node_X
   Firebase auto-generates push ID
--------------------------- */
void sendToFirebase(int node, float t, float h, int s, float r) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("ERROR: No WiFi — data not sent");
    return;
  }

  /* Build URL
     POST to sensor_readings/node_X.json creates a new child with auto ID */
  String url = "https://" + String(FIREBASE_HOST) +
               "/sensor_readings/node_" + String(node) +
               ".json?auth=" + String(FIREBASE_API_KEY);

  /* Build JSON payload */
  StaticJsonDocument<256> doc;
  doc["node_id"]      = node;
  doc["temperature"]  = t;
  doc["humidity"]     = h;
  doc["soil_moisture"]= s;
  doc["rainfall"]     = r;
  doc["alert"]        = getAlertLevel(s, r);
  doc["timestamp"]    = millis(); // will replace with NTP time later

  String payload;
  serializeJson(doc, payload);

  HTTPClient http;
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);

  Serial.println("Sending to Firebase...");

  int httpCode = http.POST(payload);

  if (httpCode > 0) {
    Serial.println("HTTP Response : " + String(httpCode));
    Serial.println("Firebase ID   : " + http.getString());
  } else {
    Serial.println("HTTP ERROR    : " + http.errorToString(httpCode));
  }

  http.end();
}

/* ---------------------------
   UPDATE NODE STATUS
   PUT to sensor_nodes/node_X
   Updates latest alert + status
--------------------------- */
void updateNodeStatus(int node, float t, float h, int s, float r) {
  if (WiFi.status() != WL_CONNECTED) return;

  String url = "https://" + String(FIREBASE_HOST) +
               "/sensor_nodes/node_" + String(node) +
               ".json?auth=" + String(FIREBASE_API_KEY);

  StaticJsonDocument<256> doc;
  doc["name"]   = "Node " + String(node);
  doc["status"] = "ACTIVE";
  doc["alert"]  = getAlertLevel(s, r);

  /* Keep existing coordinates if already set —
     only set defaults if node not yet in DB */
  if (node == 1) { doc["lat"] = 8.2489; doc["lng"] = 124.7532; doc["location"] = "Lower Slope A"; }
  if (node == 2) { doc["lat"] = 8.2495; doc["lng"] = 124.7541; doc["location"] = "Lower Slope B"; }
  if (node == 3) { doc["lat"] = 8.2501; doc["lng"] = 124.7550; doc["location"] = "Lower Slope C"; }

  String payload;
  serializeJson(doc, payload);

  HTTPClient http;
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);

  /* Use PATCH so we only update these fields,
     not overwrite the whole node document */
  int httpCode = http.PATCH(payload);

  Serial.println("Node status update : HTTP " + String(httpCode));

  http.end();
}
