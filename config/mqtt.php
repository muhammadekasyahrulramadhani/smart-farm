<?php

return [
    'host' => env('MQTT_HOST', 'smartfarm_mqtt'),
    'port' => (int) env('MQTT_PORT', 1883),
    'username' => env('MQTT_USERNAME'),
    'password' => env('MQTT_PASSWORD'),
    'client_id' => env('MQTT_CLIENT_ID', 'smartfarm-laravel'),

    'topics' => [
        'lampu' => 'smartfarm/device/esp32-001/relay/1/set',
        'mist' => 'smartfarm/device/esp32-001/relay/2/set',
    ],

    'state_topics' => [
        'lampu' => 'smartfarm/device/esp32-001/relay/1/state',
        'mist' => 'smartfarm/device/esp32-001/relay/2/state',
    ],
];
