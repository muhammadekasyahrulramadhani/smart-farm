<?php

namespace App\Services\Mqtt;

use App\Models\Actuator;
use App\Models\ActuatorCommand;
use App\Models\Device;
use App\Models\SystemLog;
use App\Enums\ActuatorState;
use App\Enums\CommandStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ActuatorStatusHandler
{
    public function handle(string $deviceId, string $actuatorType, array $payload): void
    {
        $validator = Validator::make($payload, [
            'request_id' => ['nullable', 'string'],
            'state' => ['required', 'string', 'in:ON,OFF'],
            'status' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            SystemLog::create([
                'level' => 'error',
                'source' => 'ActuatorStatusHandler',
                'message' => 'Invalid actuator status payload.',
                'context' => ['payload' => $payload, 'errors' => $validator->errors()->toArray()],
            ]);
            return;
        }

        $device = Device::where('device_id', $deviceId)->first();
        if (!$device) {
            return;
        }

        $actuator = Actuator::where('device_id', $device->id)
            ->where('actuator_type', $actuatorType)
            ->first();

        if (!$actuator) {
            return;
        }

        $command = null;
        if (!empty($payload['request_id'])) {
            $command = ActuatorCommand::where('request_id', $payload['request_id'])->first();
        }

        $statusStr = isset($payload['status']) ? strtoupper($payload['status']) : 'SUCCESS';
        $commandStatus = match ($statusStr) {
            'SUCCESS' => CommandStatus::SUCCESS,
            'FAILED' => CommandStatus::FAILED,
            'ACKNOWLEDGED' => CommandStatus::ACKNOWLEDGED,
            default => CommandStatus::SUCCESS,
        };

        $stateStr = strtoupper($payload['state']);
        $actuatorState = $stateStr === 'ON' ? ActuatorState::ON : ActuatorState::OFF;

        DB::transaction(function () use ($actuator, $command, $commandStatus, $actuatorState) {
            if ($command) {
                $command->update([
                    'status' => $commandStatus,
                    'acknowledged_at' => $command->acknowledged_at ?? now(),
                    'completed_at' => in_array($commandStatus, [CommandStatus::SUCCESS, CommandStatus::FAILED]) ? now() : null,
                ]);
            }

            $actuator->update([
                'state' => $actuatorState,
                'last_state_change' => now(),
            ]);
        });
    }
}
