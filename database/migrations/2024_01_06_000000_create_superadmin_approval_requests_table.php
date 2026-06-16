<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('superadmin_approval_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('proposed_by')->constrained('users');
            $table->text('justification');

            // Candidate snapshot — no users row exists until the request is approved.
            $table->string('candidate_email');
            $table->string('candidate_first_name', 100);
            $table->string('candidate_last_name_paternal', 100);
            $table->string('candidate_last_name_maternal', 100)->nullable();
            $table->string('candidate_phone', 30)->nullable();

            $table->string('status', 30)->default('pending_approval'); // pending_approval|approved|rejected|expired
            $table->timestampTz('expires_at');
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestampTz('resolved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_user_id')->nullable()->constrained('users');
            $table->timestampsTz();

            $table->index(['status', 'expires_at']);
            $table->index('candidate_email');
        });

        // Partial unique index — at most ONE live pending request per candidate email.
        // Race backstop for the duplicate-pending check in ProposeSuperadminCreationUseCase.
        DB::statement(
            "CREATE UNIQUE INDEX superadmin_approval_requests_pending_candidate_unique
             ON superadmin_approval_requests (candidate_email)
             WHERE status = 'pending_approval'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('superadmin_approval_requests');
    }
};
