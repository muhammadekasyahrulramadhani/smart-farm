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
use App\Models\Project;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use App\Services\Dashboard\SprayingReminderService;
use App\Services\Mqtt\ActuatorCommandPublisher;
use App\Services\Mqtt\MqttMessageRouter;
use App\Services\Mqtt\MqttTopicBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardPhase4Test extends TestCase
{
    use RefreshDatabase;

    private function seedBase(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Smart Farm Cabai']);
        $bedengan = Bedengan::create(['project_id' => $project->id, 'name' => 'Bedengan 1']);
        $device = Device::create([
            'project_id' => $project->id,
            'device_id' => 'SF-ESP32-01',
            'name' => 'Controller',
            'status' => DeviceStatus::ONLINE,
        ]);
        $uv = Actuator::create([
            'device_id' => $device->id,
            'actuator_type' => 'UV_LIGHT',
            'state' => ActuatorState::OFF,
        ]);

        return compact('user', 'project', 'bedengan', 'device', 'uv');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_open_dashboard(): void
    {
        ['user' => $user] = $this->seedBase();

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('Smart Farm Cabai - Dashboard');
    }

    public function test_store_pest_observation_and_reject_invalid(): void
    {
        ['user' => $user, 'project' => $project, 'bedengan' => $bedengan] = $this->seedBase();

        // Valid.
        $this->actingAs($user)->post(route('pest-observations.store'), [
            'project_id' => $project->id,
            'bedengan_id' => $bedengan->id,
            'observation_date' => '2026-09-24',
            'attack_level' => 'SEDANG',
            'insect_count' => 20,
            'notes' => 'Sticky trap check.',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('pest_observations', ['insect_count' => 20]);

        // Invalid attack level rejected.
        $this->actingAs($user)->post(route('pest-observations.store'), [
            'project_id' => $project->id,
            'bedengan_id' => $bedengan->id,
            'observation_date' => '2026-09-24',
            'attack_level' => 'PARAH',
            'insect_count' => 5,
        ])->assertSessionHasErrors('attack_level');

        // Negative insect_count rejected.
        $this->actingAs($user)->post(route('pest-observations.store'), [
            'project_id' => $project->id,
            'bedengan_id' => $bedengan->id,
            'observation_date' => '2026-09-24',
            'attack_level' => 'RINGAN',
            'insect_count' => -3,
        ])->assertSessionHasErrors('insect_count');
    }

    public function test_store_spraying_record_and_reject_invalid_duration(): void
    {
        ['user' => $user, 'project' => $project, 'bedengan' => $bedengan] = $this->seedBase();

        $this->actingAs($user)->post(route('spraying-records.store'), [
            'project_id' => $project->id,
            'bedengan_id' => $bedengan->id,
            'spraying_date' => '2026-09-24',
            'spraying_time' => '06:30',
            'duration_minutes' => 5,
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('spraying_records', ['duration_minutes' => 5]);

        $this->actingAs($user)->post(route('spraying-records.store'), [
            'project_id' => $project->id,
            'bedengan_id' => $bedengan->id,
            'spraying_date' => '2026-09-24',
            'spraying_time' => '06:30',
            'duration_minutes' => 0,
        ])->assertSessionHasErrors('duration_minutes');
    }

    public function test_reminder_is_7_days_after_last_spraying(): void
    {
        ['user' => $user, 'project' => $project, 'bedengan' => $bedengan] = $this->seedBase();

        $project->sprayingRecords()->create([
            'bedengan_id' => $bedengan->id,
            'created_by' => $user->id,
            'spraying_date' => '2026-09-24',
            'spraying_time' => '06:30',
            'duration_minutes' => 5,
        ]);

        $items = app(SprayingReminderService::class)->forProject($project->fresh());

        $this->assertCount(1, $items);
        $this->assertEquals('2026-09-24', $items[0]['last_date']->toDateString());
        $this->assertEquals('2026-10-01', $items[0]['next_date']->toDateString());
    }

    public function test_dashboard_shows_history_bedengan_and_charts(): void
    {
        ['user' => $user, 'project' => $project, 'bedengan' => $bedengan] = $this->seedBase();

        $project->sprayingRecords()->create([
            'bedengan_id' => $bedengan->id,
            'created_by' => $user->id,
            'spraying_date' => '2026-09-24',
            'spraying_time' => '06:30',
            'duration_minutes' => 5,
            'notes' => 'Riwayat uji.',
        ]);
        $project->pestObservations()->create([
            'bedengan_id' => $bedengan->id,
            'created_by' => $user->id,
            'observation_date' => '2026-09-24',
            'attack_level' => AttackLevel::RINGAN,
            'insect_count' => 20,
        ]);

        // Aggregation uses SUM per date.
        $trend = app(DashboardService::class)->pestTrend($project->fresh());
        $this->assertEquals([['date' => '2026-09-24', 'total' => 20]], $trend->all());

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('Bedengan 1')
            ->assertSee('Riwayat uji.')
            ->assertSee('Trend Populasi Hama')
            ->assertSee('Lampu UV (Atraktan Hama)');
    }

    public function test_actuator_command_topic_and_unique_request_id(): void
    {
        ['device' => $device, 'uv' => $uv] = $this->seedBase();

        $topic = MqttTopicBuilder::actuatorCommand(1, 'SF-ESP32-01', 'UV_LIGHT');
        $this->assertEquals('project/1/device/SF-ESP32-01/actuator/UV_LIGHT/command', $topic);

        $c1 = ActuatorCommand::create([
            'device_id' => $device->id, 'actuator_id' => $uv->id,
            'command' => 'ON', 'duration' => 30,
            'request_id' => (string) Str::uuid(), 'status' => CommandStatus::PENDING,
        ]);
        $c2 = ActuatorCommand::create([
            'device_id' => $device->id, 'actuator_id' => $uv->id,
            'command' => 'OFF',
            'request_id' => (string) Str::uuid(), 'status' => CommandStatus::PENDING,
        ]);

        $this->assertNotEquals($c1->request_id, $c2->request_id);
    }

    public function test_actuator_ack_updates_command_and_state(): void
    {
        ['device' => $device, 'uv' => $uv] = $this->seedBase();

        $requestId = (string) Str::uuid();
        $command = ActuatorCommand::create([
            'device_id' => $device->id, 'actuator_id' => $uv->id,
            'command' => 'ON', 'duration' => 30,
            'request_id' => $requestId, 'status' => CommandStatus::SENT,
            'sent_at' => now(),
        ]);

        app(MqttMessageRouter::class)->route(
            'project/1/device/SF-ESP32-01/actuator/UV_LIGHT/status',
            json_encode([
                'request_id' => $requestId,
                'state' => 'ON',
                'status' => 'SUCCESS',
                'timestamp' => now()->toIso8601String(),
            ])
        );

        $this->assertEquals(CommandStatus::SUCCESS, $command->fresh()->status);
        $this->assertEquals(ActuatorState::ON, $uv->fresh()->state);
    }

    public function test_mqtt_offline_does_not_crash_and_manual_input_still_works(): void
    {
        ['user' => $user, 'project' => $project, 'bedengan' => $bedengan, 'uv' => $uv] = $this->seedBase();

        $this->mock(ActuatorCommandPublisher::class, function ($mock) {
            $mock->shouldReceive('send')->andThrow(new \Exception('MQTT broker down'));
        });

        // Actuator command fails gracefully (redirect + error, no crash).
        $this->actingAs($user)->post(route('actuators.commands.store', $uv), [
            'command' => 'ON',
            'duration' => 30,
        ])->assertRedirect(route('dashboard'))->assertSessionHas('error');

        // Manual input still works while MQTT is offline.
        $this->actingAs($user)->post(route('spraying-records.store'), [
            'project_id' => $project->id,
            'bedengan_id' => $bedengan->id,
            'spraying_date' => '2026-09-24',
            'spraying_time' => '06:30',
            'duration_minutes' => 5,
        ])->assertRedirect(route('dashboard'))->assertSessionHas('success');

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }
}
