<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SprayHistory extends Model
{
    protected $fillable = ['date', 'time', 'bedengan', 'duration', 'notes', 'user'];
}
