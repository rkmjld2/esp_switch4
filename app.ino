/*
  ESP_SWITCH3 - ESP8266 Controller

  Automatic startup and Wi-Fi recovery.
  No manual RESET is required during normal operation.

  Each physical controller is identified by:
      CONTROLLER_ID + DEVICE_TOKEN

  Change these values for each controller.
*/

#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClientSecureBearSSL.h>
#include <UrlEncode.h>

/* ================= WIFI ================= */

const char* WIFI_SSID = "Airtel_56";
const char* WIFI_PASSWORD = "Raviuma5658";

/* ============ CONTROLLER IDENTITY ============ */

const char* CONTROLLER_ID = "ESP0001";

const char* DEVICE_TOKEN =
    "ESP0001-TOKEN-2026-A7K9X2";

/* ================= SERVER ================= */

const char* serverURL =
    "https://esp-switch3.onrender.com/api.php";

/* ================= PINS D1-D8 ================= */

const uint8_t pins[8] = {
  D1, D2, D3, D4,
  D5, D6, D7, D8
};

/* ================= TIMING ================= */

const unsigned long POLL_INTERVAL = 3000UL;
const unsigned long WIFI_RETRY_INTERVAL = 5000UL;

/* Restart only after prolonged Wi-Fi failure */
const unsigned long MAX_WIFI_FAILURE_TIME = 120000UL;

unsigned long lastPoll = 0;
unsigned long lastWiFiAttempt = 0;
unsigned long wifiFailureStarted = 0;

/* ============================================================
   START WIFI
   ============================================================ */

void startWiFi()
{
  Serial.println();
  Serial.print("Connecting to WiFi: ");
  Serial.println(WIFI_SSID);

  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);
  WiFi.persistent(false);

  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
}

/* ============================================================
   AUTOMATIC WIFI RECOVERY
   ============================================================ */

void maintainWiFi()
{
  if (WiFi.status() == WL_CONNECTED)
  {
    if (wifiFailureStarted != 0)
    {
      Serial.println("WiFi connection restored.");
      wifiFailureStarted = 0;
    }

    return;
  }

  unsigned long now = millis();

  if (wifiFailureStarted == 0)
  {
    wifiFailureStarted = now;

    Serial.println();
    Serial.println("WiFi disconnected.");
    Serial.println("Automatic reconnection started...");
  }

  if (now - lastWiFiAttempt >= WIFI_RETRY_INTERVAL)
  {
    lastWiFiAttempt = now;

    Serial.println("Retrying WiFi...");

    WiFi.disconnect();
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  }

  /*
     If Wi-Fi remains unavailable for 2 minutes,
     restart the ESP8266 automatically.
  */
  if (now - wifiFailureStarted >= MAX_WIFI_FAILURE_TIME)
  {
    Serial.println();
    Serial.println("WiFi unavailable for too long.");
    Serial.println("Automatically restarting ESP8266...");

    delay(1000);
    ESP.restart();
  }
}

/* ============================================================
   GET CONTROLLER DATA
   ============================================================ */

void getControllerData()
{
  if (WiFi.status() != WL_CONNECTED)
  {
    Serial.println("Server request skipped: WiFi not connected.");
    return;
  }

  std::unique_ptr<BearSSL::WiFiClientSecure> client(
      new BearSSL::WiFiClientSecure
  );

  /*
     HTTPS connection.
     Certificate verification is disabled in this version.
  */
  client->setInsecure();

  HTTPClient https;

  String url = String(serverURL);

  url += "?action=get";
  url += "&controller_id=";
  url += urlEncode(CONTROLLER_ID);
  url += "&device_token=";
  url += urlEncode(DEVICE_TOKEN);

  Serial.println();
  Serial.println("Requesting server...");
  Serial.println(url);

  if (!https.begin(*client, url))
  {
    Serial.println("HTTPS connection could not be started.");
    return;
  }

  https.setTimeout(15000);

  int httpCode = https.GET();

  Serial.print("HTTP Code: ");
  Serial.println(httpCode);

  if (httpCode > 0)
  {
    String response = https.getString();

    Serial.println("Server response:");
    Serial.println(response);

    if (httpCode == HTTP_CODE_OK)
    {
      int values[8];
      bool allFound = true;

      /* Read D1-D8 from JSON */
      for (int i = 0; i < 8; i++)
      {
        String key = "\"D" + String(i + 1) + "\":";

        int pos = response.indexOf(key);

        if (pos < 0)
        {
          allFound = false;
          break;
        }

        pos += key.length();

        while (
          pos < (int)response.length() &&
          response[pos] == ' '
        )
        {
          pos++;
        }

        if (
          pos >= (int)response.length() ||
          (response[pos] != '0' && response[pos] != '1')
        )
        {
          allFound = false;
          break;
        }

        values[i] = response[pos] - '0';
      }

      if (allFound)
      {
        Serial.println("Pin status updated:");

        for (int i = 0; i < 8; i++)
        {
          digitalWrite(
            pins[i],
            values[i] == 1 ? HIGH : LOW
          );

          Serial.print("D");
          Serial.print(i + 1);
          Serial.print(" = ");
          Serial.println(values[i]);
        }
      }
      else
      {
        Serial.println("ERROR: Could not read all D1-D8 values.");
      }
    }
    else
    {
      Serial.println("Server returned an HTTP error.");
    }
  }
  else
  {
    Serial.print("HTTP request failed: ");
    Serial.println(https.errorToString(httpCode));
  }

  https.end();
}

/* ============================================================
   SETUP
   ============================================================ */

void setup()
{
  Serial.begin(115200);
  delay(300);

  Serial.println();
  Serial.println("==========================================");
  Serial.println("ESP8266 ESP-SWITCH3 CONTROLLER");
  Serial.println("==========================================");

  Serial.print("Controller ID: ");
  Serial.println(CONTROLLER_ID);

  Serial.print("Device Token: ");
  Serial.println(DEVICE_TOKEN);

  /* Configure D1-D8 and start them OFF */
  for (int i = 0; i < 8; i++)
  {
    pinMode(pins[i], OUTPUT);
    digitalWrite(pins[i], LOW);
  }

  /*
     Start Wi-Fi automatically.
     No button or manual RESET is required.
  */
  startWiFi();

  Serial.println("Startup complete.");
}

/* ============================================================
   MAIN LOOP
   ============================================================ */

void loop()
{
  /* Maintain Wi-Fi automatically */
  maintainWiFi();

  unsigned long now = millis();

  /*
     Ask Render/API for the controller's D1-D8
     states every 3 seconds.
  */
  if (
    WiFi.status() == WL_CONNECTED &&
    now - lastPoll >= POLL_INTERVAL
  )
  {
    lastPoll = now;

    getControllerData();
  }

  delay(10);
}
