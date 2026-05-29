<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('levels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained('schools');
            $table->string('name', 100);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('levels');
    }
};
