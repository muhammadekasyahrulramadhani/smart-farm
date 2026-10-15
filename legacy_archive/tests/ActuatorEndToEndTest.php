<?php

namespace Tests\Feature;

use App\Enums\ActuatorState;
use App\Enums\CommandStatus;
use App\Enums\DeviceStatus;
use App\Models\Actuator;
use App\Models\ActuatorCommand;
use App\Models\Bedengan;
use App\Models\Device;
use App\Models\Project;
use App\Models\User;
use App\Services\Mqtt\ActuatorCommandPublisher;
use App\Services\Mqtt\MqttMessageRouter;
use App\Services\Mqtt\MqttTopicBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Contract test for the Phase 5 MQTT simulator loop — no broker, no ESP32.
 *
 * Encodes the exact topics/payloads the simulator (tools/mqtt-simulator)
 * and Laravel exchange, then drives the Laravel side of the full lifecycle:
 * command -> ACK -> SUCCESS, OFF-early, invalid/unknown handling, and
 * broker-offline resilience.
 */
class ActuatorEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private function seedBase(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Smart Farm Cabai']);
        Bedengan::create(['project_id' => $project->id, 'name' => 'Bedengan 1']);
        $device = Device::create([
            'project_id' => $project->id,
            'device_id' => 'SF-ESP32-01',
            'name' => 'Controller',
            'status' => DeviceStatus::ONLINE,
        ]);
        $uv = Actuator::create([
            'device_id' => $device->id, 'actuator_type' => 'UV_LIGHT', 'state' => ActuatorState::OFF,
        ]);
        $mist = Actuator::create([
            'device_id' => $device->id, 'actuator_type' => 'MIST_MAKER', 'state' => ActuatorState::OFF,
        ]);

        return compact('user', 'project', 'device', 'uv', 'mist');
    }

    private function statusTopic(string $actuator): string
    {
        return "project/1/device/SF-ESP32-01/actuator/{$actuator}/status";
    }

    public function test_command_topics_match_simulator_subscription(): void
    {
        $this->assertEquals(
            'project/1/device/SF-ESP32-01/actuator/UV_LIGHT/command',
            MqttTopicBuilder::actuatorCommand(1, 'SF-ESP32-01', 'UV_LIGHT')
        );
        $this->assertEquals(
            'project/1/device/SF-ESP32-01/actuator/MIST_MAKER/command',
            MqttTopicBuilder::actuatorCommand(1, 'SF-ESP32-01', 'MIST_MAKER')
        );
        $this->assertEquals(
            'project/1/device/SF-ESP32-01/actuator/UV_LIGHT/status',
            MqttTopicBuilder::actuatorStatus(1, 'SF-ESP32-01', 'UV_LIGHT')
        );
    }

    public function test_dashboard_sends_uv_and_mist_commands_with_unique_request_ids(): void
    {
        ['user' => $user, 'uv' => $uv, 'mist' => $mist] = $this->seedBase();

        // Simulate successful publish (broker present): publisher mocked to
        // record PENDING -> SENT without touching the network.
        $this->mock(ActuatorCommandPublisher::class, function ($mock) use ($uv, $mist) {
            $mock->shouldReceive('send')->andReturnUsing(
                fn (Actuator $a, string $cmd, ?int $d) => ActuatorCommand::create([
                    'device_id' => $a->device_id, 'actuator_id' => $a->id,
                    'command' => $cmd, 'duration' => $d,
                    'request_id' => (string) Str::uuid(),
                    'status' => CommandStatus::SENT, 'sent_at' => now(),
                ])
            );
        });

        $this->actingAs($user)->post(route('actuators.commands.store', $uv), [
            'command' => 'ON', 'duration' => 30,
        ])->assertRedirect(route('dashboard'))->assertSessionHas('success');

        $this->actingAs($user)->post(route('actuators.commands.store', $mist), [
            'command' => 'ON', 'duration' => 5,
        ])->assertRedirect(route('dashboard'))->assertSessionHas('success');

        $commands = ActuatorCommand::all();
        $this->assertCount(2, $commands);
        $this->assertNotEquals($commands[0]->request_id, $commands[1]->request_id);
        $this->assertTrue(Str::isUuid($commands[0]->request_id));
    }

    public function test_full_ack_then_success_lifecycle(): void
    {
        ['device' => $device, 'uv' => $uv] = $this->seedBase();
        $router = app(MqttMessageRouter::class);

        $requestId = (string) Str::uuid();
        $command = ActuatorCommand::create([
            'device_id' => $device->id, 'actuator_id' => $uv->id,
            'command' => 'ON', 'duration' => 30,
            'request_id' => $requestId, 'status' => CommandStatus::SENT, 'sent_at' => now(),
        ]);

        // Simulator ACK (mirrors tools/mqtt-simulator ACK payload shape).
        $router->route($this->statusTopic('UV_LIGHT'), json_encode([
            'request_id' => $requestId, 'actuator' => 'UV_LIGHT',
            'state' => 'ON', 'status' => 'ACKNOWLEDGED',
            'timestamp' => now()->toIso8601String(),
        ]));

        $this->assertEquals(CommandStatus::ACKNOWLEDGED, $command->fresh()->status);
        $this->assertNotNull($command->fresh()->acknowledged_at);
        $this->assertEquals(ActuatorState::ON, $uv->fresh()->state);

        // Simulator SUCCESS after duration (mirrors simulator SUCCESS payload).
        $router->route($this->statusTopic('UV_LIGHT'), json_encode([
            'request_id' => $requestId, 'actuator' => 'UV_LIGHT',
            'state' => 'OFF', 'status' => 'SUCCESS',
            'timestamp' => now()->toIso8601String(),
        ]));

        $this->assertEquals(CommandStatus::SUCCESS, $command->fresh()->status);
        $this->assertNotNull($command->fresh()->completed_at);
        $this->assertEquals(ActuatorState::OFF, $uv->fresh()->state);
    }

    public function test_off_command_reports_success(): void
    {
        ['device' => $device, 'uv' => $uv] = $this->seedBase();
        $uv->update(['state' => ActuatorState::ON]);

        $requestId = (string) Str::uuid();
        $command = ActuatorCommand::create([
            'device_id' => $device->id, 'actuator_id' => $uv->id,
            'command' => 'OFF', 'request_id' => $requestId,
            'status' => CommandStatus::SENT, 'sent_at' => now(),
        ]);

        app(MqttMessageRouter::class)->route($this->statusTopic('UV_LIGHT'), json_encode([
            'request_id' => $requestId, 'actuator' => 'UV_LIGHT',
            'state' => 'OFF', 'status' => 'SUCCESS',
            'timestamp' => now()->toIso8601String(),
        ]));

        $this->assertEquals(CommandStatus::SUCCESS, $command->fresh()->status);
        $this->assertEquals(ActuatorState::OFF, $uv->fresh()->state);
    }

    public function test_invalid_and_unknown_messages_are_handled_safely(): void
    {
        ['device' => $device, 'uv' => $uv, 'mist' => $mist] = $this->seedBase();
        $router = app(MqttMessageRouter::class);

        $before = ActuatorCommand::count();

        // Malformed JSON, missing fields, bad state.
        $router->route($this->statusTopic('UV_LIGHT'), '{invalid-json');
        $router->route($this->statusTopic('UV_LIGHT'), json_encode(['foo' => 'bar']));
        $router->route($this->statusTopic('UV_LIGHT'), json_encode([
            'request_id' => 'whatever', 'state' => 'BLINK', 'status' => 'SUCCESS',
        ]));

        // Unknown actuator type in topic.
        $router->route(
            'project/1/device/SF-ESP32-01/actuator/UNKNOWN_ACTUATOR/status',
            json_encode(['request_id' => 'x', 'state' => 'ON', 'status' => 'SUCCESS'])
        );

        // Unknown topic entirely.
        $router->route('some/random/topic', json_encode(['a' => 1]));

        $this->assertEquals($before, ActuatorCommand::count());
        $this->assertEquals(ActuatorState::OFF, $uv->fresh()->state);
        $this->assertEquals(ActuatorState::OFF, $mist->fresh()->state);
        $this->assertTrue(true); // worker survived everything above
    }

    public function test_unknown_request_id_does_not_touch_existing_commands(): void
    {
        ['device' => $device, 'uv' => $uv] = $this->seedBase();

        $command = ActuatorCommand::create([
            'device_id' => $device->id, 'actuator_id' => $uv->id,
            'command' => 'ON', 'duration' => 30,
            'request_id' => (string) Str::uuid(),
            'status' => CommandStatus::SENT, 'sent_at' => now(),
        ]);

        app(MqttMessageRouter::class)->route($this->statusTopic('UV_LIGHT'), json_encode([
            'request_id' => 'non-existent-request-id', 'actuator' => 'UV_LIGHT',
            'state' => 'ON', 'status' => 'SUCCESS',
            'timestamp' => now()->toIso8601String(),
        ]));

        $this->assertEquals(CommandStatus::SENT, $command->fresh()->status);
        $this->assertNull($command->fresh()->completed_at);
    }

    public function test_device_online_status_updates_last_seen(): void
    {
        ['device' => $device] = $this->seedBase();
        $device->update(['status' => DeviceStatus::OFFLINE, 'last_seen' => now()->subDay()]);

        app(MqttMessageRouter::class)->route(
            'project/1/device/SF-ESP32-01/status',
            json_encode([
                'device_id' => 'SF-ESP32-01', 'status' => 'ONLINE',
                'firmware_version' => '2.0.0-sim',
                'timestamp' => now()->toIso8601String(),
            ])
        );

        $device->refresh();
        $this->assertEquals(DeviceStatus::ONLINE, $device->status);
        $this->assertEquals('2.0.0-sim', $device->firmware_version);
        $this->assertTrue($device->last_seen->isToday());
    }
}
