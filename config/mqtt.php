<?php

return [
    'host' => env('MQTT_HOST', '127.0.0.1'),
    'port' => (int) env('MQTT_PORT', 1883),
    'username' => env('MQTT_USERNAME', null),
    'password' => env('MQTT_PASSWORD', null),
    'client_id' => env('MQTT_CLIENT_ID', 'laravel-iot-worker-' . uniqid()),
    'keep_alive' => (int) env('MQTT_KEEPALIVE', 60),
    'qos' => (int) env('MQTT_QOS', 1),
];
