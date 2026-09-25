# Smart Farm MQTT Simulator — Virtual ESP32

**DEV / TEST TOOL ONLY.** Bukan bagian dari production Laravel app
(kode Laravel tidak me-load direktori ini; tidak ada autoload ke `app/`).
Mensimulasikan ESP32 (`SF-ESP32-01`) dengan 2 actuator: `UV_LIGHT` dan `MIST_MAKER`.

> **Simulator ≠ ESP32.** Simulator hanya memvalidasi komunikasi MQTT
> (topic, payload, worker Laravel, ACK, lifecycle command, update database).
> TIDAK membuktikan: tegangan/relay, output fisik UV & mist maker, GPIO,
> stabilitas WiFi ESP32, electrical safety, dan timing aktual hardware.
> Integrasi hardware tetap phase terpisah.

## Dependency

- Node.js ≥ 18 (teruji di Windows, Node v24)
- Satu package lokal (tanpa install global): `mqtt` (MQTT.js)

```bash
cd tools/mqtt-simulator
node "C:\Program Files\nodejs\node_modules\npm\bin\npm-cli.js" install
# atau jika npm bisa langsung:  npm install
```

## Instalasi Mosquitto di Windows (jika belum ada)

1. Download installer dari https://mosquitto.org/download/
   (pilih `mosquitto-X.Y.Z-install-windows-x64.exe`).
2. Install, lalu jalankan broker (default `127.0.0.1:1883`):
   ```powershell
   & 'C:\Program Files\mosquitto\mosquitto.exe' -v
   ```
3. Verifikasi di PowerShell lain:
   ```powershell
   Test-NetConnection 127.0.0.1 -Port 1883
   ```

## Cara menjalankan (4 terminal)

| Terminal | Command |
|---|---|
| 1 — Broker | `& 'C:\Program Files\mosquitto\mosquitto.exe' -v` |
| 2 — Laravel MQTT Worker | `php artisan mqtt:worker` (dari root Laravel) |
| 3 — Laravel server | `php artisan serve` |
| 4 — Simulator | `node tools\mqtt-simulator\mqtt_simulator.js` |

Opsi simulator (argumen atau env):

```
--broker=127.0.0.1   (env MQTT_HOST)
--port=1883          (env MQTT_PORT)
--project=1          (env PROJECT_ID)
--device=SF-ESP32-01 (env DEVICE_ID)
--duration-unit=seconds   # seconds (default, test acceleration) | minutes
```

Contoh:

```bash
node tools/mqtt-simulator/mqtt_simulator.js --broker=127.0.0.1 --port=1883 --project=1 --device=SF-ESP32-01
```

## Test acceleration

Dashboard production mengirim duration dalam **menit** (UV=30, Mist=5).
Simulator default membaca duration sebagai **detik** agar test selesai
dalam hitungan detik. ESP32 asli akan membaca field yang sama sebagai menit.
Set `--duration-unit=minutes` untuk perilaku seperti firmware produksi.

## Alur pesan

Command (Laravel → simulator):

```json
{ "request_id": "uuid", "command": "ON", "duration": 30, "timestamp": "ISO-8601" }
{ "request_id": "uuid", "command": "OFF", "timestamp": "ISO-8601" }
```

Topic: `project/{project}/device/{device}/actuator/{UV_LIGHT|MIST_MAKER}/command`

Status/ACK (simulator → Laravel worker):

```json
{ "request_id": "uuid", "actuator": "UV_LIGHT", "state": "ON", "status": "ACKNOWLEDGED", "timestamp": "ISO-8601" }
{ "request_id": "uuid", "actuator": "UV_LIGHT", "state": "OFF", "status": "SUCCESS", "timestamp": "ISO-8601" }
```

Topic: `project/{project}/device/{device}/actuator/{type}/status`

Device status: `project/{project}/device/{device}/status`
`{ "device_id": "SF-ESP32-01", "status": "ONLINE", ... }` (retained).
Last Will otomatis mengirim `OFFLINE` saat simulator berhenti.

## Self-test tanpa broker

```bash
node tools/mqtt-simulator/mqtt_simulator.js --self-test
```

Menguji: topic builder/parser, validasi payload, lifecycle ON→ACK→SUCCESS,
early-OFF membatalkan timer, unknown actuator / JSON rusak / command
invalid ditangani tanpa crash.

## Manual end-to-end checklist (butuh broker)

1. Jalankan keempat terminal di atas.
2. Buka dashboard, tekan **TURN ON** pada UV → cek log simulator
   (`Command received` → `State: ON` → `ACK sent`).
3. Cek DB: `actuator_commands` status bergerak PENDING → SENT → ACKNOWLEDGED.
4. Tunggu duration (detik) → simulator kirim SUCCESS, state OFF;
   DB: command SUCCESS, `actuator.state` OFF.
5. Ulangi untuk MIST_MAKER.
6. Early-OFF: ON (duration 10) → 2 detik → OFF → timer lama tidak boleh fire.
7. Broker OFF: dashboard tetap terbuka, manual input tetap tersimpan,
   command actuator tampil error tanpa crash.
