<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_run_admission_locks', function (Blueprint $table): void {
            $table->string('name', 32)->primary();
        });
        DB::table('ai_run_admission_locks')->insert(['name' => 'global']);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_run_admission_locks');
    }
};
