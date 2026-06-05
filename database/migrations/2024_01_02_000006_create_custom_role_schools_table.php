<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_role_schools', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles');
            $table->foreignId('school_id')->constrained('schools');
            $table->primary(['role_id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_role_schools');
    }
};
