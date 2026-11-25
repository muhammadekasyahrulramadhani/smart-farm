<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\DeviceActivity;
use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Throwable;

class SubscribeMqttStatus extends Command
{
    protected $signature = 'mqtt:subscribe-status';

    protected $description = 'Listen to ESP32 actuator status reports';

    public function handle(): int
    {
        $host = config('mqtt.host');
        $port = config('mqtt.port');
        $clientId = config('mqtt.client_id') . '-subscriber';

        $topics = [];

        foreach (config('mqtt.state_topics') as $actuator => $topic) {
            $topics[$topic] = $actuator;
        }

        while (true) {
            $mqtt = null;

            try {
                $settings = (new ConnectionSettings())
                    ->setUsername(config('mqtt.username'))
                    ->setPassword(config('mqtt.password'))
                    ->setConnectTimeout(10)
                    ->setSocketTimeout(5)
                    ->setKeepAliveInterval(30);

                $mqtt = new MqttClient($host, $port, $clientId);
                $mqtt->connect($settings, true);

                $this->info('Connected to MQTT broker.');

                foreach ($topics as $topic => $actuator) {
                    $mqtt->subscribe(
                        $topic,
                        function (
                            string $receivedTopic,
                            string $message
                        ) use ($actuator): void {
                            $status = strtolower(trim($message));

                            if (!in_array($status, ['on', 'off'], true)) {
                                $this->warn("Invalid status: {$message}");
                                return;
                            }

                            $deviceName = 'Lampu-UV-01';

                            if ($actuator === 'mist') {
                                $deviceName = 'Mist-Maker-01';
                            }

                            $device = Device::firstWhere('name', $deviceName);

                            DeviceActivity::create([
                                'device_name' => $deviceName,
                                'actuator' => $actuator,
                                'status' => $status,
                                'source' => 'esp',
                                'bedengan' => $device?->bedengan,
                            ]);

                            $this->info(
                                "{$deviceName}: " . strtoupper($status)
                            );
                        },
                        1
                    );
                }

                $this->info('Subscribed to actuator state topics.');

                $mqtt->loop(true);

            } catch (Throwable $e) {
                $this->error('MQTT subscriber error: ' . $e->getMessage());
                $this->warn('Retrying in 5 seconds...');
                sleep(5);
            } finally {
                if ($mqtt !== null) {
                    try {
                        $mqtt->disconnect();
                    } catch (Throwable $ignored) {
                    }
                }
            }
        }
    }
}
