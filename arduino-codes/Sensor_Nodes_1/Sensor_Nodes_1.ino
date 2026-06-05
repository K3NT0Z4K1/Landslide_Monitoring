#include <SPI.h>
#include <LoRa.h>
#include <DHT.h>
#include <Wire.h>
#include "DFRobot_RainfallSensor.h"

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
#define NSS      10
#define RST      9
#define DIO0     2

/* ---------------------------
   LORA SETTINGS
   Must match Master Node
--------------------------- */
#define LORA_FREQ 433E6

/* ---------------------------
   SOIL CALIBRATION
--------------------------- */
const int AirValue   = 630;
const int WaterValue = 155;

/* ---------------------------
   THRESHOLDS
--------------------------- */
const int   SOIL_CAUTION = 55;
const int   SOIL_WARNING = 70;
const int   SOIL_DANGER  = 80;

const float RAIN_CAUTION = 7.5;
const float RAIN_WARNING = 15.0;
const float RAIN_DANGER  = 30.0;


/* ---------------------------
   TRANSMISSION INTERVAL
--------------------------- */
const long INTERVAL = 300000; // 5 minutes

/* ---------------------------
   OBJECTS & GLOBALS
--------------------------- */
DHT dht(DHTPIN, DHTTYPE);
DFRobot_RainfallSensor_I2C RainSensor(&Wire);

unsigned long lastSend = 0;

/* ---------------------------
   SOIL HELPERS
--------------------------- */
int readSoilAverage() {
  long total = 0;
  const int samples = 10;
  for (int i = 0; i < samples; i++) {
    total += analogRead(SOIL_PIN);
    delay(20);
  }
  return total / samples;
}

int getSoilPercent(int soilRaw) {
  int percent = map(soilRaw, AirValue, WaterValue, 0, 100);
  return constrain(percent, 0, 100);
}

String getSoilStatus(int soilPercent) {
  if (soilPercent >= SOIL_DANGER)  return "SATURATED";
  if (soilPercent >= SOIL_WARNING) return "VERY_WET";
  if (soilPercent >= SOIL_CAUTION) return "WET";
  return "NORMAL";
}

/* ---------------------------
   RAIN HELPER
--------------------------- */
String getRainStatus(float rain1Hour) {
  if (rain1Hour >= RAIN_DANGER)  return "DANGER";
  if (rain1Hour >= RAIN_WARNING) return "WARNING";
  if (rain1Hour >= RAIN_CAUTION) return "CAUTION";
  return "NORMAL";
}

/* ---------------------------
   LANDSLIDE RISK LOGIC
--------------------------- */
String getLandslideRisk(int soilPercent, float rain1Hour) {
  if (soilPercent >= SOIL_DANGER  && rain1Hour >= RAIN_DANGER)  return "HIGH_RISK";
  if (soilPercent >= SOIL_DANGER  && rain1Hour >= RAIN_WARNING) return "HIGH_RISK";
  if (soilPercent >= SOIL_WARNING && rain1Hour >= RAIN_WARNING) return "MODERATE_RISK";
  if (soilPercent >= SOIL_DANGER)                               return "MODERATE_RISK";
  if (rain1Hour   >= RAIN_DANGER)                               return "MODERATE_RISK";
  if (soilPercent >= SOIL_CAUTION && rain1Hour >= RAIN_CAUTION) return "LOW_RISK";
  return "NORMAL";
}

/* ---------------------------
   TRANSMIT
--------------------------- */
void transmit(float temperature, float humidity, int soilPercent,
              float rain1Hour, String landslideRisk,
              String soilStatus, String rainStatus, bool isAlert) {

  String payload = String(NODE_ID)          + "," +
                   String(temperature, 2)   + "," +
                   String(humidity, 2)      + "," +
                   String(soilPercent)      + "," +
                   String(rain1Hour, 2)     + "," +
                   landslideRisk;

  LoRa.beginPacket();
  LoRa.print(payload);
  LoRa.endPacket();

  Serial.println("---------------------------");
  Serial.println(isAlert ? "*** ALERT — Sending immediately ***"
                         : "Heartbeat — interval reached");
  Serial.println("Payload Sent   : " + payload);
  Serial.println("Temperature    : " + String(temperature, 2) + " C");
  Serial.println("Humidity       : " + String(humidity, 2)    + " %");
  Serial.println("Soil Moisture  : " + String(soilPercent)    + "%");
  Serial.println("Soil Status    : " + soilStatus);
  Serial.println("Rain 1 Hour    : " + String(rain1Hour, 2)   + " mm");
  Serial.println("Rain Status    : " + rainStatus);
  Serial.println("Landslide Risk : " + landslideRisk);

  lastSend = millis();
}

/* ---------------------------
   SETUP
--------------------------- */
void setup() {
  Serial.begin(9600);
  Wire.begin();

  dht.begin();

  Serial.println("Initializing rain sensor...");
  while (!RainSensor.begin()) {
    Serial.println("Rain sensor init error!");
    delay(1000);
  }
  Serial.println("Rain sensor ready!");

  LoRa.setPins(NSS, RST, DIO0);
  if (!LoRa.begin(LORA_FREQ)) {
    Serial.println("LoRa initialization failed!");
    while (1);
  }

  LoRa.setSpreadingFactor(12);
  LoRa.setSignalBandwidth(125E3);
  LoRa.setCodingRate4(5);
  LoRa.setSyncWord(0x12);

 /* NODE 1 — no stagger delay, transmits first */
  randomSeed(analogRead(A1));
  long jitter = random(0, 3000);
  delay(jitter);
  lastSend = millis();

  Serial.println("---------------------------");
  Serial.println("SlopeGuard Sensor Node Ready");
  Serial.println("Node ID  : " + String(NODE_ID));
  Serial.println("Interval : 5min heartbeat / immediate on alert");
  Serial.println("---------------------------");
}

/* ---------------------------
   MAIN LOOP
--------------------------- */
void loop() {

  float temperature = dht.readTemperature();
  float humidity    = dht.readHumidity();

  if (isnan(temperature) || isnan(humidity)) {
    Serial.println("ERROR: DHT22 failed — retrying");
    delay(2000);
    return;
  }

  int soilRaw     = readSoilAverage();
  int soilPercent = getSoilPercent(soilRaw);

  float rain1Hour = RainSensor.getRainfall(1);

  if (rain1Hour < 0 || rain1Hour > 300) {
    Serial.println("ERROR: Abnormal rain sensor reading — defaulting to 0");
    rain1Hour = 0;
  }

  String soilStatus    = getSoilStatus(soilPercent);
  String rainStatus    = getRainStatus(rain1Hour);
  String landslideRisk = getLandslideRisk(soilPercent, rain1Hour);

  bool isAlert         = (landslideRisk != "NORMAL");
  bool intervalReached = (millis() - lastSend >= INTERVAL);

  if (isAlert || intervalReached) {
    transmit(temperature, humidity, soilPercent,
             rain1Hour, landslideRisk,
             soilStatus, rainStatus, isAlert);
  }

  delay(1000);
}