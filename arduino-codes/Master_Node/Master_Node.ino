
// const char* ssid     = "Fiber@Home2.4Ggs";
// const char* password = "Jesus143";
#include <SPI.h>
#include <LoRa.h>
#include <WiFi.h>
#include <HTTPClient.h>

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
--------------------------- */
#define SIM900_RX 16
#define SIM900_TX 17

HardwareSerial sim900(2);

/* ---------------------------
   WIFI CREDENTIALS
--------------------------- */
const char* ssid     = "Fiber@Home2.4Ggs";
const char* password = "Jesus143";

/* ---------------------------
   SERVER URL
--------------------------- */
const char* serverURL = "https://ics-dev.io/slopeguard/api/receive_data.php";

/* ---------------------------
   SMS SETTINGS
--------------------------- */
String phoneNumber         = "+639278627982";
unsigned long lastSMSAlert = 0;
const unsigned long smsCooldown = 60000;

/* ---------------------------
   BUZZER SETTINGS
--------------------------- */
unsigned long buzzerStartTime = 0;
bool buzzerActive             = false;
const unsigned long buzzerDuration = 10000;

/* ---------------------------
   CONFIRMATION COUNTERS
   One per node (index 0 = unused,
   1 = Node1, 2 = Node2, 3 = Node3)
   Buzzer + SMS only fire after
   CONFIRM_THRESHOLD consecutive
   WARNING/DANGER packets
--------------------------- */
const int CONFIRM_THRESHOLD = 3;
int warningCount[4] = {0, 0, 0, 0};

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
  if (!LoRa.begin(433E6)) {
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
  Serial.println("Backend: PHP Hosting");
  Serial.println("--------------------");
}

/* ---------------------------
   MAIN LOOP
--------------------------- */
void loop() {

  /* ---------------------------
     WIFI RECONNECT
     Only call begin() when fully
     disconnected — not while still
     attempting to connect
  --------------------------- */
  if (WiFi.status() != WL_CONNECTED) {
    if (WiFi.status() == WL_DISCONNECTED) {
      Serial.println("WiFi lost — reconnecting...");
      WiFi.disconnect();
      delay(500);
      WiFi.begin(ssid, password);
      int w = 0;
      while (WiFi.status() != WL_CONNECTED && w < 20) {
        delay(500);
        w++;
      }
      if (WiFi.status() == WL_CONNECTED) {
        Serial.println("WiFi reconnected");
        Serial.print("IP: ");
        Serial.println(WiFi.localIP());
      } else {
        Serial.println("WiFi reconnect failed — will retry next loop");
      }
    } else {
      /* Still connecting from previous attempt — just wait */
      delay(500);
    }
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

  /* Convert sensor risk to dashboard status */
  String dashboardStatus = convertStatus(sensorStatus);

  if (dashboardStatus == "INVALID") {
    Serial.println("ERROR: Corrupted status — " + sensorStatus);
    return;
  }

  /* Clamp node_id to valid range 1–3 */
  if (node_id < 1 || node_id > 3) {
    Serial.println("ERROR: Invalid node ID — " + String(node_id));
    return;
  }

  Serial.println("Node ID : " + String(node_id));
  Serial.println("Temp    : " + String(temp, 2) + " C");
  Serial.println("Humidity: " + String(hum, 2)  + " %");
  Serial.println("Soil    : " + String(soil)     + "%");
  Serial.println("Rain    : " + String(rain, 2)  + " mm");
  Serial.println("Status  : " + dashboardStatus);

  /* ---------------------------
     CONFIRMATION COUNTER LOGIC
     Only trigger buzzer + SMS
     after CONFIRM_THRESHOLD
     consecutive WARNING/DANGER
     packets from the same node
  --------------------------- */
  if (dashboardStatus == "WARNING" || dashboardStatus == "DANGER") {
    warningCount[node_id]++;

    Serial.println("Confirm count [Node " + String(node_id) + "] : "
                   + String(warningCount[node_id])
                   + " / " + String(CONFIRM_THRESHOLD));

    if (warningCount[node_id] >= CONFIRM_THRESHOLD) {
      triggerBuzzer();
      if (millis() - lastSMSAlert >= smsCooldown || lastSMSAlert == 0) {
        sendSMSAlert(node_id, soil, rain, dashboardStatus);
        lastSMSAlert = millis();
      }
    }

  } else {
    /* Reset counter when node goes back to safe */
    if (warningCount[node_id] > 0) {
      Serial.println("Node " + String(node_id) + " back to normal — counter reset");
    }
    warningCount[node_id] = 0;
    digitalWrite(BUZZER_RELAY, LOW);
    buzzerActive = false;
  }

  /* Always send to PHP server regardless of alert state */
  sendToServer(node_id, temp, hum, soil, rain, dashboardStatus, data);
}

/* ---------------------------
   STATUS CONVERSION
--------------------------- */
String convertStatus(String status) {
  status.toUpperCase();
  if (status == "NORMAL")        return "SAFE";
  if (status == "LOW_RISK")      return "CAUTION";
  if (status == "MODERATE_RISK") return "WARNING";
  if (status == "HIGH_RISK")     return "DANGER";
  if (status == "SAFE" || status == "CAUTION" || status == "WARNING" || status == "DANGER") return status;
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
  message += "Node: "          + String(node)   + "\n";
  message += "Status: "        + status          + "\n";
  message += "Soil Moisture: " + String(soil)    + "%\n";
  message += "Rainfall: "      + String(rain, 2) + " mm/hr\n";

  if (status == "DANGER") {
    message += "Risk Level: HIGH. Immediate action required.";
  } else if (status == "WARNING") {
    message += "Risk Level: WARNING. Please monitor the area.";
  } else {
    message += "Risk Level: CAUTION. Stay alert.";
  }

  Serial.println("Sending SMS...");

  sim900.println("AT+CMGF=1");
  delay(1000);
  sim900.print("AT+CMGS=\"");
  sim900.print(phoneNumber);
  sim900.println("\"");
  delay(1000);
  sim900.print(message);
  delay(500);
  sim900.write(26);
  delay(5000);

  Serial.println("SMS Alert Sent");
}

/* ---------------------------
   SEND TO PHP SERVER
   Includes RSSI and raw packet
--------------------------- */
void sendToServer(int node, float t, float h, int s, float r, String status, String rawData) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("ERROR: No WiFi — data not sent");
    return;
  }

  HTTPClient http;
  http.begin(serverURL);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  http.setTimeout(5000);

  String postData = "node_id="      + String(node);
  postData += "&temperature="       + String(t, 2);
  postData += "&humidity="          + String(h, 2);
  postData += "&soil_moisture="     + String(s);
  postData += "&rainfall="          + String(r, 2);
  postData += "&status="            + status;
  postData += "&rssi="              + String(LoRa.packetRssi());
  postData += "&raw_packet="        + rawData;

  Serial.println("Sending to server...");

  int httpCode = http.POST(postData);

  if (httpCode > 0) {
    Serial.println("HTTP Response : " + String(httpCode));
    Serial.println("Server reply  : " + http.getString());
  } else {
    Serial.println("HTTP ERROR    : " + http.errorToString(httpCode));
  }

  http.end();
}