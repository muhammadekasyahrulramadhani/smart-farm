<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pest_observations', function (Blueprint $table) {
            $table->string('pest_type')->nullable()->after('bedengan');
            $table->integer('affected_plants')->nullable()->after('count');
            $table->text('notes')->nullable()->after('affected_plants');
        });
    }

    public function down(): void
    {
        Schema::table('pest_observations', function (Blueprint $table) {
            $table->dropColumn(['pest_type', 'affected_plants', 'notes']);
        });
    }
};
