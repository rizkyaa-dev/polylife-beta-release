<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chat_runs', function (Blueprint $table): void {
            $table->unsignedInteger('model_calls')->default(0);
            $table->unsignedInteger('measured_model_calls')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_runs', function (Blueprint $table): void {
            $table->dropColumn(['model_calls', 'measured_model_calls']);
        });
    }
};
