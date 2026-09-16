<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chat_runs', function (Blueprint $table): void {
            $table->uuid('request_id')->nullable()->after('id')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_runs', function (Blueprint $table): void {
            $table->dropUnique(['request_id']);
            $table->dropColumn('request_id');
        });
    }
};
