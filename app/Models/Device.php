<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = ['name', 'type', 'bedengan', 'last_seen_at', 'ip_address'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function isOnline(int $thresholdSeconds = 90): bool
    {
        return $this->last_seen_at
            && $this->last_seen_at->diffInSeconds(now()) <= $thresholdSeconds;
    }
}
