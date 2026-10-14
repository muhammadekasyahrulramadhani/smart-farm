<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceActivity extends Model
{
    protected $fillable = ['device_name', 'actuator', 'status', 'source', 'actor', 'bedengan'];
}
