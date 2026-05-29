<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_id')->nullable()->constrained('users');
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('legal_name')->nullable();
            $table->string('rfc', 13)->nullable();
            $table->jsonb('fiscal_address')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // Add FK after tenants exists to resolve circular dependency:
        // users is created before tenants, so the FK can only be added here.
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
        });

        Schema::dropIfExists('tenants');
    }
};
