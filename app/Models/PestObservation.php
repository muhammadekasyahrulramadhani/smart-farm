<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PestObservation extends Model
{
    protected $fillable = ['date', 'bedengan', 'severity', 'count', 'user'];
}
