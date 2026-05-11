<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliation_templates', function (Blueprint $table) {
            $table->id();
            $table->string('affiliation_type', 40)->nullable();
            $table->string('affiliation_name', 160);
            $table->json('aliases')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['affiliation_type', 'affiliation_name'], 'affiliation_templates_type_name_unique');
            $table->index(['is_active', 'affiliation_name'], 'affiliation_templates_active_name_index');
        });

        Schema::create('affiliation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('affiliation_template_id')->nullable()->constrained('affiliation_templates')->nullOnDelete();
            $table->string('affiliation_type', 40)->nullable();
            $table->string('affiliation_name', 160);
            $table->string('student_id_type', 32)->nullable();
            $table->string('student_id_number', 64)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'affiliation_requests_user_status_index');
            $table->index(['status', 'created_at'], 'affiliation_requests_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliation_requests');
        Schema::dropIfExists('affiliation_templates');
    }
};
