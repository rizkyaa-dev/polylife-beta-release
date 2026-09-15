<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120)->default('Percakapan Baru');
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('ai_chat_sessions')->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'system', 'tool']);
            $table->text('content')->nullable();
            $table->json('tool_calls_json')->nullable();
            $table->json('tool_results_json')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
        });

        Schema::create('ai_action_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('action_id', 64)->unique();
            $table->foreignId('session_id')->nullable()->constrained('ai_chat_sessions')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tool_name', 60);
            $table->text('summary');
            $table->json('payload_json');
            $table->string('signature', 64);
            $table->enum('status', ['pending', 'confirmed', 'rejected', 'expired'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_action_proposals');
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chat_sessions');
    }
};
