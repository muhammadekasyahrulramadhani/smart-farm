<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SprayingRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'bedengan_id',
        'created_by',
        'spraying_date',
        'spraying_time',
        'duration_minutes',
        'notes',
    ];

    protected $casts = [
        'spraying_date' => 'date',
        'duration_minutes' => 'integer',
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
