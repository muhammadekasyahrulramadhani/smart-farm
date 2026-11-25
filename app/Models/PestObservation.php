<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PestObservation extends Model
{
    protected $fillable = [
        'date',
        'bedengan',
        'pest_type',
        'severity',
        'count',
        'affected_plants',
        'notes',
        'user',
    ];
}
