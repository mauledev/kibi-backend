<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_work_schedules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->string('timezone', 64); // IANA name, e.g. 'America/Mexico_City'
            $table->jsonb('days'); // weekday codes, e.g. ["mon","tue","wed","thu","fri"]
            $table->time('start_time'); // 24h time of day
            $table->time('end_time');
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_work_schedules');
    }
};
