<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chat_runs', function (Blueprint $table): void {
            $table->unsignedSmallInteger('attempts')->default(0)->after('status');
            $table->boolean('retryable')->default(false)->after('error_code');
            $table->timestamp('heartbeat_at')->nullable()->after('started_at');
            $table->timestamp('lease_expires_at')->nullable()->after('heartbeat_at')->index();
        });

        Schema::table('ai_chat_run_steps', function (Blueprint $table): void {
            $table->longText('private_payload')->nullable()->after('public_metadata');
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_run_steps', function (Blueprint $table): void {
            $table->dropColumn('private_payload');
        });

        Schema::table('ai_chat_runs', function (Blueprint $table): void {
            $table->dropIndex(['lease_expires_at']);
            $table->dropColumn(['attempts', 'retryable', 'heartbeat_at', 'lease_expires_at']);
        });
    }
};
