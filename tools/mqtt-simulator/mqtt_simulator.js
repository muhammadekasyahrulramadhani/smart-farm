/**
 * Smart Farm MQTT Simulator — Virtual ESP32 (UV Light & Mist Maker)
 * =================================================================
 * DEV / TEST TOOL ONLY. This file is NOT part of the production Laravel
 * application (it lives under tools/, outside app/). It behaves like the
 * ESP32 firmware so the full MQTT loop can be tested without hardware:
 *
 *   Laravel ActuatorCommandPublisher -> Mosquitto -> [simulator]
 *   [simulator] -> ACK/STATUS -> Mosquitto -> Laravel MQTT Worker
 *                  -> ActuatorStatusHandler -> SQLite -> Dashboard
 *
 * TEST ACCELERATION: the dashboard sends duration in MINUTES (UV=30,
 * Mist=5). The simulator interprets duration as SECONDS by default
 * (--duration-unit=seconds) so tests finish in seconds, not minutes.
 * A real ESP32 would interpret the same field as minutes.
 *
 * Usage:
 *   node mqtt_simulator.js [--broker 127.0.0.1] [--port 1883]
 *                          [--project 1] [--device SF-ESP32-01]
 *   node mqtt_simulator.js --self-test   (no broker needed)
 */

'use strict';

const KNOWN_ACTUATORS = ['UV_LIGHT', 'MIST_MAKER'];
const FIRMWARE_VERSION = '2.0.0-sim';

const config = {
    broker: process.env.MQTT_HOST || '127.0.0.1',
    port: parseInt(process.env.MQTT_PORT || '1883', 10),
    username: process.env.MQTT_USERNAME || undefined,
    password: process.env.MQTT_PASSWORD || undefined,
    projectId: process.env.PROJECT_ID || '1',
    deviceId: process.env.DEVICE_ID || 'SF-ESP32-01',
    durationUnit: process.env.SIM_DURATION_UNIT || 'seconds', // seconds|minutes
    selfTest: process.argv.includes('--self-test'),
};

for (const arg of process.argv.slice(2)) {
    const [k, v] = arg.split('=');
    if (k === '--broker') config.broker = v;
    if (k === '--port') config.port = parseInt(v, 10);
    if (k === '--project') config.projectId = v;
    if (k === '--device') config.deviceId = v;
    if (k === '--duration-unit') config.durationUnit = v;
}

// ---------------------------------------------------------------------------
// Pure logic (no MQTT dependency — unit-testable via --self-test)
// ---------------------------------------------------------------------------

function commandTopic(projectId, deviceId, actuator = '+') {
    return `project/${projectId}/device/${deviceId}/actuator/${actuator}/command`;
}

function statusTopic(projectId, deviceId, actuator) {
    return `project/${projectId}/device/${deviceId}/actuator/${actuator}/status`;
}

function deviceStatusTopic(projectId, deviceId) {
    return `project/${projectId}/device/${deviceId}/status`;
}

/** Parse .../actuator/{type}/command -> { projectId, deviceId, actuator } | null */
function parseCommandTopic(topic) {
    const seg = String(topic).split('/');
    if (seg.length !== 7 || seg[0] !== 'project' || seg[2] !== 'device' ||
        seg[4] !== 'actuator' || seg[6] !== 'command') {
        return null;
    }
    return { projectId: seg[1], deviceId: seg[3], actuator: seg[5] };
}

/**
 * Validate dashboard command payload.
 * Accepts: { request_id, command: ON|OFF, duration?, timestamp? }
 */
function validateCommand(obj) {
    if (!obj || typeof obj !== 'object' || Array.isArray(obj)) {
        return { ok: false, error: 'payload is not a JSON object' };
    }
    if (!obj.request_id || typeof obj.request_id !== 'string') {
        return { ok: false, error: 'missing/invalid request_id' };
    }
    const command = String(obj.command || '').toUpperCase();
    if (command !== 'ON' && command !== 'OFF') {
        return { ok: false, error: `invalid command '${obj.command}' (must be ON or OFF)`, request_id: obj.request_id };
    }
    let duration = null;
    if (command === 'ON') {
        duration = Number(obj.duration);
        if (!Number.isFinite(duration) || duration <= 0) {
            return { ok: false, error: 'ON requires duration > 0', request_id: obj.request_id };
        }
    }
    return { ok: true, request_id: obj.request_id, command, duration };
}

