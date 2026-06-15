<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_tutors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tutor_user_id')->constrained('users');
            $table->foreignId('student_user_id')->constrained('users');
            $table->string('relationship', 50)->nullable();
            $table->timestampTz('linked_at')->useCurrent();
            $table->timestampTz('unlinked_at')->nullable();

            // Unique active link per tutor+student pair (partial index — only when unlinked_at IS NULL)
            $table->index('student_user_id');
        });

        // Partial unique index: only one active link per tutor+student pair
        DB::statement('CREATE UNIQUE INDEX student_tutors_active_unique ON student_tutors (tutor_user_id, student_user_id) WHERE unlinked_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('student_tutors');
    }
};
