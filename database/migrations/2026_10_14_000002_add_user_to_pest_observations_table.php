<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pest_observations', function (Blueprint $table) {
            if (!Schema::hasColumn('pest_observations', 'user')) {
                $table->string('user')->default('admin')->after('count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pest_observations', function (Blueprint $table) {
            $table->dropColumn('user');
        });
    }
};
