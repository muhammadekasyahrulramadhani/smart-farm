<?php

namespace App\Services\Dashboard;

use App\Models\Bedengan;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SprayingReminderService
{
    public const INTERVAL_DAYS = 7;

    /**
     * Build per-bedengan reminder: last spraying date, next date (+7 days),
     * and days remaining. Data source: spraying_records only.
     *
     * @return Collection<int, array{bedengan: Bedengan, last_date: ?Carbon, next_date: ?Carbon, days_remaining: ?int}>
     */
    public function forProject(Project $project): Collection
    {
        return $project->bedengans()
            ->with(['sprayingRecords' => fn ($q) => $q->orderBy('spraying_date', 'desc')->orderBy('spraying_time', 'desc')])
            ->get()
            ->map(function (Bedengan $bedengan) {
                $last = $bedengan->sprayingRecords->first();

                if (! $last) {
                    return [
                        'bedengan' => $bedengan,
                        'last_date' => null,
                        'next_date' => null,
                        'days_remaining' => null,
                    ];
                }

                $lastDate = Carbon::parse($last->spraying_date)->startOfDay();
                $nextDate = $lastDate->copy()->addDays(self::INTERVAL_DAYS);
                $daysRemaining = (int) Carbon::today()->diffInDays($nextDate, false);

                return [
                    'bedengan' => $bedengan,
                    'last_date' => $lastDate,
                    'next_date' => $nextDate,
                    'days_remaining' => $daysRemaining,
                ];
            });
    }
}
