<?php

namespace App\Models;

use App\Enums\EventSeverity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'device_id',
        'event_type',
        'severity',
        'message',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'severity' => EventSeverity::class,
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
