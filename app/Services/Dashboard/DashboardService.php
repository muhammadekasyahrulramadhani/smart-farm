<?php

namespace App\Services\Dashboard;

use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * Pest population trend aggregated with SUM(insect_count) per
     * observation_date. Optional bedengan filter.
     *
     * @return Collection<int, array{date: string, total: int}>
     */
    public function pestTrend(Project $project, ?int $bedenganId = null): Collection
    {
        $query = $project->pestObservations()
            ->selectRaw('observation_date, SUM(insect_count) as total')
            ->when($bedenganId, fn ($q) => $q->where('bedengan_id', $bedenganId))
            ->groupBy('observation_date')
            ->orderBy('observation_date');

        return $query->get()->map(fn ($row) => [
            'date' => Carbon::parse($row->observation_date)->toDateString(),
            'total' => (int) $row->total,
        ]);
    }

    /**
     * Spraying duration history aggregated with SUM(duration_minutes)
     * per spraying_date. spraying_records is the source of truth —
     * never actuator_commands.
     *
     * @return Collection<int, array{date: string, total: int}>
     */
    public function sprayingDurationHistory(Project $project, ?int $bedenganId = null): Collection
    {
        $query = $project->sprayingRecords()
            ->selectRaw('spraying_date, SUM(duration_minutes) as total')
            ->when($bedenganId, fn ($q) => $q->where('bedengan_id', $bedenganId))
            ->groupBy('spraying_date')
            ->orderBy('spraying_date');

        return $query->get()->map(fn ($row) => [
            'date' => Carbon::parse($row->spraying_date)->toDateString(),
            'total' => (int) $row->total,
        ]);
    }

    /**
     * Dates (Y-m-d) in the given month that have spraying records.
     * Used to mark the calendar.
     *
     * @return array<string, bool>
     */
    public function sprayingDatesInMonth(Project $project, int $year, int $month): array
    {
        $dates = $project->sprayingRecords()
            ->whereYear('spraying_date', $year)
            ->whereMonth('spraying_date', $month)
            ->pluck('spraying_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->values();

        return $dates->mapWithKeys(fn ($d) => [$d => true])->all();
    }
}
