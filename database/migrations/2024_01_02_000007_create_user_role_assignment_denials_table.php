<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_role_assignment_denials', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('role_user_assignment_id')->constrained('user_role_assignments');
            $table->foreignId('permission_id')->constrained('permissions');
            $table->unique(['role_user_assignment_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_assignment_denials');
    }
};
