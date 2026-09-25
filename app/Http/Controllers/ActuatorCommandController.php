<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreActuatorCommandRequest;
use App\Models\Actuator;
use App\Services\Mqtt\ActuatorCommandPublisher;
use Illuminate\Http\RedirectResponse;

class ActuatorCommandController extends Controller
{
    public function store(
        Actuator $actuator,
        StoreActuatorCommandRequest $request,
        ActuatorCommandPublisher $publisher
    ): RedirectResponse {
        $data = $request->validated();
        $command = strtoupper($data['command']);

        // Duration is required for ON, ignored for OFF.
        $duration = $command === 'ON'
            ? (int) ($data['duration'] ?? $this->defaultDurationFor($actuator))
            : null;

        $friendlyName = $this->friendlyActuatorName($actuator);

        try {
            $publisher->send($actuator, $command, $duration);
        } catch (\Throwable $e) {
            // MQTT broker unavailable: command already marked FAILED by the
            // publisher. Dashboard must not crash; manual input still works.
            // request_id tetap tersimpan di database untuk tracing/debugging.
            return redirect()->route('dashboard')
                ->with('error', "Perintah {$friendlyName} gagal dikirim.");
        }

        $action = $command === 'ON' ? 'dinyalakan' : 'dimatikan';

        return redirect()->route('dashboard')
            ->with('success', "{$friendlyName} berhasil {$action}.");
    }

    private function friendlyActuatorName(Actuator $actuator): string
    {
        return match ($actuator->actuator_type) {
            'UV_LIGHT' => 'Lampu UV',
            'MIST_MAKER' => 'Mist Maker',
            default => $actuator->actuator_type,
        };
    }

    private function defaultDurationFor(Actuator $actuator): int
    {
        return $actuator->actuator_type === 'MIST_MAKER'
            ? DashboardController::MIST_DEFAULT_DURATION
            : DashboardController::UV_DEFAULT_DURATION;
    }
}
