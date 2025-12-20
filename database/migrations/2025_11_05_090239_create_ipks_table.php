<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('target_mode', ['ips', 'ipk'])->default('ips');
            $table->unsignedTinyInteger('semester')->nullable();
            $table->string('academic_year', 9)->nullable(); // e.g. 2024/2025
            $table->decimal('ips_actual', 4, 2)->nullable(); // Semester GPA achieved
            $table->decimal('ips_target', 4, 2)->nullable(); // Semester GPA target
            $table->decimal('ipk_running', 4, 2)->nullable(); // Cumulative GPA after this semester
            $table->decimal('ipk_target', 4, 2)->nullable(); // Desired cumulative GPA
            $table->enum('status', ['planned', 'in_progress', 'final'])->default('planned');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipks');
    }
};
