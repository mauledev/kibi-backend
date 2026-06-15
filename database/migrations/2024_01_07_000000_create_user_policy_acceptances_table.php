<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_policy_acceptances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users');
            $table->string('policy_type', 50);   // e.g. 'pur'
            $table->string('version', 20);        // e.g. '1.0'
            $table->timestampTz('accepted_at')->useCurrent();
            $table->string('ip', 45)->nullable(); // acceptance origin (compliance trace)
            $table->timestampsTz();

            // One acceptance per user + policy + version (idempotency backstop).
            $table->unique(['user_id', 'policy_type', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_policy_acceptances');
    }
};
