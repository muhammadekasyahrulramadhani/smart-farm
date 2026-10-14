<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Akun default 2 permission (login pakai username, tanpa email)
        User::updateOrCreate(
            ['username' => 'admin'],
            ['name' => 'Admin', 'email' => 'admin@smartfarm.com', 'password' => 'admin123', 'role' => 'admin']
        );

        User::updateOrCreate(
            ['username' => 'user'],
            ['name' => 'User', 'email' => 'user@smartfarm.com', 'password' => 'user123', 'role' => 'user']
        );

        // Bedengan default
        \App\Models\Bedengan::firstOrCreate(['name' => 'Bedengan 1']);
        \App\Models\Bedengan::firstOrCreate(['name' => 'Bedengan 2']);
        \App\Models\Bedengan::firstOrCreate(['name' => 'Bedengan 3']);

        // Perangkat IoT default (status koneksi via heartbeat ESP32)
        \App\Models\Device::firstOrCreate(['name' => 'Lampu-UV-01'], ['type' => 'lampu', 'bedengan' => 'Bedengan 1']);
        \App\Models\Device::firstOrCreate(['name' => 'Mist-Maker-01'], ['type' => 'mist', 'bedengan' => 'Bedengan 1']);
    }
}
