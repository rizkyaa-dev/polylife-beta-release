<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matkuls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('kode', 20);
            $table->string('nama', 150);
            $table->string('kelas', 255);
            $table->string('dosen', 120);
            $table->unsignedTinyInteger('semester')->default(1);
            $table->unsignedTinyInteger('sks')->default(2);
            $table->string('hari', 255);
            $table->string('jam_mulai', 255);
            $table->string('jam_selesai', 255);
            $table->string('ruangan', 255);
            $table->string('warna_label', 20)->default('#2563eb');
            $table->text('catatan');
            $table->timestamps();

            $table->unique(['user_id', 'kode']);
            $table->index(['user_id', 'semester']);
            $table->index(['user_id', 'hari', 'jam_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matkuls');
    }
};
