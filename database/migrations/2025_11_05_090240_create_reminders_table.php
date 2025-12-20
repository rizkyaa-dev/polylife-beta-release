<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('todolist_id')->nullable()->constrained('todolists')->onDelete('cascade');
            $table->foreignId('tugas_id')->nullable()->constrained('tugas')->onDelete('cascade');
            $table->foreignId('jadwal_id')->nullable()->constrained('jadwals')->onDelete('cascade');
            $table->foreignId('kegiatan_id')->nullable()->constrained('kegiatans')->onDelete('cascade');
            $table->dateTime('waktu_reminder');
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'waktu_reminder']);
            $table->index(['user_id', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
