<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('matkul_id_list')->nullable(); // simpan seperti "1;4;7;" untuk referensi matkul
            $table->string('jenis', 20)->default('kuliah'); // kuliah, libur, uts, uas, lomba, dll
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->unsignedTinyInteger('semester')->nullable();
            $table->text('catatan_tambahan')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'tanggal_mulai']);
            $table->index(['user_id', 'tanggal_selesai']);
            $table->index(['user_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwals');
    }
};
