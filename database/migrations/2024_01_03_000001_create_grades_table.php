<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('level_id')->constrained('levels');
            $table->string('name', 50);
            $table->smallInteger('sequence');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['level_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
