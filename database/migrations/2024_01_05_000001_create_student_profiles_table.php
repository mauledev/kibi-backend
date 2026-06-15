<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the student_profiles table — one row per student user.
     * Student-specific profile data is stored here to avoid polluting the
     * users table with school-domain fields. The user_id FK is unique
     * (one profile per user) and references the users table.
     */
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->date('birth_date')->nullable();
            $table->string('national_id', 50)->nullable()->comment('CURP/RUT/CPF/DNI depending on country');
            $table->string('enrollment_number', 50)->nullable();
            $table->string('gender', 20)->nullable()->comment('male, female, other, prefer_not_to_say');
            $table->string('blood_type', 5)->nullable();
            $table->foreignId('group_id')->nullable()->constrained('groups');
            $table->timestampsTz();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
