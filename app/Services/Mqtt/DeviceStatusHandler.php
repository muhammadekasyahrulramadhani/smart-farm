<?php

namespace App\Services\Mqtt;

use App\Models\Device;
use App\Models\SystemLog;
use App\Enums\DeviceStatus;
use Illuminate\Support\Facades\Validator;

class DeviceStatusHandler
{
    public function handle(string $deviceId, array $payload): void
    {
        $validator = Validator::make($payload, [
            'status' => ['required', 'string'],
            'firmware_version' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            SystemLog::create([
                'level' => 'error',
                'source' => 'DeviceStatusHandler',
                'message' => 'Invalid device status payload validation failed.',
                'context' => ['payload' => $payload, 'errors' => $validator->errors()->toArray()],
            ]);
            return;
        }

        $device = Device::where('device_id', $deviceId)->first();

        if (!$device) {
            SystemLog::create([
                'level' => 'warning',
                'source' => 'DeviceStatusHandler',
                'message' => "Received status heartbeat for unknown device: {$deviceId}",
                'context' => ['payload' => $payload],
            ]);
            return;
        }

        $statusStr = strtoupper($payload['status']);
        $deviceStatus = match ($statusStr) {
            'ONLINE' => DeviceStatus::ONLINE,
            'OFFLINE' => DeviceStatus::OFFLINE,
            default => DeviceStatus::UNKNOWN,
        };

        $updateData = [
            'status' => $deviceStatus,
            'last_seen' => now(),
        ];

        if (!empty($payload['firmware_version'])) {
            $updateData['firmware_version'] = $payload['firmware_version'];
        }

        $device->update($updateData);
    }
}
