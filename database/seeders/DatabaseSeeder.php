<?php

namespace Database\Seeders;

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
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: safe to re-run (firstOrCreate, no duplicates).
        $user = User::firstOrCreate(
            ['email' => 'admin@smartfarm.test'],
            [
                'name' => 'Admin Smart Farm',
                'password' => 'password',
            ]
        );

        $project = Project::firstOrCreate(
            ['name' => 'Smart Farm Cabai'],
            ['description' => 'Smart Farm Cabai - Tracking Penyemprotan Pestisida & Kontrol IoT Portabel.']
        );

        foreach (['Bedengan 1', 'Bedengan 2', 'Bedengan 3'] as $name) {
            Bedengan::firstOrCreate(
                ['project_id' => $project->id, 'name' => $name],
                ['description' => "Area tanaman cabai {$name}."]
            );
        }

        $device = Device::firstOrCreate(
            ['device_id' => 'SF-ESP32-01'],
            [
                'project_id' => $project->id,
                'name' => 'Greenhouse Main Controller',
                'status' => DeviceStatus::ONLINE,
                'last_seen' => now(),
                'firmware_version' => '2.0.0',
            ]
        );

        $uv = Actuator::firstOrCreate(
            ['device_id' => $device->id, 'actuator_type' => 'UV_LIGHT'],
            ['state' => ActuatorState::OFF, 'last_state_change' => now()->subHours(2)]
        );

        Actuator::firstOrCreate(
            ['device_id' => $device->id, 'actuator_type' => 'MIST_MAKER'],
            ['state' => ActuatorState::OFF, 'last_state_change' => now()->subMinutes(15)]
        );

        // Sample manual-input data (only when tables are empty).
        $bedengan1 = Bedengan::where('project_id', $project->id)->where('name', 'Bedengan 1')->first();

        if ($bedengan1 && PestObservation::count() === 0) {
            PestObservation::create([
                'project_id' => $project->id,
                'bedengan_id' => $bedengan1->id,
                'created_by' => $user->id,
                'observation_date' => now()->subDays(1)->toDateString(),
                'attack_level' => AttackLevel::RINGAN,
                'insect_count' => 12,
                'notes' => 'Data contoh development: kutu kebul pada daun bawah.',
            ]);
        }

        if ($bedengan1 && SprayingRecord::count() === 0) {
            SprayingRecord::create([
                'project_id' => $project->id,
                'bedengan_id' => $bedengan1->id,
                'created_by' => $user->id,
                'spraying_date' => now()->toDateString(),
                'spraying_time' => '07:30',
                'duration_minutes' => 30,
                'notes' => 'Data contoh development.',
            ]);
        }

        if (ActuatorCommand::count() === 0) {
            ActuatorCommand::create([
                'device_id' => $device->id,
                'actuator_id' => $uv->id,
                'command' => 'ON',
                'duration' => 30,
                'request_id' => (string) Str::uuid(),
                'status' => CommandStatus::SUCCESS,
                'sent_at' => now()->subHour(),
                'acknowledged_at' => now()->subHour()->addSeconds(2),
                'completed_at' => now()->subHour()->addSeconds(3),
            ]);
        }
    }
}
