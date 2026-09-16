<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chat_runs', function (Blueprint $table): void {
            $table->index(['status', 'lease_expires_at'], 'ai_runs_status_lease_idx');
            $table->index(['status', 'created_at'], 'ai_runs_status_created_idx');
        });
        Schema::table('ai_action_proposals', function (Blueprint $table): void {
            $table->index(['status', 'expires_at'], 'ai_proposals_status_expiry_idx');
        });
        Schema::table('ai_chat_messages', function (Blueprint $table): void {
            $table->index(['session_id', 'parent_message_id'], 'ai_messages_session_parent_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_runs', function (Blueprint $table): void {
            $table->dropIndex('ai_runs_status_lease_idx');
            $table->dropIndex('ai_runs_status_created_idx');
        });
        Schema::table('ai_action_proposals', function (Blueprint $table): void {
            $table->dropIndex('ai_proposals_status_expiry_idx');
        });
        Schema::table('ai_chat_messages', function (Blueprint $table): void {
            $table->dropIndex('ai_messages_session_parent_idx');
        });
    }
};
