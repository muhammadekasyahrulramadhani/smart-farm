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

        try {
            $actuatorCommand = $publisher->send($actuator, $command, $duration);
        } catch (\Throwable $e) {
            // MQTT broker unavailable: command already marked FAILED by the
            // publisher. Dashboard must not crash; manual input still works.
            return redirect()->route('dashboard')
                ->with('error', 'MQTT broker tidak tersedia. Perangkat tidak dapat dihubungi.');
        }

        return redirect()->route('dashboard')
            ->with('success', "Perintah {$command} untuk {$actuator->actuator_type} dikirim (request {$actuatorCommand->request_id}).");
    }

    private function defaultDurationFor(Actuator $actuator): int
    {
        return $actuator->actuator_type === 'MIST_MAKER'
            ? DashboardController::MIST_DEFAULT_DURATION
            : DashboardController::UV_DEFAULT_DURATION;
    }
}
