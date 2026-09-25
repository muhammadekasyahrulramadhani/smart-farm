<?php

namespace App\Models;

use App\Enums\CommandStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActuatorCommand extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'actuator_id',
        'command',
        'duration',
        'request_id',
        'status',
        'sent_at',
        'acknowledged_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => CommandStatus::class,
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function actuator(): BelongsTo
    {
        return $this->belongsTo(Actuator::class);
    }
}
