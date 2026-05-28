<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('grade_id')->constrained('grades');
            $table->string('name', 50);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('grade_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