function stamp() {
    return new Date().toTimeString().slice(0, 8);
}

function log(...args) {
    console.log(`[${stamp()}]`, ...args);
}

class VirtualActuator {
    constructor(type, publish) {
        this.type = type;
        this.state = 'OFF';
        this.timer = null;
        this.activeRequestId = null;
        this.publish = publish; // (payloadObj) => void
    }

    toSeconds(duration) {
        return config.durationUnit === 'minutes' ? duration * 60 : duration;
    }

    clearTimer() {
        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
            this.activeRequestId = null;
        }
    }

    applyOn(requestId, duration) {
        this.clearTimer(); // new ON supersedes any previous timer
        this.state = 'ON';
        const seconds = this.toSeconds(duration);
        this.activeRequestId = requestId;
        log(`State: ON (${this.type}, auto-off in ${seconds}s [${duration} ${config.durationUnit}])`);
        this.publish({ request_id: requestId, actuator: this.type, state: 'ON', status: 'ACKNOWLEDGED', timestamp: new Date().toISOString() });
        log('ACK sent');
        this.timer = setTimeout(() => {
            this.timer = null;
            this.state = 'OFF';
            log('Duration completed');
            log('State: OFF');
            this.publish({ request_id: requestId, actuator: this.type, state: 'OFF', status: 'SUCCESS', timestamp: new Date().toISOString() });
            log('SUCCESS sent');
            this.activeRequestId = null;
        }, seconds * 1000);
        // Allow process to exit in self-test even with pending timers.
        if (this.timer.unref) this.timer.unref();
    }

    applyOff(requestId) {
        const hadTimer = !!this.timer;
        this.clearTimer(); // cancel stale ON timer so it can never re-fire
        this.state = 'OFF';
        log(`State: OFF (${hadTimer ? 'active timer cancelled' : 'no active timer'})`);
        this.publish({ request_id: requestId, actuator: this.type, state: 'OFF', status: 'SUCCESS', timestamp: new Date().toISOString() });
        log('SUCCESS sent');
    }
}

class VirtualDevice {
    constructor(projectId, deviceId, publish) {
        this.projectId = String(projectId);
        this.deviceId = deviceId;
        this.actuators = {};
        for (const type of KNOWN_ACTUATORS) {
            this.actuators[type] = new VirtualActuator(type, (payload) =>
                publish(statusTopic(this.projectId, this.deviceId, type), payload));
        }
        this.publish = publish;
    }

    handleCommand(topic, rawMessage) {
        const parsed = parseCommandTopic(topic);
        if (!parsed) {
            log(`Ignored non-command topic: ${topic}`);
            return;
        }
        if (parsed.deviceId !== this.deviceId) {
            log(`Ignored command for other device: ${parsed.deviceId}`);
            return;
        }
        const actuator = this.actuators[parsed.actuator];
        if (!actuator) {
            log(`Unknown actuator: ${parsed.actuator} (known: ${KNOWN_ACTUATORS.join(', ')})`);
            return;
        }

        let obj;
        try {
            obj = JSON.parse(rawMessage);
        } catch {
            log(`Invalid JSON on ${topic}: ${rawMessage}`);
            return;
        }

        const v = validateCommand(obj);
        log('Command received');
        log(`  Actuator : ${parsed.actuator}`);
        log(`  Command  : ${obj.command}  Duration: ${obj.duration ?? '-'}`);
        log(`  Request  : ${obj.request_id}`);
        if (!v.ok) {
            log(`  ERROR    : ${v.error} — state unchanged (${actuator.state})`);
            if (v.request_id) {
                // Report failure back so Laravel marks the command FAILED.
                this.publish(statusTopic(this.projectId, this.deviceId, parsed.actuator), {
                    request_id: v.request_id, actuator: parsed.actuator,
                    state: actuator.state, status: 'FAILED', timestamp: new Date().toISOString(),
                });
                log('FAILED sent');
            }
            return;
        }

        if (v.command === 'ON') actuator.applyOn(v.request_id, v.duration);
        else actuator.applyOff(v.request_id);
    }

    onlinePayload() {
        return { device_id: this.deviceId, status: 'ONLINE', firmware_version: FIRMWARE_VERSION, timestamp: new Date().toISOString() };
    }

    offlinePayload() {
        return { device_id: this.deviceId, status: 'OFFLINE', timestamp: new Date().toISOString() };
    }
}

