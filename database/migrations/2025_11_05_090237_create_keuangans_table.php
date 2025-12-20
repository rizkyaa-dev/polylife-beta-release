<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keuangans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('jenis', ['pemasukan', 'pengeluaran']);
            $table->string('kategori', 100)->nullable();
            $table->decimal('nominal', 14, 2);
            $table->text('deskripsi')->nullable();
            $table->date('tanggal');
            $table->timestamps();

            $table->index(['user_id', 'tanggal']);
            $table->index(['user_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangans');
    }
};
