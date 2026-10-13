<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chat_runs', function (Blueprint $table) {
            $table->string('response_mode', 16)->default('default');
            $table->json('context_policy')->nullable();
        });
        Schema::table('ai_chat_branches', function (Blueprint $table) {
            $table->longText('eco_context_memory')->nullable();
            $table->timestamp('eco_memory_refreshed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_branches', fn (Blueprint $table) => $table->dropColumn(['eco_context_memory', 'eco_memory_refreshed_at']));
        Schema::table('ai_chat_runs', fn (Blueprint $table) => $table->dropColumn(['response_mode', 'context_policy']));
    }
};
