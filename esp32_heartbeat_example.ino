// Contoh ESP32 -> Dashboard Smart Farm Cabai
// 1) Heartbeat tiap 30 detik (POST /api/devices/heartbeat) agar kehadiran
//    perangkat tercatat (last_seen) di server.
// 2) Laporan ON/OFF aktuator (POST /api/device-activities/report) setiap
//    relay berubah agar panel "Aktivitas Aktuator" tampil real-time.
// Sesuaikan WIFI_SSID, WIFI_PASS, SERVER_IP, DEVICE_TOKEN, DEVICE_NAME.

#include <WiFi.h>
#include <HTTPClient.h>

const char* WIFI_SSID = "NAMA_WIFI";
const char* WIFI_PASS = "PASSWORD_WIFI";
const char* SERVER_IP = "http://192.168.1.10:8000"; // IP laptop server Laravel
const char* DEVICE_TOKEN = "smartfarm-iot-2026"; // samakan dengan DEVICE_TOKEN di .env Laravel
const char* DEVICE_NAME = "Lampu-UV-01";         // harus sama dengan nama di daftar perangkat
const char* DEVICE_TYPE = "lampu";               // lampu | mist | lain
const char* DEVICE_BEDENGAN = "Bedengan 1";

const int RELAY_PIN = 26; // pin relay aktuator unit ini
bool lastRelayState = false;

int postJson(const String& path, const String& body) {
  HTTPClient http;
  http.begin(String(SERVER_IP) + path);
  http.addHeader("Content-Type", "application/json");
  int code = http.POST(body);
  http.end();
  return code;
}

void sendHeartbeat() {
  String body = String("{\"token\":\"") + DEVICE_TOKEN +
                "\",\"name\":\"" + DEVICE_NAME +
                "\",\"type\":\"" + DEVICE_TYPE +
                "\",\"bedengan\":\"" + DEVICE_BEDENGAN + "\"}";
  Serial.printf("Heartbeat -> HTTP %d\n", postJson("/api/devices/heartbeat", body));
}

// Panggil setiap relay berubah: kirimStatusAktuator("lampu", true) = ON
void kirimStatusAktuator(const char* actuator, bool nyala) {
  String body = String("{\"token\":\"") + DEVICE_TOKEN +
                "\",\"name\":\"" + DEVICE_NAME +
                "\",\"actuator\":\"" + actuator +
                "\",\"status\":\"" + (nyala ? "on" : "off") +
                "\",\"bedengan\":\"" + DEVICE_BEDENGAN + "\"}";
  Serial.printf("Aktuator %s=%s -> HTTP %d\n", actuator, nyala ? "ON" : "OFF",
                postJson("/api/device-activities/report", body));
}

void setup() {
  Serial.begin(115200);
  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, LOW);
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  while (WiFi.status() != WL_CONNECTED) { delay(500); Serial.print("."); }
  Serial.println("\nWiFi terhubung: " + WiFi.localIP().toString());
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) { delay(1000); return; }

  // TODO: ganti dengan logika mode otomatis/manual Anda.
  // Contoh: baca perintah / sensor di sini, lalu atur relay.
  bool relayOn = false; // <-- isi dari logika kontrol Anda

  if (relayOn != lastRelayState) {
    digitalWrite(RELAY_PIN, relayOn ? HIGH : LOW);
    kirimStatusAktuator(DEVICE_TYPE, relayOn); // langsung terlihat di dashboard
    lastRelayState = relayOn;
  }

  sendHeartbeat();
  delay(30000); // heartbeat tiap 30 detik (batas Online: 90 detik)
}
