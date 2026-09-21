<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_science_executions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('ai_chat_runs')->cascadeOnDelete();
            $table->foreignId('step_id')->unique()->constrained('ai_chat_run_steps')->cascadeOnDelete();
            $table->string('status', 24)->default('waiting_client');
            $table->unsignedInteger('attempt')->default(0);
            $table->unsignedInteger('server_attempts')->default(0);
            $table->string('client_id', 36)->nullable();
            $table->uuid('claim_token')->nullable();
            $table->timestamp('lease_expires_at');
            $table->timestamp('deadline_at');
            $table->longText('private_payload')->nullable();
            $table->timestamps();
            $table->index(['status', 'lease_expires_at']);
            $table->index(['run_id', 'status']);
        });
        Schema::table('ai_chat_runs', function (Blueprint $table): void {
            $table->boolean('science_client')->default(false);
            // No cyclic cascading FK: every lookup also checks execution.run_id.
            $table->unsignedBigInteger('science_execution_id')->nullable();
            $table->uuid('claim_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_runs', function (Blueprint $table): void {
            $table->dropColumn(['science_client', 'science_execution_id', 'claim_token']);
        });
        Schema::dropIfExists('ai_science_executions');
    }
};
