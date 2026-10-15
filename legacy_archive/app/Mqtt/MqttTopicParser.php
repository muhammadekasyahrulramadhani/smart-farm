<?php

namespace App\Services\Mqtt;

class MqttTopicParser
{
    public static function parse(string $topic): ?array
    {
        $segments = explode('/', $topic);

        // 5 segments: project/{p}/device/{d}/status (or /event).
        // 7 segments: project/{p}/device/{d}/actuator/{type}/{command|status}.
        if (count($segments) < 5 || $segments[0] !== 'project' || $segments[2] !== 'device') {
            return null;
        }

        return [
            'project_id' => $segments[1],
            'device_id' => $segments[3],
            'type' => $segments[4], // actuator, status, event
            'subtype' => $segments[5] ?? null, // e.g., UV_LIGHT, MIST_MAKER
            'action' => $segments[6] ?? null, // command, status
        ];
    }
}
