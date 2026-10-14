<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_activities', function (Blueprint $table) {
            $table->id();
            $table->string('device_name');
            $table->string('actuator'); // lampu | mist
            $table->string('status'); // on | off
            $table->string('source')->default('esp'); // esp | web
            $table->string('actor')->nullable(); // username bila via web
            $table->string('bedengan')->nullable();
            $table->timestamps();
            $table->index(['device_name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_activities');
    }
};
