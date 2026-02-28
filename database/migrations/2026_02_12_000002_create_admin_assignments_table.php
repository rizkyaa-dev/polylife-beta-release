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
        Schema::create('admin_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('affiliation_type', 40)->default('other');
            $table->string('affiliation_name', 160);
            $table->string('position_name', 120)->nullable();

            $table->string('whatsapp_number', 25)->nullable();
            $table->string('instagram_handle', 80)->nullable();
            $table->string('telegram_username', 80)->nullable();
            $table->string('contact_email', 150)->nullable();

            $table->string('status', 32)->default('active');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revoke_reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'affiliation_type', 'affiliation_name'],
                'admin_assignments_user_affiliation_unique'
            );
            $table->index(['affiliation_name', 'status'], 'admin_assignments_affiliation_status_idx');
            $table->index(['status', 'assigned_at'], 'admin_assignments_status_assigned_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_assignments');
    }
};
