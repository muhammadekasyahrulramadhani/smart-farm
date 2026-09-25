<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    public function bedengans(): HasMany
    {
        return $this->hasMany(Bedengan::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
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
