<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chat_runs', function (Blueprint $table): void {
            $table->unsignedInteger('prompt_tokens')->default(0)->after('duration_ms');
            $table->unsignedInteger('completion_tokens')->default(0)->after('prompt_tokens');
            $table->unsignedInteger('total_tokens')->default(0)->after('completion_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_runs', function (Blueprint $table): void {
            $table->dropColumn(['prompt_tokens', 'completion_tokens', 'total_tokens']);
        });
    }
};
