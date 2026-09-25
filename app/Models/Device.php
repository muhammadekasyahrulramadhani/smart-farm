<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'device_id',
        'name',
        'status',
        'last_seen',
        'firmware_version',
    ];

    protected $casts = [
        'status' => DeviceStatus::class,
        'last_seen' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function actuators(): HasMany
    {
        return $this->hasMany(Actuator::class);
    }

    public function actuatorCommands(): HasMany
    {
        return $this->hasMany(ActuatorCommand::class);
    }
}
