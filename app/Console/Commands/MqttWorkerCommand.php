<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use App\Services\Mqtt\MqttMessageRouter;
use App\Services\Mqtt\MqttTopicBuilder;
use App\Models\SystemLog;

class MqttWorkerCommand extends Command
{
    protected $signature = 'mqtt:worker';
    protected $description = 'Start persistent MQTT background worker for IoT communication';

    protected MqttMessageRouter $router;

    public function __construct(MqttMessageRouter $router)
    {
        parent::__construct();
        $this->router = $router;
    }

    public function handle(): int
    {
        $host = config('mqtt.host');
        $port = config('mqtt.port');
        $clientId = config('mqtt.client_id');
        $username = config('mqtt.username');
        $password = config('mqtt.password');

        $this->info("Starting MQTT Worker connecting to {$host}:{$port} as client ID [{$clientId}]...");

        $mqtt = new MqttClient($host, $port, $clientId);
        $connectionSettings = (new ConnectionSettings)
            ->setKeepAliveInterval(config('mqtt.keep_alive'))
            ->setReconnectAutomatically(true)
            ->setResendTimeout(5);

        if (!empty($username)) {
            $connectionSettings->setUsername($username);
            $connectionSettings->setPassword($password);
        }

        try {
            // Persistent session (clean=false) is required for automatic
            // reconnects and keeps worker subscriptions across broker restarts.
            $mqtt->connect($connectionSettings, false);
            $this->info("Connected to MQTT Broker successfully.");

            SystemLog::create([
                'level' => 'info',
                'source' => 'MqttWorkerCommand',
                'message' => 'MQTT Worker connected to broker successfully.',
            ]);

            $topic = MqttTopicBuilder::wildcardSubscription();
            $this->info("Subscribing to wildcard topic: {$topic}");

            $mqtt->subscribe($topic, function (string $topic, string $message) {
                $this->line("[<info>MQTT</info>] Received topic: <comment>{$topic}</comment>");
                try {
                    $this->router->route($topic, $message);
                } catch (\Exception $e) {
                    $this->error("Error routing MQTT message: " . $e->getMessage());
                    SystemLog::create([
                        'level' => 'error',
                        'source' => 'MqttWorkerCommand',
                        'message' => 'Exception caught during message routing: ' . $e->getMessage(),
                        'context' => ['topic' => $topic, 'exception' => $e->getTraceAsString()],
                    ]);
                }
            }, config('mqtt.qos'));

            $mqtt->loop(true);
        } catch (\Exception $e) {
            $this->error("MQTT Worker encountered fatal connection error: " . $e->getMessage());
            SystemLog::create([
                'level' => 'critical',
                'source' => 'MqttWorkerCommand',
                'message' => 'MQTT Worker fatal error: ' . $e->getMessage(),
            ]);
            return 1;
        }

        return 0;
    }
}
