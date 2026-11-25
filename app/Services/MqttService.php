<?php

namespace App\Services;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use RuntimeException;
use Throwable;

class MqttService
{
    public function publish(string $topic, string $message): void
    {
        $mqtt = null;

        try {
            $host = config('mqtt.host');
            $port = config('mqtt.port');
            $clientId = config('mqtt.client_id');

            $settings = (new ConnectionSettings())
                ->setUsername(config('mqtt.username'))
                ->setPassword(config('mqtt.password'))
                ->setConnectTimeout(5)
                ->setSocketTimeout(5)
                ->setKeepAliveInterval(30);

            $mqtt = new MqttClient(
                $host,
                $port,
                $clientId
            );

            $mqtt->connect($settings, true);

            $mqtt->publish(
                $topic,
                $message,
                1,
                false
            );

            $mqtt->disconnect();

        } catch (Throwable $e) {
            if ($mqtt !== null) {
                try {
                    $mqtt->disconnect();
                } catch (Throwable $ignored) {
                    // Ignore disconnect errors.
                }
            }

            throw new RuntimeException(
                'Gagal mengirim perintah MQTT: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function controlActuator(string $actuator, string $status): void
    {
        $topics = config('mqtt.topics');

        if (!isset($topics[$actuator])) {
            throw new RuntimeException('Aktuator tidak dikenal.');
        }

        if (!in_array($status, ['on', 'off'], true)) {
            throw new RuntimeException('Status aktuator tidak valid.');
        }

        $this->publish(
            $topics[$actuator],
            strtoupper($status)
        );
    }
}
