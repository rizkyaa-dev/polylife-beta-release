<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tugas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('matkul_id')->nullable()->constrained('matkuls')->nullOnDelete();
            $table->string('nama_tugas', 150);
            $table->text('deskripsi')->nullable();
            $table->dateTime('deadline');
            $table->boolean('status_selesai')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'deadline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tugas');
    }
};
