<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants');
            $table->string('name', 100)->notNull();
            $table->string('slug', 100)->notNull();
            $table->smallInteger('hierarchy_level')->notNull();
            $table->boolean('is_system_role')->default(false);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();

            $table->index(['tenant_id', 'slug']);
            $table->index('hierarchy_level');
            $table->index('is_system_role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
