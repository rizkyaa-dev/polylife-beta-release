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
        Schema::create('affiliation_broadcast_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')
                ->constrained('affiliation_broadcasts')
                ->cascadeOnDelete();
            $table->string('affiliation_type', 40)->nullable();
            $table->string('affiliation_name', 160);
            $table->timestamps();

            $table->unique(
                ['broadcast_id', 'affiliation_type', 'affiliation_name'],
                'aff_broadcast_targets_unique'
            );
            $table->index(
                ['affiliation_name', 'affiliation_type'],
                'aff_broadcast_targets_lookup_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliation_broadcast_targets');
    }
};
