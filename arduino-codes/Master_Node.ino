#include <SPI.h>
#include <LoRa.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

/* ---------------------------
   LORA PINS
--------------------------- */
#define NSS  5
#define RST  14
#define DIO0 2

/* ---------------------------
   BUZZER RELAY PIN
--------------------------- */
#define BUZZER_RELAY 33

/* ---------------------------
   SIM900A PINS
   ESP32 TX2 = GPIO17 → SIM900A RX
   ESP32 RX2 = GPIO16 → SIM900A TX
--------------------------- */
#define SIM900_RX 16
#define SIM900_TX 17

HardwareSerial sim900(2);

/* ---------------------------
   WIFI CREDENTIALS
   Change before deploying
--------------------------- */
const char* ssid     = "YOUR_WIFI_SSID";
const char* password = "YOUR_WIFI_PASSWORD";

/* ---------------------------
   FIREBASE CONFIG
--------------------------- */
const char* FIREBASE_HOST    = "landslide-monitoring-da1f4-default-rtdb.asia-southeast1.firebasedatabase.app";
const char* FIREBASE_API_KEY = "AIzaSyAz_06xdYHF1LMugG5Xi48hYuYqnJBv_gc";

/* ---------------------------
   SMS SETTINGS
--------------------------- */
String phoneNumber   = "+639278627982";
unsigned long lastSMSAlert = 0;
const unsigned long smsCooldown = 60000; // 1 minute cooldown

/* ---------------------------
   BUZZER SETTINGS
--------------------------- */
unsigned long buzzerStartTime = 0;
bool buzzerActive = false;
const unsigned long buzzerDuration = 10000; // 10 seconds

/* ---------------------------
   SETUP
--------------------------- */
void setup() {
  Serial.begin(115200);
  delay(1000);

  pinMode(BUZZER_RELAY, OUTPUT);
  digitalWrite(BUZZER_RELAY, LOW);

  /* SIM900A */
  sim900.begin(9600, SERIAL_8N1, SIM900_RX, SIM900_TX);
  delay(2000);
  Serial.println("Initializing SIM900A...");
  sim900.println("AT");
  delay(1000);
  sim900.println("AT+CMGF=1");
  delay(1000);
  Serial.println("SIM900A Ready");

  /* WiFi */
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

  /* LoRa */
  LoRa.setPins(NSS, RST, DIO0);
  if (!LoRa.begin(915E6)) {
    Serial.println("LoRa Failed!");
    while (1);
  }

  LoRa.setSpreadingFactor(12);
  LoRa.setSignalBandwidth(125E3);
  LoRa.setCodingRate4(5);
  LoRa.setSyncWord(0x12);
  LoRa.enableCrc();

  Serial.println("--------------------");
  Serial.println("SlopeGuard Master Node Ready");
  Serial.println("Backend: Firebase Realtime DB");
  Serial.println("--------------------");
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
  }

  int packetSize = LoRa.parsePacket();

  if (packetSize) {
    String data = "";
    while (LoRa.available()) {
      data += (char)LoRa.read();
    }
    data.trim();

    Serial.println("--------------------");
    Serial.println("Received : " + data);
    Serial.print("RSSI     : ");
    Serial.println(LoRa.packetRssi());

    parseAndSend(data);
  }

  handleBuzzer();
}

/* ---------------------------
   PARSE CSV PACKET
   Format: node,temp,hum,soil%,rain,landslideRisk
--------------------------- */
void parseAndSend(String data) {
  int p1 = data.indexOf(',');
  int p2 = data.indexOf(',', p1 + 1);
  int p3 = data.indexOf(',', p2 + 1);
  int p4 = data.indexOf(',', p3 + 1);
  int p5 = data.indexOf(',', p4 + 1);

  if (p1 == -1 || p2 == -1 || p3 == -1 || p4 == -1 || p5 == -1) {
    Serial.println("ERROR: Invalid packet format");
    return;
  }

  int    node_id      = data.substring(0, p1).toInt();
  float  temp         = data.substring(p1 + 1, p2).toFloat();
  float  hum          = data.substring(p2 + 1, p3).toFloat();
  int    soil         = data.substring(p3 + 1, p4).toInt();
  float  rain         = data.substring(p4 + 1, p5).toFloat();
  String sensorStatus = data.substring(p5 + 1);
  sensorStatus.trim();

  /* Convert sensor risk level to dashboard status */
  String dashboardStatus = convertStatus(sensorStatus);

  if (dashboardStatus == "INVALID") {
    Serial.println("ERROR: Corrupted status — " + sensorStatus);
    return;
  }

  Serial.println("Node ID : " + String(node_id));
  Serial.println("Temp    : " + String(temp, 2) + " C");
  Serial.println("Humidity: " + String(hum, 2)  + " %");
  Serial.println("Soil    : " + String(soil)     + "%");
  Serial.println("Rain    : " + String(rain, 2)  + " mm");
  Serial.println("Status  : " + dashboardStatus);

  /* Buzzer + SMS on WARNING or DANGER */
  if (dashboardStatus == "WARNING" || dashboardStatus == "DANGER") {
    triggerBuzzer();

    if (millis() - lastSMSAlert >= smsCooldown || lastSMSAlert == 0) {
      sendSMSAlert(node_id, soil, rain, dashboardStatus);
      lastSMSAlert = millis();
    }
  } else {
    digitalWrite(BUZZER_RELAY, LOW);
    buzzerActive = false;
  }

  /* Send to Firebase */
  sendToFirebase(node_id, temp, hum, soil, rain, dashboardStatus);
  updateNodeStatus(node_id, soil, rain, dashboardStatus);

  /* Log to alert_history on WARNING or DANGER only */
  if (dashboardStatus == "WARNING" || dashboardStatus == "DANGER") {
    logAlertHistory(node_id, soil, rain, dashboardStatus);
  }
}

