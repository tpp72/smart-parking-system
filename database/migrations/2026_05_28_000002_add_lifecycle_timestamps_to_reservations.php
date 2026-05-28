<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('checked_in_at', 0)->nullable()->after('reserve_start');
            $table->timestamp('completed_at',  0)->nullable()->after('checked_in_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['checked_in_at', 'completed_at']);
        });
    }
};
