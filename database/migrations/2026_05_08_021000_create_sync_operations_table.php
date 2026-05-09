<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('operation_uuid');
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('action', 40);
            $table->string('status', 30)->default('synced');
            $table->json('response_json')->nullable();
            $table->json('error_json')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'operation_uuid'], 'sync_operations_user_operation_unique');
            $table->index(['user_id', 'entity_type', 'entity_id'], 'sync_operations_entity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
    }
};
