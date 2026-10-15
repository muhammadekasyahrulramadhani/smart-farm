<?php

namespace Tests\Feature;

use App\Enums\ActuatorState;
use App\Enums\AttackLevel;
use App\Enums\CommandStatus;
use App\Enums\DeviceStatus;
use App\Models\Actuator;
use App\Models\ActuatorCommand;
use App\Models\Bedengan;
use App\Models\Device;
use App\Models\PestObservation;
use App\Models\Project;
use App\Models\SprayingRecord;
use App\Models\User;
use App\Services\Mqtt\MqttMessageRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SmartFarmArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_has_many_bedengans_and_records(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Farm Cabai']);
        $bedengan = Bedengan::create([
            'project_id' => $project->id,
            'name' => 'Bedengan 1',
        ]);

        $observation = PestObservation::create([
            'project_id' => $project->id,
            'bedengan_id' => $bedengan->id,
            'created_by' => $user->id,
            'observation_date' => now()->toDateString(),
            'attack_level' => AttackLevel::SEDANG,
            'insect_count' => 15,
        ]);

        $spraying = SprayingRecord::create([
            'project_id' => $project->id,
            'bedengan_id' => $bedengan->id,
            'created_by' => $user->id,
            'spraying_date' => now()->toDateString(),
            'spraying_time' => '08:00',
            'duration_minutes' => 20,
        ]);

        $this->assertCount(1, $project->bedengans);
        $this->assertCount(1, $bedengan->pestObservations);
        $this->assertCount(1, $bedengan->sprayingRecords);
        $this->assertEquals(AttackLevel::SEDANG, $observation->attack_level);
        $this->assertEquals(20, $spraying->duration_minutes);
    }

    public function test_device_has_actuators_and_commands(): void
    {
        $project = Project::create(['name' => 'Farm Cabai']);
        $device = Device::create([
            'project_id' => $project->id,
            'device_id' => 'SF-ESP32-01',
            'name' => 'Controller 1',
            'status' => DeviceStatus::ONLINE,
        ]);

        $actuator = Actuator::create([
            'device_id' => $device->id,
            'actuator_type' => 'UV_LIGHT',
            'state' => ActuatorState::OFF,
        ]);

        $requestId = (string) Str::uuid();
        $command = ActuatorCommand::create([
            'device_id' => $device->id,
            'actuator_id' => $actuator->id,
            'command' => 'ON',
            'duration' => 1800,
            'request_id' => $requestId,
            'status' => CommandStatus::PENDING,
        ]);

        $this->assertCount(1, $device->actuators);
        $this->assertEquals('UV_LIGHT', $actuator->actuator_type);
        $this->assertEquals(CommandStatus::PENDING, $command->status);
        $this->assertNotNull($command->request_id);
    }

    public function test_mqtt_actuator_status_acknowledgement_flow(): void
    {
        $project = Project::create(['name' => 'Farm Cabai']);
        $device = Device::create([
            'project_id' => $project->id,
            'device_id' => 'SF-ESP32-01',
            'name' => 'Controller 1',
        ]);
        $actuator = Actuator::create([
            'device_id' => $device->id,
            'actuator_type' => 'MIST_MAKER',
            'state' => ActuatorState::OFF,
        ]);

        $requestId = (string) Str::uuid();
        $command = ActuatorCommand::create([
            'device_id' => $device->id,
            'actuator_id' => $actuator->id,
            'command' => 'ON',
            'duration' => 30,
            'request_id' => $requestId,
            'status' => CommandStatus::SENT,
            'sent_at' => now(),
        ]);

        $ackPayload = [
            'request_id' => $requestId,
            'device_id' => 'SF-ESP32-01',
            'actuator' => 'MIST_MAKER',
            'state' => 'ON',
            'status' => 'SUCCESS',
            'timestamp' => now()->toIso8601String(),
        ];

        $router = app(MqttMessageRouter::class);
        $router->route('project/1/device/SF-ESP32-01/actuator/MIST_MAKER/status', json_encode($ackPayload));

        $command->refresh();
        $actuator->refresh();

        $this->assertEquals(CommandStatus::SUCCESS, $command->status);
        $this->assertEquals(ActuatorState::ON, $actuator->state);
    }
}
