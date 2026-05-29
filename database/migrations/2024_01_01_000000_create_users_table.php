<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->boolean('is_staff')->default(false);
            $table->string('email')->unique();
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password_hash')->nullable();
            $table->string('google_id', 100)->nullable();
            $table->string('microsoft_id', 100)->nullable();
            $table->string('first_name', 100);
            $table->string('last_name_paternal', 100);
            $table->string('last_name_maternal', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['tenant_id', 'status']);
            $table->index('is_staff');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
