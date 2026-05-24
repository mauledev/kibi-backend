<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('school_id')->nullable()->constrained('schools');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('action', 100)->notNull();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->jsonb('struct_before')->nullable();
            $table->jsonb('struct_after')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['school_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
