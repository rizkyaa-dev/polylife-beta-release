<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chat_messages', function (Blueprint $table): void {
            $table->string('status', 20)->default('completed')->after('role');
            $table->string('error_code', 80)->nullable()->after('tool_results_json');
            $table->index(['session_id', 'status', 'id'], 'ai_messages_session_status_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_messages', function (Blueprint $table): void {
            $table->dropIndex('ai_messages_session_status_id_idx');
            $table->dropColumn(['status', 'error_code']);
        });
    }
};
