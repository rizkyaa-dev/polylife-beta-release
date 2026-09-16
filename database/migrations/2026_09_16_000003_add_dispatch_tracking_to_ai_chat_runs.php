<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chat_runs', function (Blueprint $table): void {
            $table->unsignedSmallInteger('dispatch_attempts')->default(0)->after('attempts');
            $table->timestamp('last_dispatched_at')->nullable()->after('heartbeat_at');
            $table->index(
                ['status', 'heartbeat_at', 'last_dispatched_at'],
                'ai_runs_dispatch_recovery_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_runs', function (Blueprint $table): void {
            $table->dropIndex('ai_runs_dispatch_recovery_idx');
            $table->dropColumn(['dispatch_attempts', 'last_dispatched_at']);
        });
    }
};
