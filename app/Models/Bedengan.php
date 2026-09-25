<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bedengan extends Model
{
    use HasFactory;

    protected $table = 'bedengans';

    protected $fillable = [
        'project_id',
        'name',
        'description',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function pestObservations(): HasMany
    {
        return $this->hasMany(PestObservation::class);
    }

    public function sprayingRecords(): HasMany
    {
        return $this->hasMany(SprayingRecord::class);
    }
}
