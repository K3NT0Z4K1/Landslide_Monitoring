#include <SPI.h>
#include <LoRa.h>
#include <DHT.h>

/* ---------------------------
   NODE CONFIGURATION
   Change NODE_ID to 1, 2, or 3
   before uploading to each node
--------------------------- */
#define NODE_ID  1

/* ---------------------------
   PIN DEFINITIONS
--------------------------- */
#define DHTPIN   7
#define DHTTYPE  DHT22

#define SOIL_PIN A0
#define RAIN_PIN 5

#define NSS      10
#define RST      9
#define DIO0     2

/* ---------------------------
   ALERT THRESHOLDS
   Match these with Master Node
   and dashboard values
--------------------------- */
#define SOIL_WARN   500
#define SOIL_DANGER 700
#define RAIN_WARN   10
#define RAIN_DANGER 20

/* ---------------------------
   SEND INTERVAL
   Normal monitoring: 1 minute
   Alert: sends immediately
--------------------------- */
const long INTERVAL = 60000; // 1 minute in milliseconds

/* ---------------------------
   OBJECTS & GLOBALS
--------------------------- */
DHT dht(DHTPIN, DHTTYPE);

volatile int rainCount = 0;
unsigned long lastSend  = 0;

/* ---------------------------
   RAIN GAUGE INTERRUPT
   0.2794mm per tip
--------------------------- */
void rainISR() {
  rainCount++;
}

/* ---------------------------
   SETUP
--------------------------- */
void setup() {
  Serial.begin(9600);

  pinMode(RAIN_PIN, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(RAIN_PIN), rainISR, FALLING);

  dht.begin();

  LoRa.setPins(NSS, RST, DIO0);
  if (!LoRa.begin(915E6)) {
    Serial.println("LoRa initialization failed");
    while (1);
  }

  Serial.println("---------------------------");
  Serial.println("SlopeGuard Sensor Node Ready");
  Serial.println("Node ID  : " + String(NODE_ID));
  Serial.println("Interval : 60s normal / immediate on alert");
  Serial.println("---------------------------");
}

/* ---------------------------
   TRANSMIT PAYLOAD
--------------------------- */
void transmit(float temperature, float humidity, int soil, float rainfall, bool isAlert) {
  /* Format: node_id,temp,humidity,soil,rainfall */
  String payload = String(NODE_ID)         + "," +
                   String(temperature, 2)  + "," +
                   String(humidity, 2)     + "," +
                   String(soil)            + "," +
                   String(rainfall, 2);

  LoRa.beginPacket();
  LoRa.print(payload);
  LoRa.endPacket();

  /* Serial log */
  Serial.println("---------------------------");
  Serial.println(isAlert ? "*** ALERT — Sending immediately ***"
                         : "Routine reading — interval reached");
  Serial.println("Payload      : " + payload);
  Serial.println("Temperature  : " + String(temperature, 2) + " C");
  Serial.println("Humidity     : " + String(humidity, 2)    + " %");
  Serial.println("Soil         : " + String(soil));
  Serial.println("Rainfall     : " + String(rainfall, 2)    + " mm");
  Serial.println("Alert level  : " + String(
    (soil > SOIL_DANGER && rainfall > RAIN_DANGER) ? "HIGH RISK" :
    (soil > SOIL_WARN   && rainfall > RAIN_WARN)   ? "WARNING"   :
                                                      "NORMAL"
  ));

  /* Reset timer */
  lastSend = millis();
}

/* ---------------------------
   MAIN LOOP
   Checks every 1 second
   Sends immediately on alert
   Sends every 60s on normal
--------------------------- */
void loop() {

  /* Read sensors */
  float temperature = dht.readTemperature();
  float humidity    = dht.readHumidity();
  int   soil        = analogRead(SOIL_PIN);
  float rainfall    = rainCount * 0.2794;

  /* Reset rain counter after reading */
  rainCount = 0;

  /* Validate DHT reading */
  if (isnan(temperature) || isnan(humidity)) {
    Serial.println("ERROR: DHT22 read failed — retrying");
    delay(2000);
    return;
  }

  /* Determine if alert condition is active */
  bool isAlert = (soil > SOIL_WARN   && rainfall > RAIN_WARN) ||
                 (soil > SOIL_DANGER && rainfall > RAIN_DANGER);

  /* Determine if normal interval has been reached */
  bool intervalReached = (millis() - lastSend >= INTERVAL);

  /* Send if alert triggered OR interval reached */
  if (isAlert || intervalReached) {
    transmit(temperature, humidity, soil, rainfall, isAlert);
  }

  /* Check every 1 second */
  delay(1000);
}
