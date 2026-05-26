<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_subject_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('group_id')->constrained('groups');
            $table->timestampTz('assigned_at')->useCurrent();
            $table->timestampTz('unassigned_at')->nullable();

            $table->index(['user_id', 'unassigned_at']);

            // Partial unique index: only one active teacher per subject+group
            // where unassigned_at IS NULL. PostgreSQL partial index syntax
            // is applied via a raw statement after table creation.
        });

        // Partial unique index — only enforced while unassigned_at IS NULL
        DB::statement(
            'CREATE UNIQUE INDEX teacher_subject_groups_active_unique
             ON teacher_subject_groups (subject_id, group_id)
             WHERE unassigned_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_subject_groups');
    }
};
