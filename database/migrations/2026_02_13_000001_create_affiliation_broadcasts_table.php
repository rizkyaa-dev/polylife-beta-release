<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('affiliation_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->text('body');
            $table->string('image_path', 255)->nullable();
            $table->string('target_mode', 20)->default('affiliation'); // affiliation | global
            $table->boolean('send_push')->default(true);
            $table->string('status', 20)->default('draft'); // draft | published | archived
            $table->timestamp('published_at')->nullable();
            $table->timestamp('push_started_at')->nullable();
            $table->timestamp('push_completed_at')->nullable();
            $table->unsignedInteger('push_success_count')->default(0);
            $table->unsignedInteger('push_failed_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['created_by', 'status']);
            $table->index('target_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliation_broadcasts');
    }
};
