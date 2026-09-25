<?php

namespace App\Services\Mqtt;

class MqttTopicBuilder
{
    public static function status(string|int $projectId, string $deviceId): string
    {
        return "project/{$projectId}/device/{$deviceId}/status";
    }

    public static function actuatorCommand(string|int $projectId, string $deviceId, string $actuatorType): string
    {
        return "project/{$projectId}/device/{$deviceId}/actuator/{$actuatorType}/command";
    }

    public static function actuatorStatus(string|int $projectId, string $deviceId, string $actuatorType): string
    {
        return "project/{$projectId}/device/{$deviceId}/actuator/{$actuatorType}/status";
    }

    public static function event(string|int $projectId, string $deviceId): string
    {
        return "project/{$projectId}/device/{$deviceId}/event";
    }

    public static function wildcardSubscription(): string
    {
        return "project/+/device/+/+/#";
    }
}
