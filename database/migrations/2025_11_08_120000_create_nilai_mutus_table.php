<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nilai_mutus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('kampus', 150)->nullable();
            $table->string('program_studi', 150)->nullable();
            $table->string('kurikulum', 20)->nullable();
            $table->json('grades_plus_minus')->nullable(); // e.g. [{"letter":"A-","min":80,"max":84.9,"point":3.7}]
            $table->json('grades_ab')->nullable(); // e.g. [{"letter":"AB","min":80,"max":84.9,"point":3.7}]
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'kampus', 'program_studi']);
            $table->unique(['user_id', 'kampus', 'program_studi', 'kurikulum'], 'nilai_mutu_unique_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_mutus');
    }
};
