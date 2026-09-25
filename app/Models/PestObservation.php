<?php

namespace App\Models;

use App\Enums\AttackLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PestObservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'bedengan_id',
        'created_by',
        'observation_date',
        'attack_level',
        'insect_count',
        'notes',
    ];

    protected $casts = [
        'attack_level' => AttackLevel::class,
        'observation_date' => 'date',
        'insect_count' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function bedengan(): BelongsTo
    {
        return $this->belongsTo(Bedengan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
