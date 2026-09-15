<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_ai_assistants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('assistant_name', 60)->default('PolyBot');
            $table->string('personality_tone', 30)->default('friendly_peer');
            $table->text('custom_instructions')->nullable();
            $table->string('avatar_type', 40)->default('default');
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_ai_assistants');
    }
};
