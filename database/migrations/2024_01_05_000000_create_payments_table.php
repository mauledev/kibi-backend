<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();

            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('status', 30)->default('pending');
            $table->string('payer_name', 255);
            $table->string('reference', 100)->nullable();

            // Amounts stored as integer cents to avoid float drift.
            $table->bigInteger('amount_cents');
            $table->bigInteger('received_amount_cents')->nullable();

            $table->char('currency', 3)->default('MXN'); // ISO 4217
            $table->timestampTz('paid_at')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'school_id']);
            $table->index(['tenant_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
