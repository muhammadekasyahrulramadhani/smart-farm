<?php

namespace App\Services\Mqtt;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use App\Models\Actuator;
use App\Models\ActuatorCommand;
use App\Enums\CommandStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ActuatorCommandPublisher
{
    public function send(Actuator $actuator, string $command, ?int $duration = null): ActuatorCommand
    {
        $device = $actuator->device;
        $project = $device->project;

        $requestId = (string) Str::uuid();

        $actuatorCommand = ActuatorCommand::create([
            'device_id' => $device->id,
            'actuator_id' => $actuator->id,
            'command' => strtoupper($command),
            'duration' => $duration,
            'request_id' => $requestId,
            'status' => CommandStatus::PENDING,
        ]);

        $topic = MqttTopicBuilder::actuatorCommand($project->id, $device->device_id, $actuator->actuator_type);

        $payload = json_encode([
            'request_id' => $requestId,
            'command' => strtoupper($command),
            'duration' => $duration,
            'timestamp' => now()->toIso8601String(),
        ]);

        try {
            $host = config('mqtt.host');
            $port = config('mqtt.port');
            $clientId = config('mqtt.client_id') . '-pub-' . uniqid();
            $username = config('mqtt.username');
            $password = config('mqtt.password');

            $mqtt = new MqttClient($host, $port, $clientId);
            $connectionSettings = (new ConnectionSettings)
                ->setKeepAliveInterval(config('mqtt.keep_alive'));

            if (!empty($username)) {
                $connectionSettings->setUsername($username);
                $connectionSettings->setPassword($password);
            }

            $mqtt->connect($connectionSettings, true);
            $mqtt->publish($topic, $payload, config('mqtt.qos'));
            $mqtt->interrupt();

            $actuatorCommand->update([
                'status' => CommandStatus::SENT,
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            $actuatorCommand->update([
                'status' => CommandStatus::FAILED,
            ]);
            Log::error("Failed to publish MQTT actuator command: " . $e->getMessage());
            throw $e;
        }

        return $actuatorCommand;
    }
}
