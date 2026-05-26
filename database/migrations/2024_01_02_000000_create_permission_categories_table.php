<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('school_id')->nullable()->constrained('schools');
            $table->string('name', 100)->notNull();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();

            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_categories');
    }
};
