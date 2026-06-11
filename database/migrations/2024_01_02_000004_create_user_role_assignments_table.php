<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_role_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('role_id')->constrained('roles');
            $table->foreignId('school_id')->nullable()->constrained('schools');
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            $table->timestampTz('assigned_at')->useCurrent();
            $table->timestampTz('revoked_at')->nullable();

            $table->index(['user_id', 'revoked_at']);
            $table->index(['school_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_assignments');
    }
};
