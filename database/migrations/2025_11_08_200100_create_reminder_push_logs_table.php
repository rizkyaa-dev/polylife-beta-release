<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_push_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reminder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('milestone_seconds');
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamps();

            $table->unique(['reminder_id', 'user_id', 'milestone_seconds'], 'reminder_push_unique');
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_push_logs');
    }
};
