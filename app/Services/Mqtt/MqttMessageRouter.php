<?php

namespace App\Services\Mqtt;

use App\Models\SystemLog;

class MqttMessageRouter
{
    protected DeviceStatusHandler $statusHandler;
    protected ActuatorStatusHandler $actuatorStatusHandler;

    public function __construct(
        DeviceStatusHandler $statusHandler,
        ActuatorStatusHandler $actuatorStatusHandler
    ) {
        $this->statusHandler = $statusHandler;
        $this->actuatorStatusHandler = $actuatorStatusHandler;
    }

    public function route(string $topic, string $message): void
    {
        $parsed = MqttTopicParser::parse($topic);

        if (!$parsed) {
            SystemLog::create([
                'level' => 'warning',
                'source' => 'MqttMessageRouter',
                'message' => "Received message on unparsable topic: {$topic}",
                'context' => ['topic' => $topic],
            ]);
            return;
        }

        $payload = json_decode($message, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            SystemLog::create([
                'level' => 'error',
                'source' => 'MqttMessageRouter',
                'message' => "Received malformed JSON on topic: {$topic}",
                'context' => ['topic' => $topic, 'message' => $message],
            ]);
            return;
        }

        $deviceId = $parsed['device_id'];
        $type = $parsed['type'];
        $subtype = $parsed['subtype'];

        switch ($type) {
            case 'status':
                $this->statusHandler->handle($deviceId, $payload);
                break;

            case 'actuator':
                if ($subtype && $parsed['action'] === 'status') {
                    $this->actuatorStatusHandler->handle($deviceId, $subtype, $payload);
                }
                break;

            default:
                SystemLog::create([
                    'level' => 'info',
                    'source' => 'MqttMessageRouter',
                    'message' => "Unhandled MQTT message type: {$type} on topic {$topic}",
                ]);
                break;
        }
    }
}
