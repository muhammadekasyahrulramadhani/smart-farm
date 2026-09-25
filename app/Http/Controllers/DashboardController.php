<?php

namespace App\Http\Controllers;

use App\Models\Actuator;
use App\Models\ActuatorCommand;
use App\Models\Project;
use App\Services\Dashboard\DashboardService;
use App\Services\Dashboard\SprayingReminderService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public const UV_DEFAULT_DURATION = 30; // minutes

    public const MIST_DEFAULT_DURATION = 5; // minutes

    public function index(
        Request $request,
        DashboardService $dashboard,
        SprayingReminderService $reminders
    ): View {
        $project = Project::with(['bedengans', 'devices.actuators'])->firstOrFail();

        $bedenganFilter = $request->integer('bedengan_id') ?: null;
        if ($bedenganFilter && ! $project->bedengans->contains('id', $bedenganFilter)) {
            $bedenganFilter = null;
        }

        // Reminder interval 7 hari / bedengan (dari spraying_records).
        $reminderItems = $reminders->forProject($project);

        // Riwayat penyemprotan terbaru.
        $sprayingHistory = $project->sprayingRecords()
            ->with(['bedengan', 'user'])
            ->orderBy('spraying_date', 'desc')
            ->orderBy('spraying_time', 'desc')
            ->limit(20)
            ->get();

        // Kalender bulan berjalan.
        $calYear = (int) $request->input('cal_year', now()->year);
        $calMonth = (int) $request->input('cal_month', now()->month);
        $calMonth = max(1, min(12, $calMonth));
        $sprayingDates = $dashboard->sprayingDatesInMonth($project, $calYear, $calMonth);
        $monthStart = Carbon::create($calYear, $calMonth, 1)->startOfDay();
        $calendarCells = $this->buildCalendarCells($monthStart);

        // Detail tanggal kalender yang dipilih.
        $selectedDate = $request->input('selected_date');
        $selectedSprayings = collect();
        if ($selectedDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
            $selectedSprayings = $project->sprayingRecords()
                ->with(['bedengan', 'user'])
                ->whereDate('spraying_date', $selectedDate)
                ->orderBy('spraying_time')
                ->get();
        }

        // Grafik: agregasi SUM per tanggal.
        $pestTrend = $dashboard->pestTrend($project, $bedenganFilter);
        $sprayingDurations = $dashboard->sprayingDurationHistory($project, $bedenganFilter);

        // Actuator per device (UV_LIGHT & MIST_MAKER).
        $devices = $project->devices()->with(['actuators', 'actuators.actuatorCommands' => function ($q) {
            $q->latest()->limit(5);
        }])->get();

        $actuatorPanels = [];
        foreach ($devices as $device) {
            foreach ($device->actuators as $actuator) {
                $actuatorPanels[] = $this->buildActuatorPanel($actuator);
            }
        }

        // Riwayat command actuator terbaru.
        $commandHistory = ActuatorCommand::with(['device', 'actuator'])
            ->whereIn('device_id', $project->devices()->pluck('id'))
            ->latest()
            ->limit(15)
            ->get();

        return view('dashboard.index', [
            'project' => $project,
            'bedenganFilter' => $bedenganFilter,
            'reminderItems' => $reminderItems,
            'sprayingHistory' => $sprayingHistory,
            'calYear' => $calYear,
            'calMonth' => $calMonth,
            'monthStart' => $monthStart,
            'calendarCells' => $calendarCells,
            'sprayingDates' => $sprayingDates,
            'selectedDate' => $selectedDate,
            'selectedSprayings' => $selectedSprayings,
            'pestTrend' => $pestTrend,
            'sprayingDurations' => $sprayingDurations,
            'devices' => $devices,
            'actuatorPanels' => $actuatorPanels,
            'commandHistory' => $commandHistory,
            'uvDefault' => self::UV_DEFAULT_DURATION,
            'mistDefault' => self::MIST_DEFAULT_DURATION,
        ]);
    }

    /**
     * Build 6x7 calendar grid cells for the month view.
     *
     * @return array<int, array{date: Carbon, in_month: bool}>
     */
    private function buildCalendarCells(Carbon $monthStart): array
    {
        // Monday-first grid.
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $cells = [];
        for ($i = 0; $i < 42; $i++) {
            $date = $gridStart->copy()->addDays($i);
            $cells[] = [
                'date' => $date,
                'in_month' => $date->month === $monthStart->month,
            ];
        }

        return $cells;
    }

    /**
     * Build actuator panel data including auto-off countdown basis.
     * Auto-off source of truth is the device (via last_state_change);
     * the browser countdown is display-only.
     *
     * @return array{actuator: Actuator, default_duration: int, remaining_seconds: ?int, last_command: ?ActuatorCommand}
     */
    private function buildActuatorPanel(Actuator $actuator): array
    {
        $defaultDuration = $actuator->actuator_type === 'MIST_MAKER'
            ? self::MIST_DEFAULT_DURATION
            : self::UV_DEFAULT_DURATION;

        $lastCommand = $actuator->actuatorCommands()->latest()->first();

        $remainingSeconds = null;
        if ($actuator->state->value === 'ON' && $actuator->last_state_change && $lastCommand?->duration) {
            $elapsed = now()->diffInSeconds($actuator->last_state_change);
            $total = ((int) $lastCommand->duration) * 60;
            $remainingSeconds = max(0, $total - $elapsed);
        }

        return [
            'actuator' => $actuator,
            'default_duration' => $defaultDuration,
            'remaining_seconds' => $remainingSeconds,
            'last_command' => $lastCommand,
        ];
    }
}