// ---------------------------------------------------------------------------
// Self-test (no broker required)
// ---------------------------------------------------------------------------

async function runSelfTest() {
    const assert = require('assert');
    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
    let passed = 0;

    const check = (name, fn) => {
        fn();
        passed += 1;
        console.log(`  PASS: ${name}`);
    };

    console.log('Simulator self-test (no broker needed)');

    check('topic builder matches Laravel MqttTopicBuilder', () => {
        assert.strictEqual(commandTopic(1, 'SF-ESP32-01', 'UV_LIGHT'), 'project/1/device/SF-ESP32-01/actuator/UV_LIGHT/command');
        assert.strictEqual(statusTopic(1, 'SF-ESP32-01', 'UV_LIGHT'), 'project/1/device/SF-ESP32-01/actuator/UV_LIGHT/status');
        assert.strictEqual(deviceStatusTopic(1, 'SF-ESP32-01'), 'project/1/device/SF-ESP32-01/status');
    });

    check('topic parser accepts command topics, rejects others', () => {
        assert.deepStrictEqual(parseCommandTopic('project/1/device/SF-ESP32-01/actuator/UV_LIGHT/command'),
            { projectId: '1', deviceId: 'SF-ESP32-01', actuator: 'UV_LIGHT' });
        assert.strictEqual(parseCommandTopic('project/1/device/SF-ESP32-01/sensor/temp'), null);
        assert.strictEqual(parseCommandTopic('garbage'), null);
    });

    check('payload validation (valid / invalid / missing duration)', () => {
        assert.strictEqual(validateCommand({ request_id: 'r1', command: 'ON', duration: 30 }).ok, true);
        assert.strictEqual(validateCommand({ request_id: 'r1', command: 'OFF' }).ok, true);
        assert.strictEqual(validateCommand({}).ok, false);
        assert.strictEqual(validateCommand({ request_id: 'r1', command: 'INVALID' }).ok, false);
        assert.strictEqual(validateCommand({ request_id: 'r1', command: 'ON' }).ok, false);
        assert.strictEqual(validateCommand({ request_id: 'r1', command: 'ON', duration: 0 }).ok, false);
    });

    // Full ON -> ACK -> SUCCESS lifecycle with 1s duration.
    {
        const sent = [];
        const dev = new VirtualDevice('1', 'SF-ESP32-01', (t, p) => sent.push({ t, p }));
        dev.handleCommand(commandTopic('1', 'SF-ESP32-01', 'UV_LIGHT'), JSON.stringify({ request_id: 'r-on', command: 'ON', duration: 1 }));
        check('ON sets state and sends ACKNOWLEDGED', () => {
            assert.strictEqual(dev.actuators.UV_LIGHT.state, 'ON');
            assert.strictEqual(sent.length, 1);
            assert.strictEqual(sent[0].p.status, 'ACKNOWLEDGED');
            assert.strictEqual(sent[0].p.request_id, 'r-on');
        });
        await sleep(1300);
        check('duration expiry sends SUCCESS with state OFF', () => {
            assert.strictEqual(dev.actuators.UV_LIGHT.state, 'OFF');
            assert.strictEqual(sent.length, 2);
            assert.strictEqual(sent[1].p.status, 'SUCCESS');
            assert.strictEqual(sent[1].p.state, 'OFF');
        });
    }

    // Early OFF cancels the pending ON timer.
    {
        const sent = [];
        const dev = new VirtualDevice('1', 'SF-ESP32-01', (t, p) => sent.push({ t, p }));
        dev.handleCommand(commandTopic('1', 'SF-ESP32-01', 'UV_LIGHT'), JSON.stringify({ request_id: 'r-long', command: 'ON', duration: 60 }));
        await sleep(100);
        dev.handleCommand(commandTopic('1', 'SF-ESP32-01', 'UV_LIGHT'), JSON.stringify({ request_id: 'r-off', command: 'OFF' }));
        const countAfterOff = sent.length;
        await sleep(300);
        check('early OFF cancels timer (no stale SUCCESS fires)', () => {
            assert.strictEqual(dev.actuators.UV_LIGHT.state, 'OFF');
            assert.strictEqual(sent.length, countAfterOff); // timer truly cancelled
            assert.strictEqual(sent[sent.length - 1].p.request_id, 'r-off');
            assert.strictEqual(sent[sent.length - 1].p.status, 'SUCCESS');
        });
    }

    // Unknown actuator + invalid payload never crash, never touch known states.
    {
        const sent = [];
        const dev = new VirtualDevice('1', 'SF-ESP32-01', (t, p) => sent.push({ t, p }));
        dev.handleCommand(commandTopic('1', 'SF-ESP32-01', 'UNKNOWN_ACTUATOR'), JSON.stringify({ request_id: 'r-x', command: 'ON', duration: 5 }));
        dev.handleCommand(commandTopic('1', 'SF-ESP32-01', 'UV_LIGHT'), '{invalid-json');
        dev.handleCommand(commandTopic('1', 'SF-ESP32-01', 'UV_LIGHT'), JSON.stringify({ command: 'INVALID' }));
        check('unknown actuator / bad JSON / bad command handled safely', () => {
            assert.strictEqual(dev.actuators.UV_LIGHT.state, 'OFF');
            assert.strictEqual(dev.actuators.MIST_MAKER.state, 'OFF');
        });
    }

    console.log(`\nSelf-test: ${passed} checks passed.`);
    process.exit(0);
}

