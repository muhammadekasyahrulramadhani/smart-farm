<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    /**
     * Get list of registered devices and their online status.
     */
    public function index(): JsonResponse
    {
        $threshold = (int) env('DEVICE_ONLINE_THRESHOLD', 90);

        $devices = Device::orderBy('name')->get()->map(function ($device) use ($threshold) {
            return [
                'id'           => $device->id,
                'name'         => $device->name,
                'type'         => $device->type,
                'bedengan'     => $device->bedengan,
                'last_seen_at' => $device->last_seen_at,
                'ip_address'   => $device->ip_address,
                'is_online'    => $device->isOnline($threshold),
            ];
        });

        return response()->json($devices);
    }

    /**
     * Add a new device (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:50', 'unique:devices,name', 'regex:/^[A-Za-z0-9_-]+$/'],
            'type'     => ['required', 'in:lampu,mist,lain'],
            'bedengan' => ['nullable', 'string', 'max:50'],
        ]);

        $device = Device::create($data);

        return response()->json(['success' => true, 'device' => $device], 201);
    }

    /**
     * Delete a device (Admin only).
     */
    public function destroy(int|string $id): JsonResponse
    {
        Device::destroy($id);

        return response()->json(['success' => true]);
    }

    /**
     * Get actuator activities feed (50 latest, Admin only).
     */
    public function getActivities(): JsonResponse
    {
        $activities = DeviceActivity::orderBy('created_at', 'desc')->limit(50)->get();

        return response()->json($activities);
    }

    /**
     * Record web dashboard toggle activity (Admin only).
     */
    public function logWebActivity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'actuator'    => ['required', 'in:lampu,mist'],
            'status'      => ['required', 'in:on,off'],
            'device_name' => ['nullable', 'string', 'max:50'],
        ]);

        $deviceName = $data['device_name']
            ?? Device::where('type', $data['actuator'])->orderBy('name')->value('name')
            ?? ($data['actuator'] === 'lampu' ? 'Lampu-UV-01' : 'Mist-Maker-01');

        $device = Device::firstWhere('name', $deviceName);

        $activity = DeviceActivity::create([
            'device_name' => $deviceName,
            'actuator'    => $data['actuator'],
            'status'      => $data['status'],
            'source'      => 'web',
            'actor'       => session('username'),
            'bedengan'    => $device?->bedengan,
        ]);

        return response()->json(['success' => true, 'activity' => $activity]);
    }

    /**
     * ESP32 Heartbeat endpoint.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        if ($request->input('token') !== env('DEVICE_TOKEN', 'smartfarm-iot-2026')) {
            return response()->json(['error' => 'Invalid device token'], 403);
        }

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:50'],
            'type'     => ['nullable', 'in:lampu,mist,lain'],
            'bedengan' => ['nullable', 'string', 'max:50'],
        ]);

        $device = Device::updateOrCreate(
            ['name' => $data['name']],
            [
                'type'         => $data['type'] ?? 'lain',
                'bedengan'     => $data['bedengan'] ?? null,
                'last_seen_at' => now(),
                'ip_address'   => $request->ip(),
            ]
        );

        return response()->json(['success' => true, 'device' => $device->name]);
    }

    /**
     * Report actuator ON/OFF state from ESP32.
     */
    public function reportActivity(Request $request): JsonResponse
    {
        if ($request->input('token') !== env('DEVICE_TOKEN', 'smartfarm-iot-2026')) {
            return response()->json(['error' => 'Invalid device token'], 403);
        }

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:50'],
            'actuator' => ['required', 'in:lampu,mist'],
            'status'   => ['required', 'in:on,off'],
            'bedengan' => ['nullable', 'string', 'max:50'],
        ]);

        $device = Device::updateOrCreate(
            ['name' => $data['name']],
            [
                'last_seen_at' => now(),
                'ip_address'   => $request->ip(),
                'bedengan'     => $data['bedengan'] ?? Device::where('name', $data['name'])->value('bedengan'),
            ]
        );

        $activity = DeviceActivity::create([
            'device_name' => $device->name,
            'actuator'    => $data['actuator'],
            'status'      => $data['status'],
            'source'      => 'esp',
            'bedengan'    => $device->bedengan,
        ]);

        return response()->json(['success' => true, 'activity' => $activity->id]);
    }
}
