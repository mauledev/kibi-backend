<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only log of every state change a payment goes through.
        // Surfaces as `state_log` in the payment detail endpoint.
        Schema::create('payment_state_transitions', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->foreignId('payment_id')->constrained('payments');

            $table->string('event', 30); // created | approved | rejected | observation_requested
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);

            // Nullable when the transition was triggered by the system rather than a user.
            $table->foreignId('actor_user_id')->nullable()->constrained('users');

            // Snapshot of the actor's display name at the time of the transition —
            // remains stable even if the user later changes their name.
            $table->string('actor_name', 255);

            // Populated only when event = 'rejected'.
            $table->string('reason', 50)->nullable();

            // Free-text annotation for either approve or reject.
            $table->text('note')->nullable();

            // Append-only: no updated_at.
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['payment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_state_transitions');
    }
};
