<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipk_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipk_id')->constrained('ipks')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('matkul_id')->nullable()->constrained('matkuls')->nullOnDelete();
            $table->string('course_code', 20)->nullable();
            $table->string('course_name', 150)->nullable();
            $table->unsignedTinyInteger('semester_reference')->nullable();
            $table->unsignedTinyInteger('sks')->default(0);
            $table->decimal('grade_point', 4, 2)->nullable();
            $table->string('grade_letter', 2)->nullable();
            $table->decimal('target_grade_point', 4, 2)->nullable();
            $table->decimal('score_actual', 5, 2)->nullable();
            $table->decimal('score_target', 5, 2)->nullable();
            $table->boolean('is_retake')->default(false);
            $table->enum('status', ['planned', 'in_progress', 'completed'])->default('planned');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'ipk_id']);
            $table->index(['user_id', 'matkul_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipk_courses');
    }
};
