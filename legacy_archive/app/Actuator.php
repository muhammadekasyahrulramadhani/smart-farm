<?php

namespace App\Models;

use App\Enums\ActuatorState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Actuator extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'actuator_type',
        'state',
        'last_state_change',
    ];

    protected $casts = [
        'state' => ActuatorState::class,
        'last_state_change' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function actuatorCommands(): HasMany
    {
        return $this->hasMany(ActuatorCommand::class);
    }
}
