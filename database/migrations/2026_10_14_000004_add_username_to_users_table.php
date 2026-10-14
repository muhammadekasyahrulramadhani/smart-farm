<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->unique()->after('name');
            }
        });

        // Backfill username untuk data lama
        foreach (DB::table('users')->whereNull('username')->get() as $u) {
            $base = $u->name
                ? strtolower(preg_replace('/[^a-z0-9]+/i', '', $u->name))
                : strstr($u->email, '@', true);
            $base = $base ?: ('user'.$u->id);
            $candidate = $base;
            $i = 1;
            while (DB::table('users')->where('username', $candidate)->exists()) {
                $candidate = $base.$i;
                $i++;
            }
            DB::table('users')->where('id', $u->id)->update(['username' => $candidate]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
