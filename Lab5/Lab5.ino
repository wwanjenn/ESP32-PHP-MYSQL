#include <WiFi.h>
#include <HTTPClient.h>
#include <DHT.h>

#define DHTPIN 22             // Pin where the DHT11 is connected
#define DHTTYPE DHT11       // DHT 11

const char WIFI_SSID[] = "1";     // CHANGE IT
const char WIFI_PASSWORD[] = "12345678";  // CHANGE IT

String HOST_NAME = "http://192.168.108.162"; 
String PATH_NAME = "/collect_temp.php";

DHT dht(DHTPIN, DHTTYPE);

void setup() {
  Serial.begin(115200); 
  Serial.println("Starting");
  dht.begin();  // Initialize the DHT sensor

  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.println("Connecting to WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  
  Serial.println("");
  Serial.print("Connected to WiFi network with IP Address: ");
  Serial.println(WiFi.localIP());
}
void loop() {
  if (Serial.available()) {
    char command = Serial.read();
    if (command == 'C' || command == 'c') {
      float temperature = dht.readTemperature();
      float humidity = dht.readHumidity();

      if (isnan(temperature) || isnan(humidity)) {
        Serial.println("Failed to read from DHT sensor!");
        return;
      }

      Serial.print("Temperature: ");
      Serial.print(temperature);
      Serial.print(" °C, Humidity: ");
      Serial.print(humidity);
      Serial.println(" %");

      String queryString = "?temperature=" + String(temperature) + "&humidity=" + String(humidity);

      HTTPClient http;
      http.begin(HOST_NAME + PATH_NAME + queryString); 

      int httpCode = http.GET();

      if (httpCode > 0) {
        if (httpCode == HTTP_CODE_OK) {
          String payload = http.getString();
          Serial.println("Response from server: " + payload);
        } else {
          Serial.printf("[HTTP] GET... code: %d\n", httpCode);
        }
      } else {
        Serial.printf("[HTTP] GET... failed, error: %s\n", http.errorToString(httpCode).c_str());
      }

      http.end();
    }
  }
}
