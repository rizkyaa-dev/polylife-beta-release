<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_ai_assistants', function (Blueprint $table) {
            $table->string('thinking_effort', 10)->default('high')->after('personality_tone');
        });

        Schema::table('ai_chat_messages', function (Blueprint $table) {
            $table->longText('reasoning_content')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_messages', function (Blueprint $table) {
            $table->dropColumn('reasoning_content');
        });

        Schema::table('user_ai_assistants', function (Blueprint $table) {
            $table->dropColumn('thinking_effort');
        });
    }
};
