<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_id')->constrained('jadwals')->onDelete('cascade');
            $table->string('nama_kegiatan', 120);
            $table->string('lokasi', 120)->nullable();
            $table->time('waktu');
            $table->date('tanggal_deadline')->nullable();
            $table->string('status', 50)->default('belum_dimulai');
            $table->timestamps();

            $table->index(['jadwal_id', 'tanggal_deadline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatans');
    }
};
