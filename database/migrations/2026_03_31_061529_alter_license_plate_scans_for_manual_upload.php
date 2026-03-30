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
        Schema::table('license_plate_scans', function (Blueprint $table) {
            // Make device_id nullable (was required FK)
            $table->foreignId('device_id')->nullable()->change();

            // Who uploaded (manual scan)
            $table->foreignId('user_id')->nullable()->after('device_id')
                ->constrained('users')->nullOnDelete();

            // Detected vehicle (after matching license plate in vehicles table)
            $table->foreignId('vehicle_id')->nullable()->after('user_id')
                ->constrained('vehicles')->nullOnDelete();

            // AI detection results
            $table->string('color', 60)->nullable()->after('license_plate');
            $table->string('brand', 60)->nullable()->after('color');
            $table->float('confidence')->nullable()->after('brand')
                ->comment('OCR confidence 0-100');

            // Blacklist flag
            $table->boolean('is_suspicious')->default(false)->after('confidence');

            // Source: device or manual_upload
            $table->string('source', 20)->default('device')->after('is_suspicious');
        });
    }

    public function down(): void
    {
        Schema::table('license_plate_scans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropColumn(['color', 'brand', 'confidence', 'is_suspicious', 'source']);
            $table->foreignId('device_id')->nullable(false)->change();
        });
    }
};