/* ---------------------------
   STATUS CONVERSION
   Sensor node risk → dashboard status
--------------------------- */
String convertStatus(String status) {
  status.toUpperCase();
  if (status == "NORMAL")        return "SAFE";
  if (status == "LOW_RISK")      return "WARNING";
  if (status == "MODERATE_RISK") return "WARNING";
  if (status == "HIGH_RISK")     return "DANGER";
  if (status == "SAFE" || status == "WARNING" || status == "DANGER") return status;
  return "INVALID";
}

/* ---------------------------
   BUZZER
--------------------------- */
void triggerBuzzer() {
  digitalWrite(BUZZER_RELAY, HIGH);
  buzzerStartTime = millis();
  buzzerActive    = true;
  Serial.println("BUZZER ALARM ON");
}

void handleBuzzer() {
  if (buzzerActive && millis() - buzzerStartTime >= buzzerDuration) {
    digitalWrite(BUZZER_RELAY, LOW);
    buzzerActive = false;
    Serial.println("BUZZER ALARM OFF");
  }
}

/* ---------------------------
   SMS ALERT
--------------------------- */
void sendSMSAlert(int node, int soil, float rain, String status) {
  String message  = "SLOPEGUARD ALERT!\n";
  message += "Node: "          + String(node)      + "\n";
  message += "Status: "        + status             + "\n";
  message += "Soil Moisture: " + String(soil)       + "%\n";
  message += "Rainfall: "      + String(rain, 2)    + " mm/hr\n";

  if (status == "DANGER") {
    message += "Risk Level: HIGH. Immediate action required.";
  } else {
    message += "Risk Level: WARNING. Please monitor the area.";
  }

  Serial.println("Sending SMS...");
  Serial.println(message);

  sim900.println("AT+CMGF=1");
  delay(1000);
  sim900.print("AT+CMGS=\"");
  sim900.print(phoneNumber);
  sim900.println("\"");
  delay(1000);
  sim900.print(message);
  delay(500);
  sim900.write(26); // CTRL+Z
  delay(5000);

  Serial.println("SMS Alert Sent");
}

/* ---------------------------
   SEND READING TO FIREBASE
   POST to sensor_readings/node_X
--------------------------- */
void sendToFirebase(int node, float t, float h, int s, float r, String status) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("ERROR: No WiFi — data not sent");
    return;
  }

  String url = "https://" + String(FIREBASE_HOST) +
               "/sensor_readings/node_" + String(node) +
               ".json?auth=" + String(FIREBASE_API_KEY);

  StaticJsonDocument<256> doc;
  doc["node_id"]       = node;
  doc["temperature"]   = t;
  doc["humidity"]      = h;
  doc["soil_moisture"] = s;
  doc["rainfall"]      = r;
  doc["status"]        = status;
  doc["timestamp"]     = millis();

  String payload;
  serializeJson(doc, payload);

  HTTPClient http;
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);

  int httpCode = http.POST(payload);

  Serial.println("Firebase Response : " + String(httpCode));
  if (httpCode > 0) {
    Serial.println("Firebase ID       : " + http.getString());
  } else {
    Serial.println("Firebase ERROR    : " + http.errorToString(httpCode));
  }

  http.end();
}

/* ---------------------------
   UPDATE NODE STATUS
   PATCH sensor_nodes/node_X
--------------------------- */
void updateNodeStatus(int node, int soil, float rain, String status) {
  if (WiFi.status() != WL_CONNECTED) return;

  String url = "https://" + String(FIREBASE_HOST) +
               "/sensor_nodes/node_" + String(node) +
               ".json?auth=" + String(FIREBASE_API_KEY);

  StaticJsonDocument<256> doc;
  doc["name"]   = "Node " + String(node);
  doc["status"] = "ACTIVE";
  doc["alert"]  = status;

  if (node == 1) { doc["lat"] = 8.2489; doc["lng"] = 124.7532; doc["location"] = "Lower Slope A"; }
  if (node == 2) { doc["lat"] = 8.2495; doc["lng"] = 124.7541; doc["location"] = "Lower Slope B"; }
  if (node == 3) { doc["lat"] = 8.2501; doc["lng"] = 124.7550; doc["location"] = "Lower Slope C"; }

  String payload;
  serializeJson(doc, payload);

  HTTPClient http;
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);

  int httpCode = http.PATCH(payload);
  Serial.println("Node status update : HTTP " + String(httpCode));

  http.end();
}

/* ---------------------------
   LOG ALERT HISTORY
   POST to alert_history/
   Only called on WARNING or DANGER
--------------------------- */
void logAlertHistory(int node, int soil, float rain, String status) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("ERROR: No WiFi — alert history not logged");
    return;
  }

  String url = "https://" + String(FIREBASE_HOST) +
               "/alert_history.json?auth=" + String(FIREBASE_API_KEY);

  StaticJsonDocument<192> doc;
  doc["node_id"]       = node;
  doc["soil_moisture"] = soil;
  doc["rainfall"]      = rain;
  doc["status"]        = status;
  doc["timestamp"]     = millis();

  String payload;
  serializeJson(doc, payload);

  HTTPClient http;
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);

  int httpCode = http.POST(payload);

  Serial.println("Alert history log : HTTP " + String(httpCode));
  if (httpCode > 0) {
    Serial.println("Alert history ID  : " + http.getString());
  } else {
    Serial.println("Alert history ERR : " + http.errorToString(httpCode));
  }

  http.end();
}