// ---------------------------------------------------------------------------
// Live mode (requires broker + `npm install`)
// ---------------------------------------------------------------------------

function runLive() {
    let mqtt;
    try {
        mqtt = require('mqtt');
    } catch {
        console.error("Missing dependency 'mqtt'. Run:  npm install  (inside tools/mqtt-simulator)");
        process.exit(1);
    }

    console.log('=================================');
    console.log('Smart Farm MQTT Simulator');
    console.log('=================================');
    console.log(`\nBroker : ${config.broker}:${config.port}`);
    console.log(`Device : ${config.deviceId} (project ${config.projectId})`);
    console.log(`Actuators: ${KNOWN_ACTUATORS.join(', ')} (initial state OFF/OFF)`);
    console.log(`Duration unit: ${config.durationUnit} (TEST ACCELERATION — production ESP32 uses minutes)`);

    const client = mqtt.connect(`mqtt://${config.broker}:${config.port}`, {
        clientId: `smartfarm-sim-${config.deviceId}-${Date.now()}`,
        username: config.username,
        password: config.password,
        keepalive: 60,
        reconnectPeriod: 2000,
        will: {
            topic: deviceStatusTopic(config.projectId, config.deviceId),
            payload: JSON.stringify({ device_id: config.deviceId, status: 'OFFLINE', timestamp: new Date().toISOString() }),
            qos: 1,
            retain: false,
        },
    });

    const device = new VirtualDevice(config.projectId, config.deviceId, (topic, payload) => {
        client.publish(topic, JSON.stringify(payload), { qos: 1 });
    });

    client.on('connect', () => {
        const sub = commandTopic(config.projectId, config.deviceId);
        client.subscribe(sub, { qos: 1 }, (err) => {
            if (err) {
                console.error('Subscribe failed:', err.message);
                return;
            }
            console.log(`\nSubscribed: ${sub}`);
            // Retained ONLINE so the dashboard sees the virtual device.
            client.publish(deviceStatusTopic(config.projectId, config.deviceId),
                JSON.stringify(device.onlinePayload()), { qos: 1, retain: true });
            console.log('\nStatus: ONLINE\n\nWaiting for commands...\n');
        });
    });

    client.on('message', (topic, message) => {
        try {
            device.handleCommand(topic, message.toString());
        } catch (e) {
            log(`Handler error (no crash): ${e.message}`);
        }
        console.log('');
    });

    client.on('reconnect', () => log('Reconnecting to broker...'));
    client.on('error', (e) => log(`MQTT error: ${e.message}`));

    const shutdown = () => {
        log('Shutting down (Last Will marks device OFFLINE)...');
        client.end(false, () => process.exit(0));
        setTimeout(() => process.exit(0), 1500).unref();
    };
    process.on('SIGINT', shutdown);
    process.on('SIGTERM', shutdown);
}

if (config.selfTest) {
    runSelfTest().catch((e) => {
        console.error(`SELF-TEST FAILED: ${e.message}`);
        process.exit(1);
    });
} else {
    runLive();
}

module.exports = { parseCommandTopic, validateCommand, commandTopic, statusTopic, deviceStatusTopic, VirtualDevice, KNOWN_ACTUATORS };
