<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Projects table
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Bedengans table
        Schema::create('bedengans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('project_id');
        });

        // 3. Pest Observations table (Manual input)
        Schema::create('pest_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('bedengan_id')->constrained('bedengans')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->date('observation_date');
            $table->string('attack_level'); // RINGAN, SEDANG, BERAT
            $table->unsignedInteger('insect_count')->default(0); // sticky trap count
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index('bedengan_id');
            $table->index('observation_date');
        });

        // 4. Spraying Records table (Manual input)
        Schema::create('spraying_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('bedengan_id')->constrained('bedengans')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->date('spraying_date');
            $table->time('spraying_time');
            $table->unsignedInteger('duration_minutes');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index('bedengan_id');
            $table->index('spraying_date');
        });

        // 5. Devices table (ESP32 controllers)
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('device_id')->unique(); // e.g. 'SF-ESP32-01'
            $table->string('name');
            $table->string('status')->default('UNKNOWN'); // ONLINE, OFFLINE, UNKNOWN
            $table->timestamp('last_seen')->nullable();
            $table->string('firmware_version')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index('device_id');
            $table->index('status');
        });

        // 6. Actuators table (UV_LIGHT, MIST_MAKER)
        Schema::create('actuators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('actuator_type'); // UV_LIGHT, MIST_MAKER
            $table->string('state')->default('OFF'); // ON, OFF
            $table->timestamp('last_state_change')->nullable();
            $table->timestamps();

            $table->index('device_id');
            $table->index('actuator_type');
            $table->unique(['device_id', 'actuator_type']);
        });

        // 7. Actuator Commands table
        Schema::create('actuator_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('actuator_id')->constrained('actuators')->cascadeOnDelete();
            $table->string('command'); // ON, OFF
            $table->integer('duration')->nullable(); // in seconds or minutes
            $table->string('request_id')->unique();
            $table->string('status')->default('PENDING'); // PENDING, SENT, ACKNOWLEDGED, SUCCESS, FAILED, TIMEOUT
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('device_id');
            $table->index('actuator_id');
            $table->index('request_id');
            $table->index('status');
        });

        // 8. Events table
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('event_type');
            $table->string('severity')->default('info');
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('project_id');
            $table->index('device_id');
            $table->index('event_type');
        });

        // 9. System Logs table
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level');
            $table->string('source');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index('level');
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_logs');
        Schema::dropIfExists('events');
        Schema::dropIfExists('actuator_commands');
        Schema::dropIfExists('actuators');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('spraying_records');
        Schema::dropIfExists('pest_observations');
        Schema::dropIfExists('bedengans');
        Schema::dropIfExists('projects');
    }
};
