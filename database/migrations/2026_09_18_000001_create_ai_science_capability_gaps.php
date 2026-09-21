<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_science_capability_gaps', function (Blueprint $table): void {
            $table->id();
            $table->string('kernel_version', 64);
            $table->string('capability', 64);
            $table->unsignedBigInteger('unsupported_count')->default(0);
            $table->unsignedBigInteger('prepared_count')->default(0);
            $table->unsignedBigInteger('client_result_count')->default(0);
            $table->unsignedBigInteger('failure_count')->default(0);
            $table->timestamps();
            $table->unique(['kernel_version', 'capability']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_science_capability_gaps');
    }
};
