<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('ai_chat_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('forked_from_message_id')->nullable()->index();
            $table->unsignedBigInteger('head_message_id')->nullable()->index();
            $table->longText('context_digest')->nullable();
            $table->unsignedBigInteger('digest_through_message_id')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'updated_at']);
        });

        Schema::table('ai_chat_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_message_id')->nullable()->after('session_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->after('parent_message_id')->index();
            $table->uuid('revision_group_id')->nullable()->after('branch_id')->index();
        });

        Schema::table('ai_chat_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('active_branch_id')->nullable()->after('title')->index();
        });

        Schema::create('ai_chat_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('ai_chat_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('user_message_id')->index();
            $table->unsignedBigInteger('assistant_message_id')->nullable()->index();
            $table->unsignedBigInteger('previous_branch_id')->nullable()->index();
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error_code', 120)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'status']);
        });

        Schema::create('ai_chat_run_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('ai_chat_runs')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->enum('kind', ['reasoning_summary', 'tool_call']);
            $table->enum('status', ['running', 'completed', 'failed']);
            $table->string('label', 180);
            $table->string('tool_name', 80)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('public_metadata')->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'sequence']);
        });

        $this->backfillExistingSessions();
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_run_steps');
        Schema::dropIfExists('ai_chat_runs');

        Schema::table('ai_chat_sessions', function (Blueprint $table) {
            $table->dropColumn('active_branch_id');
        });

        Schema::table('ai_chat_messages', function (Blueprint $table) {
            $table->dropColumn(['parent_message_id', 'branch_id', 'revision_group_id']);
        });

        Schema::dropIfExists('ai_chat_branches');
    }

    private function backfillExistingSessions(): void
    {
        DB::table('ai_chat_sessions')->orderBy('id')->eachById(function (object $session): void {
            $now = now();
            $branchId = DB::table('ai_chat_branches')->insertGetId([
                'session_id' => $session->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $parentId = null;

            DB::table('ai_chat_messages')
                ->where('session_id', $session->id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id'])
                ->each(function (object $message) use ($branchId, &$parentId): void {
                    DB::table('ai_chat_messages')->where('id', $message->id)->update([
                        'parent_message_id' => $parentId,
                        'branch_id' => $branchId,
                        'revision_group_id' => (string) Str::uuid(),
                    ]);
                    $parentId = $message->id;
                });

            DB::table('ai_chat_branches')->where('id', $branchId)->update(['head_message_id' => $parentId]);
            DB::table('ai_chat_sessions')->where('id', $session->id)->update(['active_branch_id' => $branchId]);
        });
    }
};
