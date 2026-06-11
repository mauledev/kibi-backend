<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_progress', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->unique();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->smallInteger('current_step')->default(1);
            $table->string('status', 20)->default('in_progress');
            $table->timestampTz('grace_period_ends_at');
            $table->timestampsTz();
        });

        Schema::create('onboarding_step_status', function (Blueprint $table) {
            $table->unsignedBigInteger('progress_id');
            $table->foreign('progress_id')->references('id')->on('onboarding_progress')->cascadeOnDelete();
            $table->smallInteger('step');
            $table->string('name', 40);
            $table->string('status', 20)->default('pending');
            $table->timestampTz('completed_at')->nullable();
            $table->primary(['progress_id', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_step_status');
        Schema::dropIfExists('onboarding_progress');
    }
};
