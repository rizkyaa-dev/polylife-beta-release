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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('user')->after('is_admin');
            $table->string('account_status', 32)->default('active')->after('role');

            $table->timestamp('banned_at')->nullable()->after('account_status');
            $table->foreignId('banned_by')->nullable()->after('banned_at')->constrained('users')->nullOnDelete();
            $table->string('ban_reason_code', 50)->nullable()->after('banned_by');
            $table->text('ban_reason_text')->nullable()->after('ban_reason_code');

            $table->string('affiliation_type', 40)->nullable()->after('ban_reason_text');
            $table->string('affiliation_name', 160)->nullable()->after('affiliation_type');
            $table->string('student_id_type', 32)->nullable()->after('affiliation_name');
            $table->string('student_id_number', 64)->nullable()->after('student_id_type');
            $table->string('affiliation_status', 32)->default('pending')->after('student_id_number');
            $table->timestamp('affiliation_verified_at')->nullable()->after('affiliation_status');
            $table->foreignId('affiliation_verified_by')
                ->nullable()
                ->after('affiliation_verified_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('role');
            $table->index('account_status');
            $table->index('student_id_number');
            $table->index('affiliation_status');
            $table->index(['affiliation_name', 'affiliation_status'], 'users_affiliation_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['account_status']);
            $table->dropIndex(['student_id_number']);
            $table->dropIndex(['affiliation_status']);
            $table->dropIndex('users_affiliation_lookup_idx');

            $table->dropConstrainedForeignId('affiliation_verified_by');
            $table->dropConstrainedForeignId('banned_by');

            $table->dropColumn([
                'role',
                'account_status',
                'banned_at',
                'ban_reason_code',
                'ban_reason_text',
                'affiliation_type',
                'affiliation_name',
                'student_id_type',
                'student_id_number',
                'affiliation_status',
                'affiliation_verified_at',
            ]);
        });
    }
};
